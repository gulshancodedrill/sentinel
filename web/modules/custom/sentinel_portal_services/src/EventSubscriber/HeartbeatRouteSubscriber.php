<?php

namespace Drupal\sentinel_portal_services\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber to make format optional for heartbeat endpoint.
 */
class HeartbeatRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Modify the heartbeat route to default to JSON format.
    if ($route = $collection->get('rest.sentinel_heartbeat.GET')) {
      // Set default format to JSON - this will be used when _format is not in URL.
      $route->setDefault('_format', 'json');
      // Keep the format requirement - the request subscriber will set the format
      // early so the route matches.
    }
  }

}

