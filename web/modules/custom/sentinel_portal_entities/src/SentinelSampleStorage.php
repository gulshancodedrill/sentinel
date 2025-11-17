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
    $update = !$full_save || !$entity->isNew();
    $revision_saved = FALSE;
    $new_vid = NULL;

    // If this is a new revision, save it FIRST before updating base table.
    // This ensures we have a valid vid before the base table update.
    if ($full_save && $update && $entity->isNewRevision() && $this->revisionTable) {
      // Save revision first to get the new vid.
      $new_vid = $this->saveRevision($entity);
      
      // Also save to revision data table if it exists (contains most entity fields).
      if ($this->revisionDataTable) {
        $this->saveToSharedTables($entity, $this->revisionDataTable, TRUE);
      }
      
      $revision_saved = TRUE;
    }

    // Call parent to handle base table and other fields.
    // We need to temporarily mark revision as not new to prevent duplicate save.
    if ($revision_saved) {
      $entity->setNewRevision(FALSE);
    }
    
    parent::doSaveFieldItems($entity, $names);
    
    // Restore new revision flag if we saved it.
    if ($revision_saved) {
      $entity->setNewRevision(TRUE);
      // Ensure base table vid is updated to the new revision.
      if ($new_vid && $entity->isDefaultRevision()) {
        $this->database->update($this->baseTable)
          ->fields([$this->revisionKey => $new_vid])
          ->condition($this->idKey, $entity->id())
          ->execute();
        // Update entity's vid field.
        $entity->set($this->getEntityType()->getKey('revision'), $new_vid);
      }
    }
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







