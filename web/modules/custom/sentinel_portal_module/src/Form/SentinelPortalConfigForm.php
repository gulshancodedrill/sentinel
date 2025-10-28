<?php

namespace Drupal\sentinel_portal_module\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Sentinel Portal.
 */
class SentinelPortalConfigForm extends ConfigFormBase {

  /**
   * Constructs a SentinelPortalConfigForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sentinel_portal.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sentinel_portal.settings');

    $form['new_pack_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Pack Submission Email'),
      '#description' => $this->t('The email address that the newly submitted packs should be sent to.'),
      '#default_value' => $config->get('new_pack_email') ?: '',
    ];

    $form['stop_emails'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Filter'),
      '#description' => $this->t('These are emails that will be filtered out when creating client records as these records might contain multiple values. (add one per line)'),
      '#default_value' => $config->get('stop_emails') ?: '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('new_pack_email');
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('new_pack_email', $this->t('Not a valid email address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sentinel_portal.settings');
    
    $config->set('new_pack_email', $form_state->getValue('new_pack_email'));
    
    $stop_emails = $form_state->getValue('stop_emails');
    if (!empty($stop_emails)) {
      $lines = explode(PHP_EOL, $stop_emails);
      $domains = [];
      foreach ($lines as $line) {
        $domains[] = trim($line);
      }
      $config->set('stop_emails', implode(PHP_EOL, $domains));
      
      $message = '<p>' . $this->t('The following domains have been saved to the email filter:') . '<br>';
      foreach ($domains as $domain) {
        $message .= ' - ' . $domain . '<br>';
      }
      $message .= '</p>';
      $this->messenger()->addMessage($message);
    }
    
    $config->save();
    
    parent::submitForm($form, $form_state);
  }

}
