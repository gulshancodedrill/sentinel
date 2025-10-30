<?php

namespace Drupal\dui_view\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for DUI View settings.
 */
class DuiViewConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dui_view_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dui_view.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dui_view.settings');

    $form['site_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The public site key'),
      '#required' => TRUE,
      '#default_value' => $config->get('site_key'),
      '#description' => $this->t('This key is used for generating the HMAC hash for authentication.'),
    ];

    $form['site_key_priv'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The private site key'),
      '#required' => TRUE,
      '#default_value' => $config->get('site_key_priv'),
      '#description' => $this->t('This key is used for encryption of the JSON data.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dui_view.settings')
      ->set('site_key', $form_state->getValue('site_key'))
      ->set('site_key_priv', $form_state->getValue('site_key_priv'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}


