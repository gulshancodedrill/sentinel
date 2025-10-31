<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Sentinel Client entities.
 */
class SentinelClientViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, if needed.

    return $data;
  }

}
