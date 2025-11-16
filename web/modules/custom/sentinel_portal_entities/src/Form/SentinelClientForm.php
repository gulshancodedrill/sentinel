<?php

namespace Drupal\sentinel_portal_entities\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form controller for Sentinel Client edit forms.
 */
class SentinelClientForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    // Add meta information (display only).
    if (!$entity->isNew()) {
      $form['meta_info'] = [
        '#type' => 'item',
        '#title' => $this->t('Client Information'),
        '#markup' => '<p><strong>' . $this->t('Customer ID') . '</strong>: ' . $entity->id() . '</p>' .
                     '<p><strong>' . $this->t('Drupal User ID') . '</strong>: ' . ($entity->get('uid')->value ?: '0') . '</p>',
        '#weight' => -10,
      ];
    }

    // Update field labels and descriptions to match D7.
    if (isset($form['name']['widget'][0]['value'])) {
      $form['name']['widget'][0]['value']['#title'] = $this->t('The client name.');
    }
    if (isset($form['email']['widget'][0]['value'])) {
      $form['email']['widget'][0]['value']['#title'] = $this->t('The client email.');
    }
    if (isset($form['uid']['widget'][0]['value'])) {
      $form['uid']['widget'][0]['value']['#title'] = $this->t('The Drupal User ID');
      $form['uid']['widget'][0]['value']['#description'] = $this->t('Changing this can have unforeseen consequences.');
    }
    if (isset($form['api_key']['widget'][0]['value'])) {
      $form['api_key']['widget'][0]['value']['#title'] = $this->t('The client API key.');
      $form['api_key']['widget'][0]['value']['#description'] = $this->t('Leave empty to disable API key access');
    }
    if (isset($form['global_access']['widget']['value'])) {
      $form['global_access']['widget']['value']['#title'] = $this->t('Should this client get global access to all samples?');
      $form['global_access']['widget']['value']['#description'] = $this->t('This permission applies to the API interface.');
    }
    if (isset($form['send_pending']['widget']['value'])) {
      $form['send_pending']['widget']['value']['#title'] = $this->t('Whether pending statuses should be sent back via an API call.');
      $form['send_pending']['widget']['value']['#description'] = $this->t('This should be coupled with an API interface');
    }
    if (isset($form['company']['widget'][0]['value'])) {
      $form['company']['widget'][0]['value']['#title'] = $this->t('Company');
    }

    // Make UCR read-only.
    if (isset($form['ucr']['widget'][0]['value'])) {
      $form['ucr']['widget'][0]['value']['#disabled'] = TRUE;
      $form['ucr']['widget'][0]['value']['#description'] = $this->t('This number is auto generated and can not be saved or altered.');
    }

    // Format created/updated as separate date/time fields (read-only).
    if (!$entity->isNew() && !$entity->get('created')->isEmpty()) {
      $created = $entity->get('created')->value;
      if ($created) {
        // Created field is a timestamp, need to convert to DateTime
        $created_date = new \DateTime();
        $created_date->setTimestamp((int) $created);
        $form['created_display'] = [
          '#type' => 'item',
          '#title' => $this->t('When this record was created'),
          '#weight' => 8,
          'date' => [
            '#type' => 'textfield',
            '#title' => $this->t('Date'),
            '#default_value' => $created_date->format('d-m-Y'),
            '#disabled' => TRUE,
            '#attributes' => ['placeholder' => $this->t('E.g., 31-10-2025')],
          ],
          'time' => [
            '#type' => 'textfield',
            '#title' => $this->t('Time'),
            '#default_value' => $created_date->format('H:i'),
            '#disabled' => TRUE,
            '#attributes' => ['placeholder' => $this->t('E.g., 06:20')],
          ],
        ];
      }
    }

    if (!$entity->isNew() && !$entity->get('updated')->isEmpty()) {
      $updated = $entity->get('updated')->value;
      if ($updated) {
        // Updated field is a timestamp, need to convert to DateTime
        $updated_date = new \DateTime();
        $updated_date->setTimestamp((int) $updated);
        $form['updated_display'] = [
          '#type' => 'item',
          '#title' => $this->t('When this record was last updated'),
          '#weight' => 9,
          'date' => [
            '#type' => 'textfield',
            '#title' => $this->t('Date'),
            '#default_value' => $updated_date->format('d-m-Y'),
            '#disabled' => TRUE,
            '#attributes' => ['placeholder' => $this->t('E.g., 31-10-2025')],
          ],
          'time' => [
            '#type' => 'textfield',
            '#title' => $this->t('Time'),
            '#default_value' => $updated_date->format('H:i'),
            '#disabled' => TRUE,
            '#attributes' => ['placeholder' => $this->t('E.g., 06:20')],
          ],
        ];
      }
    }

    // Ensure all base fields are accessible and set weights.
    $base_fields = [
      'name' => 0,
      'email' => 1,
      'uid' => 2,
      'api_key' => 3,
      'global_access' => 4,
      'send_pending' => 5,
      'ucr' => 6,
      'company' => 7,
    ];
    foreach ($base_fields as $field_name => $weight) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = TRUE;
        $form[$field_name]['#weight'] = $weight;
      }
    }

    // Hide the original created/updated fields since we're displaying custom ones.
    $form['created']['#access'] = FALSE;
    $form['updated']['#access'] = FALSE;

    // Check if field_user_cohorts exists and add it if available.
    if ($entity->hasField('field_user_cohorts') && isset($form['field_user_cohorts'])) {
      $form['field_user_cohorts']['#access'] = TRUE;
      $form['field_user_cohorts']['#weight'] = 10;
      if (isset($form['field_user_cohorts']['widget'])) {
        $form['field_user_cohorts']['widget']['#title'] = $this->t('User cohorts');
      }
    }

    // Remove the delete button from the actions.
    if (isset($form['actions']['delete'])) {
      unset($form['actions']['delete']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $email = $form_state->getValue('email')[0]['value'];
    if (!empty($email) && !\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setError($form['email'], $this->t('Client email address must be valid'));
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
        $this->messenger()->addMessage($this->t('Created the %label Sentinel client.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Sentinel client.', [
          '%label' => $entity->label(),
        ]));
    }

    // Redirect to collection page after save
    $form_state->setRedirect('entity.sentinel_client.collection');
  }

}
