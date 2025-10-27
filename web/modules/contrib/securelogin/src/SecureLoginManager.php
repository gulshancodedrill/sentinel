<?php

namespace Drupal\securelogin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\user\Plugin\Block\UserLoginBlock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines the secure login service.
 */
class SecureLoginManager implements TrustedCallbackInterface {

  use DependencySerializationTrait;

  /**
   * Form action placeholder for the form builder.
   */
  const FORM_PLACEHOLDER = 'form_action_p_pvdeGsVG5zNF_XLGPTvYSKCf43t8qZYSwcfZl2uzM';

  /**
   * Form action placeholder for the user login block.
   */
  const USER_PLACEHOLDER = 'form_action_p_4r8ITd22yaUvXM6SzwrSe9rnQWe48hz9k1Sxto3pBvE';

  /**
   * Constructs the secure login service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[Autowire('@event_dispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    protected RequestStack $requestStack,
    protected FormBuilderInterface $formBuilder,
  ) {
  }

  /**
   * Rewrites the form action to use the secure base URL.
   *
   * @param mixed[] $form
   *   The form array.
   */
  public function secureForm(array &$form): void {
    if (isset($form['#cache']) && !\is_array($form['#cache'])) {
      return;
    }
    // Rebuild this form based on the URL scheme.
    $form['#cache']['contexts'][] = 'url.site';
    // Flag form as secure for theming purposes.
    $form['#https'] = TRUE;
    // Core secures the form action when #https is enabled, but doesn't use our
    // configured base URL, so undo it, and re-do it if necessary.
    $form['#after_build'][] = [$this, 'afterBuild'];
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->isSecure()) {
      return;
    }
    // Redirect to secure page, if enabled.
    if ($this->configFactory->get('securelogin.settings')->get('secure_forms')) {
      // Disable caching, as this form must be rebuilt to set the redirect.
      $form['#cache']['max-age'] = 0;
      $this->secureRedirect();
    }
    $form['#action'] = $this->secureUrl($form['#action']);
    if (isset($form['#attached']['placeholders'][self::FORM_PLACEHOLDER])) {
      $form['#attached']['placeholders'][self::FORM_PLACEHOLDER]['#lazy_builder'] = [
        'securelogin.manager:renderPlaceholderFormAction',
        [self::FORM_PLACEHOLDER],
      ];
    }
    if (isset($form['#attached']['placeholders'][self::USER_PLACEHOLDER])) {
      $form['#attached']['placeholders'][self::USER_PLACEHOLDER]['#lazy_builder'] = [
        'securelogin.manager:renderPlaceholderFormAction',
        [self::USER_PLACEHOLDER],
      ];
    }
  }

  /**
   * Reimplement core form securing to use configured base URL.
   *
   * @param mixed[] $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed[]
   *   The form array.
   */
  public function afterBuild(array $form, FormStateInterface $form_state): array {
    global $base_root;
    $prefix = str_replace('http://', 'https://', $base_root);
    if (!empty($form['#https']) && isset($form['#action']) && strpos($form['#action'], $prefix) === 0) {
      // Undo core's forcing the URL to HTTPS.
      $form['#action'] = substr($form['#action'], \strlen($prefix));
      $request = $this->requestStack->getCurrentRequest();
      // Resecure form action if we're on HTTP site.
      if ($request && !$request->isSecure()) {
        $form['#action'] = $this->secureUrl($form['#action']);
      }
    }
    return $form;
  }

  /**
   * Redirects a request to the same path on the secure base URL.
   */
  public function secureRedirect(): void {
    $request = $this->requestStack->getCurrentRequest();
    // Do not redirect from HTTPS requests.
    if ($request && $request->isSecure()) {
      return;
    }
    $status = ($request && $request->isMethodCacheable()) ? TrustedRedirectResponse::HTTP_MOVED_PERMANENTLY : TrustedRedirectResponse::HTTP_PERMANENTLY_REDIRECT;
    // Build the redirect URL from the main request.
    $request = $this->requestStack->getMainRequest();
    if (!$request) {
      return;
    }
    // Request may be a 404 so handle as unrouted URI.
    $url = Url::fromUri("internal:{$request->getPathInfo()}");
    $url->setOption('absolute', TRUE)
      ->setOption('external', FALSE)
      ->setOption('https', TRUE)
      ->setOption('query', $request->query->all());
    // Create listener to set the redirect response.
    $listener = function ($event) use ($url, $status) {
      $response = new TrustedRedirectResponse($url->toString(), $status);
      // Add cache context for this redirect.
      $response->addCacheableDependency(new SecureLoginCacheableDependency());
      $event->setResponse($response);
      // Redirect URL has destination so consider this the final destination.
      $event->getRequest()->query->set('destination', '');
    };
    // Add listener to response event at high priority.
    $this->eventDispatcher->addListener(KernelEvents::RESPONSE, $listener, 222);
  }

  /**
   * Rewrites a URL to use the secure base URL.
   */
  public function secureUrl(string $url): string {
    global $base_path, $base_secure_url;
    // Set the form action to use secure base URL in place of base path.
    if (strpos($url, $base_path) === 0) {
      $base_url = $this->configFactory->get('securelogin.settings')->get('base_url') ?: $base_secure_url;
      return substr_replace($url, $base_url, 0, \strlen($base_path) - 1);
    }
    // If URL is absolute, forcibly rewrite to HTTPS.
    $url = preg_replace('#^http://#i', 'https://', $url);
    if (!\is_string($url)) {
      throw new \UnexpectedValueException('Regular expression error.');
    }
    return $url;
  }

  /**
   * Lazy builder callback; renders a form action URL including destination.
   *
   * @param string $placeholder
   *   The placeholder string, which is used to determine which
   *   renderPlaceholderFormAction() method should be called.
   *
   * @return string[]
   *   A renderable array representing the form action.
   *
   * @see \Drupal\Core\Form\FormBuilder::renderPlaceholderFormAction()
   */
  public function renderPlaceholderFormAction($placeholder = NULL) {
    // This method is not defined in the interface, so check that it exists.
    if ($placeholder === self::FORM_PLACEHOLDER && method_exists($this->formBuilder, 'renderPlaceholderFormAction')) {
      $action = $this->formBuilder->renderPlaceholderFormAction();
    }
    else {
      $action = UserLoginBlock::renderPlaceholderFormAction();
    }
    $action['#markup'] = $this->secureUrl($action['#markup']);
    return $action;
  }

  /**
   * Returns list of trusted callbacks.
   */
  public static function trustedCallbacks() {
    return ['renderPlaceholderFormAction', 'userLoginBlockPreRender'];
  }

  /**
   * Pre-render callback to re-secure the user login block.
   *
   * @param mixed[] $build
   *   The block renderable array.
   *
   * @return mixed[]
   *   The block renderable array.
   */
  public function userLoginBlockPreRender(array $build) {
    if (isset($build['content']) && \is_array($build['content']) && isset($build['content']['user_login_form']) && \is_array($build['content']['user_login_form']) && !empty($build['content']['user_login_form']['#https'])) {
      $this->secureForm($build['content']['user_login_form']);
    }
    return $build;
  }

}
