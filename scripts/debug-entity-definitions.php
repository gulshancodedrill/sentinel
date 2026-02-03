<?php

/**
 * @file
 * Debug script to check entity field definition mismatches.
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/debug-entity-definitions.php
 */

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Debug entity field definitions.
 */
function debug_entity_definitions() {
  $entity_type_id = 'sentinel_sample';
  $fields_to_check = ['pid', 'vid', 'created', 'changed'];
  
  /** @var EntityDefinitionUpdateManagerInterface $update_manager */
  $update_manager = \Drupal::service('entity.definition_update_manager');
  /** @var EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  
  $results = [];
  $results[] = "=== Debugging Entity Field Definitions ===";
  $results[] = "";
  
  // Get current and last installed definitions.
  $current_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
  $last_installed_definitions = $schema_repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
  
  // Check what the update manager thinks needs updating.
  $change_list = $update_manager->getChangeList();
  $results[] = "Change list for {$entity_type_id}:";
  if (isset($change_list[$entity_type_id])) {
    $entity_changes = $change_list[$entity_type_id];
    if (!empty($entity_changes['field_storage_definitions'])) {
      foreach ($entity_changes['field_storage_definitions'] as $field_name => $change_type) {
        $results[] = "  - {$field_name}: {$change_type}";
      }
    }
    else {
      $results[] = "  - No field storage definition changes detected";
    }
  }
  else {
    $results[] = "  - No changes detected for this entity type";
  }
  $results[] = "";
  
  // Compare each field.
  foreach ($fields_to_check as $field_name) {
    $results[] = "--- Field: {$field_name} ---";
    
    $current_exists = isset($current_definitions[$field_name]);
    $last_exists = isset($last_installed_definitions[$field_name]);
    
    $results[] = "Current definition exists: " . ($current_exists ? 'YES' : 'NO');
    $results[] = "Last installed definition exists: " . ($last_exists ? 'YES' : 'NO');
    
    if ($current_exists && $last_exists) {
      $current = $current_definitions[$field_name];
      $last = $last_installed_definitions[$field_name];
      
      $results[] = "Current type: " . $current->getType();
      $results[] = "Last type: " . $last->getType();
      $results[] = "Current provider: " . $current->getProvider();
      $results[] = "Last provider: " . $last->getProvider();
      $results[] = "Current settings: " . json_encode($current->getSettings());
      $results[] = "Last settings: " . json_encode($last->getSettings());
      
      // Try to update and see what error we get.
      try {
        $update_manager->updateFieldStorageDefinition($current);
        $results[] = "Update result: SUCCESS";
      }
      catch (\Exception $e) {
        $results[] = "Update result: FAILED - " . $e->getMessage();
        $results[] = "Exception class: " . get_class($e);
      }
    }
    elseif ($current_exists && !$last_exists) {
      $results[] = "Field needs to be INSTALLED (not in last installed)";
    }
    elseif (!$current_exists && $last_exists) {
      $results[] = "Field needs to be UNINSTALLED (not in current)";
    }
    else {
      $results[] = "Field not found in either definition set!";
    }
    
    $results[] = "";
  }
  
  // Check if syncing would help.
  $results[] = "=== Testing Sync ===";
  try {
    $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $current_definitions);
    $results[] = "Sync completed successfully";
    
    // Check change list again.
    $change_list_after = $update_manager->getChangeList();
    $results[] = "Change list AFTER sync:";
    if (isset($change_list_after[$entity_type_id])) {
      $entity_changes = $change_list_after[$entity_type_id];
      if (!empty($entity_changes['field_storage_definitions'])) {
        foreach ($entity_changes['field_storage_definitions'] as $field_name => $change_type) {
          $results[] = "  - {$field_name}: {$change_type}";
        }
      }
      else {
        $results[] = "  - No field storage definition changes detected (FIXED!)";
      }
    }
    else {
      $results[] = "  - No changes detected (FIXED!)";
    }
  }
  catch (\Exception $e) {
    $results[] = "Sync failed: " . $e->getMessage();
  }
  
  return implode("\n", $results);
}

// Execute if run directly.
if (php_sapi_name() === 'cli' || (function_exists('drush_main') && drush_main())) {
  echo debug_entity_definitions() . "\n";
}
