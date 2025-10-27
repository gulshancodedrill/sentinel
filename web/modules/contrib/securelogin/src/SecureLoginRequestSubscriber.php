<?php

namespace Drupal\securelogin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens for insecure password reset login requests and redirects to HTTPS.
 */
class SecureLoginRequestSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new SecureLoginRequestSubscriber.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Redirects insecure password reset attempts to the secure site.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if ($request->isSecure()) {
      return;
    }
    if ($this->routeMatch->getRouteName() !== 'user.reset.login') {
      return;
    }
    $config = $this->configFactory->get('securelogin.settings');
    $forms = $config->get('forms');
    if (!$config->get('all_forms') && (!\is_array($forms) || !\in_array('user_pass_reset', $forms))) {
      return;
    }
    $url = Url::fromRouteMatch($this->routeMatch)
      ->setAbsolute()
      ->setOption('external', FALSE)
      ->setOption('https', TRUE)
      ->setOption('query', $request->query->all())
      ->toString();
    $status = $request->isMethodCacheable() ? TrustedRedirectResponse::HTTP_MOVED_PERMANENTLY : TrustedRedirectResponse::HTTP_PERMANENTLY_REDIRECT;
    $event->setResponse(new TrustedRedirectResponse($url, $status));
    // Redirect URL has destination so consider this the final destination.
    $request->query->set('destination', '');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onRequest', 2];
    return $events;
  }

}
