<?php

namespace Drupal\sentinel_addresses\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Form for adding/editing address notes.
 */
class AddressNoteForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The address entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $address;

  /**
   * The note delta.
   *
   * @var int|null
   */
  protected $noteDelta;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_addresses_address_note_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $address = NULL, $note_delta = NULL) {
    $this->address = $address;
    $this->noteDelta = $note_delta;

    $form['note_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address Note'),
      '#collapsible' => FALSE,
    ];

    // Load note type vocabulary terms.
    $vocabulary = Vocabulary::load('address_note_type');
    $type_options = [];
    if ($vocabulary) {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $terms = $term_storage->loadByProperties(['vid' => 'address_note_type']);
      foreach ($terms as $term) {
        $type_options[$term->id()] = $term->getName();
      }
    }

    $form['note_fieldset']['note_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Device Fitted'),
      '#required' => TRUE,
      '#options' => $type_options,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['note_fieldset']['note_details'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Device Name'),
      '#required' => TRUE,
      '#maxlength' => 150,
      '#description' => $this->t('Maximum of 150 characters.'),
    ];

    $form['note_fieldset']['note_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#required' => TRUE,
    ];

    $form['note_fieldset']['entity'] = [
      '#type' => 'value',
      '#value' => $address->id(),
    ];

    // Load existing note if editing.
    if (!is_null($note_delta) && $address->hasField('field_address_note')) {
      $notes = $address->get('field_address_note');
      if (isset($notes[$note_delta])) {
        $note = $notes[$note_delta]->entity;
        if ($note) {
          $form['note_fieldset']['note_type']['#default_value'] = $note->get('field_field_address_note_type')->target_id ?? NULL;
          $form['note_fieldset']['note_details']['#default_value'] = $note->get('field_address_note_details')->value ?? '';
          if ($note->hasField('field_address_note_date') && !$note->get('field_address_note_date')->isEmpty()) {
            $date_value = $note->get('field_address_note_date')->value;
            if ($date_value) {
              $date = new \DateTime($date_value);
              $form['note_fieldset']['note_date']['#default_value'] = $date->format('Y-m-d');
            }
          }
          if ($note->hasField('field_field_address_note_type') && !$note->get('field_field_address_note_type')->isEmpty()) {
            $form['note_fieldset']['note_type']['#default_value'] = $note->get('field_field_address_note_type')->target_id;
          }
          $form['note_fieldset']['note_delta'] = [
            '#type' => 'value',
            '#value' => $note_delta,
          ];
          $form['note_fieldset']['submit']['#value'] = $this->t('Update Note');
        }
      }
    }

    $form['note_fieldset']['submit'] = [
      '#type' => 'submit',
      '#value' => !is_null($note_delta) ? $this->t('Update Note') : $this->t('Add Note'),
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $type = $form_state->getValue('note_type');
    $details = $form_state->getValue('note_details');
    $date = $form_state->getValue('note_date');
    $entity_id = $form_state->getValue('entity');
    $note_delta = $form_state->getValue('note_delta');

    // Load the address entity.
    $address = $this->entityTypeManager
      ->getStorage('address')
      ->load($entity_id);

    if (!$address) {
      return;
    }

    if (!is_null($note_delta)) {
      // Update existing note.
      $notes = $address->get('field_address_note');
      if (isset($notes[$note_delta])) {
        $note = $notes[$note_delta]->entity;
        if ($note) {
          $note->set('field_address_note_details', $details);
          $note->set('field_address_note_date', ['value' => $date]);
          $note->set('field_field_address_note_type', ['target_id' => $type]);
          $note->save();
        }
      }
    } else {
      // Create new note (using Paragraphs).
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
      $note = $paragraph_storage->create([
        'type' => 'field_address_note',
        'field_address_note_details' => $details,
        'field_address_note_date' => ['value' => $date],
        'field_field_address_note_type' => ['target_id' => $type],
      ]);
      $note->save();

      // Add to address entity.
      $address->get('field_address_note')->appendItem($note);
    }

    $address->save();

    $this->messenger()->addStatus($this->t('Address note saved.'));
    $form_state->setRedirect('sentinel_addresses.address_view_legacy', ['address' => $entity_id]);
  }

}
