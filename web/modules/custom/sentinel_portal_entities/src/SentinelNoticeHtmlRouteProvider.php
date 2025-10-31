<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides routes for Sentinel Notice entities.
 */
class SentinelNoticeHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    if ($route) {
      $route->setOption('_admin_route', FALSE);
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route) {
      $route->setOption('_admin_route', FALSE);
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getDeleteFormRoute($entity_type);
    if ($route) {
      $route->setOption('_admin_route', FALSE);
    }
    return $route;
  }

}

