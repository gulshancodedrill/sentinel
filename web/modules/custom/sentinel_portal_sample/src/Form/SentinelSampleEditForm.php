<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Sample edit form.
 */
class SentinelSampleEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_sample_edit_form';
  }

  /**
   * Builds the edit form for a sentinel sample.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SentinelSample $sentinel_sample = NULL) {
    $form_state->set('sentinel_sample', $sentinel_sample);

    $options = $this->getHoldStateOptions();
    $form['sentinel_sample_hold_state_target_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Sample hold state'),
      '#options' => $options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $sentinel_sample->get('sentinel_sample_hold_state_target_id')->value ?: NULL,
      '#description' => $this->t('Select the hold state to apply to this sample.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\sentinel_portal_entities\Entity\SentinelSample $sentinel_sample */
    $sentinel_sample = $form_state->get('sentinel_sample');
    $hold_state = $form_state->getValue('sentinel_sample_hold_state_target_id');

    if ($hold_state === '' || $hold_state === NULL) {
      $sentinel_sample->set('sentinel_sample_hold_state_target_id', NULL);
    }
    else {
      $sentinel_sample->set('sentinel_sample_hold_state_target_id', (int) $hold_state);
    }

    // Create a new revision on each save.
    $sentinel_sample->setNewRevision(TRUE);
    $sentinel_sample->save();

    $this->messenger()->addMessage($this->t('Sample updated successfully.'));
    $form_state->setRedirect('entity.sentinel_sample.canonical', ['sentinel_sample' => $sentinel_sample->id()]);
  }

  /**
   * Builds the options list for hold state vocabulary terms.
   */
  protected function getHoldStateOptions(): array {
    $options = [];
    $vocabulary = Vocabulary::load('hold_state_values');
    if (!$vocabulary) {
      return $options;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadTree($vocabulary->id());

    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }

}
