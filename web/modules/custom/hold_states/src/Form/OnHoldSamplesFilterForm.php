<?php

namespace Drupal\hold_states\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Filter form for on-hold samples.
 */
class OnHoldSamplesFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'on_hold_samples_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $selected_tid = NULL, $pack_reference = NULL) {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('hold_states.on_hold_samples')->toString();

    // Hold state filter
    $hold_state_options = ['' => $this->t('- Any -')];
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'hold_state_values']);

    foreach ($terms as $term) {
      $hold_state_options[$term->id()] = $term->getName();
    }

    $form['tid'] = [
      '#type' => 'select',
      '#title' => $this->t('Sample on hold states'),
      '#options' => $hold_state_options,
      '#default_value' => $selected_tid ?: '',
    ];

    // Pack reference filter
    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pack Reference Number'),
      '#default_value' => $pack_reference ?: '',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form uses GET method, no submit needed
  }

}










