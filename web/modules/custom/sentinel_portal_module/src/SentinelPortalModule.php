<?php

namespace Drupal\sentinel_portal_module;

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

  /**
   * Access callback that enforces role-based portal access rules.
   */
  public static function portalRoleAccess(AccountInterface $account): AccessResult {
    $result = AccessResult::forbidden()->addCacheContexts(['user.roles']);

    if ($account->isAnonymous()) {
      return $result;
    }

    $route = \Drupal::routeMatch()->getRouteObject();
    if (!$route) {
      return $result;
    }

    $route_path = self::normalizePath($route->getPath());
    if (self::isAllowedPath($account, $route_path)) {
      return AccessResult::allowed()->addCacheContexts(['user.roles']);
    }

    return $result;
  }

  /**
   * Determines if the current route path matches the configured path.
   */
  public static function pathMatches(string $route_path, string $base_path): bool {
    $route_path = self::normalizePath($route_path);
    $base_path = self::normalizePath($base_path);

    if ($route_path === $base_path) {
      return TRUE;
    }

    return str_starts_with($route_path, $base_path . '/');
  }

  /**
   * Checks whether the account has any of the specified roles.
   */
  protected static function accountHasAnyRole(AccountInterface $account, array $allowed_roles): bool {
    $account_roles = self::expandRoleIdentifiers($account->getRoles());
    $allowed = self::expandRoleIdentifiers($allowed_roles);

    return (bool) array_intersect($account_roles, $allowed);
  }

  /**
   * Expands role identifiers to support both labels and machine names.
   */
  protected static function expandRoleIdentifiers(array $roles): array {
    $expanded = [];

    foreach ($roles as $role) {
      if (!is_string($role)) {
        continue;
      }

      $role = trim($role);
      if ($role === '') {
        continue;
      }

      $expanded[] = $role;
      $expanded[] = strtolower($role);
      $expanded[] = str_replace(' ', '_', strtolower($role));
      $expanded[] = str_replace('_', ' ', strtolower($role));
    }

    return array_unique($expanded);
  }

  /**
   * Provides the portal path to role access mapping.
   */
  public static function getPortalRoleMap(): array {
    return [
      '/portal/admin/clients' => ['administrator'],
      '/portal/admin/notice' => ['administrator'],
      '/portal/admin/queue' => ['administrator'],
      '/portal/admin/config' => ['administrator'],
      '/portal/admin/sample' => ['administrator', 'sample administrator', 'sales team', 'technical'],
      '/portal/admin/invalid-vaillant-samples' => ['administrator', 'sample administrator', 'sales team'],
      '/portal/admin/invalid-worcester-samples' => ['administrator', 'sample administrator', 'sales team'],
      '/portal/admin/on-hold-samples' => ['administrator', 'sample administrator', 'sales team'],
      '/portal/admin' => ['administrator', 'sample administrator', 'sales team', 'technical'],
      '/portal/bulk-upload' => ['administrator', 'portal bulk upload'],
      '/portal/sample/submit' => ['administrator', 'portal user'],
      '/portal/portal-test-pdf-logic' => ['administrator'],
      '/portal/pdf-logic' => ['administrator', 'technical'],
      '/portal/explore-your-stats' => ['administrator', 'portal user'],
      '/portal/samples' => TRUE,
      '/portal' => TRUE,
    ];
  }

  /**
   * Determines whether the given account may access a specific path.
   */
  public static function isAllowedPath(AccountInterface $account, string $path): bool {
    $path = self::normalizePath($path);

    foreach (self::getPortalRoleMap() as $base_path => $allowed_roles) {
      if (self::pathMatches($path, $base_path)) {
        if ($allowed_roles === TRUE) {
          return TRUE;
        }

        if (!is_array($allowed_roles)) {
          $allowed_roles = [$allowed_roles];
        }

        if ($allowed_roles === []) {
          return TRUE;
        }

        return self::accountHasAnyRole($account, $allowed_roles);
      }
    }

    return FALSE;
  }

  /**
   * Filters menu items based on portal role access rules.
   */
  /**
   * Normalizes a path to ensure consistent comparisons.
   */
  public static function normalizePath(string $path): string {
    $normalized = '/' . ltrim($path, '/');
    $normalized = rtrim($normalized, '/');

    return $normalized === '' ? '/' : $normalized;
  }

}
