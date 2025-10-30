<?php

namespace Drupal\sentinel_portal_user_map;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for user map functionality.
 */
class UserMapAccess implements AccessInterface {

  /**
   * Checks access for user map routes.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    // Admin user always has access.
    if ($account->id() == 1) {
      return AccessResult::allowed();
    }

    // Check if user has the required permission.
    if (!$account->hasPermission('sentinel portal')) {
      return AccessResult::forbidden();
    }

    // Check if the user has cohorts.
    if (!sentinel_portal_user_map_client_has_cohorts($account)) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}


