<?php

namespace Drupal\sentinel_portal_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_entities\Entity\SentinelClient;

/**
 * Form for testing UCR generation.
 */
class SentinelClientUcrForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_client_ucr_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number'),
      '#description' => $this->t('The number to generate a UCR from. This is the internal client ID'),
      '#required' => TRUE,
    ];

    if ($form_state->getUserInput()) {
      $input = $form_state->getUserInput();
      if (isset($input['number'])) {
        $form['number']['#default_value'] = $input['number'];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 50,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $number = $form_state->getValue('number');

    if (!is_numeric($number)) {
      $form_state->setError($form['number'], $this->t('Please enter a number.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Create a temporary client entity to use the UCR generation method.
    $client = SentinelClient::create([]);

    // Grab the entered number from the form.
    $number = $form_state->getValue('number');

    // Generate the UCR and pass it back to the user.
    $ucr = $client->generateUcr($number);
    $this->messenger()->addMessage($this->t('The @number has the UCR value of @ucr', [
      '@number' => $number,
      '@ucr' => $ucr,
    ]));

    // Rebuild the form so that we can put the input back into the form.
    $form_state->setRebuild(TRUE);
  }

}
