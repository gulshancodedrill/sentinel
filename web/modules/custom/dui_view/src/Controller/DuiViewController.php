<?php

namespace Drupal\dui_view\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for DUI View module.
 */
class DuiViewController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Access callback to check if the key is valid.
   *
   * @param string $key
   *   The key from the URL.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkKey($key) {
    if (empty($key)) {
      return AccessResult::forbidden();
    }

    // Get the keys from the config of the site.
    $site_key = $this->config('dui_view.settings')->get('site_key');
    $site_key_private = $this->config('dui_view.settings')->get('site_key_priv');

    // Work out the hmac value.
    $hmac = hash_hmac('sha256', $site_key, $site_key_private);

    // Check key validity.
    if ($hmac == $key) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Page callback to list modules.
   *
   * Generate a list of currently active modules and return it as a JSON string.
   * This JSON string will also contain information related to the Drupal Core
   * version.
   *
   * @param string $key
   *   The key from the URL.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The encrypted JSON response.
   */
  public function listModules($key) {
    // Get the current module list.
    $module_handler = \Drupal::service('module_handler');
    $module_list = $module_handler->getModuleList();

    $modules = [];
    $extension_discovery = \Drupal::service('extension.list.module');

    foreach ($module_list as $name => $module) {
      $extension = $extension_discovery->getExtension($name, 'module');

      // Skip hidden, multi-project, core, or inactive modules.
      if ($extension->isHidden()) {
        continue;
      }
      if ($extension->getProject() != $name) {
        continue;
      }
      if ($extension->getPackage() == 'Core') {
        continue;
      }

      // Each module that passes the conditionals are stored in an array.
      $modules[$name] = [
        'name' => $extension->getName(),
        'version' => $extension->getVersion(),
        'status' => 'enabled',
      ];
    }

    // Global variables and module array stored within a single array.
    $config = $this->config('system.site');
    $site = [
      'sitename' => $config->get('name'),
      'core' => \Drupal::VERSION,
      'modules' => $modules,
      'data' => [],
    ];

    // Get some configuration values.
    $config = $this->config('system.performance');
    $site['data']['preprocess_css'] = $config->get('css.preprocess') ? 1 : 0;
    $site['data']['preprocess_js'] = $config->get('js.preprocess') ? 1 : 0;
    $site['data']['page_compression'] = $config->get('response.gzip') ? 1 : 0;
    $site['data']['cache'] = 1; // Caching is always on in D11
    $site['data']['block_cache'] = 1; // Block cache is always on
    $site['data']['clean_url'] = 1; // Clean URLs are always on
    $site['data']['cron_last'] = \Drupal::state()->get('system.cron_last');
    $site['data']['error_level'] = 0; // Error level is configured differently in D11

    // Log the site data.
    \Drupal::logger('dui_view')->info('Site data generated: @sitedata', [
      '@sitedata' => print_r($site, TRUE),
    ]);

    // Outputs the filtered data as JSON string.
    $json = json_encode($site);

    // We encrypt the JSON before sending it out.
    $encrypted = $this->encrypt($json, $this->config('dui_view.settings')->get('site_key_priv'));

    $response = new Response($encrypted);
    $response->headers->set('Content-Type', 'text/html; charset=utf-8');

    return $response;
  }

  /**
   * Encrypt plain data.
   *
   * @param string $data
   *   The plain text data.
   * @param string $key
   *   The private site key.
   *
   * @return string
   *   The encrypted data.
   */
  protected function encrypt($data, $key) {
    // In Drupal 11, we use OpenSSL instead of mcrypt (deprecated).
    $key = hash('sha256', $key, TRUE);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    $encrypted = base64_encode($iv . $encrypted);
    $encrypted = str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);

    return trim($encrypted);
  }

}


