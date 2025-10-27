<?php

namespace Drupal\securelogin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens for login to the 403 page and redirects to destination.
 */
class SecureLoginResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new SecureLoginResponseSubscriber.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected CurrentPathStack $currentPath,
    protected RouteMatchInterface $routeMatch,
    protected AccountInterface $currentUser,
    protected RedirectDestinationInterface $redirectDestination,
  ) {
  }

  /**
   * Redirects login attempts on already-logged-in session to the destination.
   */
  public function onRespond(ResponseEvent $event): void {
    $response = $event->getResponse();
    $request = $event->getRequest();
    if ($response instanceof TrustedRedirectResponse && \in_array('securelogin', $response->getCacheableMetadata()->getCacheTags())) {
      // Remove destination set when a 404 page is handled by the dynamic
      // page cache and not the page cache.
      $request->query->remove('destination');
    }
    // Return early in most cases.
    if ($request->getMethod() !== 'POST') {
      return;
    }
    if (!$this->currentUser->isAuthenticated()) {
      return;
    }
    if (!$event->isMainRequest()) {
      return;
    }
    if (!$request->query->has('destination')) {
      return;
    }
    if ($response instanceof RedirectResponse) {
      return;
    }
    // @todo Find a better way to figure out if we landed on the 403/404 page.
    $page_403 = $this->configFactory->get('system.site')->get('page.403');
    $page_404 = $this->configFactory->get('system.site')->get('page.404');
    $path = $this->currentPath->getPath();
    $route = $this->routeMatch->getRouteName();
    if ($route == 'system.403' || ($page_403 && $path == $page_403) || $route == 'system.404' || ($page_404 && $path == $page_404)) {
      // RedirectResponseSubscriber will convert to absolute URL for us.
      $event->setResponse(new RedirectResponse($this->redirectDestination->get(), RedirectResponse::HTTP_SEE_OTHER));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onRespond', 2];
    return $events;
  }

}
