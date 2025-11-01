<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Sentinel Sample entities.
 */
class SentinelSampleViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Provide the custom Pass/Fail/Pending filter for the pass_fail field.
    if (isset($data['sentinel_sample']['pass_fail']['filter'])) {
      $data['sentinel_sample']['pass_fail']['filter']['id'] = 'sentinel_sample_result';
    }

    return $data;
  }

}

