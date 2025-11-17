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

    // Get all revision IDs for navigation.
    $storage = $this->entityTypeManager()->getStorage('sentinel_sample');
    $query = $storage->getQuery()
      ->condition('pid', $sentinel_sample->id())
      ->allRevisions()
      ->sort('vid', 'DESC')
      ->accessCheck(FALSE)
      ->execute();
    $revision_ids = array_keys($query);

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
      if (in_array($field_name, ['created', 'changed', 'vid', 'pid', 'uuid'], TRUE)) {
        continue;
      }

      $source_value = $this->getFieldValue($source, $field_name, $info);
      $target_value = $this->getFieldValue($target, $field_name, $info);

      // Compare values.
      if ($source_value !== $target_value) {
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
        ['class' => 'diff-marker'],
        ['class' => 'diff-content'],
        ['class' => 'diff-marker'],
        ['class' => 'diff-content'],
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

    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return NULL;
    }

    // Get string representation.
    $value = $field->getString();
    
    // Handle special field types.
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

    // Format datetime fields.
    if (isset($info['type']) && $info['type'] === 'datetime' && $value) {
      try {
        $date = new \DateTime(str_replace('/', '-', (string) $value));
        return $date->format('d/m/Y H:i:s');
      }
      catch (\Exception $e) {
        return $value;
      }
    }

    return $value;
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
    $date = '';
    if ($revision->hasField('changed') && !$revision->get('changed')->isEmpty()) {
      $changed_value = $revision->get('changed')->value;
      if ($changed_value) {
        try {
          $timestamp = strtotime($changed_value);
          if ($timestamp) {
            $date = date('Y-m-d H:i:s', $timestamp);
          }
          else {
            $date = $changed_value;
          }
        }
        catch (\Exception $e) {
          $date = $changed_value;
        }
      }
    }
    
    if (empty($date) && $revision->hasField('created') && !$revision->get('created')->isEmpty()) {
      $created_value = $revision->get('created')->value;
      if ($created_value) {
        try {
          $timestamp = strtotime($created_value);
          if ($timestamp) {
            $date = date('Y-m-d H:i:s', $timestamp);
          }
          else {
            $date = $created_value;
          }
        }
        catch (\Exception $e) {
          $date = $created_value;
        }
      }
    }

    return $date ?: $this->t('Unknown date');
  }


}

