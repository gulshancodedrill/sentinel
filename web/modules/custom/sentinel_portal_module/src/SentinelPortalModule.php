<?php

namespace Drupal\sentinel_portal_module;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Sentinel Portal module hooks and utilities.
 */
class SentinelPortalModule {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  public static function theme($existing, $type, $theme, $path) {
    return [
      'sentinel_portal_main_page' => [
        'variables' => [
          'user_branch' => NULL,
        ],
        'template' => 'sentinel_portal_main_page',
        'path' => $path . '/templates',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_block().
   */
  public static function preprocessBlock(&$variables) {
    if (isset($variables['elements']['#id'])) {
      $block_id = $variables['elements']['#id'];
      if (strpos($block_id, 'menu_block:portal') === 0) {
        $variables['attributes']['class'][] = 'col-sm-4';
        $variables['attributes']['class'][] = 'col-md-12';
      }
    }
  }

  /**
   * Access callback for portal routes.
   */
  public static function portalAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'sentinel portal');
  }

  /**
   * Access callback for portal admin routes.
   */
  public static function portalAdminAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'sentinel portal administration');
  }

  /**
   * Access callback for portal config routes.
   */
  public static function portalConfigAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'sentinel portal administration');
  }

}
