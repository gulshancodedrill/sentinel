<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Sentinel Client entities.
 */
class SentinelClientHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    if ($route) {
      // Remove the _admin_route option so it uses the frontend theme
      $route->setOption('_admin_route', FALSE);
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    if ($route) {
      // Remove the _admin_route option so it uses the frontend theme
      $route->setOption('_admin_route', FALSE);
    }
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    if ($route) {
      // Remove the _admin_route option so it uses the frontend theme
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
      // Remove the _admin_route option so it uses the frontend theme
      $route->setOption('_admin_route', FALSE);
    }
    return $route;
  }

}

