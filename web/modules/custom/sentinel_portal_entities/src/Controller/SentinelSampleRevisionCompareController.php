<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for comparing two revisions of a Sentinel Sample.
 */
class SentinelSampleRevisionCompareController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a SentinelSampleRevisionCompareController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter')
    );
  }

  /**
   * Title callback for the revision comparison page.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $sentinel_sample
   *   The sample entity.
   *
   * @return string
   *   The page title.
   */
  public function title(SentinelSample $sentinel_sample) {
    $title = '';
    if ($sentinel_sample->hasField('pack_reference_number') && !$sentinel_sample->get('pack_reference_number')->isEmpty()) {
      $title = $sentinel_sample->get('pack_reference_number')->value;
      // Remove "-FINAL" suffix if present.
      $title = preg_replace('/-FINAL$/i', '', $title);
    }
    if (empty($title)) {
      $title = $sentinel_sample->label();
      // Remove "-FINAL" suffix if present.
      $title = preg_replace('/-FINAL$/i', '', $title);
    }
    if (empty($title)) {
      $title = $this->t('Sample @id', ['@id' => $sentinel_sample->id()]);
    }
    return $this->t('Changes to @title', ['@title' => $title]);
  }

  /**
   * Compares two revisions of a Sentinel Sample.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $sentinel_sample
   *   The sample entity.
   * @param int $source_revision
   *   The source revision ID.
   * @param int $target_revision
   *   The target revision ID.
   *
   * @return array
   *   A render array.
   */
  public function compare(SentinelSample $sentinel_sample, $source_revision, $target_revision) {
    $storage = $this->entityTypeManager()->getStorage('sentinel_sample');
    
    // Load both revisions.
    $source = $storage->loadRevision($source_revision);
    $target = $storage->loadRevision($target_revision);

    if (!$source || !$target) {
      throw new NotFoundHttpException();
    }

    // Verify both revisions belong to the same sample.
    if ($source->id() != $sentinel_sample->id() || $target->id() != $sentinel_sample->id()) {
      throw new NotFoundHttpException();
    }

    $build = [];

    // Get all revision IDs for navigation, sorted by updated field.
    // Use raw SQL query to properly handle NULL values and ensure correct sorting.
    // For MySQL, we use ISNULL() to push NULL values to the end.
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample_revision', 'r')
      ->fields('r', ['vid'])
      ->condition('r.pid', $sentinel_sample->id());
    
    // Add expression for NULL handling
    $query->addExpression('ISNULL(r.updated)', 'is_null_updated');
    
    // Order by NULL status first, then updated date, then vid
    $query->orderBy('is_null_updated', 'ASC'); // NULL values last (ISNULL returns 1 for NULL, 0 for not NULL)
    $query->orderBy('r.updated', 'DESC'); // Then sort by updated DESC
    $query->orderBy('r.vid', 'DESC'); // Secondary sort by vid for consistent ordering
    
    $revision_ids = $query->execute()->fetchCol();
    
    // Filter out NULL and invalid revision IDs to prevent array_flip() errors.
    $revision_ids = array_filter($revision_ids, function($vid) {
      return $vid !== NULL && (is_string($vid) || is_int($vid));
    });
    // Re-index array after filtering.
    $revision_ids = array_values($revision_ids);

    // Get revision dates.
    $source_date = $this->getRevisionDate($source);
    $target_date = $this->getRevisionDate($target);

    // Get field definitions.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('sentinel_sample', $sentinel_sample->bundle());
    
    // Get sample fields helper function.
    if (function_exists('sentinel_portal_entities_get_sample_fields')) {
      $fields = sentinel_portal_entities_get_sample_fields();
    }
    else {
      // Fallback: get all field definitions.
      $fields = [];
      foreach ($field_definitions as $field_name => $definition) {
        $fields[$field_name] = [
          'type' => $definition->getType(),
          'portal_config' => ['title' => $definition->getLabel()],
        ];
      }
    }

    // Build comparison table - exact Drupal 7 structure.
    $rows = [];

    // Navigation row (Previous difference).
    $prev_link = $this->buildPreviousLink($sentinel_sample, $revision_ids, $source_revision, $target_revision);
    $next_link = $this->buildNextLink($sentinel_sample, $revision_ids, $source_revision, $target_revision);
    
    $rows[] = [
      [
        'data' => $prev_link,
        'class' => ['diff-prevlink'],
        'colspan' => 2,
      ],
      [
        'data' => $next_link,
        'class' => ['diff-nextlink'],
        'colspan' => 2,
      ],
    ];

    // Build diff rows for each changed field.
    foreach ($fields as $field_name => $info) {
      // Skip certain fields.
      $skip_fields = [
        'created', 
        'changed', 
        'updated',
        'vid', 
        'pid', 
        'uuid',
        'sentinel_sample_hold_state_target_id',
        'sentinel_company_address_target_id',
        'sentinel_sample_address_target_id',
      ];
      if (in_array($field_name, $skip_fields, TRUE)) {
        continue;
      }

      $source_value = $this->getFieldValue($source, $field_name, $info);
      $target_value = $this->getFieldValue($target, $field_name, $info);

      // Compare values - normalize for comparison
      $source_normalized = $this->normalizeValueForComparison($source_value);
      $target_normalized = $this->normalizeValueForComparison($target_value);

      // Compare values.
      if ($source_normalized !== $target_normalized) {
        $title = $info['portal_config']['title'] ?? ucwords(str_replace('_', ' ', $field_name));
        
        // Section title row.
        $rows[] = [
          [
            'data' => ['#markup' => 'Changes to <em class="placeholder">' . $title . '</em>'],
            'class' => ['diff-section-title'],
            'colspan' => 4,
          ],
        ];
        
        // Diff row with markers and content.
        $source_markup = $this->formatDiffValueForTable($source_value, 'removed');
        $target_markup = $this->formatDiffValueForTable($target_value, 'added');
        
        $rows[] = [
          [
            'data' => ['#markup' => '-'],
            'class' => ['diff-marker'],
          ],
          [
            'data' => ['#markup' => '<div>' . $source_markup . '</div>'],
            'class' => ['diff-context', 'diff-deletedline'],
          ],
          [
            'data' => ['#markup' => '+'],
            'class' => ['diff-marker'],
          ],
          [
            'data' => ['#markup' => '<div>' . $target_markup . '</div>'],
            'class' => ['diff-context', 'diff-addedline'],
          ],
        ];
      }
    }

    // Build table with exact Drupal 7 structure.
    if (empty($rows) || count($rows) <= 1) {
      $build['no_changes'] = [
        '#markup' => '<p>' . $this->t('No differences found between the two revisions.') . '</p>',
      ];
    }
    else {
      // Colgroup for diff table.
      $colgroups = [
        ['class' => ['diff-marker']],
        ['class' => ['diff-content']],
        ['class' => ['diff-marker']],
        ['class' => ['diff-content']],
      ];
      
      // Table header with dates.
      $header = [
        [
          'data' => $source_date,
          'colspan' => 2,
        ],
        [
          'data' => $target_date,
          'colspan' => 2,
        ],
      ];
      
      $build['comparison_table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['diff', 'table', 'table-striped'],
        ],
        '#colgroups' => $colgroups,
      ];
    }

    // Back to revisions list link.
    $build['back_link'] = Link::createFromRoute(
      $this->t('Back to revisions list'),
      'entity.sentinel_sample.version_history',
      ['sentinel_sample' => $sentinel_sample->id()],
      ['attributes' => ['class' => ['button']]]
    )->toRenderable();

    return $build;
  }

  /**
   * Gets the field value for comparison.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param array $info
   *   Field info array.
   *
   * @return mixed
   *   The field value.
   */
  protected function getFieldValue(SentinelSample $entity, $field_name, array $info) {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }

    // For revision comparison, read field values directly from the revision table
    // to ensure we get the correct revision-specific values.
    // First, try to get the value directly from the revision table.
    $vid = $entity->getRevisionId();
    $database = \Drupal::database();
    
    // Check if the field is stored in the revision table directly (as a column).
    $revision_table = 'sentinel_sample_revision';
    $schema = $database->schema();
    if ($schema->fieldExists($revision_table, $field_name)) {
      $query = $database->select($revision_table, 'r')
        ->fields('r', [$field_name])
        ->condition('r.vid', $vid)
        ->execute();
      $db_value = $query->fetchField();
      
      // If we got a value from the database, use it (even if it's NULL/empty).
      // Only fall back to entity field if database query fails.
      if ($db_value !== FALSE) {
        // Handle NULL and empty string
        if ($db_value === NULL || $db_value === '') {
          return NULL;
        }
        
        // Get field definition to determine type for proper formatting
        $field = $entity->get($field_name);
        $field_definition = $field->getFieldDefinition();
        $field_type = $field_definition->getType();
        
        // Format the value based on field type
        return $this->formatFieldValueByType($db_value, $field_type, $field_name);
      }
    }

    // Also check if field is in a field table (for fields not in revision table directly)
    $field_table = 'sentinel_sample_revision__' . $field_name;
    if ($schema->tableExists($field_table)) {
      // Try different possible column names
      $possible_columns = [
        $field_name . '_value',
        $field_name,
        'value',
      ];
      
      foreach ($possible_columns as $column) {
        if ($schema->fieldExists($field_table, $column)) {
          $query = $database->select($field_table, 'f')
            ->fields('f', [$column])
            ->condition('f.entity_id', $entity->id())
            ->condition('f.revision_id', $vid)
            ->execute();
          $db_value = $query->fetchField();
          
          if ($db_value !== FALSE) {
            if ($db_value === NULL || $db_value === '') {
              return NULL;
            }
            
            // Get field definition to determine type for proper formatting
            $field = $entity->get($field_name);
            $field_definition = $field->getFieldDefinition();
            $field_type = $field_definition->getType();
            
            // Format the value based on field type
            return $this->formatFieldValueByType($db_value, $field_type, $field_name);
          }
          break;
        }
      }
    }

    // Fallback to entity field if not in revision table or query failed.
    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return NULL;
    }

    // Get field definition to determine type
    $field_definition = $field->getFieldDefinition();
    $field_type = $field_definition->getType();
    
    // Handle different field types
    switch ($field_type) {
      case 'boolean':
      case 'list_boolean':
        $raw = $field->value;
        if ($raw === '1' || $raw === 1 || $raw === TRUE) {
          return '1';
        }
        elseif ($raw === '0' || $raw === 0 || $raw === FALSE) {
          return '0';
        }
        return NULL;
        
      case 'datetime':
      case 'timestamp':
        $value = $field->value;
        if ($value && $value !== '0000-00-00 00:00:00') {
          try {
            $timestamp = strtotime($value);
            if ($timestamp && $timestamp > 0) {
              // Use same format as revisions list page: Y-m-d H:i:s
              return date('Y-m-d H:i:s', $timestamp);
            }
            return $value;
          }
          catch (\Exception $e) {
            return $value;
          }
        }
        return NULL;
        
      case 'integer':
      case 'float':
      case 'decimal':
        return $field->value;
        
      case 'string':
      case 'string_long':
      case 'text':
      case 'text_long':
        return $field->getString();
        
      default:
        // For all other types, try to get string representation
        $value = $field->getString();
        
        // Handle special field: pass_fail
        if ($field_name === 'pass_fail') {
          $raw = $field->value;
          if ($raw === '1' || $raw === 1) {
            return $this->t('Pass');
          }
          elseif ($raw === '0' || $raw === 0) {
            return $this->t('Fail');
          }
          return $this->t('Pending');
        }
        
        return $value;
    }
  }

  /**
   * Formats a field value based on its type.
   *
   * @param mixed $value
   *   The raw value from the database.
   * @param string $field_type
   *   The field type.
   * @param string $field_name
   *   The field name (for special handling).
   *
   * @return mixed
   *   The formatted value.
   */
  protected function formatFieldValueByType($value, $field_type, $field_name) {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    
    switch ($field_type) {
      case 'boolean':
      case 'list_boolean':
        if ($value === '1' || $value === 1 || $value === TRUE) {
          return '1';
        }
        elseif ($value === '0' || $value === 0 || $value === FALSE) {
          return '0';
        }
        return NULL;
        
      case 'datetime':
      case 'timestamp':
        if ($value && $value !== '0000-00-00 00:00:00') {
          try {
            $timestamp = strtotime($value);
            if ($timestamp && $timestamp > 0) {
              // Use same format as revisions list page: Y-m-d H:i:s
              return date('Y-m-d H:i:s', $timestamp);
            }
            return $value;
          }
          catch (\Exception $e) {
            return $value;
          }
        }
        return NULL;
        
      case 'integer':
      case 'float':
      case 'decimal':
        return $value;
        
      case 'string':
      case 'string_long':
      case 'text':
      case 'text_long':
      case 'email':
        return (string) $value;
        
      default:
        // Handle special field: pass_fail
        if ($field_name === 'pass_fail') {
          if ($value === '1' || $value === 1) {
            return $this->t('Pass');
          }
          elseif ($value === '0' || $value === 0) {
            return $this->t('Fail');
          }
          return $this->t('Pending');
        }
        
        return (string) $value;
    }
  }

  /**
   * Formats a value for display.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return array
   *   A render array.
   */
  protected function formatValue($value) {
    if ($value === NULL || $value === '') {
      return ['#markup' => '<em>' . $this->t('(empty)') . '</em>'];
    }

    if (is_array($value)) {
      $value = implode(', ', array_filter($value));
    }

    return ['#plain_text' => (string) $value];
  }

  /**
   * Formats a value for diff display with styling.
   *
   * @param mixed $value
   *   The value to format.
   * @param string $type
   *   Either 'added' or 'removed'.
   *
   * @return array
   *   A render array.
   */
  protected function formatDiffValue($value, $type = 'added') {
    if ($value === NULL || $value === '') {
      return ['#markup' => '<em>' . $this->t('(empty)') . '</em>'];
    }

    if (is_array($value)) {
      $value = implode(', ', array_filter($value));
    }

    $prefix = $type === 'removed' ? '-' : '+';
    $class = $type === 'removed' ? 'diff-deleted' : 'diff-added';
    
    return [
      '#markup' => '<span class="' . $class . '">' . $prefix . ' ' . htmlspecialchars((string) $value) . '</span>',
    ];
  }

  /**
   * Formats a value for diff table display with diffchange spans.
   *
   * @param mixed $value
   *   The value to format.
   * @param string $type
   *   Either 'added' or 'removed'.
   *
   * @return string
   *   HTML markup string.
   */
  protected function formatDiffValueForTable($value, $type = 'added') {
    if ($value === NULL || $value === '') {
      return '<em>' . $this->t('(empty)') . '</em>';
    }

    if (is_array($value)) {
      $value = implode(', ', array_filter($value));
    }

    $value_str = htmlspecialchars((string) $value);
    // Wrap the entire value in diffchange span like Drupal 7.
    return '<span class="diffchange">' . $value_str . '</span>';
  }

  /**
   * Builds previous difference link.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $entity
   *   The entity.
   * @param array $revision_ids
   *   All revision IDs.
   * @param int $source_revision
   *   Source revision ID.
   * @param int $target_revision
   *   Target revision ID.
   *
   * @return array
   *   Render array for link or empty string.
   */
  protected function buildPreviousLink(SentinelSample $entity, array $revision_ids, $source_revision, $target_revision) {
    $source_index = array_search($source_revision, $revision_ids);
    
    if ($source_index !== FALSE && $source_index > 0) {
      $prev_source = $revision_ids[$source_index - 1];
      return Link::createFromRoute(
        $this->t('< Previous difference'),
        'entity.sentinel_sample.revision_compare',
        [
          'sentinel_sample' => $entity->id(),
          'source_revision' => $prev_source,
          'target_revision' => $source_revision,
        ]
      )->toRenderable();
    }
    
    return ['#markup' => ''];
  }

  /**
   * Builds next difference link.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $entity
   *   The entity.
   * @param array $revision_ids
   *   All revision IDs.
   * @param int $source_revision
   *   Source revision ID.
   * @param int $target_revision
   *   Target revision ID.
   *
   * @return array
   *   Render array for link or empty string.
   */
  protected function buildNextLink(SentinelSample $entity, array $revision_ids, $source_revision, $target_revision) {
    $target_index = array_search($target_revision, $revision_ids);
    
    if ($target_index !== FALSE && isset($revision_ids[$target_index + 1])) {
      $next_target = $revision_ids[$target_index + 1];
      return Link::createFromRoute(
        $this->t('Next difference >'),
        'entity.sentinel_sample.revision_compare',
        [
          'sentinel_sample' => $entity->id(),
          'source_revision' => $target_revision,
          'target_revision' => $next_target,
        ]
      )->toRenderable();
    }
    
    return ['#markup' => ''];
  }

  /**
   * Gets the revision date formatted.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $revision
   *   The revision entity.
   *
   * @return string
   *   The formatted date.
   */
  protected function getRevisionDate(SentinelSample $revision) {
    // Get revision date from 'updated' field - format as YYYY-MM-DD HH:MM:SS like revisions list page.
    // Always use 'updated' field for display to match the sorting.
    // Get directly from database to ensure we get the actual value used for sorting.
    $vid = $revision->getRevisionId();
    $pid = $revision->id();
    $database = \Drupal::database();
    $date_query = $database->select('sentinel_sample_revision', 'r')
      ->fields('r', ['updated'])
      ->condition('r.vid', $vid)
      ->condition('r.pid', $pid)
      ->execute();
    $db_updated = $date_query->fetchField();
    
    $revision_date = '';
    if ($db_updated && $db_updated !== '0000-00-00 00:00:00' && $db_updated !== NULL) {
      try {
        $timestamp = strtotime($db_updated);
        if ($timestamp && $timestamp > 0) {
          $revision_date = date('Y-m-d H:i:s', $timestamp);
        }
        else {
          $revision_date = $db_updated;
        }
      }
      catch (\Exception $e) {
        $revision_date = $db_updated;
      }
    }
    
    // Fallback to entity field if database query didn't return a value
    if (empty($revision_date) && $revision->hasField('updated') && !$revision->get('updated')->isEmpty()) {
      $updated_value = $revision->get('updated')->value;
      if ($updated_value && $updated_value !== '0000-00-00 00:00:00') {
        try {
          $timestamp = strtotime($updated_value);
          if ($timestamp && $timestamp > 0) {
            $revision_date = date('Y-m-d H:i:s', $timestamp);
          }
          else {
            $revision_date = $updated_value;
          }
        }
        catch (\Exception $e) {
          $revision_date = $updated_value;
        }
      }
    }
    
    // Only fallback to 'changed' field if updated is truly empty (not just old date).
    if (empty($revision_date) && $revision->hasField('changed') && !$revision->get('changed')->isEmpty()) {
      $changed_value = $revision->get('changed')->value;
      if ($changed_value && $changed_value !== '0000-00-00 00:00:00') {
        try {
          $timestamp = strtotime($changed_value);
          if ($timestamp && $timestamp > 0) {
            $revision_date = date('Y-m-d H:i:s', $timestamp);
          }
          else {
            $revision_date = $changed_value;
          }
        }
        catch (\Exception $e) {
          $revision_date = $changed_value;
        }
      }
    }
    
    // Final fallback to 'created' field only if both updated and changed are empty.
    if (empty($revision_date) && $revision->hasField('created') && !$revision->get('created')->isEmpty()) {
      $created_value = $revision->get('created')->value;
      if ($created_value && $created_value !== '0000-00-00 00:00:00') {
        try {
          $timestamp = strtotime($created_value);
          if ($timestamp && $timestamp > 0) {
            $revision_date = date('Y-m-d H:i:s', $timestamp);
          }
          else {
            $revision_date = $created_value;
          }
        }
        catch (\Exception $e) {
          $revision_date = $created_value;
        }
      }
    }

    // Default to vid if no date available.
    if (empty($revision_date)) {
      $revision_date = $this->t('Revision @vid', ['@vid' => $vid]);
    }

    return $revision_date;
  }

  /**
   * Normalizes a value for comparison.
   *
   * @param mixed $value
   *   The value to normalize.
   *
   * @return string
   *   Normalized string value.
   */
  protected function normalizeValueForComparison($value) {
    if ($value === NULL || $value === '') {
      return '';
    }
    
    if (is_array($value)) {
      return implode(', ', array_filter($value));
    }
    
    return trim((string) $value);
  }

}

