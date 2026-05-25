<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Drupal\sentinel_portal_entities\Utility\PackTypeFilter;
use Drupal\sentinel_portal_sample\AnonymousSampleFlowTranslationTrait;
use Drupal\sentinel_portal_sample\AnonymousSampleLanguageRedirect;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;
use Drupal\sentinel_portal_sample\PortalSampleCountryOptions;
use Drupal\sentinel_portal_sample\SentinelCustomerServiceLookup;

/**
 * Anonymous QR flow: property / boiler details only (standardised fields).
 */
class AnonymousSamplePropertyDetailsForm extends SentinelSampleSubmissionForm {

  use AnonymousSampleAccessGateTrait;
  use AnonymousSampleEmailValidationTrait;
  use AnonymousSampleFlowTranslationTrait;

  /**
   * The sample entity being updated.
   *
   * @var \Drupal\sentinel_portal_entities\Entity\SentinelSample|null
   */
  protected $sample;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'anonymous_sample_property_details_form';
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

    $form_state->set('property_prn', $prn);

    $this->sample = $this->loadAnonymousSampleByPrn($prn);
    if (!$this->sample || !$this->sample->id()) {
      $this->messenger()->addWarning($this->tFlow('Please complete the first step before continuing.'));
      $form['#redirect'] = Url::fromRoute('sentinel_portal_sample.anonymous_submit', [], AnonymousSampleWizardProgress::prnRedirectOptions($prn));
      return $form;
    }

    $flow = AnonymousSampleWizardProgress::resolveUserType($this->sample, $form_state, $this->getFormId())
      ?? AnonymousSampleWizardProgress::inferUserTypeFromSample($this->sample)
      ?? 'company';
    $step_access = AnonymousSampleWizardProgress::wizardStepAccess($this->sample, $flow);
    if (empty($step_access[3])) {
      $step2_route = AnonymousSampleWizardProgress::step2RouteName($flow);
      $form['#redirect'] = Url::fromRoute($step2_route, [], AnonymousSampleWizardProgress::prnRedirectOptions($prn));
      return $form;
    }

    $session = $this->getRequest()->getSession();
    $session_whitelist_key = 'sentinel_sample_add_details_whitelist_' . $this->sample->id();
    $session_whitelist_timestamp = $session->get($session_whitelist_key);
    $max_age = 1800;
    if ($session_whitelist_timestamp !== NULL) {
      $age = \Drupal::time()->getRequestTime() - (int) $session_whitelist_timestamp;
      if ($age >= 0 && $age <= $max_age) {
        $session->set('sentinel_sample_verified_' . $this->sample->id(), TRUE);
      }
      else {
        $session->remove($session_whitelist_key);
      }
    }

    if ($this->anonymousSampleRequiresVerification((int) $this->sample->id())) {
      return $this->buildAnonymousVerificationForm((int) $this->sample->id(), $form, $form_state);
    }

    if (AnonymousSampleWizardProgress::sampleIsFullySubmitted($this->sample)) {
      $prn = $this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()
        ? $this->sample->get('pack_reference_number')->value
        : $this->tFlow('N/A');
      $form['#title'] = $this->tFlow('Sample Already Submitted');
      $form['message'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<p><strong>' . $this->tFlow('This record already exists.') . '</strong></p>' .
          '<p>' . $this->tFlow('A sample with Packet Reference Number @prn has already been submitted with complete details.', [
            '@prn' => $prn,
          ]) . '</p>' .
          '</div>',
        '#weight' => -10,
      ];
      return $form;
    }

    AnonymousSampleWizardProgress::prependToForm($form, $form_state, 'anonymous_sample_property_details_form', $this->sample);

    $form['#title'] = $this->tFlow('Property details');

    $form['intro'] = [
      '#markup' => '<p class="sentinel-property-details-intro">' .
        $this->tFlow('Enter the property and boiler details below.') .
        '</p>',
      '#weight' => -20,
    ];

    $form['property_ajax_root'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'property-ajax-root'],
      '#weight' => -10,
    ];

    $form['property_ajax_root']['property_address_search'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Search property address'),
      '#placeholder' => $this->tFlow('Start typing to search property addresses...'),
      '#limit_validation_errors' => [],
      '#autocomplete_route_name' => 'sentinel_portal_sample.property_address_autocomplete',
      '#autocomplete_route_parameters' => [
        'user_type' => $this->anonymousWizardFlowType(),
      ],
      '#ajax' => [
        'callback' => '::ajaxPropertyAddressResolve',
        'event' => 'change',
        'wrapper' => 'property-ajax-root',
      ],
      '#weight' => -15,
    ];

    $manual_mode = (bool) ($form_state->get('manual_property_mode') ?? FALSE);

    $form['property_ajax_root']['manual_property_btn'] = [
      '#type' => 'submit',
      '#value' => $manual_mode ? $this->tFlow('Cancel property details') : $this->tFlow('Enter address manually'),
      '#submit' => ['::submitToggleManualProperty'],
      '#ajax' => [
        'callback' => '::ajaxPropertyRefresh',
        'wrapper' => 'property-ajax-root',
        'progress' => ['type' => 'none'],
      ],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    $form['property_ajax_root']['property_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'property-wrapper'],
    ];

    if ($manual_mode) {
      $prefill = $form_state->get('property_address_prefill');
      if (!is_array($prefill)) {
        $prefill = [];
      }
      $af = $form_state->getValue(['system_details', 'address', 'address_fields']);
      if (!is_array($af)) {
        $af = [];
      }
      $form['property_ajax_root']['property_wrapper']['address_fields'] = [
        '#type' => 'container',
        '#tree' => TRUE,
        '#parents' => ['system_details', 'address', 'address_fields'],
      ];
      $form['property_ajax_root']['property_wrapper']['address_fields']['country'] = [
        '#type' => 'select',
        '#title' => $this->tFlow('Country'),
        '#options' => PortalSampleCountryOptions::options(function ($label) {
          return $this->tFlow($label);
        }),
        '#default_value' => $prefill['country'] ?? $af['country'] ?? 'GB',
        '#weight' => 1,
      ];
      $form['property_ajax_root']['property_wrapper']['address_fields']['address_1'] = [
        '#type' => 'textfield',
        '#title' => $this->tFlow('Address 1'),
        '#default_value' => $prefill['address_1'] ?? $af['address_1'] ?? $this->getSampleScalar('street'),
        '#weight' => 2,
      ];
      $form['property_ajax_root']['property_wrapper']['address_fields']['town_city'] = [
        '#type' => 'textfield',
        '#title' => $this->tFlow('Town/City'),
        '#default_value' => $prefill['town_city'] ?? $af['town_city'] ?? $this->getSampleScalar('town_city'),
        '#weight' => 3,
      ];
      $form['property_ajax_root']['property_wrapper']['address_fields']['postcode'] = [
        '#type' => 'textfield',
        '#title' => $this->tFlow('Postcode'),
        '#default_value' => $prefill['postcode'] ?? $af['postcode'] ?? $this->getSampleScalar('postcode') ?: $this->getSampleScalar('company_postcode'),
        '#weight' => 4,
      ];
    }

    $val_6m = $form_state->getValue('system_6_months') ?? $this->getSampleScalar('system_6_months');
    if ($val_6m === '') $val_6m = NULL;

    $form['system_6_months'] = [
      '#type' => 'radios',
      '#title' => $this->tFlow('Age of system'),
      '#required' => TRUE,
      '#options' => [
        'LESS6' => $this->tFlow('Less than 6 months'),
        'MORE6' => $this->tFlow('More than 6 months'),
      ],
      '#default_value' => $val_6m,
      '#weight' => 3,
    ];

    $form['boiler_id'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Boiler serial number'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('boiler_id')
        ?? $this->getSampleScalar('boiler_id'),
      '#weight' => 4,
    ];

    $val_boiler_type = $form_state->getValue('boiler_type') ?? $this->getSampleScalar('boiler_type');

    $form['boiler_type'] = [
      '#type' => 'select',
      '#title' => $this->tFlow('Boiler type'),
      '#required' => TRUE,
      '#options' => [
        '' => $this->tFlow('- Select -'),
        'Combi' => $this->tFlow('Combi'),
        'System' => $this->tFlow('System'),
        'Regular / heat only' => $this->tFlow('Regular / heat only'),
        'Worcester Bosch' => $this->tFlow('Worcester Bosch'),
        'Other' => $this->tFlow('Other'),
         'gas' => $this->t('gas'),
        'GAS 210 ECO 200' => $this->t('GAS 210 ECO 200'),
        'TOCROSSAL 200' => $this->t('TOCROSSAL 200'),
        'gas 210 prox2' => $this->t('gas 210 prox2'),
        'CONCORD SUPER S4X4' => $this->t('CONCORD SUPER S4X4'),
        'Greenstar 25 si' => $this->t('Greenstar 25 si'),
        'Greenstar 15Ri' => $this->t('Greenstar 15Ri'),
        'Greenstar 30i' => $this->t('Greenstar 30i'),
        'Eco-tech PRO 30' => $this->t('Eco-tech PRO 30'),
        'Eco-tech PRO 28' => $this->t('Eco-tech PRO 28'),
        'imax xtra' => $this->t('imax xtra'),
      ],
      '#default_value' => $val_boiler_type,
      '#weight' => 5,
    ];

    $date_installed_default = NULL;
    if ($this->sample->hasField('date_installed') && !$this->sample->get('date_installed')->isEmpty()) {
      $raw = $this->sample->get('date_installed')->value;
      if ($raw) {
        try {
          $date_installed_default = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s', $raw)
            ?: DrupalDateTime::createFromFormat('Y-m-d', substr($raw, 0, 10));
        }
        catch (\Exception $e) {
          $date_installed_default = NULL;
        }
      }
    }
    $form['date_installed'] = [
      '#type' => 'datetime',
      '#title' => $this->tFlow('Boiler install date (optional)'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#date_timezone' => date_default_timezone_get(),
      '#date_date_format' => 'd/m/Y',
      '#required' => FALSE,
      '#default_value' => $form_state->getValue('date_installed') ?? $date_installed_default,
      '#weight' => 6,
    ];

    $form['installer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Installer name (optional)'),
      '#default_value' => $form_state->getValue('installer_name')
        ?? $this->getSampleScalar('installer_name'),
      '#weight' => 7,
    ];

    $form['installer_email'] = [
      '#type' => 'email',
      '#title' => $this->tFlow('Installer email (optional)'),
      '#default_value' => $form_state->getValue('installer_email')
        ?? $this->getSampleScalar('installer_email'),
      '#weight' => 8,
    ];

    $form['actions'] = ['#type' => 'actions', '#weight' => 20];
    // GET link (same classes as core primary submit in input__submit) so it matches the Next button visually.
    $form['actions']['back'] = [
      '#type' => 'link',
      '#title' => $this->tFlow('Back'),
      '#url' => $this->propertyWizardBackUrl(),
      '#attributes' => [
        'class' => [
          'button',
          'button--primary',
          'js-form-submit',
          'form-submit',
        ],
      ],
      '#weight' => -5,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'property_next',
      '#value' => (string) $this->tFlow('Next'),
      '#button_type' => 'primary',
      '#weight' => 0,
    ];

    return $form;
  }

  protected function propertyWizardBackUrl(): Url {
    $prn = '';
    if ($this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()) {
      $prn = trim((string) $this->sample->get('pack_reference_number')->value);
    }
    $to_company = $this->anonymousWizardFlowType() === 'company';
    $route = $to_company
      ? 'sentinel_portal_sample.anonymous_submit_company'
      : 'sentinel_portal_sample.anonymous_submit_individual';
    return Url::fromRoute($route, [], AnonymousSampleWizardProgress::prnRedirectOptions($prn));
  }

  /**
   * True when the sample has a company ID from the company wizard (strong signal for company path).
   */
  protected function sampleHasPersistedCompanyWizardData(): bool {
    if (!$this->sample || !$this->sample->hasField('customer_id')) {
      return FALSE;
    }
    return !$this->sample->get('customer_id')->isEmpty();
  }

  /**
   * Resolves company vs individual for wizard navigation (field + session fallbacks).
   */
  protected function anonymousWizardFlowType(): string {
    $resolved = AnonymousSampleWizardProgress::resolveUserType(
      $this->sample,
      new FormState(),
      $this->getFormId()
    );
    if ($resolved === 'company' || $resolved === 'individual') {
      return $resolved;
    }
    return 'company';
  }

  public function submitBackPropertyStep3(array &$form, FormStateInterface $form_state): void {
    $url = $this->propertyWizardBackUrl();
    $form_state->setRedirect($url->getRouteName(), [], $url->getOptions());
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->sample && $this->anonymousSampleRequiresVerification((int) $this->sample->id())) {
      return;
    }

    $age = $form_state->getValue('system_6_months');
    if ($age === NULL || $age === '') {
      $form_state->setErrorByName('system_6_months', $this->tFlow('Please select the age of the system.'));
    }
    
    $manual_mode = (bool) ($form_state->get('manual_property_mode') ?? FALSE);
    $prefill = $form_state->get('property_address_prefill');
    if (!is_array($prefill)) {
      $prefill = [];
    }

    if ($manual_mode) {
      $af_err = 'system_details][address][address_fields';
      $af = $form_state->getValue(['system_details', 'address', 'address_fields']) ?? [];
      if (!is_array($af)) {
        $af = [];
      }
      if (trim((string) ($af['address_1'] ?? $prefill['address_1'] ?? '')) === '') {
        $form_state->setErrorByName($af_err . '][address_1', $this->tFlow('Address 1 is required. Select a property address or enter it manually.'));
      }
      if (trim((string) ($af['postcode'] ?? $prefill['postcode'] ?? '')) === '') {
        $form_state->setErrorByName($af_err . '][postcode', $this->tFlow('Postcode is required.'));
      }
    }
    elseif (!$this->propertySearchHasValidSelection($form_state)) {
      $form_state->setErrorByName('property_address_search', $this->tFlow('Please select a property address from search, or click “Enter address manually”.'));
    }
    if (trim((string) $form_state->getValue('boiler_id')) === '') {
      $form_state->setErrorByName('boiler_id', $this->tFlow('Boiler serial number is required.'));
    }
    if (trim((string) $form_state->getValue('boiler_type')) === '') {
      $form_state->setErrorByName('boiler_type', $this->tFlow('Boiler type is required.'));
    }

    $this->validateEmailFormValue(
      $form_state,
      'installer_email',
      $form_state->getValue('installer_email'),
      FALSE,
      (string) $this->tFlow('Please enter a valid installer email address.')
    );
  }

  public function ajaxPropertyRefresh(array &$form, FormStateInterface $form_state) {
    return $form['property_ajax_root'];
  }

  /**
   * When a property autocomplete value is chosen, open manual fields and fill them.
   */
  public function ajaxPropertyAddressResolve(array &$form, FormStateInterface $form_state) {
    if ($this->propertySearchHasValidSelection($form_state)) {
      $this->applyPropertyAddressSelectionToFormState($form_state, FALSE);
    }
    $form_state->setRebuild(TRUE);
    return $form['property_ajax_root'];
  }

  /**
   * Loads a selected property address into manual address fields.
   */
  protected function applyPropertyAddressSelectionToFormState(FormStateInterface $form_state, bool $open_manual_fields = FALSE): void {
    $address_id = $this->propertySearchSelectionId($form_state);
    if ($address_id === NULL) {
      return;
    }
    $address_entity = $this->entityTypeManager->getStorage('address')->load($address_id);
    if (!$address_entity || !$address_entity->hasField('field_address') || $address_entity->get('field_address')->isEmpty()) {
      return;
    }
    $addr = $address_entity->get('field_address')->first();
    $fields = [
      'country' => strtoupper(trim((string) ($addr->country_code ?? ''))) ?: 'GB',
      'address_1' => trim((string) ($addr->address_line1 ?? '')),
      'town_city' => trim((string) ($addr->locality ?? '')),
      'postcode' => trim((string) ($addr->postal_code ?? '')),
      'county' => trim((string) ($addr->administrative_area ?? '')),
    ];
    $form_state->set('property_address_prefill', $fields);

    if ($open_manual_fields) {
      $form_state->set('manual_property_mode', TRUE);
      $input = $form_state->getUserInput();
      if (!isset($input['system_details']) || !is_array($input['system_details'])) {
        $input['system_details'] = [];
      }
      if (!isset($input['system_details']['address']) || !is_array($input['system_details']['address'])) {
        $input['system_details']['address'] = [];
      }
      $input['system_details']['address']['address_fields'] = $fields;
      $form_state->setUserInput($input);
      $form_state->setValue(['system_details', 'address', 'address_fields'], $fields);
    }
  }

  /**
   * Parsed address entity id from property search autocomplete, if any.
   */
  protected function propertySearchSelectionId(FormStateInterface $form_state): ?int {
    $input = $form_state->getUserInput();
    $val = trim((string) ($input['property_address_search'] ?? $form_state->getValue('property_address_search') ?? ''));
    if ($val === '') {
      return NULL;
    }
    if (preg_match('/\((\d+)\)\s*$/', $val, $m)) {
      return (int) $m[1];
    }
    if (preg_match('/^\((\d+)\)\s+/', $val, $m)) {
      return (int) $m[1];
    }
    return NULL;
  }

  /**
   * Whether the property address search field contains a chosen autocomplete row.
   */
  protected function propertySearchHasValidSelection(FormStateInterface $form_state): bool {
    return $this->propertySearchSelectionId($form_state) !== NULL;
  }

  /**
   * Clears nested manual address + landlord values from raw user input.
   */
  protected function clearPortalStylePropertyManualUserInput(array &$input): void {
    if (!isset($input['system_details']) || !is_array($input['system_details'])) {
      return;
    }
    if (isset($input['system_details']['address']['address_fields']) && is_array($input['system_details']['address']['address_fields'])) {
      foreach (['country', 'address_1', 'property_name', 'property_number', 'town_city', 'postcode'] as $k) {
        unset($input['system_details']['address']['address_fields'][$k]);
      }
    }
    if (isset($input['system_details']['landlord_wrapper']['landlord'])) {
      unset($input['system_details']['landlord_wrapper']['landlord']);
    }
  }

  public function submitToggleManualProperty(array &$form, FormStateInterface $form_state) {
    $mode = (bool) ($form_state->get('manual_property_mode') ?? FALSE);

    if (!$mode) {
      $form_state->set('manual_property_mode', TRUE);
      $this->applyPropertyAddressSelectionToFormState($form_state, TRUE);
    }
    else {
      $form_state->set('manual_property_mode', FALSE);
      $form_state->set('property_address_prefill', []);
      $input = $form_state->getUserInput();
      $this->clearPortalStylePropertyManualUserInput($input);
      $form_state->setUserInput($input);
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->sample) {
      $this->messenger()->addError($this->tFlow('Sample not found.'));
      return;
    }

    if ($this->propertySearchHasValidSelection($form_state)) {
      $this->applyPropertyAddressSelectionToFormState($form_state, FALSE);
    }
    $search_val = trim((string) ($form_state->getUserInput()['property_address_search'] ?? $form_state->getValue('property_address_search') ?? ''));

    $postcode = '';
    $property_number = '';
    $street = '';
    $town_city = '';
    $county = '';
    $country = 'GB';
    $landlord = 'Not specified';

    $prefill = $form_state->get('property_address_prefill');
    if (!is_array($prefill)) {
      $prefill = [];
    }
    $af = $form_state->getValue(['system_details', 'address', 'address_fields']) ?? [];
    if (!is_array($af)) {
      $af = [];
    }
    $postcode = trim((string) ($af['postcode'] ?? $prefill['postcode'] ?? ''));
    $street = trim((string) ($af['address_1'] ?? $prefill['address_1'] ?? ''));
    $town_city = trim((string) ($af['town_city'] ?? $prefill['town_city'] ?? ''));
    $county = trim((string) ($af['county'] ?? $prefill['county'] ?? ''));
    $country = trim((string) ($af['country'] ?? $prefill['country'] ?? '')) ?: 'GB';

    $system_location_parts = array_filter([$street, $town_city, $postcode]);
    $system_location = implode(', ', $system_location_parts);

    $system_6 = (string) $form_state->getValue('system_6_months');
    $boiler_id = trim((string) $form_state->getValue('boiler_id'));
    $boiler_type = trim((string) $form_state->getValue('boiler_type'));
    $installer_name = trim((string) $form_state->getValue('installer_name')) ?: 'Not provided';
    $installer_email = trim((string) $form_state->getValue('installer_email'));
    $boiler_manufacturer = 'Not specified';

    $date_installed = $this->normalizeDateFormValue($form_state->getValue('date_installed'));
    if ($date_installed === '') {
      $date_installed = $this->normalizeDateFormValue(DrupalDateTime::createFromTimestamp(\Drupal::time()->getRequestTime()));
    }

    $boiler_manufacturer = 'Not specified';
    if ($installer_name === '') {
      $installer_name = 'Not provided';
    }

    $company_name = '';
    if ($this->sample->hasField('company_name') && !$this->sample->get('company_name')->isEmpty()) {
      $company_name = trim((string) $this->sample->get('company_name')->value);
    }
    $company_address1 = '';
    if ($this->sample->hasField('company_address1') && !$this->sample->get('company_address1')->isEmpty()) {
      $company_address1 = trim((string) $this->sample->get('company_address1')->value);
    }

    $user_type = AnonymousSampleWizardProgress::resolveUserType($this->sample, $form_state, $this->getFormId());
    $is_individual = ($user_type === 'individual');

    // This step does not render the company address dropdown; ensureAddressEntities
    // still needs the existing address entity id so it does not create a duplicate.
    $existing_company_address_id = NULL;
    if (!$is_individual) {
      if ($this->sample->hasField('field_company_address') && !$this->sample->get('field_company_address')->isEmpty()) {
        $existing_company_address_id = (int) $this->sample->get('field_company_address')->first()->target_id;
      }
      elseif ($this->sample->hasField('sentinel_company_address_target_id') && !$this->sample->get('sentinel_company_address_target_id')->isEmpty()) {
        $existing_company_address_id = (int) $this->sample->get('sentinel_company_address_target_id')->value;
      }
    }

    $values = [
      'postcode' => $postcode,
      'system_location' => $system_location,
      'system_6_months' => $system_6,
      'boiler_id' => $boiler_id,
      'boiler_type' => $boiler_type,
      'boiler_manufacturer' => $boiler_manufacturer,
      'date_installed' => $date_installed,
      'installer_name' => $installer_name,
      'landlord' => $landlord,
      'street' => $street,
      'town_city' => $town_city,
      'county' => $county,
      'property_number' => $property_number,
      'skip_company_address' => $is_individual,
      'company_details' => [
        'company_address' => [
          'company' => $company_name,
          'company_address_1' => $company_address1,
          'company_town_city' => '',
          'company_postcode' => '',
          'company_country' => 'GB',
          'company_address_selection' => $existing_company_address_id ?: '',
        ],
      ],
      'system_details' => [
        'address' => [
          'sample_address_selection' => $search_val,
          'address_fields' => [
            'address_1' => $street,
            'postcode' => $postcode,
            'town_city' => $town_city,
            'county' => $county,
            'country' => $country,
          ],
        ],
        'landlord_wrapper' => [
          'landlord' => $landlord,
        ],
      ],
      'job_details' => [
        'boiler_id' => $boiler_id,
        'boiler_type' => $boiler_type,
        'boiler_manufacturer' => $boiler_manufacturer,
        'date_installed' => $date_installed,
        'installer_name' => $installer_name,
      ],
    ];

    if ($installer_email !== '') {
      $values['installer_email'] = $installer_email;
      $values['job_details']['installer_email'] = $installer_email;
    }

    $original_values = $values;

    try {
      if ($this->sample->hasField('company_name') && $this->sample->get('company_name')->isEmpty()) {
        $fallback_name = $installer_name !== 'Not provided' ? $installer_name : 'Individual';
        $this->sample->set('company_name', $fallback_name);
      }
      if ($this->sample->hasField('company_email') && $this->sample->get('company_email')->isEmpty()) {
        if ($installer_email !== '') {
          $this->sample->set('company_email', $installer_email);
        }
        elseif ($this->sample->hasField('installer_email') && !$this->sample->get('installer_email')->isEmpty()) {
          $this->sample->set('company_email', $this->sample->get('installer_email')->value);
        }
      }

      $this->sample->set('postcode', $postcode);
      if ($this->sample->hasField('system_6_months')) {
        $this->sample->set('system_6_months', $system_6);
      }
      $this->sample->set('boiler_id', $boiler_id);
      $this->sample->set('boiler_type', $boiler_type);
      $this->sample->set('boiler_manufacturer', $boiler_manufacturer);
      $this->sample->set('date_installed', $date_installed);
      $this->sample->set('installer_name', $installer_name);
      if ($this->sample->hasField('installer_email')) {
        if ($installer_email !== '') {
          $this->sample->set('installer_email', $installer_email);
        }
      }
      $this->sample->set('landlord', $landlord);
      $this->sample->set('street', $street);
      $this->sample->set('town_city', $town_city);
      $this->sample->set('county', $county);
      $this->sample->set('property_number', $property_number);
      if ($this->sample->hasField('system_location')) {
        $this->sample->set('system_location', $system_location);
      }

      $this->maybeFetchUcrForIndividualFromCustomerService($form_state);

      $this->mapFormValuesToEntity($this->sample, $values, $form);
      $this->ensureAddressEntities($this->sample, $values, $original_values, $form);
      $this->setLegacyAddressTargetIds($this->sample);

      $ucr = NULL;
      if ($this->sample->hasField('ucr') && !$this->sample->get('ucr')->isEmpty()) {
        $ucr = trim((string) $this->sample->get('ucr')->value);
      }
      $client_id = NULL;
      $client_name = NULL;
      if ($this->sample->hasField('client_id') && !$this->sample->get('client_id')->isEmpty()) {
        $existing_cid = (int) $this->sample->get('client_id')->value;
        if ($existing_cid > 0) {
          $client_id = $existing_cid;
        }
      }
      if ($this->sample->hasField('client_name') && !$this->sample->get('client_name')->isEmpty()) {
        $client_name = trim((string) $this->sample->get('client_name')->value);
      }
      $client_storage = $this->entityTypeManager->getStorage('sentinel_client');

      if ($ucr !== NULL && $ucr !== '') {
        foreach ([$ucr, (string) (int) $ucr] as $ucr_candidate) {
          if ($ucr_candidate === '' || $ucr_candidate === '0') {
            continue;
          }
          $client_ids = $client_storage->getQuery()
            ->condition('ucr', $ucr_candidate)
            ->accessCheck(FALSE)
            ->range(0, 1)
            ->execute();
          if (!empty($client_ids)) {
            $client = $client_storage->load(reset($client_ids));
            if ($client) {
              $client_id = $client->id();
              if ($client->hasField('name') && !$client->get('name')->isEmpty()) {
                $client_name = $client->get('name')->value;
              }
              break;
            }
          }
        }
      }

      if ($client_id === NULL) {
        $lookup_email = '';
        if ($this->sample->hasField('installer_email') && !$this->sample->get('installer_email')->isEmpty()) {
          $lookup_email = trim((string) $this->sample->get('installer_email')->value);
        }
        if ($lookup_email === '' && $this->sample->hasField('company_email') && !$this->sample->get('company_email')->isEmpty()) {
          $lookup_email = trim((string) $this->sample->get('company_email')->value);
        }
        if ($lookup_email !== '') {
          $client_ids = $client_storage->getQuery()
            ->condition('email', $lookup_email)
            ->accessCheck(FALSE)
            ->range(0, 1)
            ->execute();
          if (!empty($client_ids)) {
            $client = $client_storage->load(reset($client_ids));
            if ($client) {
              $client_id = $client->id();
              if ($client->hasField('name') && !$client->get('name')->isEmpty()) {
                $client_name = $client->get('name')->value;
              }
              if ($this->sample->hasField('ucr') && $this->sample->get('ucr')->isEmpty() && $client->getRealUcr()) {
                $this->sample->set('ucr', (string) (int) $client->getRealUcr());
              }
            }
          }
        }
      }
      if ($client_id !== NULL && $this->sample->hasField('client_id')) {
        $this->sample->set('client_id', $client_id);
      }
      if ($client_name !== NULL && $this->sample->hasField('client_name')) {
        $this->sample->set('client_name', $client_name);
      }

      $pack_type = NULL;
      if ($this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()) {
        $pack_type = SentinelSample::getPackType([
          'pack_reference_number' => $this->sample->get('pack_reference_number')->value,
        ]);
      }
      if ($pack_type !== NULL && $this->sample->hasField('pack_type')) {
        $this->sample->set('pack_type', PackTypeFilter::getPackTypeDbValue($pack_type));
      }

      $this->sample->save();

      $session = $this->getRequest()->getSession();
      $session->remove('sentinel_anonymous_company_locked_' . $this->sample->id());
      $flow = AnonymousSampleWizardProgress::resolveUserType($this->sample, $form_state, $this->getFormId())
        ?? AnonymousSampleWizardProgress::inferUserTypeFromSample($this->sample);
      if ($flow !== NULL) {
        $session->set('sentinel_anonymous_last_flow', $flow);
      }
      elseif ($this->sample->hasField('user_type') && !$this->sample->get('user_type')->isEmpty()) {
        $session->set('sentinel_anonymous_last_flow', AnonymousSampleWizardProgress::normalizeUserTypeKey((string) $this->sample->get('user_type')->value));
      }

      $form_state->setRedirect(
        'sentinel_portal_sample.anonymous_thank_you',
        [],
        AnonymousSampleLanguageRedirect::options() + ['query' => ['sid' => (string) $this->sample->id()]]
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_portal_sample')->error('Anonymous property details save failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->tFlow('An error occurred while saving. Please try again or contact support.'));
    }
  }

  /**
   * Calls customer-service for individual drafts missing UCR or portal client id.
   */
  protected function maybeFetchUcrForIndividualFromCustomerService(FormStateInterface $form_state): void {
    $user_type = AnonymousSampleWizardProgress::resolveUserType($this->sample, $form_state, $this->getFormId());
    if ($user_type !== 'individual') {
      return;
    }
    $needUcr = !$this->sample->hasField('ucr') || $this->sample->get('ucr')->isEmpty() || trim((string) $this->sample->get('ucr')->value) === '';
    $needClient = !$this->sample->hasField('client_id') || $this->sample->get('client_id')->isEmpty() || (int) $this->sample->get('client_id')->value === 0;
    if (!$needUcr && !$needClient) {
      return;
    }

    $email = '';
    if ($this->sample->hasField('installer_email') && !$this->sample->get('installer_email')->isEmpty()) {
      $email = trim((string) $this->sample->get('installer_email')->value);
    }
    if ($email === '' && $this->sample->hasField('company_email') && !$this->sample->get('company_email')->isEmpty()) {
      $email = trim((string) $this->sample->get('company_email')->value);
    }
    $name = '';
    if ($this->sample->hasField('installer_name') && !$this->sample->get('installer_name')->isEmpty()) {
      $name = trim((string) $this->sample->get('installer_name')->value);
    }
    if ($name === '' && $this->sample->hasField('company_name') && !$this->sample->get('company_name')->isEmpty()) {
      $name = trim((string) $this->sample->get('company_name')->value);
    }
    if ($email === '' || $name === '') {
      return;
    }

    $company = $name;
    if ($this->sample->hasField('company_name') && !$this->sample->get('company_name')->isEmpty()) {
      $company = trim((string) $this->sample->get('company_name')->value);
    }

    $http = \Drupal::service('http_client');
    $api = SentinelCustomerServiceLookup::fetch($http, $this->getRequest(), $email, $name, $company);
    if ($needUcr && $api['ucr'] !== NULL && $this->sample->hasField('ucr')) {
      $this->sample->set('ucr', $api['ucr']);
    }
    if ($needClient && $api['client_cid'] !== NULL && $this->sample->hasField('client_id')) {
      $this->sample->set('client_id', $api['client_cid']);
      $client = $this->entityTypeManager->getStorage('sentinel_client')->load($api['client_cid']);
      if ($client && $this->sample->hasField('client_name') && $client->hasField('name') && !$client->get('name')->isEmpty()) {
        $this->sample->set('client_name', $client->get('name')->value);
      }
    }
  }

  /**
   * Reads a scalar field value from the loaded sample.
   */
  protected function getSampleScalar(string $field): string {
    if (!$this->sample || !$this->sample->hasField($field) || $this->sample->get($field)->isEmpty()) {
      return '';
    }
    $v = $this->sample->get($field)->value;
    return $v === NULL || $v === FALSE ? '' : (string) $v;
  }

  /**
 * Fetch system location using:
 * UCR -> sentinel_client -> client_id -> address table.
 */
/**
 * Fetch system location directly from address table using UCR.
 */
protected function getSystemLocationFromCompany(): string {

  if (!$this->sample) {
    return '';
  }

  // Get company name from sample.
  $company_name = '';
  if (
    $this->sample->hasField('company_name') &&
    !$this->sample->get('company_name')->isEmpty()
  ) {
    $company_name = trim((string) $this->sample->get('company_name')->value);
  }

  if ($company_name === '') {
    return '';
  }

  // Fetch address using company name matching organization.
  $query = \Drupal::database()->select('address__field_address', 'a');
  $query->fields('a', [
    'field_address_address_line1',
  ]);
  $query->condition('field_address_organization', $company_name);
  $query->range(0, 1);

  $address = $query->execute()->fetchField();

  return !empty($address)
    ? trim((string) $address)
    : '';
}

}
