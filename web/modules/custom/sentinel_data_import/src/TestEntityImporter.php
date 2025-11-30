<?php

namespace Drupal\sentinel_data_import;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Handles importing Drupal 7 test_entity records into Drupal 11.
 */
class TestEntityImporter {

  /**
   * Constructs the importer.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
    protected TimeInterface $time,
  ) {
    $this->logger = $loggerFactory->get('sentinel_data_import');
  }

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Mapping definition of CSV keys to Drupal field names and types.
   *
   * @var array<string, array{fields: string[], type: string}>
   */
  protected const FIELD_MAP = [
    'appearance_result' => [
      'fields' => ['field_appearance_result', 'field_test_appearance_result'],
      'type' => 'decimal',
    ],
    'appearance_pass_fail' => [
      'fields' => ['field_appearance_pass_fail'],
      'type' => 'boolean',
    ],
    'ph_result' => [
      'fields' => ['field_ph_result', 'field_test_ph_level'],
      'type' => 'decimal',
    ],
    'ph_pass_fail' => [
      'fields' => ['field_ph_pass_fail'],
      'type' => 'boolean',
    ],
    'boron_result' => [
      'fields' => ['field_boron_result', 'field_test_boron_result'],
      'type' => 'decimal',
    ],
    'boiler_type' => [
      'fields' => ['field_boiler_type', 'field_test_boiler_type'],
      'type' => 'string',
    ],
    'molybdenum_result' => [
      'fields' => ['field_molybdenum_result', 'field_test_molybdenum_result'],
      'type' => 'decimal',
    ],
    'sys_cond_result' => [
      'fields' => ['field_sys_cond_result', 'field_system_conductivity_result'],
      'type' => 'decimal',
    ],
    'mains_cond_result' => [
      'fields' => ['field_mains_cond_result', 'field_main_conductivity_result'],
      'type' => 'decimal',
    ],
    'mains_calcium_result' => [
      'fields' => ['field_mains_calcium_result', 'field_test_main_calcium_result'],
      'type' => 'decimal',
    ],
    'sys_calcium_result' => [
      'fields' => ['field_sys_calcium_result', 'field_test_system_calcium_result'],
      'type' => 'decimal',
    ],
    'sys_cl_result' => [
      'fields' => ['field_sys_cl_result', 'field_system_chloride_result'],
      'type' => 'decimal',
    ],
    'mains_cl_result' => [
      'fields' => ['field_mains_cl_result', 'field_test_mains_chloride_result'],
      'type' => 'decimal',
    ],
    'iron_result' => [
      'fields' => ['field_iron_result', 'field_test_dissolved_iron_result'],
      'type' => 'decimal',
    ],
    'iron_pass_fail' => [
      'fields' => ['field_iron_pass_fail'],
      'type' => 'boolean',
    ],
    'copper_result' => [
      'fields' => ['field_copper_result', 'field_test_copper_result'],
      'type' => 'decimal',
    ],
    'copper_pass_fail' => [
      'fields' => ['field_copper_pass_fail'],
      'type' => 'boolean',
    ],
    'aluminium_result' => [
      'fields' => ['field_aluminium_result', 'field_test_dissolved_aluminium'],
      'type' => 'decimal',
    ],
    'aluminium_pass_fail' => [
      'fields' => ['field_aluminium_pass_fail'],
      'type' => 'boolean',
    ],
    'cond_pass_fail' => [
      'fields' => ['field_cond_pass_fail'],
      'type' => 'boolean',
    ],
    'cl_pass_fail' => [
      'fields' => ['field_cl_pass_fail'],
      'type' => 'boolean',
    ],
    'calcium_pass_fail' => [
      'fields' => ['field_calcium_pass_fail'],
      'type' => 'boolean',
    ],
    'sentinel_x100_pass_fail' => [
      'fields' => ['field_sentinel_x100_pass_fail'],
      'type' => 'boolean',
    ],
    'sentinel_x100_result' => [
      'fields' => ['field_sentinel_x100_result'],
      'type' => 'decimal',
    ],
    'installer_name' => [
      'fields' => ['field_installer_name'],
      'type' => 'string',
    ],
    'company_name' => [
      'fields' => ['field_company_name'],
      'type' => 'string',
    ],
    'company_address1' => [
      'fields' => ['field_company_address1'],
      'type' => 'string',
    ],
    'company_address2' => [
      'fields' => ['field_company_address2'],
      'type' => 'string',
    ],
    'company_town' => [
      'fields' => ['field_company_town'],
      'type' => 'string',
    ],
    'company_county' => [
      'fields' => ['field_company_county'],
      'type' => 'string',
    ],
    'company_postcode' => [
      'fields' => ['field_company_postcode'],
      'type' => 'string',
    ],
    'property_number' => [
      'fields' => ['field_property_number'],
      'type' => 'string',
    ],
    'street' => [
      'fields' => ['field_street'],
      'type' => 'string',
    ],
    'town_city' => [
      'fields' => ['field_town_city'],
      'type' => 'string',
    ],
    'county' => [
      'fields' => ['field_county'],
      'type' => 'string',
    ],
    'postcode' => [
      'fields' => ['field_postcode'],
      'type' => 'string',
    ],
    'system_6_months' => [
      'fields' => ['field_system_6_months', 'field_test_system_6_months'],
      'type' => 'string',
    ],
    'pack_reference_number' => [
      'fields' => ['field_pack_reference_number', 'field_test_pack_reference_number'],
      'type' => 'string',
    ],
    'project_id' => [
      'fields' => ['field_project_id'],
      'type' => 'string',
    ],
    'boiler_id' => [
      'fields' => ['field_boiler_id'],
      'type' => 'string',
    ],
    'system_age' => [
      'fields' => ['field_system_age'],
      'type' => 'string',
    ],
    'site_address' => [
      'fields' => ['field_site_address'],
      'type' => 'string',
    ],
    'customer_id' => [
      'fields' => ['field_customer_id'],
      'type' => 'string',
    ],
    'date_reported' => [
      'fields' => ['field_date_reported'],
      'type' => 'string',
    ],
    'company_address1' => [
      'fields' => ['field_company_address1'],
      'type' => 'string',
    ],
    'pass_fail' => [
      'fields' => ['field_pass_fail'],
      'type' => 'int',
    ],
    'uid' => [
      'fields' => ['uid'],
      'type' => 'int',
    ],
  ];

  /**
   * Process a single queue item.
   *
   * @throws \RuntimeException
   *   If there is an unrecoverable error.
   */
  public function processItem(array $item): void {
    $id = isset($item['id']) ? (int) $item['id'] : 0;
    if ($id <= 0) {
      $this->logger->warning('Skipping test_entity import with invalid id (@id).', ['@id' => $item['id'] ?? 'missing']);
      return;
    }

    $storage = $this->entityTypeManager->getStorage('test_entity');
    
    // Clear cache before loading to ensure we get the latest entity
    $storage->resetCache([$id]);
    
    // Check if entity exists - if so, update it; otherwise create new
    $entity = $storage->load($id);
    $is_update = $entity !== NULL;
    
    if ($is_update) {
      $this->logger->info('Found existing test_entity @id, will update.', ['@id' => $id]);
    }
    else {
      $this->logger->info('No existing test_entity @id found, will create new.', ['@id' => $id]);
    }

    $created = isset($item['created']) && $item['created'] !== '' ? (int) $item['created'] : $this->time->getRequestTime();
    $changed = isset($item['changed']) && $item['changed'] !== '' ? (int) $item['changed'] : $this->time->getRequestTime();
    $langcode = $this->mapLanguage($item['language'] ?? 'und');

    $values = [
      'id' => $id,
      'type' => $item['type'] ?? 'condition_entity',
      'uid' => isset($item['uid']) && $item['uid'] !== '' ? (int) $item['uid'] : 0,
      'created' => $created,
      'changed' => $changed,
      'langcode' => $langcode,
    ];

    foreach (self::FIELD_MAP as $column => $definition) {
      if (!array_key_exists($column, $item)) {
        continue;
      }
      $raw = $item[$column];
      // For updates, include empty values to clear fields; for creates, skip empty
      if ($is_update || ($raw !== '' && $raw !== NULL)) {
        $this->applyFieldValues($values, $definition['fields'], $raw, $definition['type']);
      }
    }

    try {
      if ($is_update) {
        // Update existing entity
        $updated_fields = [];
        
        // Update basic fields
        if (isset($values['type']) && $entity->bundle() !== $values['type']) {
          // Type change not typically allowed, but log it
          $this->logger->warning('Cannot change type of test_entity @id from @old to @new.', [
            '@id' => $id,
            '@old' => $entity->bundle(),
            '@new' => $values['type'],
          ]);
        }
        
        if (isset($values['uid'])) {
          $current_uid = $entity->get('uid')->target_id ?? 0;
          $new_uid = is_array($values['uid']) ? ($values['uid']['value'] ?? 0) : (int) $values['uid'];
          if ($current_uid != $new_uid) {
            $entity->set('uid', $new_uid);
            $updated_fields[] = 'uid';
          }
        }
        
        if (isset($values['langcode']) && $entity->language()->getId() !== $values['langcode']) {
          $entity->set('langcode', $values['langcode']);
          $updated_fields[] = 'langcode';
        }
        
        // Update mapped fields from values array
        foreach ($values as $fieldName => $fieldValue) {
          // Skip core entity fields that are handled separately
          if (in_array($fieldName, ['id', 'type', 'created', 'changed', 'langcode', 'uid'], TRUE)) {
            continue;
          }
          
          if (!$entity->hasField($fieldName)) {
            continue;
          }
          
          // Get current value
          $field_item = $entity->get($fieldName);
          $current_value = $field_item->isEmpty() ? NULL : $field_item->value;
          
          // Get new value
          $new_value = is_array($fieldValue) ? ($fieldValue['value'] ?? NULL) : $fieldValue;
          
          // Normalize empty strings to NULL for comparison
          if ($new_value === '') {
            $new_value = NULL;
          }
          if ($current_value === '') {
            $current_value = NULL;
          }
          
          // Update if value has changed
          if ($current_value != $new_value) {
            if ($new_value === NULL || $new_value === '') {
              $entity->set($fieldName, NULL);
            }
            else {
              $entity->set($fieldName, $fieldValue);
            }
            $updated_fields[] = $fieldName;
          }
        }
        
        // Always update changed timestamp
        $entity->set('changed', $changed);
        
        // Create new revision on update (if entity supports revisions)
        if ($entity->getEntityType()->isRevisionable()) {
          $entity->setNewRevision(TRUE);
        }
        
        $entity->save();
        
        $this->logger->notice('Updated test_entity @id. Updated fields: @fields', [
          '@id' => $id,
          '@fields' => implode(', ', $updated_fields) ?: 'none (no changes)',
        ]);
      }
      else {
        // Create new entity
        // Only set created timestamp if not provided
        if (!isset($values['created']) || $values['created'] === $this->time->getRequestTime()) {
          $values['created'] = $created;
        }
        
        $entity = $storage->create($values);
        $entity->save();
        $this->logger->notice('Imported test_entity @id.', ['@id' => $id]);
      }
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed ' . ($is_update ? 'updating' : 'importing') . ' test_entity @id: @message', [
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Apply values to one or more fields with the appropriate type casting.
   */
  protected function applyFieldValues(array &$values, array $fieldNames, mixed $raw, string $type): void {
    foreach ($fieldNames as $fieldName) {
      // Special handling for core entity keys mapped via FIELD_MAP (uid).
      if ($fieldName === 'uid') {
        $values['uid'] = (int) $raw;
        continue;
      }

      $values[$fieldName] = $this->formatFieldValue($raw, $type);
    }
  }

  /**
   * Format a raw CSV value according to field type.
   */
  protected function formatFieldValue(mixed $raw, string $type): array {
    switch ($type) {
      case 'boolean':
        $value = $this->toBoolean($raw);
        return ['value' => $value];

      case 'int':
        return ['value' => (int) $raw];

      case 'decimal':
        // Preserve as string to avoid precision loss.
        return ['value' => (string) $raw];

      case 'string':
      default:
        return ['value' => (string) $raw];
    }
  }

  /**
   * Convert mixed raw value to a Drupal boolean (0/1).
   */
  protected function toBoolean(mixed $raw): int {
    if (is_numeric($raw)) {
      return (int) ((int) $raw !== 0);
    }

    $normalized = strtolower(trim((string) $raw));
    return in_array($normalized, ['1', 'true', 'yes', 'y'], TRUE) ? 1 : 0;
  }

  /**
   * Maps Drupal 7 language codes to Drupal 11.
   */
  protected function mapLanguage(string $langcode): string {
    return ($langcode === 'und' || $langcode === '') ? 'en' : $langcode;
  }

}


