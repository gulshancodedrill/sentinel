<?php

namespace Drupal\sentinel_portal_entities\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;

/**
 * Helper utilities for working with sentinel_sample entities.
 */
class SentinelSampleEntityHelper {

  /**
   * Apply an associative array of values to a sentinel sample entity.
   */
  public static function applySampleData(SentinelSample $entity, array $data): void {
    unset($data['form_id']);
    unset($data['field_sentinel_sample_address']);

    foreach ($data as $field => $value) {
      if (!$entity->hasField($field)) {
        continue;
      }

      if (is_array($value)) {
        // Skip complex structures (handled elsewhere).
        continue;
      }

      if ($value === NULL || $value === '') {
        $entity->set($field, NULL);
      }
      else {
        $entity->set($field, $value);
      }
    }
  }

  /**
   * Load a sample entity by its pack reference number.
   */
  public static function loadSampleByPackReference(string $pack_reference_number): ?SentinelSample {
    if ($pack_reference_number === '') {
      return NULL;
    }

    $ids = \Drupal::entityQuery('sentinel_sample')
      ->condition('pack_reference_number', $pack_reference_number)
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    return $storage->load(reset($ids));
  }

  /**
   * Determine if the given sample would be considered a duplicate.
   */
  public static function isDuplicate(SentinelSample $sample): bool {
    $pack_reference_number = $sample->get('pack_reference_number')->value;
    if (empty($pack_reference_number)) {
      return FALSE;
    }

    $query = \Drupal::database()->select('sentinel_sample', 's')
      ->fields('s', ['pid'])
      ->condition('pack_reference_number', $pack_reference_number, '=');

    $date_installed = $sample->get('date_installed')->value;
    if (!empty($date_installed)) {
      $group = $query->orConditionGroup()
        ->condition('date_installed', $date_installed, '<>')
        ->isNull('date_installed');
      $query->condition($group);
    }

    $date_booked = $sample->get('date_booked')->value ?? NULL;
    if (!empty($date_booked)) {
      $group = $query->orConditionGroup()
        ->condition('date_booked', $date_booked, '<>')
        ->isNull('date_booked');
      $query->condition($group);
    }

    $query->isNotNull('pass_fail');

    $system_location = $sample->get('system_location')->value ?? NULL;
    if (!empty($system_location)) {
      $query->condition('system_location', $system_location, '<>');
    }

    $result = $query->range(0, 1)->execute()->fetchField();
    return !empty($result);
  }

  /**
   * Find an existing duplicate sample.
   */
  public static function findDuplicate(SentinelSample $sample): ?SentinelSample {
    $pack_reference_number = $sample->get('pack_reference_number')->value;
    if (empty($pack_reference_number)) {
      return NULL;
    }

    $query = \Drupal::entityQuery('sentinel_sample')
      ->condition('pack_reference_number', $pack_reference_number, 'STARTS_WITH')
      ->accessCheck(FALSE);

    $date_installed = $sample->get('date_installed')->value ?? NULL;
    if (!empty($date_installed)) {
      $query->condition('date_installed', $date_installed, '=');
    }

    $date_booked = $sample->get('date_booked')->value ?? NULL;
    if (!empty($date_booked)) {
      $query->condition('date_booked', $date_booked, '=');
    }

    $system_location = $sample->get('system_location')->value ?? NULL;
    if (!empty($system_location)) {
      $query->condition('system_location', $system_location, '=');
    }

    $duplicate_of = $sample->get('duplicate_of')->value ?? NULL;
    if (!empty($duplicate_of)) {
      $query->condition('duplicate_of', $duplicate_of, '=');
    }

    $ids = $query->range(0, 1)->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var EntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    return $storage->load(reset($ids));
  }

  /**
   * Rename a duplicate sample to the next available D-suffixed reference number.
   */
  public static function renameDuplicate(SentinelSample $sample): void {
    $pack_reference_number = $sample->get('pack_reference_number')->value;
    if (empty($pack_reference_number)) {
      return;
    }

    if (strpos($pack_reference_number, 'D') !== FALSE) {
      $actual_number = substr($pack_reference_number, 0, strpos($pack_reference_number, 'D'));
      $duplicate_count = (int) substr($pack_reference_number, strpos($pack_reference_number, 'D') + 1);
    }
    else {
      $actual_number = $pack_reference_number;
      $duplicate_count = 0;
    }

    do {
      $duplicate_count++;
      $new_pack_reference_number = $actual_number . 'D' . $duplicate_count;
    } while (self::loadSampleByPackReference($new_pack_reference_number));

    $sample->set('pack_reference_number', $new_pack_reference_number);
  }

}


