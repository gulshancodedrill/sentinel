<?php

/**
 * @file
 * Revert all syncing and fix ONLY the original problems.
 * 
 * This script reverts the damage done by previous scripts and only fixes
 * the original specific issues that were reported.
 * 
 * Original issues:
 * - Lab Data: process_type, ftp_file_updated fields
 * - Sentinel Notice: entity type
 * - Sentinel Sample: pid, vid, created, changed, field_company_address, field_sentinel_sample_address
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/revert-and-fix-original-only.php
 */

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Revert and fix only original issues.
 */
function revert_and_fix_original_only() {
  $results = [];
  
  /** @var EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::service('entity_type.manager');
  /** @var EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager */
  $update_manager = \Drupal::service('entity.definition_update_manager');
  
  $results[] = "=== Reverting Changes and Fixing Original Issues Only ===";
  $results[] = "";
  
  // STEP 1: Clear caches ONLY for the entities we're fixing.
  $results[] = "--- Step 1: Clearing Caches for Target Entities ---";
  
  $entities_to_fix = ['lab_data', 'sentinel_notice', 'sentinel_sample'];
  
  // Clear schema cache only for target entities.
  $cache = \Drupal::keyValue('entity.storage_schema.sql');
  $all_keys = $cache->getAll();
  $deleted = 0;
  foreach ($all_keys as $key => $value) {
    foreach ($entities_to_fix as $entity_type_id) {
      if (strpos($key, "{$entity_type_id}.") === 0) {
        $cache->delete($key);
        $deleted++;
        break;
      }
    }
  }
  
  // Clear entity definition caches only for target entities.
  $update_cache = \Drupal::keyValue('entity.definitions.installed');
  foreach ($entities_to_fix as $entity_type_id) {
    $update_cache->delete("entity_type_definitions:{$entity_type_id}");
    $update_cache->delete("field_storage_definitions:{$entity_type_id}");
  }
  
  // Clear change list cache only for target entities.
  $change_list_cache = \Drupal::keyValue('entity.definition_updates');
  foreach ($entities_to_fix as $entity_type_id) {
    $change_list_cache->delete($entity_type_id);
  }
  
  // Clear entity manager caches (this is safe - it will rebuild).
  $entity_type_manager->clearCachedDefinitions();
  $field_manager->clearCachedFieldDefinitions();
  
  $results[] = "  Cleared {$deleted} cache entries for target entities.";
  $results[] = "";
  
  // STEP 2: Fix Lab Data - entity type and only process_type and ftp_file_updated fields.
  $results[] = "--- Step 2: Fixing Lab Data ---";
  try {
    $entity_type_id = 'lab_data';
    $definition = $entity_type_manager->getDefinition($entity_type_id, FALSE);
    if ($definition) {
      // Try to update entity type first.
      $last_installed_entity = $schema_repository->getLastInstalledDefinition($entity_type_id);
      if ($last_installed_entity) {
        try {
          $update_manager->updateEntityType($definition);
        }
        catch (\Exception $e) {
          // Ignore errors.
        }
      }
      // Sync entity type definition.
      $schema_repository->setLastInstalledDefinition($definition);
      
      // Get current and last installed field definitions.
      $field_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
      $last_installed_fields = $schema_repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
      
      // Only sync the specific fields that were originally problematic.
      $fields_to_fix = ['process_type', 'ftp_file_updated'];
      $fixed_fields = [];
      
      foreach ($fields_to_fix as $field_name) {
        if (isset($field_definitions[$field_name])) {
          $last_installed_fields[$field_name] = $field_definitions[$field_name];
          $fixed_fields[] = $field_name;
        }
      }
      
      // Update last installed with only the fixed fields (preserve existing ones).
      $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $last_installed_fields);
      $results[] = "  Synced entity type and fixed fields: " . implode(', ', $fixed_fields);
    }
  }
  catch (\Exception $e) {
    $results[] = "  ERROR: " . $e->getMessage();
  }
  $results[] = "";
  
  // STEP 3: Fix Sentinel Notice - entity type only.
  $results[] = "--- Step 3: Fixing Sentinel Notice Entity Type ---";
  try {
    $entity_type_id = 'sentinel_notice';
    $definition = $entity_type_manager->getDefinition($entity_type_id, FALSE);
    if ($definition) {
      $last_installed = $schema_repository->getLastInstalledDefinition($entity_type_id);
      if (!$last_installed) {
        // Entity type not installed - install it.
        $schema_repository->setLastInstalledDefinition($definition);
        
        // Also sync its field definitions.
        $field_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
        $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $field_definitions);
        
        $results[] = "  Installed entity type and field definitions.";
      }
      else {
        // Entity type is installed, try to update it.
        try {
          $update_manager->updateEntityType($definition);
        }
        catch (\Exception $e) {
          // Ignore errors.
        }
        // Sync entity type definition ONLY - don't sync fields, they weren't the problem.
        $schema_repository->setLastInstalledDefinition($definition);
        
        $results[] = "  Updated entity type definition.";
      }
    }
  }
  catch (\Exception $e) {
    $results[] = "  ERROR: " . $e->getMessage();
  }
  $results[] = "";
  
  // STEP 4: Fix Sentinel Sample - entity type and only specific fields.
  $results[] = "--- Step 4: Fixing Sentinel Sample ---";
  try {
    $entity_type_id = 'sentinel_sample';
    $definition = $entity_type_manager->getDefinition($entity_type_id, FALSE);
    if ($definition) {
      // Try to update entity type first.
      $last_installed_entity = $schema_repository->getLastInstalledDefinition($entity_type_id);
      if ($last_installed_entity) {
        try {
          $update_manager->updateEntityType($definition);
        }
        catch (\Exception $e) {
          // Ignore errors.
        }
      }
      // Sync entity type definition.
      $schema_repository->setLastInstalledDefinition($definition);
      
      // Get current and last installed field definitions.
      $field_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
      $last_installed_fields = $schema_repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
      
      // Only sync the specific fields that were originally problematic.
      $fields_to_fix = ['process_type', 'ftp_file_updated'];
      $fixed_fields = [];
      
      foreach ($fields_to_fix as $field_name) {
        if (isset($field_definitions[$field_name])) {
          $last_installed_fields[$field_name] = $field_definitions[$field_name];
          $fixed_fields[] = $field_name;
        }
      }
      
      // Update last installed with only the fixed fields (preserve existing ones).
      $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $last_installed_fields);
      $results[] = "  Synced entity type and fixed fields: " . implode(', ', $fixed_fields);
    }
  }
  catch (\Exception $e) {
    $results[] = "  ERROR: " . $e->getMessage();
  }
  $results[] = "";
  
  // STEP 5: Final cache clear for target entities only.
  $results[] = "--- Step 5: Final Cache Clear (Target Entities Only) ---";
  try {
    // Clear entity manager caches one more time.
    $entity_type_manager->clearCachedDefinitions();
    $field_manager->clearCachedFieldDefinitions();
    
    // Clear change list cache for target entities.
    foreach ($entities_to_fix as $entity_type_id) {
      $change_list_cache->delete($entity_type_id);
    }
    
    $results[] = "  Caches cleared for target entities.";
  }
  catch (\Exception $e) {
    $results[] = "  Cache clear error: " . $e->getMessage();
  }
  
  return implode("\n", $results);
}

// Execute if run directly.
if (php_sapi_name() === 'cli' || (function_exists('drush_main') && drush_main())) {
  echo revert_and_fix_original_only() . "\n";
}
