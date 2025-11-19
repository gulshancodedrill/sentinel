<?php

namespace Drupal\sentinel_portal_services\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Request subscriber to set default format for heartbeat endpoint.
 */
class HeartbeatRequestSubscriber implements EventSubscriberInterface {

  /**
   * Sets the request format to JSON if not set for heartbeat endpoint.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    
    // Check if this is an endpoint that should default to JSON.
    $path_info = $request->getPathInfo();
    $paths = [
      '/sentinel/heartbeat',
      '/sentinel/customerservice',
       '/sentinel/sampleservice'
    ];

    $matches = array_filter($paths, function ($path) use ($path_info) {
      return $path_info === $path || strpos($path_info, $path . '/') === 0;
    });

    if (!empty($matches)) {
      // If no format is set in query string or Accept header, default to JSON.
      $format = $request->getRequestFormat(NULL);
      if (empty($format)) {
        $request->setRequestFormat('json');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run very early, before route matching and format filtering.
    // Priority 50 runs before RequestFormatRouteFilter.
    $events[KernelEvents::REQUEST][] = ['onRequest', 50];
    return $events;
  }

}

