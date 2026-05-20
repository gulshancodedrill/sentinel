<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_sample\AnonymousSampleFlowTranslationTrait;
use Drupal\sentinel_portal_sample\AnonymousSampleLanguageRedirect;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;
use Drupal\sentinel_portal_sample\SentinelCustomerServiceLookup;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Individual contact step (email, name, phone) before property details.
 */
class AnonymousSampleIndividualContactForm extends FormBase {

  use AnonymousSampleAccessGateTrait;
  use AnonymousSampleEmailValidationTrait;
  use AnonymousSampleFlowTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * HTTP client for customer-service calls.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new AnonymousSampleIndividualContactForm.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'anonymous_sample_individual_contact_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $token = NULL) {
    if (!$token) {
      $this->messenger()->addError($this->tFlow('Invalid sample.'));
      return $form;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if (str_starts_with($token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }

    if (!$sample) {
      $this->messenger()->addError($this->tFlow('Sample not found.'));
      return $form;
    }

    if ($this->anonymousSampleRequiresVerification($token)) {
      return $this->buildAnonymousVerificationForm($token, $form, $form_state);
    }

    $form['#title'] = $this->tFlow('Your details');
    $form_state->set('contact_token', $token);

    $prn = '';
    if ($sample->hasField('pack_reference_number') && !$sample->get('pack_reference_number')->isEmpty()) {
      $prn = $sample->get('pack_reference_number')->value;
    }

    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Pack number'),
      '#default_value' => $prn,
      '#disabled' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->tFlow('Email'),
      '#required' => TRUE,
      '#default_value' => $sample->hasField('installer_email') && !$sample->get('installer_email')->isEmpty()
        ? $sample->get('installer_email')->value
        : '',
    ];

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Full name'),
      '#required' => TRUE,
      '#default_value' => $sample->hasField('installer_name') && !$sample->get('installer_name')->isEmpty()
        ? $sample->get('installer_name')->value
        : '',
    ];

    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->tFlow('Phone'),
      '#default_value' => $sample->hasField('company_tel') && !$sample->get('company_tel')->isEmpty()
        ? $sample->get('company_tel')->value
        : '',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->tFlow('Back'),
      '#submit' => ['::submitBackFromIndividual'],
      '#limit_validation_errors' => [],
      '#weight' => -5,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->tFlow('Next'),
      '#button_type' => 'primary',
      '#weight' => 0,
    ];

    AnonymousSampleWizardProgress::prependToForm($form, $form_state, 'anonymous_sample_individual_contact_form', $sample);

    return $form;
  }

  /**
   * Navigates to the previous step (account type / language).
   */
  public function submitBackFromIndividual(array &$form, FormStateInterface $form_state): void {
    $token = $form_state->get('contact_token');
    $session = $this->getRequest()->getSession();
    if ($session->get('sentinel_anonymous_entry') === 'options') {
      $form_state->setRedirect('sentinel_portal_sample.anonymous_options', [
        'token' => $token,
      ], AnonymousSampleLanguageRedirect::options());
      return;
    }
    
    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if (str_starts_with($token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }
    
    $prn = '';
    if ($sample && $sample->hasField('pack_reference_number') && !$sample->get('pack_reference_number')->isEmpty()) {
      $prn = trim((string) $sample->get('pack_reference_number')->value);
    }
    if ($prn !== '') {
      $form_state->setRedirect(
        'sentinel_portal_sample.anonymous_submit',
        [],
        AnonymousSampleLanguageRedirect::options() + ['query' => ['prn' => $prn]]
      );
    }
    else {
      $form_state->setRedirect('sentinel_portal_sample.anonymous_options', [
        'token' => $token,
      ], AnonymousSampleLanguageRedirect::options());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if ($trigger && !empty($trigger['#limit_validation_errors'])) {
      return;
    }

    $this->validateEmailFormValue(
      $form_state,
      'email',
      $form_state->getValue('email'),
      TRUE,
      (string) $this->tFlow('Please enter a valid email address.'),
      (string) $this->tFlow('Email is required.')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $token = $form_state->get('contact_token');
    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if (str_starts_with($token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }

    if (!$sample) {
      $this->messenger()->addError($this->tFlow('Sample not found.'));
      return;
    }

    $full_name = trim((string) $form_state->getValue('full_name'));
    $email = trim((string) $form_state->getValue('email'));
    $phone = trim((string) $form_state->getValue('phone'));

    if ($sample->hasField('installer_name')) {
      $sample->set('installer_name', $full_name);
    }
    if ($sample->hasField('installer_email')) {
      $sample->set('installer_email', $email);
    }
    if ($sample->hasField('company_tel')) {
      $sample->set('company_tel', $phone);
    }
    if ($sample->hasField('company_name')) {
      $sample->set('company_name', $full_name);
    }
    if ($sample->hasField('company_email')) {
      $sample->set('company_email', $email);
    }
    $api = SentinelCustomerServiceLookup::fetch(
      $this->httpClient,
      $this->getRequest(),
      $email,
      $full_name,
      $full_name
    );
    if ($sample->hasField('ucr') && $api['ucr'] !== NULL) {
      $sample->set('ucr', $api['ucr']);
    }
    if ($api['client_cid'] !== NULL && $sample->hasField('client_id')) {
      $sample->set('client_id', $api['client_cid']);
      $client = $this->entityTypeManager->getStorage('sentinel_client')->load($api['client_cid']);
      if ($client && $sample->hasField('client_name') && $client->hasField('name') && !$client->get('name')->isEmpty()) {
        $sample->set('client_name', $client->get('name')->value);
      }
    }

    if (str_starts_with($token, 'draft_')) {
      $this->getRequest()->getSession()->set('sentinel_draft_' . $token, $sample->toArray());
    } else {
      $sample->save();
    }

    $form_state->setRedirect('sentinel_portal_sample.anonymous_details', [
      'token' => $token,
    ], AnonymousSampleLanguageRedirect::options());
  }

}

