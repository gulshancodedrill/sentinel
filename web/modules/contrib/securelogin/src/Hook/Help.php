<?php

namespace Drupal\securelogin\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Implements hook_help().
 */
#[Hook('help')]
class Help {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  public function __invoke(?string $route_name): ?TranslatableMarkup {
    return match ($route_name) {
      'help.page.securelogin' => $this->t('The Secure Login module allows user login and other forms to be submitted to a configurable secure (HTTPS) URL from the insecure (HTTP) site. By securing the user login forms, a site can enforce secure authenticated sessions, which are immune to <a rel="noreferrer" href="https://en.wikipedia.org/wiki/Session_hijacking">session hijacking</a>.'),
      'securelogin.admin' => $this->t('You may configure the user login and other forms to be submitted to the secure (HTTPS) base URL. By securing all forms that create a session, a site can enforce secure sessions which are immune to <a rel="noreferrer" href="https://en.wikipedia.org/wiki/Session_hijacking">session hijacking</a> by eavesdroppers.'),
      default => NULL,
    };
  }

}
