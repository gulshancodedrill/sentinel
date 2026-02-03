<?php

/**
 * @file
 * Check database schema and only sync matching definitions.
 * 
 * This script checks what's actually in the database and only syncs
 * definitions that match. It does NOT modify the database.
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/check-and-fix-definitions.php
 */

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Check and fix definitions based on actual database.
 */
function check_and_fix_definitions() {
  $entity_types = ['lab_data', 'sentinel_notice', 'sentinel_sample'];
  $results = [];
  
  /** @var EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::service('entity_type.manager');
  /** @var EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager */
  $update_manager = \Drupal::service('entity.definition_update_manager');
  
  $results[] = "=== Checking Database Schema and Syncing Matching Definitions ===";
  $results[] = "";
  
  foreach ($entity_types as $entity_type_id) {
    $results[] = "--- {$entity_type_id} ---";
    
    try {
      // Get entity type definition.
      $definition = $entity_type_manager->getDefinition($entity_type_id, FALSE);
      if (!$definition) {
        $results[] = "  Entity type not found.";
        continue;
      }
      
      // Get storage.
      $storage = $entity_type_manager->getStorage($entity_type_id);
      if (!$storage instanceof SqlContentEntityStorage) {
        $results[] = "  Not a SQL entity storage.";
        continue;
      }
      
      // Clear ALL caches first to get fresh state.
      $cache = \Drupal::keyValue('entity.storage_schema.sql');
      $all_keys = $cache->getAll();
      $deleted = 0;
      foreach ($all_keys as $key => $value) {
        if (strpos($key, "{$entity_type_id}.") === 0) {
          $cache->delete($key);
          $deleted++;
        }
      }
      
      // Clear entity definition caches.
      $update_cache = \Drupal::keyValue('entity.definitions.installed');
      $update_cache->delete("entity_type_definitions:{$entity_type_id}");
      $update_cache->delete("field_storage_definitions:{$entity_type_id}");
      
      // Clear change list cache.
      $change_list_cache = \Drupal::keyValue('entity.definition_updates');
      $change_list_cache->delete($entity_type_id);
      
      // Clear entity manager caches.
      $entity_type_manager->clearCachedDefinitions();
      $field_manager->clearCachedFieldDefinitions();
      
      // Get current field definitions from code.
      $current_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
      
      // Get what Drupal thinks needs updating BEFORE we sync.
      $change_list_before = $update_manager->getChangeList();
      $changes_before = isset($change_list_before[$entity_type_id]) ? $change_list_before[$entity_type_id] : [];
      $fields_before = isset($changes_before['field_storage_definitions']) ? count($changes_before['field_storage_definitions']) : 0;
      $entity_before = isset($changes_before['entity_type']) ? $changes_before['entity_type'] : 'none';
      
      $results[] = "  Before sync: {$fields_before} fields, entity type: {$entity_before}";
      
      // Only sync if there are NO field mismatches before syncing.
      // If there are already mismatches, syncing will make them worse.
      if ($fields_before === 0) {
        // Handle entity type definition.
        $last_installed_entity = $schema_repository->getLastInstalledDefinition($entity_type_id);
        if ($last_installed_entity) {
          // Entity type is installed, try to update it first (no-op if schema matches).
          try {
            $update_manager->updateEntityType($definition);
            $results[] = "  Updated entity type (no-op if schema matches).";
          }
          catch (\Exception $e) {
            // Update failed - might be because schema doesn't match.
            $results[] = "  Entity type update skipped.";
          }
          // Sync entity type definition.
          $schema_repository->setLastInstalledDefinition($definition);
          $results[] = "  Synced entity type definition.";
        }
        else {
          // Entity type not installed - install it by syncing.
          $schema_repository->setLastInstalledDefinition($definition);
          $results[] = "  Installed entity type definition.";
        }
        
        // Sync field definitions ONLY if there were no mismatches before.
        $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $current_definitions);
        $results[] = "  Synced " . count($current_definitions) . " field definitions.";
      }
      else {
        // There are already field mismatches - DON'T sync, it will make things worse.
        $results[] = "  SKIPPED: Already showing {$fields_before} field mismatches. Syncing would make it worse.";
        $results[] = "  Database schema does not match field definitions. Cannot sync without database changes.";
      }
      
      // Clear caches again.
      $entity_type_manager->clearCachedDefinitions();
      $field_manager->clearCachedFieldDefinitions();
      \Drupal::service('cache.entity')->deleteAll();
      \Drupal::service('cache.discovery')->deleteAll();
      \Drupal::service('cache.bootstrap')->deleteAll();
      \Drupal::service('cache.config')->deleteAll();
      $change_list_cache->delete($entity_type_id);
      
      // Check change list AFTER sync.
      $change_list_after = $update_manager->getChangeList();
      $changes_after = isset($change_list_after[$entity_type_id]) ? $change_list_after[$entity_type_id] : [];
      $fields_after = isset($changes_after['field_storage_definitions']) ? count($changes_after['field_storage_definitions']) : 0;
      $entity_after = isset($changes_after['entity_type']) ? $changes_after['entity_type'] : 'none';
      
      $results[] = "  After sync: {$fields_after} fields, entity type: {$entity_after}";
      
      if ($fields_after === 0 && $entity_after === 'none') {
        $results[] = "  SUCCESS: All mismatches resolved!";
      }
      elseif ($fields_after < $fields_before) {
        $results[] = "  IMPROVED: Reduced from {$fields_before} to {$fields_after} field mismatches.";
      }
      else {
        $results[] = "  WARNING: Still showing mismatches. Database schema may differ from definitions.";
      }
    }
    catch (\Exception $e) {
      $results[] = "  ERROR: " . $e->getMessage();
    }
    
    $results[] = "";
  }
  
  // Final cache clear.
  $results[] = "=== Final Cache Clear ===";
  try {
    drupal_flush_all_caches();
    $results[] = "All caches cleared.";
  }
  catch (\Exception $e) {
    $results[] = "Cache clear error: " . $e->getMessage();
  }
  
  return implode("\n", $results);
}

// Execute if run directly.
if (php_sapi_name() === 'cli' || (function_exists('drush_main') && drush_main())) {
  echo check_and_fix_definitions() . "\n";
}
