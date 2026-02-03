<?php

/**
 * @file
 * Debug script to check what Drupal thinks needs updating for entity types.
 *
 * Usage:
 *   ./vendor/bin/drush php:script scripts/debug-entity-type-changes.php
 */

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;

/**
 * Debug entity type changes.
 */
function debug_entity_type_changes() {
  $entity_types = ['lab_data', 'sentinel_notice', 'sentinel_sample'];
  $results = [];
  
  /** @var EntityDefinitionUpdateManagerInterface $update_manager */
  $update_manager = \Drupal::service('entity.definition_update_manager');
  /** @var EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::service('entity_type.manager');
  /** @var EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  
  $results[] = "=== Debugging Entity Type Changes ===";
  $results[] = "";
  
  foreach ($entity_types as $entity_type_id) {
    $results[] = "--- {$entity_type_id} ---";
    
    // Get current definition.
    $current_def = $entity_type_manager->getDefinition($entity_type_id, FALSE);
    if (!$current_def) {
      $results[] = "  Current definition: NOT FOUND";
      continue;
    }
    
    // Get last installed definition.
    $last_installed_def = $schema_repository->getLastInstalledDefinition($entity_type_id);
    
    $results[] = "  Current definition exists: YES";
    $results[] = "  Last installed definition exists: " . ($last_installed_def ? 'YES' : 'NO');
    
    if ($current_def && $last_installed_def) {
      // Compare key properties.
      $results[] = "  Current class: " . get_class($current_def);
      $results[] = "  Last installed class: " . get_class($last_installed_def);
      $results[] = "  Current id: " . $current_def->id();
      $results[] = "  Last installed id: " . $last_installed_def->id();
      $results[] = "  Current label: " . $current_def->getLabel();
      $results[] = "  Last installed label: " . $last_installed_def->getLabel();
      
      // Check if they're the same object or have same properties.
      if ($current_def === $last_installed_def) {
        $results[] = "  Definitions are the SAME object";
      }
      else {
        $results[] = "  Definitions are DIFFERENT objects";
        
        // Try to update to see what error we get.
        try {
          $update_manager->updateEntityType($current_def);
          $results[] = "  Update attempt: SUCCESS (no changes needed)";
        }
        catch (\Exception $e) {
          $results[] = "  Update attempt: FAILED - " . $e->getMessage();
          $results[] = "  Exception class: " . get_class($e);
        }
      }
    }
    
    // Check what getChangeList() says.
    $change_list = $update_manager->getChangeList();
    if (isset($change_list[$entity_type_id])) {
      $changes = $change_list[$entity_type_id];
      $results[] = "  Change list:";
      foreach ($changes as $type => $value) {
        if ($type === 'entity_type') {
          $results[] = "    Entity type: " . $value;
        }
        elseif (is_array($value)) {
          $results[] = "    {$type}: " . count($value) . " items";
          foreach ($value as $key => $val) {
            $results[] = "      - {$key}: {$val}";
          }
        }
        else {
          $results[] = "    {$type}: {$value}";
        }
      }
    }
    else {
      $results[] = "  Change list: No changes detected";
    }
    
    $results[] = "";
  }
  
  return implode("\n", $results);
}

// Execute if run directly.
if (php_sapi_name() === 'cli' || (function_exists('drush_main') && drush_main())) {
  echo debug_entity_type_changes() . "\n";
}
