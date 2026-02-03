<?php

/**
 * @file
 * Simple script to sync definitions - NO database changes, NO updates.
 * 
 * This script ONLY syncs Drupal's internal metadata. It does NOT:
 * - Update entity types
 * - Update field storage definitions  
 * - Install new fields
 * - Modify database
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/simple-sync-definitions.php
 */

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Simple sync - just update metadata, no database changes.
 */
function simple_sync_definitions() {
  $entity_types = ['lab_data', 'sentinel_notice', 'sentinel_sample'];
  $results = [];
  
  /** @var EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::service('entity_type.manager');
  /** @var EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  
  $results[] = "=== Simple Sync (Metadata Only) ===";
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
      
      // Get current field definitions.
      $field_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
      
      // Clear ALL caches for this entity type.
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
      
      // ONLY sync definitions - no updates, no installs, no database changes.
      $schema_repository->setLastInstalledDefinition($definition);
      $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $field_definitions);
      
      $results[] = "  Cleared {$deleted} cache entries.";
      $results[] = "  Synced entity type and " . count($field_definitions) . " field definitions.";
      $results[] = "  SUCCESS: Definitions synced (no database changes).";
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
  echo simple_sync_definitions() . "\n";
}
