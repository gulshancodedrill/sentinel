<?php

namespace Drupal\securelogin\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements hook_securelogin_alter().
 */
class SecureLoginAlter {

  use StringTranslationTrait;

  /**
   * Implements hook_securelogin_alter() for comment module.
   *
   * @param mixed[] $forms
   *   Form array.
   */
  #[Hook('securelogin_alter', 'comment', 'comment')]
  public function comment(array &$forms): void {
    $forms['comment_form'] = ['#title' => $this->t('Comment form')];
  }

  /**
   * Implements hook_securelogin_alter() for contact module.
   *
   * @param mixed[] $forms
   *   Form array.
   */
  #[Hook('securelogin_alter', 'contact', 'contact')]
  public function contact(array &$forms): void {
    $forms['contact_message_form'] = ['#title' => $this->t('Contact form')];
  }

  /**
   * Implements hook_securelogin_alter() for node module.
   *
   * @param mixed[] $forms
   *   Form array.
   */
  #[Hook('securelogin_alter', 'node', 'node')]
  public function node(array &$forms): void {
    $forms['node_form'] = ['#title' => $this->t('Node form')];
  }

  /**
   * Implements hook_securelogin_alter() for user module.
   *
   * @param mixed[] $forms
   *   Form array.
   */
  #[Hook('securelogin_alter', 'user', 'user')]
  public function user(array &$forms): void {
    $forms['user_form'] = ['#title' => $this->t('User edit form')];
    $forms['user_login_form'] = ['#title' => $this->t('User login form')];
    $forms['user_pass'] = ['#title' => $this->t('User password request form')];
    $forms['user_pass_reset'] = ['#title' => $this->t('User password reset form')];
    $forms['user_register_form'] = ['#title' => $this->t('User registration form')];
  }

  /**
   * Implements hook_securelogin_alter() for webform module.
   *
   * @param mixed[] $forms
   *   Form array.
   */
  #[Hook('securelogin_alter', 'webform', 'webform')]
  public function webform(array &$forms): void {
    $forms['webform_client_form'] = ['#title' => $this->t('Webform')];
  }

}
