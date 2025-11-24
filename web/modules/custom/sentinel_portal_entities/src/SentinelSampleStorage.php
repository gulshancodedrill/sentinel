<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Storage handler for Sentinel Sample entities.
 */
class SentinelSampleStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    // Ensure new revision is created on each save (except for new entities).
    if (!$entity->isNew() && !$entity->isNewRevision()) {
      $entity->setNewRevision(TRUE);
      // Explicitly clear vid to ensure a new one is generated.
      $revision_key = $this->getEntityType()->getKey('revision');
      $entity->set($revision_key, NULL);
    }

    // Call parent save - it will create the new revision and update base table vid.
    return parent::doSave($id, $entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    $full_save = empty($names);
    $is_new_entity = $entity->isNew();
    $revision_saved = FALSE;
    $new_vid = NULL;

    // For new entities, we need to ensure vid is not NULL in the base table insert.
    // Since we can't create a revision before we have a pid, we'll:
    // 1. Set vid to 0 (default) temporarily and prevent parent from creating revision
    // 2. Override the base table insert to explicitly include vid=0
    // 3. Let parent insert base table (gets pid)
    // 4. Create revision with pid
    // 5. Update base table with real vid
    $was_new_revision = FALSE;
    if ($full_save && $is_new_entity && $this->revisionTable) {
      // Ensure new entities get a new revision.
      if ($entity->isNewRevision()) {
        $was_new_revision = TRUE;
      }
      else {
        $entity->setNewRevision(TRUE);
        $was_new_revision = TRUE;
      }
      
      // Temporarily set vid to 0 to avoid NULL constraint violation.
      $revision_key = $this->getEntityType()->getKey('revision');
      $entity->set($revision_key, 0);
      
      // Temporarily prevent parent from trying to create revision (we'll do it after base insert).
      $entity->setNewRevision(FALSE);
    }

    // For existing entities with new revisions, save revision first.
    if ($full_save && !$is_new_entity && $entity->isNewRevision() && $this->revisionTable) {
      // Save revision first to get the new vid.
      $new_vid = $this->saveRevision($entity);
      
      // Also save to revision data table if it exists.
      if ($this->revisionDataTable) {
        $this->saveToSharedTables($entity, $this->revisionDataTable, TRUE);
      }
      
      $revision_saved = TRUE;
      // Temporarily mark as not new revision to prevent duplicate save in parent.
      $entity->setNewRevision(FALSE);
      // Set vid on entity so parent includes it in base table update.
      $entity->set($this->getEntityType()->getKey('revision'), $new_vid);
    }
    
    // Call parent to handle base table and other fields.
    // The mapToStorageRecord override will ensure vid is included.
    parent::doSaveFieldItems($entity, $names);
    
    // For new entities, create revision after base table insert (now we have pid).
    if ($full_save && $is_new_entity && $this->revisionTable && $entity->id() && $was_new_revision) {
      // Restore new revision flag.
      $entity->setNewRevision(TRUE);
      
      // Save revision to get the new vid.
      $new_vid = $this->saveRevision($entity);
      
      // Also save to revision data table if it exists.
      if ($this->revisionDataTable) {
        $this->saveToSharedTables($entity, $this->revisionDataTable, TRUE);
      }
      
      // Update base table with the real vid.
      if ($new_vid) {
        $this->database->update($this->baseTable)
          ->fields([$this->revisionKey => $new_vid])
          ->condition($this->idKey, $entity->id())
          ->execute();
        // Update entity's vid field.
        $entity->set($this->getEntityType()->getKey('revision'), $new_vid);
      }
    }
    
    // Restore new revision flag if we temporarily disabled it.
    if ($revision_saved) {
      $entity->setNewRevision(TRUE);
      // Ensure base table vid is set correctly.
      if ($new_vid && $entity->id()) {
        $current_vid = $this->database->select($this->baseTable, 'base')
          ->fields('base', [$this->revisionKey])
          ->condition($this->idKey, $entity->id())
          ->execute()
          ->fetchField();
        
        if ($current_vid != $new_vid) {
          $this->database->update($this->baseTable)
            ->fields([$this->revisionKey => $new_vid])
            ->condition($this->idKey, $entity->id())
            ->execute();
        }
        $entity->set($this->getEntityType()->getKey('revision'), $new_vid);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function mapToStorageRecord(ContentEntityInterface $entity, $table_name = NULL) {
    $record = parent::mapToStorageRecord($entity, $table_name);
    
    // For base table inserts of new entities, ensure vid is included.
    if (!isset($table_name) || $table_name === $this->baseTable) {
      $revision_key = $this->getEntityType()->getKey('revision');
      
      // If vid is not in the record, ensure it's set (to 0 for new entities to avoid NULL).
      if (!isset($record->{$revision_key})) {
        if ($entity->hasField($revision_key) && !$entity->get($revision_key)->isEmpty()) {
          $vid_value = $entity->get($revision_key)->value;
          // Include vid even if it's 0 (to avoid NULL constraint violation).
          $record->{$revision_key} = $vid_value !== NULL ? $vid_value : 0;
        }
        // For new entities, if vid is not set on entity, set it to 0.
        elseif ($entity->isNew()) {
          $record->{$revision_key} = 0;
        }
      }
    }
    
    return $record;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildQuery($ids, $revision_ids = FALSE) {
    $query = $this->database->select($this->baseTable, 'base');

    $query->addTag($this->entityTypeId . '_load_multiple');

    if ($revision_ids) {
      $query->join($this->revisionTable, 'revision', "[revision].[{$this->idKey}] = [base].[{$this->idKey}] AND [revision].[{$this->revisionKey}] IN (:revisionIds[])", [':revisionIds[]' => $revision_ids]);
    }
    elseif ($this->revisionTable) {
      // Use LEFT JOIN instead of INNER JOIN to include entities without revisions
      $query->leftJoin($this->revisionTable, 'revision', "[revision].[{$this->revisionKey}] = [base].[{$this->revisionKey}]");
    }

    // Add fields from the {entity} table.
    $table_mapping = $this->getTableMapping();
    $entity_fields = $table_mapping->getAllColumns($this->baseTable);

    if ($this->revisionTable) {
      // Add all fields from the {entity_revision} table.
      $entity_revision_fields = $table_mapping->getAllColumns($this->revisionTable);
      $entity_revision_fields = array_combine($entity_revision_fields, $entity_revision_fields);
      // The ID field is provided by entity, so remove it.
      unset($entity_revision_fields[$this->idKey]);

      // Remove all fields from the base table that are also fields by the same
      // name in the revision table.
      $entity_field_keys = array_flip($entity_fields);
      foreach ($entity_revision_fields as $name) {
        if (isset($entity_field_keys[$name])) {
          unset($entity_fields[$entity_field_keys[$name]]);
        }
      }
      $query->fields('revision', $entity_revision_fields);

      // Compare revision ID of the base and revision table, if equal then this
      // is the default revision. Handle NULL case for entities without revisions.
      $query->addExpression('CASE WHEN [revision].[' . $this->revisionKey . '] IS NULL THEN 0 WHEN [base].[' . $this->revisionKey . '] = [revision].[' . $this->revisionKey . '] THEN 1 ELSE 0 END', 'isDefaultRevision');
    }

    $query->fields('base', $entity_fields);

    if ($ids) {
      $query->condition("base.{$this->idKey}", $ids, 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function saveRevision(ContentEntityInterface $entity) {
    // Ensure vid is NULL for new revisions so auto-increment generates a new one.
    if ($entity->isNewRevision()) {
      $revision_key = $this->getEntityType()->getKey('revision');
      $entity->set($revision_key, NULL);
    }
    
    // Call parent to save the revision - this saves to revision table.
    $vid = parent::saveRevision($entity);
    
    // For sentinel_sample, all fields are stored in the revision table itself,
    // not in a separate revision data table. So we need to update the revision
    // record with all entity data after it's created.
    if ($entity->isNewRevision() && $vid) {
      // Get all fields from the entity and update the revision record.
      $record = $this->mapToStorageRecord($entity->getUntranslated(), $this->baseTable);
      
      // Remove pid, vid, and uuid from the record (pid and vid are already set,
      // and uuid doesn't exist in revision table).
      unset($record->{$this->idKey});
      unset($record->{$this->revisionKey});
      unset($record->uuid);
      
      // Update the revision record with all entity data.
      $this->database->update($this->revisionTable)
        ->fields((array) $record)
        ->condition($this->revisionKey, $vid)
        ->execute();
    }
    
    // Update the entity's vid field with the new revision ID.
    $entity->set($this->getEntityType()->getKey('revision'), $vid);
    
    return $vid;
  }

}







