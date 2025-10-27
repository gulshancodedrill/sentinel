<?php

namespace Drupal\securelogin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Secure login path processor.
 *
 * This path processor applies a configured secure base URL. It is useful for
 * sites that have multiple insecure base URLs and an SSL certificate valid only
 * for one secure base URL.
 */
class SecureLoginPathProcessor implements OutboundPathProcessorInterface {

  /**
   * Constructs secure login path processor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore missingType.iterableValue
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!empty($options['https']) && ($baseUrl = $this->configFactory->get('securelogin.settings')->get('base_url'))) {
      $options['absolute'] = TRUE;
      $options['base_url'] = $baseUrl;
    }
    return $path;
  }

}
