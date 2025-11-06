<?php

namespace Drupal\sentinel_portal_services\Helper;

/**
 * Helper class for Sentinel Sample Entity operations.
 */
class SentinelSampleEntityHelper {

  /**
   * Apply sample data array to entity.
   *
   * @param object $entity
   *   The sample entity.
   * @param array $data
   *   The data array to apply.
   */
  public static function applySampleData($entity, array $data) {
    foreach ($data as $field_name => $value) {
      if ($entity->hasField($field_name)) {
        $entity->set($field_name, $value);
      }
    }
  }

  /**
   * Check if a sample is a duplicate.
   *
   * @param object $sample
   *   The sample entity to check.
   *
   * @return bool
   *   TRUE if duplicate, FALSE otherwise.
   */
  public static function isDuplicate($sample) {
    // Check if sample has duplicate detection method
    if (method_exists($sample, 'isDuplicate')) {
      return $sample->isDuplicate();
    }
    
    // Fallback: Use function if available
    if (function_exists('sentinel_portal_entities_is_duplicate')) {
      return sentinel_portal_entities_is_duplicate($sample);
    }
    
    return FALSE;
  }

  /**
   * Find an existing duplicate sample.
   *
   * @param object $sample
   *   The sample entity.
   *
   * @return mixed
   *   The duplicate sample entity or FALSE.
   */
  public static function findDuplicate($sample) {
    if (function_exists('sentinel_portal_entities_find_duplciate')) {
      return sentinel_portal_entities_find_duplciate($sample);
    }
    
    return FALSE;
  }

  /**
   * Rename a duplicate sample with DNX suffix.
   *
   * @param object $sample
   *   The sample entity to rename.
   */
  public static function renameDuplicate($sample) {
    if (function_exists('sentinel_portal_entities_rename_duplicate')) {
      sentinel_portal_entities_rename_duplicate($sample);
    }
  }

  /**
   * Load a sample by pack reference number.
   *
   * @param string $pack_reference_number
   *   The pack reference number.
   *
   * @return mixed
   *   The sample entity or FALSE.
   */
  public static function loadSampleByPackReference($pack_reference_number) {
    if (function_exists('sentinel_portal_entities_get_sample_by_reference_number')) {
      return sentinel_portal_entities_get_sample_by_reference_number($pack_reference_number);
    }
    
    // Fallback: Query database directly
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 's')
      ->fields('s', ['pid'])
      ->condition('s.pack_reference_number', $pack_reference_number, '=')
      ->range(0, 1);
    
    $pid = $query->execute()->fetchField();
    
    if ($pid) {
      $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
      return $storage->load($pid);
    }
    
    return FALSE;
  }

}


