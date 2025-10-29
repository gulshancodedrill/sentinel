<?php

namespace Drupal\sentinel_portal_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for Sentinel Portal pages.
 */
class SentinelPortalController extends ControllerBase {

  /**
   * Main portal page callback.
   *
   * @return array|RedirectResponse
   *   A renderable array or redirect response.
   */
  public function mainPage() {
    // If user is anonymous, redirect to login page with destination
    if ($this->currentUser()->isAnonymous()) {
      $url = Url::fromRoute('user.login', [], [
        'query' => ['destination' => '/portal']
      ]);
      return new RedirectResponse($url->toString());
    }
    
    if ($this->currentUser()->hasPermission('sentinel portal')) {
      // Render the analytics block programmatically
      $block_manager = \Drupal::service('plugin.manager.block');
      $block_plugin = $block_manager->createInstance('sentinel_portal_test_analytics');
      $analytics_block = $block_plugin->build();
      
      return [
        '#theme' => 'sentinel_portal_main_page',
        '#user_branch' => NULL,
        '#analytics_block' => $analytics_block,
      ];
    }
    else {
      // If the user doesn't have permission, show access denied
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
  }

  /**
   * Admin page callback.
   *
   * @return array
   *   A renderable array.
   */
  public function adminPage() {
    $output = [];

    $output[] = [
      '#markup' => $this->t('Welcome to the Sentinel Portal admin area.'),
    ];

    if ($this->currentUser()->hasPermission('sentinel portal administration')) {
      $output[] = [
        '#markup' => '<h2>' . $this->t('Administration') . '</h2>',
      ];

      if ($this->moduleHandler()->moduleExists('sentinel_portal_entities')) {
        // Check if entity routes exist before creating links
        $route_provider = \Drupal::service('router.route_provider');
        
        try {
          $route_provider->getRouteByName('entity.sentinel_client.collection');
          $output[] = [
            '#markup' => '<p>' . Link::createFromRoute($this->t('Sentinel Clients'), 'entity.sentinel_client.collection')->toString() . '</p>',
          ];
        } catch (\Exception $e) {
          $output[] = [
            '#markup' => '<p>' . $this->t('Sentinel Clients') . ' - ' . $this->t('Route not available yet') . '</p>',
          ];
        }

        try {
          $route_provider->getRouteByName('entity.sentinel_sample.collection');
          $output[] = [
            '#markup' => '<p>' . Link::createFromRoute($this->t('Sentinel Samples'), 'entity.sentinel_sample.collection')->toString() . '</p>',
          ];
        } catch (\Exception $e) {
          $output[] = [
            '#markup' => '<p>' . $this->t('Sentinel Samples') . ' - ' . $this->t('Route not available yet') . '</p>',
          ];
        }
      }

      if ($this->moduleHandler()->moduleExists('sentinel_portal_notice')) {
        try {
          $route_provider = \Drupal::service('router.route_provider');
          $route_provider->getRouteByName('entity.sentinel_notice.collection');
          $output[] = [
            '#markup' => '<p>' . Link::createFromRoute($this->t('Sentinel Notices'), 'entity.sentinel_notice.collection')->toString() . '</p>',
          ];
        } catch (\Exception $e) {
          $output[] = [
            '#markup' => '<p>' . $this->t('Sentinel Notices') . ' - ' . $this->t('Route not available yet') . '</p>',
          ];
        }
      }

      if ($this->moduleHandler()->moduleExists('sentinel_portal_queue')) {
        try {
          $route_provider = \Drupal::service('router.route_provider');
          $route_provider->getRouteByName('sentinel_portal.queue.admin');
          $output[] = [
            '#markup' => '<p>' . Link::createFromRoute($this->t('Sentinel Queue'), 'sentinel_portal.queue.admin')->toString() . '</p>',
          ];
        } catch (\Exception $e) {
          $output[] = [
            '#markup' => '<p>' . $this->t('Sentinel Queue') . ' - ' . $this->t('Route not available yet') . '</p>',
          ];
        }
      }

      $output[] = [
        '#markup' => '<p>' . Link::createFromRoute($this->t('Portal Config'), 'sentinel_portal.config')->toString() . '</p>',
      ];

      $output[] = [
        '#markup' => '<h2>' . $this->t('Tools') . '</h2>',
      ];

      try {
        $route_provider = \Drupal::service('router.route_provider');
        $route_provider->getRouteByName('sentinel_portal.ucr_test');
        $output[] = [
          '#markup' => '<p>' . Link::createFromRoute($this->t('Simple tool to check and generate UCR numbers'), 'sentinel_portal.ucr_test')->toString() . '</p>',
        ];
      } catch (\Exception $e) {
        $output[] = [
          '#markup' => '<p>' . $this->t('Simple tool to check and generate UCR numbers') . ' - ' . $this->t('Route not available yet') . '</p>',
        ];
      }
    }

    return $output;
  }

}
