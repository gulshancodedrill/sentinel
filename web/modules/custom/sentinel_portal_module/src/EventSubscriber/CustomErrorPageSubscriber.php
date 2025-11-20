<?php

namespace Drupal\sentinel_portal_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Custom error page subscriber to display a user-friendly error message.
 */
class CustomErrorPageSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before FinalExceptionSubscriber (priority -256) to handle exceptions first.
    $events[KernelEvents::EXCEPTION][] = ['onException', -255];
    return $events;
  }

  /**
   * Handles exceptions and displays a custom error page.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    // Don't override if a response has already been set.
    if ($event->hasResponse()) {
      return;
    }

    $exception = $event->getThrowable();
    $request = $event->getRequest();

    // Only handle HTML requests.
    if ($request->getRequestFormat() !== 'html') {
      return;
    }

    // Only handle 5xx errors (server errors).
    $status_code = 500;
    if ($exception instanceof HttpExceptionInterface) {
      $status_code = $exception->getStatusCode();
    }

    // Only handle 5xx errors.
    if ($status_code < 500 || $status_code >= 600) {
      return;
    }

    // Create custom error page HTML.
    $content = '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Temporarily Unavailable</title>
  <style>
    body {
      text-align: center;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 50px 20px;
    }
    h3 {
      margin-bottom: 20px;
    }
    p {
      margin: 10px 0;
    }
  </style>
</head>
<body class="">
<h3>Temporarily Unavailable</h3>

<p>The website that you\'re trying to reach is having technical difficulties and is currently unavailable.</p>
<p>We are aware of the issue and are working hard to fix it. Thank you for your patience.</p>

<div id="quick-start-container"></div>
</body>
</html>';

    $response = new Response($content, $status_code, ['Content-Type' => 'text/html']);
    $event->setResponse($response);
    $event->stopPropagation();
  }

}

