<?php

namespace Drupal\sentinel_portal_services\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to ensure format is set for heartbeat endpoint.
 */
class HeartbeatResponseSubscriber implements EventSubscriberInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a HeartbeatResponseSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * Ensures format is set for heartbeat endpoint before REST processing.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof ResourceResponseInterface) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $this->routeMatch->getRouteName();
    
    // Check if this is the heartbeat endpoint.
    if ($route_name === 'rest.sentinel_heartbeat.GET') {
      // Ensure format is set to JSON if not already set.
      // This ensures getResponseFormat will return 'json' instead of NULL.
      if (!$request->getRequestFormat()) {
        $request->setRequestFormat('json');
      }
      // Also set it in route defaults to ensure it's available.
      $route = $this->routeMatch->getRouteObject();
      if ($route && !$route->hasRequirement('_format')) {
        $route->setDefault('_format', 'json');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run before REST response subscriber (which runs at priority 5).
    $events[KernelEvents::RESPONSE][] = ['onResponse', 10];
    return $events;
  }

}

