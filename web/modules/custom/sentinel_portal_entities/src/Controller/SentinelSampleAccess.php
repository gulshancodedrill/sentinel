<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;

/**
 * Access checker for sentinel sample canonical pages.
 */
class SentinelSampleAccess extends ControllerBase {

  /**
   * Determines access to the sample view.
   */
  public function access(AccountInterface $account, SentinelSample $sentinel_sample) {
    $allowed = AccessResult::allowed()->cachePerPermissions()->cachePerUser();
    $forbidden = AccessResult::forbidden()->cachePerPermissions()->cachePerUser();

    if ($account->hasPermission('sentinel view all sentinel_sample') || $account->hasPermission('sentinel portal administration')) {
      return $allowed;
    }

    if (!$account->isAuthenticated()) {
      return $forbidden;
    }

    $sample_ucr = $sentinel_sample->hasField('ucr') ? $sentinel_sample->get('ucr')->value : NULL;
    $client = sentinel_portal_entities_get_client_by_user($account);

    if ($account->hasPermission('sentinel view own sentinel_sample') && $client && $sample_ucr !== NULL) {
      $client_ucr = $client->get('ucr')->value ?? NULL;
      if ($client_ucr !== NULL && (string) $client_ucr === (string) $sample_ucr) {
        return $allowed;
      }
    }

    if ($client && $sample_ucr !== NULL) {
      $sample_client = sentinel_portal_entities_get_client_by_ucr($sample_ucr);
      if ($sample_client && function_exists('get_more_clients_based_client_cohorts')) {
        $cohort_ids = get_more_clients_based_client_cohorts($client) ?: [];
        if (in_array($sample_client->id(), $cohort_ids, TRUE)) {
          return $allowed;
        }
      }
    }

    return $forbidden;
  }

}

