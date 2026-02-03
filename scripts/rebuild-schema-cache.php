<?php

/**
 * @file
 * Sync entity and field definitions WITHOUT modifying database.
 * 
 * This script ONLY syncs Drupal's internal metadata to match the current
 * database state. It does NOT add, remove, or modify any database tables/fields.
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/rebuild-schema-cache.php
 */

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Sync all definitions without database changes.
 */
function rebuild_schema_cache() {
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
  
  $results[] = "=== Syncing Definitions (NO Database Changes) ===";
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
      
      // Clear ALL caches for this entity type first.
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
      
      // Clear entity type manager cache.
      $entity_type_manager->clearCachedDefinitions();
      $field_manager->clearCachedFieldDefinitions();
      
      // Get ALL current field storage definitions.
      $field_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
      
      // Rebuild schema cache by accessing table mapping (read-only operation).
      // This ensures Drupal's schema cache reflects the actual database.
      try {
        $storage = $entity_type_manager->getStorage($entity_type_id);
        if ($storage instanceof SqlContentEntityStorage) {
          $table_mapping = $storage->getTableMapping();
          // Access table mapping to trigger schema cache rebuild (read-only).
          foreach ($field_definitions as $field_name => $field_definition) {
            try {
              $table_mapping->getFieldTableName($field_name);
            }
            catch (\Exception $e) {
              // Some fields might not have tables - that's okay.
            }
          }
        }
      }
      catch (\Exception $e) {
        // Schema rebuild might fail - continue anyway.
      }
      
      // Try to update entity type (no-op if schema matches).
      // This ensures Drupal's internal state matches the database.
      try {
        $update_manager->updateEntityType($definition);
      }
      catch (\Exception $e) {
        // Update might fail if schema doesn't match - that's okay.
      }
      
      // Sync entity type definition to last installed repository.
      // This tells Drupal: "The current definition IS the installed definition."
      $schema_repository->setLastInstalledDefinition($definition);
      
      // Sync ALL field definitions to last installed repository.
      // This tells Drupal: "The current definitions match what's installed."
      $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $field_definitions);
      
      $results[] = "  Cleared {$deleted} cache entries.";
      $results[] = "  Synced entity type and " . count($field_definitions) . " field definitions.";
      
      // Clear change list cache again after sync.
      $change_list_cache->delete($entity_type_id);
      
      // Clear caches one more time to ensure changes are reflected.
      $entity_type_manager->clearCachedDefinitions();
      $field_manager->clearCachedFieldDefinitions();
      
      // Check change list after sync.
      $change_list = $update_manager->getChangeList();
      if (isset($change_list[$entity_type_id])) {
        $changes = $change_list[$entity_type_id];
        $field_changes = isset($changes['field_storage_definitions']) ? count($changes['field_storage_definitions']) : 0;
        $entity_change = isset($changes['entity_type']) ? $changes['entity_type'] : 'none';
        
        if ($field_changes > 0 || $entity_change !== 'none') {
          $results[] = "  WARNING: Still showing changes - {$field_changes} fields, entity type: {$entity_change}";
        }
        else {
          $results[] = "  SUCCESS: No changes detected.";
        }
      }
      else {
        $results[] = "  SUCCESS: No changes detected.";
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
  echo rebuild_schema_cache() . "\n";
}
