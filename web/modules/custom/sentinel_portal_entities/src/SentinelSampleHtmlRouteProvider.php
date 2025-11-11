<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides non-admin HTML routes for Sentinel Sample entities.
 */
class SentinelSampleHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    if ($route) {
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

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    if ($route) {
      $route->setDefault('_controller', '\Drupal\sentinel_portal_entities\Controller\SentinelSampleViewController::view');
      $route->setDefault('_title_callback', '\Drupal\sentinel_portal_entities\Controller\SentinelSampleViewController::title');
      $route->setRequirement('_custom_access', '\Drupal\sentinel_portal_entities\Controller\SentinelSampleAccess::access');
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

}
