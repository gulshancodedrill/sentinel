<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
    $prn = $this->getAnonymousPrn();
    if ($prn === '') {
      $this->messenger()->addError($this->tFlow('Invalid sample.'));
      return $form;
    }

    $sample = $this->loadAnonymousSampleByPrn($prn);
    if (!$sample || !$sample->id()) {
      $this->messenger()->addWarning($this->tFlow('Please complete the first step before continuing.'));
      $form['#redirect'] = Url::fromRoute('sentinel_portal_sample.anonymous_submit', [], AnonymousSampleWizardProgress::prnRedirectOptions($prn));
      return $form;
    }

    if (AnonymousSampleWizardProgress::sampleIsFullySubmitted($sample)) {
      $form['#title'] = $this->tFlow('Sample Already Submitted');
      $form['message'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<p><strong>' . $this->tFlow('This record already exists.') . '</strong></p>' .
          '<p>' . $this->tFlow('A sample with Packet Reference Number @prn has already been submitted with complete details.', [
            '@prn' => $prn,
          ]) . '</p></div>',
        '#weight' => -10,
      ];
      return $form;
    }

    $flow = AnonymousSampleWizardProgress::resolveUserType($sample, $form_state, $this->getFormId())
      ?? AnonymousSampleWizardProgress::inferUserTypeFromSample($sample)
      ?? 'individual';
    if (AnonymousSampleWizardProgress::isCompanyFlow($flow)) {
      $form['#redirect'] = Url::fromRoute(
        'sentinel_portal_sample.anonymous_submit_company',
        [],
        AnonymousSampleWizardProgress::prnRedirectOptions($prn)
      );
      return $form;
    }

    $step_access = AnonymousSampleWizardProgress::wizardStepAccess($sample, $flow);
    if (empty($step_access[2])) {
      $form['#redirect'] = Url::fromRoute('sentinel_portal_sample.anonymous_submit', [], AnonymousSampleWizardProgress::prnRedirectOptions($prn));
      return $form;
    }

    if ($this->anonymousSampleRequiresVerification((int) $sample->id())) {
      return $this->buildAnonymousVerificationForm((int) $sample->id(), $form, $form_state);
    }

    $form['#title'] = $this->tFlow('Your details');
    $form_state->set('contact_prn', $prn);
    $form_state->set('contact_sample_id', $sample->id());

    $prn = '';
    if ($sample->hasField('pack_reference_number') && !$sample->get('pack_reference_number')->isEmpty()) {
      $prn = $sample->get('pack_reference_number')->value;
    }

    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Sample Pack Ref No'),
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
    $prn = $form_state->get('contact_prn') ?: $this->getAnonymousPrn();
    if ($prn !== '') {
      $form_state->setRedirect(
        'sentinel_portal_sample.anonymous_submit',
        [],
        AnonymousSampleWizardProgress::prnRedirectOptions($prn)
      );
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
    $prn = $form_state->get('contact_prn') ?: $this->getAnonymousPrn();
    $sample = $this->loadAnonymousSampleByPrn($prn);
    if (!$sample || !$sample->id()) {
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

    $sample->save();
    $this->getRequest()->getSession()->set(
      'sentinel_anonymous_last_flow',
      'individual'
    );

    $form_state->setRedirect(
      'sentinel_portal_sample.anonymous_submit_other_details',
      [],
      AnonymousSampleWizardProgress::prnRedirectOptions($prn)
    );
  }

}

