<?php

namespace Drupal\sentinel_portal_module\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirect anonymous users hitting portal routes to the login page.
 */
class PortalRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The current user proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The list of route path patterns that bypass the redirect.
   *
   * @var string[]
   */
  protected $allowedPatterns = [
    '/user/login',
    '/user/login/*',
    '/user/password',
    '/user/password/*',
    '/user/register',
    '/user/register/*',
  ];

  /**
   * PortalRedirectSubscriber constructor.
   */
  public function __construct(AccountProxyInterface $current_user, PathMatcherInterface $path_matcher) {
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 35];
    return $events;
  }

  /**
   * Redirect anonymous users from portal paths to the login page.
   */
  public function onKernelRequest(RequestEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();

    $is_portal_path = $this->pathMatcher->matchPath($path, '/portal') || $this->pathMatcher->matchPath($path, '/portal/*');
    $is_front_page = $path === '/' || $path === '';

    if (!$is_portal_path && !$is_front_page) {
      return;
    }

    foreach ($this->allowedPatterns as $pattern) {
      if ($this->pathMatcher->matchPath($path, $pattern)) {
        return;
      }
    }

    $query = $request->query->all();
    $destination = $request->getRequestUri();
    $query['destination'] = $destination;

    $login_url = Url::fromRoute('user.login', [], ['query' => $query])->toString();
    $event->setResponse(new RedirectResponse($login_url));
  }

}

