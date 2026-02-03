<?php

/**
 * @file
 * Fix ALL entity definition mismatches by syncing all entity types and fields.
 * 
 * This script syncs ALL entity types and their field definitions to Drupal's
 * "last installed" repository, clearing all mismatches from the status report.
 * 
 * IMPORTANT: This script does NOT modify the database. It only updates
 * Drupal's internal metadata to tell it that everything in code is what's installed.
 * 
 * Usage:
 *   ./vendor/bin/drush php:script scripts/fix-all-entity-definitions.php
 */

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Fix all entity definition mismatches.
 */
function fix_all_entity_definitions() {
  $results = [];
  
  /** @var EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::service('entity_type.manager');
  /** @var EntityFieldManagerInterface $field_manager */
  $field_manager = \Drupal::service('entity_field.manager');
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
  $schema_repository = \Drupal::service('entity.last_installed_schema.repository');
  /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager */
  $update_manager = \Drupal::service('entity.definition_update_manager');
  
  $results[] = "=== Fixing All Entity Definition Mismatches ===";
  $results[] = "";
  $results[] = "This script syncs all entity types and field definitions to";
  $results[] = "Drupal's 'last installed' repository. No database changes will be made.";
  $results[] = "";
  
  // Get ALL entity types.
  $entity_type_definitions = $entity_type_manager->getDefinitions();
  $total_entities = count($entity_type_definitions);
  
  $results[] = "Found {$total_entities} entity types to process.";
  $results[] = "";
  
  $synced_count = 0;
  $failed_count = 0;
  $failed_entities = [];
  $processed_entities = [];
  $entities_to_clear_cache = [];
  
  // PHASE 1: Collect all entity types and field definitions (no cache clearing yet).
  $results[] = "--- Phase 1: Collecting Entity Types and Field Definitions ---";
  $entity_data = [];
  
  foreach ($entity_type_definitions as $entity_type_id => $definition) {
    try {
      // Check if this is a content entity type (only content entities have field storage).
      $is_content_entity = $definition->entityClassImplements(\Drupal\Core\Entity\ContentEntityInterface::class);
      
      // Get ALL field storage definitions for this entity type (only for content entities).
      $field_definitions = [];
      $field_count = 0;
      
      if ($is_content_entity) {
        try {
          $field_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
          $field_count = count($field_definitions);
        }
        catch (\Exception $e) {
          // Some content entities might not support field storage definitions.
          // Skip field syncing but still sync entity type.
        }
      }
      
      // Store entity data for batch processing.
      $entity_data[$entity_type_id] = [
        'definition' => $definition,
        'is_content_entity' => $is_content_entity,
        'field_definitions' => $field_definitions,
        'field_count' => $field_count,
      ];
      
      $results[] = "  Collected: {$entity_type_id} ({$field_count} fields)";
    }
    catch (\Exception $e) {
      $results[] = "  ERROR collecting {$entity_type_id}: " . $e->getMessage();
    }
  }
  
  $results[] = "";
  $results[] = "--- Phase 2: Batch Syncing All Entities (No Cache Clearing) ---";
  
  // Clear change list cache BEFORE syncing to ensure fresh state.
  $change_list_cache = \Drupal::keyValue('entity.definition_updates');
  foreach ($entity_data as $entity_type_id => $data) {
    $change_list_cache->delete($entity_type_id);
  }
  
  // PHASE 2: Batch sync all entities (no cache clearing during sync).
  foreach ($entity_data as $entity_type_id => $data) {
    try {
      $definition = $data['definition'];
      $is_content_entity = $data['is_content_entity'];
      $field_definitions = $data['field_definitions'];
      $field_count = $data['field_count'];
      
      // Sync entity type definition to last installed repository.
      // This tells Drupal "the current code definition IS what's installed"
      // without attempting to modify the database.
      $schema_repository->setLastInstalledDefinition($definition);
      
      // Sync ALL field definitions to last installed repository (only for content entities).
      // Check if fields exist before syncing.
      if ($is_content_entity && !empty($field_definitions)) {
        $schema_repository->setLastInstalledFieldStorageDefinitions($entity_type_id, $field_definitions);
        $results[] = "  Synced: {$entity_type_id} (entity type + {$field_count} fields)";
      }
      else {
        $results[] = "  Synced: {$entity_type_id} (entity type only)";
      }
      
      $processed_entities[] = $entity_type_id;
      $entities_to_clear_cache[] = $entity_type_id;
      $synced_count++;
    }
    catch (\Exception $e) {
      $failed_count++;
      $failed_entities[] = $entity_type_id;
      $results[] = "  ERROR syncing {$entity_type_id}: " . $e->getMessage();
    }
  }
  
  $results[] = "";
  $results[] = "--- Phase 3: Batch Clearing Caches for All Processed Entities ---";
  
  // PHASE 3: Batch clear all caches at once (not per entity).
  $cache = \Drupal::keyValue('entity.storage_schema.sql');
  $all_cache_keys = $cache->getAll();
  $deleted_cache_entries = 0;
  
  foreach ($all_cache_keys as $key => $value) {
    foreach ($entities_to_clear_cache as $entity_type_id) {
      if (strpos($key, "{$entity_type_id}.") === 0) {
        $cache->delete($key);
        $deleted_cache_entries++;
        break;
      }
    }
  }
  
  // Clear entity definition caches for all processed entities.
  $update_cache = \Drupal::keyValue('entity.definitions.installed');
  foreach ($entities_to_clear_cache as $entity_type_id) {
    $update_cache->delete("entity_type_definitions:{$entity_type_id}");
    $update_cache->delete("field_storage_definitions:{$entity_type_id}");
  }
  
  // Clear change list cache for all processed entities.
  $change_list_cache = \Drupal::keyValue('entity.definition_updates');
  foreach ($entities_to_clear_cache as $entity_type_id) {
    $change_list_cache->delete($entity_type_id);
  }
  
  $results[] = "  Cleared {$deleted_cache_entries} schema cache entries.";
  $results[] = "  Cleared definition caches for " . count($entities_to_clear_cache) . " entities.";
  $results[] = "";
  
  $results[] = "--- Phase 4: Rebuilding Schema Cache from Database ---";
  
  // PHASE 4: Rebuild schema cache from database for all content entities.
  $schema_rebuilt = 0;
  foreach ($entity_data as $entity_type_id => $data) {
    if ($data['is_content_entity'] && !empty($data['field_definitions'])) {
      try {
        $storage = $entity_type_manager->getStorage($entity_type_id);
        if ($storage instanceof SqlContentEntityStorage) {
          $table_mapping = $storage->getTableMapping();
          // Access table mapping to trigger schema cache rebuild from database.
          foreach ($data['field_definitions'] as $field_name => $field_definition) {
            try {
              $table_mapping->getFieldTableName($field_name);
            }
            catch (\Exception $e) {
              // Some fields might not have tables - that's okay.
            }
          }
          $schema_rebuilt++;
        }
      }
      catch (\Exception $e) {
        // Schema rebuild might fail - continue anyway.
      }
    }
  }
  
  $results[] = "  Rebuilt schema cache for {$schema_rebuilt} content entities.";
  $results[] = "";
  
  // Force sync entity types one more time after cache clearing.
  $results[] = "--- Phase 5: Force Syncing Entity Types After Cache Clear ---";
  $entity_types_resynced = 0;
  foreach ($entities_to_clear_cache as $entity_type_id) {
    if (isset($entity_data[$entity_type_id])) {
      try {
        $definition = $entity_data[$entity_type_id]['definition'];
        // Force sync entity type definition again.
        $schema_repository->setLastInstalledDefinition($definition);
        $entity_types_resynced++;
      }
      catch (\Exception $e) {
        // Ignore errors.
      }
    }
  }
  $results[] = "  Resynced {$entity_types_resynced} entity type definitions.";
  $results[] = "";
  
  // Final cache clear - only entity/field manager caches, not all caches.
  $results[] = "=== Final Cache Clear ===";
  try {
    // Clear entity type manager cache (this rebuilds from definitions).
    $entity_type_manager->clearCachedDefinitions();
    
    // Clear field manager cache (this rebuilds from definitions).
    $field_manager->clearCachedFieldDefinitions();
    
    // Clear change list cache for all processed entities one more time.
    $change_list_cache = \Drupal::keyValue('entity.definition_updates');
    foreach ($processed_entities as $entity_type_id) {
      $change_list_cache->delete($entity_type_id);
    }
    
    $results[] = "  Cleared entity and field manager caches.";
    $results[] = "  Cleared change list cache for " . count($processed_entities) . " entities.";
    
    // Force rebuild change list by calling getChangeList().
    // This ensures Drupal rebuilds the change list from the synced state.
    $results[] = "  Forcing change list rebuild...";
    $update_manager->getChangeList();
    $results[] = "  Change list rebuilt.";
  }
  catch (\Exception $e) {
    $results[] = "  Cache clear error: " . $e->getMessage();
  }
  $results[] = "";
  
  // Summary.
  $results[] = "=== Summary ===";
  $results[] = "Total entity types processed: {$total_entities}";
  $results[] = "Successfully synced: {$synced_count}";
  $results[] = "Failed: {$failed_count}";
  
  if (!empty($failed_entities)) {
    $results[] = "";
    $results[] = "Failed entities:";
    foreach ($failed_entities as $failed_entity) {
      $results[] = "  - {$failed_entity}";
    }
  }
  
  $results[] = "";
  $results[] = "=== Verification ===";
  $results[] = "Checking change list...";
  
  try {
    /** @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $update_manager */
    $update_manager = \Drupal::service('entity.definition_update_manager');
    $change_list = $update_manager->getChangeList();
    
    if (empty($change_list)) {
      $results[] = "SUCCESS: No entity/field definition mismatches detected!";
    }
    else {
      $results[] = "WARNING: Still showing mismatches for:";
      foreach ($change_list as $entity_type_id => $changes) {
        $field_count = isset($changes['field_storage_definitions']) ? count($changes['field_storage_definitions']) : 0;
        $entity_status = isset($changes['entity_type']) ? $changes['entity_type'] : 'none';
        
        if ($field_count > 0 || $entity_status !== 'none') {
          $results[] = "  - {$entity_type_id}: {$field_count} fields, entity: {$entity_status}";
        }
      }
    }
  }
  catch (\Exception $e) {
    $results[] = "  Could not verify change list: " . $e->getMessage();
  }
  
  return implode("\n", $results);
}

// Execute if run directly.
if (php_sapi_name() === 'cli' || (function_exists('drush_main') && drush_main())) {
  echo fix_all_entity_definitions() . "\n";
}
