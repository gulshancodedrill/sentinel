<?php

/**
 * @file
 * Force sync field definitions by directly updating the last installed schema.
 * 
 * This script bypasses the normal update process and directly syncs the
 * last installed field storage definitions to match the current definitions.
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/force-sync-field-definitions.php
 */

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Force sync field definitions.
 */
function force_sync_field_definitions() {
  $entity_type_id = 'sentinel_sample';
  $fields_to_sync = ['pid', 'vid', 'created', 'changed'];
  
  /** @var EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  
  $results = [];
  $results[] = "=== Force Syncing Field Definitions ===";
  $results[] = "";
  
  try {
    // Get all current field storage definitions.
    $current_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
    
    $results[] = "Current definitions found: " . count($current_definitions);
    
    // Get last installed definitions.
    $last_installed = $schema_repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $results[] = "Last installed definitions found: " . count($last_installed);
    $results[] = "";
    
    // Check each field.
    foreach ($fields_to_sync as $field_name) {
      $results[] = "--- Field: {$field_name} ---";
      
      if (!isset($current_definitions[$field_name])) {
        $results[] = "  ERROR: Field not found in current definitions!";
        continue;
      }
      
      $current = $current_definitions[$field_name];
      $last = isset($last_installed[$field_name]) ? $last_installed[$field_name] : NULL;
      
      $results[] = "  Current type: " . $current->getType();
      $results[] = "  Current provider: " . $current->getProvider();
      
      if ($last) {
        $results[] = "  Last type: " . $last->getType();
        $results[] = "  Last provider: " . $last->getProvider();
        $results[] = "  Types match: " . ($current->getType() === $last->getType() ? 'YES' : 'NO');
      }
      else {
        $results[] = "  Last: NOT FOUND (needs installation)";
      }
    }
    
    $results[] = "";
    $results[] = "=== Performing Force Sync ===";
    
    // Force sync ALL current definitions to last installed.
    // This tells Drupal that the current state IS the installed state.
    $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $current_definitions);
    
    $results[] = "Synced all field storage definitions.";
    $results[] = "";
    
    // Clear all caches.
    $results[] = "=== Clearing Caches ===";
    
    $field_manager->clearCachedFieldDefinitions();
    \Drupal::service('cache.entity')->deleteAll();
    \Drupal::service('cache.discovery')->deleteAll();
    \Drupal::service('cache.bootstrap')->deleteAll();
    \Drupal::service('cache.config')->deleteAll();
    
    // Clear entity storage schema cache.
    $cache = \Drupal::keyValue('entity.storage_schema.sql');
    foreach ($current_definitions as $field_name => $field_def) {
      $cache_key = "{$entity_type_id}.{$field_name}";
      $cache->delete($cache_key);
    }
    
    // Clear entity definition update cache.
    $update_cache = \Drupal::keyValue('entity.definitions.installed');
    $update_cache->delete("entity_type_definitions:{$entity_type_id}");
    $update_cache->delete("field_storage_definitions:{$entity_type_id}");
    
    $results[] = "All caches cleared.";
    $results[] = "";
    
    // Check change list after sync.
    $results[] = "=== Checking Change List After Sync ===";
    $update_manager = \Drupal::service('entity.definition_update_manager');
    $change_list = $update_manager->getChangeList();
    
    if (isset($change_list[$entity_type_id])) {
      $entity_changes = $change_list[$entity_type_id];
      if (!empty($entity_changes['field_storage_definitions'])) {
        $remaining = array_keys($entity_changes['field_storage_definitions']);
        $results[] = "WARNING: These fields still show as needing updates:";
        foreach ($remaining as $field_name) {
          $results[] = "  - {$field_name}";
        }
        $results[] = "";
        $results[] = "This means Drupal is detecting database schema differences.";
        $results[] = "The definitions are synced, but the actual database schema may differ.";
      }
      else {
        $results[] = "SUCCESS: No field storage definition changes detected!";
      }
    }
    else {
      $results[] = "SUCCESS: No changes detected for this entity type!";
    }
    
    $results[] = "";
    $results[] = "=== Summary ===";
    $results[] = "Force sync completed. Check the status report to verify.";
    
  }
  catch (\Exception $e) {
    $results[] = "ERROR: " . $e->getMessage();
    $results[] = "Stack trace: " . $e->getTraceAsString();
  }
  
  return implode("\n", $results);
}

// Execute if run directly.
if (php_sapi_name() === 'cli' || (function_exists('drush_main') && drush_main())) {
  echo force_sync_field_definitions() . "\n";
}
