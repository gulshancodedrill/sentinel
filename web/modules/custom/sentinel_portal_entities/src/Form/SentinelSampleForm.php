<?php

namespace Drupal\sentinel_portal_entities\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Form controller for Sentinel Sample edit forms.
 */
class SentinelSampleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $form['sentinel_sample_hold_state_target_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Sample hold state'),
      '#options' => $this->getHoldStateOptions(),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $entity->get('sentinel_sample_hold_state_target_id')->value ?: NULL,
      '#weight' => -50,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $hold_state = $form_state->getValue('sentinel_sample_hold_state_target_id');
    if ($hold_state === '' || $hold_state === NULL) {
      $this->entity->set('sentinel_sample_hold_state_target_id', NULL);
    }
    else {
      $this->entity->set('sentinel_sample_hold_state_target_id', (int) $hold_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Sentinel sample.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Sentinel sample.', [
          '%label' => $entity->label(),
        ]));
    }

    $form_state->setRedirectUrl($entity->toUrl('canonical'));
  }

  /**
   * Builds the options list for the hold state vocabulary terms.
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

