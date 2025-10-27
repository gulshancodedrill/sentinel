<?php

namespace Drupal\securelogin;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Defines the SecureLoginCacheableDependency.
 */
class SecureLoginCacheableDependency implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // FormBuilder adds the user.roles:authenticated cache context, so add it
    // here too to avoid a warning re: trying to overwrite a cache redirect with
    // one that has nothing in common.
    return ['url', 'url.site', 'user.roles:authenticated'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['securelogin'];
  }

}
