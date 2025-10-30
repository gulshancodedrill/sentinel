<?php

namespace Drupal\sentinel_systemcheck_vaillant_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Sentinel Vaillant API settings.
 */
class VaillantApiConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_systemcheck_vaillant_api_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sentinel_systemcheck_vaillant_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sentinel_systemcheck_vaillant_api.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vaillant API Key'),
      '#description' => $this->t('Enter the Vaillant API key. This can also be set via the VAILLANT_API environment variable. If set in environment, it will take precedence.'),
      '#default_value' => $config->get('api_key'),
      '#maxlength' => 255,
      '#required' => FALSE,
    ];

    $form['endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Vaillant API Endpoint'),
      '#description' => $this->t('Enter the Vaillant API endpoint URL. This can also be set via the VAILLANT_API_ENDPOINT environment variable. If set in environment, it will take precedence.'),
      '#default_value' => $config->get('endpoint') ?: 'https://vaillant.sparkitsupport.co.uk/api/new-watersample',
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning"><p>' . $this->t('<strong>Note:</strong> Configuration values set in environment variables (VAILLANT_API, VAILLANT_API_ENDPOINT) will override the values entered here. This form is useful when environment variables are not available or for local development.') . '</p></div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('sentinel_systemcheck_vaillant_api.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
