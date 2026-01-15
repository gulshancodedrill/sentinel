<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Options form after anonymous sample submission.
 */
class AnonymousSampleOptionsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AnonymousSampleOptionsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
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
    return 'anonymous_sample_options_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sample_id = NULL) {
    if (!$sample_id) {
      $this->messenger()->addError($this->t('Invalid sample.'));
      return $form;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $sample = $storage->load($sample_id);

    if (!$sample) {
      $this->messenger()->addError($this->t('Sample not found.'));
      return $form;
    }

    $pack_reference_number = $sample->get('pack_reference_number')->value;

    $form['#title'] = $this->t('Sample submitted successfully but you need to add full details');

    $form['message'] = [
      '#markup' => '<div class="messages messages--status">' .
        '<p>' . $this->t('Your sample with Packet Reference Number <strong>@pack</strong> has been submitted successfully.', [
          '@pack' => $pack_reference_number,
        ]) . '</p>' .
        '<p>' . $this->t('What would you like to do next?') . '</p>' .
        '</div>',
      '#weight' => -10,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 10,
    ];

    // Add Details Now button
    $form['actions']['add_details_now'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Details Now'),
      '#submit' => ['::addDetailsNow'],
      '#attributes' => [
        'class' => ['button--primary'],
      ],
    ];

    // Add Details Later button
    $form['actions']['add_details_later'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Details Later'),
      '#submit' => ['::addDetailsLater'],
    ];

    $form_state->set('sample_id', $sample_id);

    return $form;
  }

  /**
   * Submit handler for "Add Details Now".
   */
  public function addDetailsNow(array &$form, FormStateInterface $form_state) {
    $sample_id = $form_state->get('sample_id');
    
    // Redirect to anonymous details form
    $form_state->setRedirect('sentinel_portal_sample.anonymous_details', [
      'sample_id' => $sample_id,
    ]);
  }

  /**
   * Submit handler for "Add Details Later".
   */
  public function addDetailsLater(array &$form, FormStateInterface $form_state) {
    // Redirect to thank you page
    $form_state->setRedirect('sentinel_portal_sample.anonymous_thank_you');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Default submit handler (should not be called)
  }
}
