<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Entity\SentinelClient;
use Drupal\sentinel_portal_sample\AnonymousSampleFlowTranslationTrait;
use Drupal\sentinel_portal_sample\AnonymousSampleLanguageRedirect;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;
use Drupal\sentinel_portal_sample\PortalSampleCountryOptions;
use Drupal\sentinel_portal_sample\SentinelCustomerServiceLookup;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Company ID lookup and review step before property details (anonymous QR flow).
 */
class AnonymousSampleCompanyWizardForm extends FormBase {

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
   * Constructs a new AnonymousSampleCompanyWizardForm.
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
    return 'anonymous_sample_company_wizard_form';
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
      ?? 'company';
    if (!AnonymousSampleWizardProgress::isCompanyFlow($flow)) {
      $form['#redirect'] = Url::fromRoute(
        'sentinel_portal_sample.anonymous_submit_individual',
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

    $form_state->set('wizard_prn', $prn);
    $form_state->set('wizard_sample_id', $sample->id());
    $form['#title'] = $this->tFlow('Company details');

    $fetched = $this->getFetchedCompanyData($form_state);
    if (empty($fetched) && $this->sampleHasPersistedCompanyWizard($sample)) {
      $fetched = $this->wizardCompanyDataFromSample($sample);
      $form_state->set('fetched_company', $fetched);
      $this->persistFetchedCompanyToSession($form_state, $fetched);
    }

    // One AJAX wrapper around progress + inner content so "Fetch Details"
    // rebuild updates Step 2 vs Step 3 and the bar (progress lives outside
    // the old #company-wizard-wrapper-only replace region).
    $form['company_wizard_ajax_root'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'company-wizard-ajax-root'],
    ];
    AnonymousSampleWizardProgress::prependToForm(
      $form['company_wizard_ajax_root'],
      $form_state,
      'anonymous_sample_company_wizard_form',
      $sample
    );
    $form['company_wizard_ajax_root']['company_wizard_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'company-wizard-wrapper'],
    ];

    $form['company_wizard_ajax_root']['company_wizard_wrapper']['help'] = [
      '#markup' => '<p>' . $this->tFlow('Enter your Company ID (Sentinel customer reference). We will load your company name and email where available.') . '</p>',
      '#weight' => -10,
    ];

    $company_id_alert = $form_state->get('company_id_alert');
    if (is_string($company_id_alert) && $company_id_alert !== '') {
      $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_id_alert'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'company-id-alert',
          'class' => ['messages', 'messages--error', 'sentinel-company-id-alert'],
          'role' => 'alert',
        ],
        '#weight' => -8,
        'text' => [
          '#markup' => '<p>' . Html::escape($company_id_alert) . '</p>',
        ],
      ];
    }

    $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_id'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Company ID'),
      '#required' => FALSE,
      '#default_value' => $form_state->getValue('company_id') ?? ($fetched['company_id'] ?? ''),
      '#weight' => 0,
      '#attributes' => (is_string($company_id_alert) && $company_id_alert !== '') ? [
        'aria-invalid' => 'true',
        'aria-describedby' => 'company-id-alert',
      ] : [],
    ];
  
    $form['company_wizard_ajax_root']['company_wizard_wrapper']['fetch'] = [
      '#type' => 'submit',
      '#value' => $this->tFlow('Fetch Details'),
      '#submit' => ['::submitFetchCompany'],
      '#ajax' => [
        'callback' => '::ajaxWizardRefresh',
        'wrapper' => 'company-wizard-ajax-root',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
      '#limit_validation_errors' => [['company_id']],
      '#weight' => 10,
    ];

    $data = is_array($fetched) ? $fetched : [
      'name' => '',
      'addresses' => [],
      'email' => '',
      'phone' => '',
    ];
    $addresses = $data['addresses'] ?? [];
    $has_fetched_company = !empty($fetched);
    $manual_mode = (bool) ($form_state->get('manual_address_mode') ?? FALSE);

    {
      $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_name'] = [
        '#type' => 'textfield',
        '#title' => $this->tFlow('Company name'),
        '#default_value' => $form_state->getValue('company_name') ?? ($data['name'] ?? ''),
        '#weight' => 11,
      ];

      $options = ['' => $this->tFlow('- Select an address or enter manually below -')];
      if (count($addresses) > 0) {
        foreach ($addresses as $entity_id => $addr_data) {
          $options[(string) $entity_id] = $this->formatCompanyAddressSelectLabel($addr_data);
        }
      }
      $selected_address = $this->getCompanyAddressSelectValue($form_state);
      if ($selected_address === NULL && count($addresses) > 0) {
        $client = NULL;
        if (!empty($data['client_cid'])) {
          $client = $this->entityTypeManager->getStorage('sentinel_client')->load($data['client_cid']);
        }
        $latest_id = $this->resolveDefaultCompanyAddressId($client, $addresses);
        if ($latest_id !== NULL) {
          $selected_address = (string) $latest_id;
          $form_state->set('company_address_selected_id', $selected_address);
          $form_state->setValue('company_address_select', $selected_address);
        }
      }
      if ($selected_address !== NULL && empty($form_state->get('company_address_prefill'))) {
        $this->applyCompanyAddressSelectionToFormState($form_state, FALSE);
      }
      $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_select'] = [
        '#type' => 'select',
        '#title' => $this->tFlow('Select company address'),
        '#options' => $options,
        '#default_value' => $selected_address !== NULL ? (string) $selected_address : NULL,
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxCompanyAddressSelect',
          'wrapper' => 'company-wizard-ajax-root',
          'event' => 'change',
        ],
        '#weight' => 11.5,
      ];

      $form['company_wizard_ajax_root']['company_wizard_wrapper']['manual_address_btn'] = [
        '#type' => 'submit',
        '#value' => $manual_mode ? $this->tFlow('Cancel manual address') : $this->tFlow('Enter address manually'),
        '#submit' => ['::submitToggleManualAddress'],
        '#ajax' => [
          'callback' => '::ajaxWizardRefresh',
          'wrapper' => 'company-wizard-ajax-root',
          'progress' => ['type' => 'none'],
        ],
        '#limit_validation_errors' => [],
        '#weight' => 11.6,
        '#attributes' => ['class' => ['button', 'button--small']],
      ];

      $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'company-address-wrapper'],
        '#weight' => 12,
      ];

      if ($manual_mode) {
        $prefill = $form_state->get('company_address_prefill');
        if (!is_array($prefill)) {
          $prefill = [];
        }
        $company_name_default = $form_state->getValue('company_name') ?? ($data['name'] ?? '');

        $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_wrapper']['company_country'] = [
          '#type' => 'select',
          '#title' => $this->tFlow('Country'),
          '#options' => PortalSampleCountryOptions::options(function ($label) {
            return $this->tFlow($label);
          }),
          '#default_value' => $prefill['company_country'] ?? $form_state->getValue('company_country') ?: 'GB',
          '#weight' => 0,
        ];
        $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_wrapper']['company_address_1'] = [
          '#type' => 'textfield',
          '#title' => $this->tFlow('Address 1'),
          '#default_value' => $prefill['company_address_1'] ?? $form_state->getValue('company_address_1') ?? '',
          '#weight' => 1,
        ];
        $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_wrapper']['company_town_city'] = [
          '#type' => 'textfield',
          '#title' => $this->tFlow('Town/City'),
          '#default_value' => $prefill['company_town_city'] ?? $form_state->getValue('company_town_city') ?? '',
          '#weight' => 2,
        ];
        $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_wrapper']['company_postcode'] = [
          '#type' => 'textfield',
          '#title' => $this->tFlow('Postcode'),
          '#default_value' => $prefill['company_postcode'] ?? $form_state->getValue('company_postcode') ?? '',
          '#weight' => 3,
        ];
        $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_address_wrapper']['company'] = [
          '#type' => 'hidden',
          '#value' => $prefill['company'] ?? $form_state->getValue('company') ?? $company_name_default,
          '#weight' => 4,
        ];
      }

      $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_email'] = [
        '#type' => 'email',
        '#required' => TRUE,
        '#title' => $this->tFlow('Company email'),
        '#default_value' => $form_state->getValue('company_email') ?? ($data['email'] ?? ''),
        '#weight' => 13,
      ];

      $form['company_wizard_ajax_root']['company_wizard_wrapper']['company_phone'] = [
        '#type' => 'textfield',
        '#title' => $this->tFlow('Company phone'),
        '#default_value' => $form_state->getValue('company_phone') ?? ($data['phone'] ?? ''),
        '#weight' => 14,
      ];
    }

    $form['company_wizard_ajax_root']['company_wizard_wrapper']['nav_step2'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentinel-anon-wizard-nav']],
      '#weight' => 19,
      'back' => [
        '#type' => 'submit',
        '#value' => $this->tFlow('Back'),
        '#submit' => ['::submitBackCompanyStep1'],
        '#limit_validation_errors' => [],
      ],
    ];

    if ($has_fetched_company) {
      $form['company_wizard_ajax_root']['company_wizard_wrapper']['nav_step2']['next'] = [
        '#type' => 'submit',
        '#value' => $this->tFlow('Next'),
        '#submit' => ['::submitCompanyReview'],
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }

  /**
   * AJAX callback: return the refreshed wrapper.
   */
  public function ajaxWizardRefresh(array &$form, FormStateInterface $form_state) {
    return $form['company_wizard_ajax_root'];
  }

  /**
   * Fills manual address fields when an address is chosen from the dropdown.
   */
  public function ajaxCompanyAddressSelect(array &$form, FormStateInterface $form_state) {
    $selected_id = $this->getCompanyAddressSelectValue($form_state);
    if ($selected_id !== NULL) {
      $form_state->setValue('company_address_select', $selected_id);
    }
    $this->applyCompanyAddressSelectionToFormState($form_state, FALSE);
    $form_state->setRebuild(TRUE);
    return $form['company_wizard_ajax_root'];
  }

  /**
   * Copies the selected fetched address into manual address fields.
   */
  protected function applyCompanyAddressSelectionToFormState(FormStateInterface $form_state, bool $open_manual_fields = FALSE): void {
    $selected_id = $this->getCompanyAddressSelectValue($form_state);
    if ($selected_id === NULL) {
      return;
    }
    $data = $this->getFetchedCompanyData($form_state);
    if (!is_array($data)) {
      return;
    }
    $addresses = $data['addresses'] ?? [];
    $addr = $this->getFetchedAddressRow($addresses, $selected_id);
    if ($addr === NULL) {
      return;
    }

    $line1 = trim((string) ($addr['address1'] ?? ''));
    $extra = trim(implode(' ', array_filter([
      trim((string) ($addr['address2'] ?? '')),
      trim((string) ($addr['address3'] ?? '')),
    ], static function ($v) {
      return $v !== '';
    })));
    if ($extra !== '') {
      $line1 = $line1 !== '' ? $line1 . ', ' . $extra : $extra;
    }

    $country = strtoupper(trim((string) ($addr['country'] ?? '')));
    if ($country === '') {
      $country = 'GB';
    }
    $company = trim((string) ($form_state->getValue('company_name') ?? ($data['name'] ?? '')));

    $values = [
      'company_country' => $country,
      'company' => $company,
      'company_address_1' => $line1,
      'company_town_city' => trim((string) ($addr['locality'] ?? '')),
      'company_postcode' => trim((string) ($addr['postcode'] ?? '')),
    ];
    $form_state->set('company_address_prefill', $values);
    $form_state->set('company_address_selected_id', $selected_id);
    $form_state->setValue('company_address_select', $selected_id);

    foreach ($values as $key => $value) {
      $form_state->setValue($key, $value);
    }

    if ($open_manual_fields) {
      $form_state->set('manual_address_mode', TRUE);
      $user_input = $form_state->getUserInput();
      foreach ($values as $key => $value) {
        $user_input[$key] = $value;
      }
      $form_state->setUserInput($user_input);
    }
    else {
      $form_state->set('manual_address_mode', FALSE);
      $user_input = $form_state->getUserInput();
      foreach ($this->portalStyleManualCompanyAddressInputKeys() as $k) {
        unset($user_input[$k]);
      }
      $form_state->setUserInput($user_input);
    }
  }

  /**
   * Selected company address id from user input, form values, or prior AJAX.
   */
  protected function getCompanyAddressSelectValue(FormStateInterface $form_state): ?string {
    $input = $form_state->getUserInput();
    $paths = [
      ['company_address_select'],
      ['company_wizard_ajax_root', 'company_wizard_wrapper', 'company_address_select'],
      ['company_wizard_wrapper', 'company_address_select'],
    ];
    foreach ($paths as $path) {
      $value = NestedArray::getValue($input, $path, $exists);
      if ($exists && $value !== '' && $value !== NULL) {
        return (string) $value;
      }
    }
    $stored = $form_state->get('company_address_selected_id');
    if ($stored !== NULL && $stored !== '') {
      return (string) $stored;
    }
    $value = $form_state->getValue('company_address_select');
    if ($value !== NULL && $value !== '') {
      return (string) $value;
    }
    return NULL;
  }

  /**
   * Fetched company payload from form state, with session fallback for AJAX.
   */
  protected function getFetchedCompanyData(FormStateInterface $form_state): ?array {
    $data = $form_state->get('fetched_company');
    if (is_array($data) && $data !== []) {
      return $data;
    }
    $sample_id = (int) $form_state->get('wizard_sample_id');
    if ($sample_id <= 0) {
      return is_array($data) ? $data : NULL;
    }
    $session_data = $this->getRequest()->getSession()->get('sentinel_company_fetched_' . $sample_id);
    if (is_array($session_data) && $session_data !== []) {
      $form_state->set('fetched_company', $session_data);
      return $session_data;
    }
    return is_array($data) ? $data : NULL;
  }

  /**
   * Persists fetched company data for AJAX rebuilds on the same draft token.
   */
  protected function persistFetchedCompanyToSession(FormStateInterface $form_state, array $data): void {
    $sample_id = (int) $form_state->get('wizard_sample_id');
    if ($sample_id <= 0) {
      return;
    }
    $this->getRequest()->getSession()->set('sentinel_company_fetched_' . $sample_id, $data);
  }

  /**
   * Resolves an address row from fetched company data (handles string/int keys).
   */
  protected function getFetchedAddressRow(array $addresses, $selected_id): ?array {
    if (isset($addresses[$selected_id]) && is_array($addresses[$selected_id])) {
      return $addresses[$selected_id];
    }
    $key = (string) $selected_id;
    if (isset($addresses[$key]) && is_array($addresses[$key])) {
      return $addresses[$key];
    }
    $int_key = (int) $selected_id;
    if ($int_key > 0 && isset($addresses[$int_key]) && is_array($addresses[$int_key])) {
      return $addresses[$int_key];
    }
    return NULL;
  }

  /**
   * User-input keys for portal-aligned manual company address fields.
   *
   * @return string[]
   */
  protected function portalStyleManualCompanyAddressInputKeys(): array {
    return [
      'company_country',
      'company',
      'company_address_1',
      'company_town_city',
      'company_postcode',
    ];
  }

  /**
   * Toggles the manual address entry mode.
   */
  public function submitToggleManualAddress(array &$form, FormStateInterface $form_state) {
    $mode = (bool) ($form_state->get('manual_address_mode') ?? FALSE);

    if (!$mode) {
      $form_state->set('manual_address_mode', TRUE);
      $this->applyCompanyAddressSelectionToFormState($form_state, TRUE);
    }
    else {
      // Closing manual fields: clear manual input only, keep address dropdown.
      $form_state->set('manual_address_mode', FALSE);
      $form_state->set('company_address_prefill', []);
      $input = $form_state->getUserInput();
      foreach ($this->portalStyleManualCompanyAddressInputKeys() as $k) {
        unset($input[$k]);
      }
      $form_state->setUserInput($input);
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Loads company data from the portal client table (UCR / Company ID).
   */
  public function submitFetchCompany(array &$form, FormStateInterface $form_state) {
    $form_state->set('company_id_alert', NULL);

    $company_id = trim((string) $form_state->getValue('company_id'));
    if ($company_id === '') {
      $form_state->set('company_id_alert', (string) $this->tFlow('Please enter a Company ID.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $client = $this->lookupSentinelClientByCompanyId($company_id);
    if (!$client) {
      $form_state->set('company_id_alert', (string) $this->tFlow('Company ID is not valid.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $form_state->set('company_id_alert', NULL);
    $wizard_data = $this->clientToWizardData($client, $company_id);
    $form_state->set('fetched_company', $wizard_data);
    $form_state->set('manual_address_mode', FALSE);
    $form_state->set('company_address_prefill', []);

    $addresses = $wizard_data['addresses'] ?? [];
    $latest_id = $this->resolveDefaultCompanyAddressId($client, $addresses);
    if ($latest_id !== NULL) {
      $selection = (string) $latest_id;
      $form_state->set('company_address_selected_id', $selection);
      $form_state->setValue('company_address_select', $selection);
      $input = $form_state->getUserInput();
      $input['company_address_select'] = $selection;
      $form_state->setUserInput($input);
      // Match portal submit behavior by showing the selected address prefilled
      // in the editable company address fields right after company fetch.
      $this->applyCompanyAddressSelectionToFormState($form_state, TRUE);
    }
    else {
      $form_state->set('company_address_selected_id', NULL);
      $form_state->setValue('company_address_select', '');
    }

    // Clear user-entered values so they are replaced by the new fetched data.
    $input = $form_state->getUserInput();
    unset($input['company_name'], $input['company_address'], $input['company_email'], $input['company_phone']);
    foreach ($this->portalStyleManualCompanyAddressInputKeys() as $k) {
      unset($input[$k]);
    }
    $form_state->setUserInput($input);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Persists company data on the sample and continues to property details.
   */
  public function submitCompanyReview(array &$form, FormStateInterface $form_state) {
    $data = $this->getFetchedCompanyData($form_state);
    if (empty($data)) {
      $this->messenger()->addError($this->tFlow('Please fetch company details first.'));
      $form_state->setRebuild(TRUE);
      return;
    }
    $data['email'] = $form_state->getValue('company_email');
    $data['phone'] = $form_state->getValue('company_phone');
    $data['name'] = $form_state->getValue('company_name');
    $data['address'] = $form_state->getValue('company_address');
    $prn = $form_state->get('wizard_prn') ?: $this->getAnonymousPrn();
    $sample = $this->loadAnonymousSampleByPrn($prn);
    if (!$sample || !$sample->id()) {
      $this->messenger()->addError($this->tFlow('Sample not found.'));
      return;
    }

    $cid_value = $form_state->getValue('company_id');
    if ($sample->hasField('customer_id')) {
      $sample->set('customer_id', $cid_value);
    }
    if ($sample->hasField('company_name') && !empty($data['name'])) {
      $sample->set('company_name', $data['name']);
    }
    if ($sample->hasField('company_email') && !empty($data['email'])) {
      $sample->set('company_email', $data['email']);
    }
    if ($sample->hasField('company_tel') && isset($data['phone']) && $data['phone'] !== '') {
      $sample->set('company_tel', $data['phone']);
    }

    $addresses = $data['addresses'] ?? [];
    $manual_mode = (bool) ($form_state->get('manual_address_mode') ?? FALSE);
    $selected_id = $this->getCompanyAddressSelectValue($form_state);

    if ($selected_id !== NULL && !$manual_mode) {
      $this->applyCompanyAddressSelectionToFormState($form_state, FALSE);
    }

    $scalars = $this->resolveCompanyAddressScalarsForSample($form_state, $data);
    $this->setSampleCompanyAddressScalars($sample, $scalars);

    $company_address_target_id = $this->resolveCompanyAddressEntityTargetId(
      $form_state,
      $data,
      $scalars
    );

    if ($sample->hasField('field_company_address')) {
      if ($company_address_target_id) {
        $sample->set('field_company_address', ['target_id' => $company_address_target_id]);
      }
      else {
        $sample->set('field_company_address', NULL);
      }
    }
    if ($sample->hasField('sentinel_company_address_target_id')) {
      $sample->set('sentinel_company_address_target_id', $company_address_target_id ?: NULL);
    }

    if ($sample->hasField('ucr')) {
      if (!empty($data['stored_real_ucr'])) {
        $sample->set('ucr', (string) (int) $data['stored_real_ucr']);
      }
      else {
        $entered_ucr = preg_replace('/\D/', '', (string) $cid_value);
        if ($entered_ucr !== '' && (int) $entered_ucr > 0) {
          $sample->set('ucr', (string) (int) $entered_ucr);
        }
      }
    }

    if (!empty($data['client_cid']) && $sample->hasField('client_id')) {
      $sample->set('client_id', (int) $data['client_cid']);
      $client = $this->entityTypeManager->getStorage('sentinel_client')->load((int) $data['client_cid']);
      if ($client instanceof SentinelClient) {
        if ($sample->hasField('client_name') && $client->hasField('name') && !$client->get('name')->isEmpty()) {
          $sample->set('client_name', $client->get('name')->value);
        }
        if ($sample->hasField('ucr') && $sample->get('ucr')->isEmpty() && $client->getRealUcr()) {
          $sample->set('ucr', (string) (int) $client->getRealUcr());
        }
      }
    }
    elseif ($sample->hasField('ucr') && $sample->get('ucr')->isEmpty()) {
      $api = SentinelCustomerServiceLookup::fetch(
        $this->httpClient,
        $this->getRequest(),
        (string) ($data['email'] ?? ''),
        (string) ($data['name'] ?? ''),
        (string) ($data['name'] ?? '')
      );
      if ($api['ucr'] !== NULL) {
        $sample->set('ucr', $api['ucr']);
      }
      if ($api['client_cid'] !== NULL && $sample->hasField('client_id')) {
        $sample->set('client_id', $api['client_cid']);
        $client = $this->entityTypeManager->getStorage('sentinel_client')->load($api['client_cid']);
        if ($client && $sample->hasField('client_name') && $client->hasField('name') && !$client->get('name')->isEmpty()) {
          $sample->set('client_name', $client->get('name')->value);
        }
      }
    }

    $sample->save();
    $this->getRequest()->getSession()->set(
      'sentinel_anonymous_last_flow',
      'company'
    );
    $this->getRequest()->getSession()->set(
      'sentinel_anonymous_company_locked_' . $sample->id(),
      \Drupal::time()->getRequestTime()
    );

    $form_state->setRedirect(
      'sentinel_portal_sample.anonymous_submit_other_details',
      [],
      AnonymousSampleWizardProgress::prnRedirectOptions($prn)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Resolve a portal client from a pasted Company ID / UCR (digits).
   */
  protected function lookupSentinelClientByCompanyId(string $raw): ?SentinelClient {
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') {
      return NULL;
    }

    $n = (int) $digits;
    $candidates = array_unique(array_filter([$n, (int) floor($n / 10)], static function ($v) {
      return $v > 0;
    }));

    $storage = $this->entityTypeManager->getStorage('sentinel_client');
    foreach ($candidates as $ucr) {
      $ids = $storage->getQuery()
        ->condition('ucr', $ucr)
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
      if (!empty($ids)) {
        $client = $storage->load(reset($ids));
        if ($client instanceof SentinelClient) {
          return $client;
        }
      }
    }

    return NULL;
  }

  /**
   * Map a client entity to wizard display / save payload.
   */
  protected function clientToWizardData(SentinelClient $client, string $entered_id): array {

    $company = trim((string) ($client->get('company')->value ?? ''));

    // ONLY use company field.
    // If empty, leave blank for manual input.
    $display_name = $company;
  
    // Get UCR.
    $ucr_display = '';
if (method_exists($client, 'getUcr')) {
  $ucr = $client->getUcr();
  $ucr_display = $ucr !== NULL ? (string) $ucr : '';
}

    $addresses = [];
    $formatted_address = '';

    if ($company !== '') {
      $address_query = \Drupal::database()
        ->select('address__field_address', 'a')
        ->fields('a', [
          'entity_id',
          'field_address_organization',
          'field_address_address_line1',
          'field_address_address_line2',
          'field_address_address_line3',
          'field_address_locality',
          'field_address_postal_code',
          'field_address_administrative_area',
          'field_address_country_code',
        ])
        ->condition('field_address_organization', $company)
        ->orderBy('entity_id', 'DESC')
        ->execute();

      foreach ($address_query as $address_row) {
        $addresses[$address_row->entity_id] = [
          'organization' => $address_row->field_address_organization ?? '',
          'address1' => $address_row->field_address_address_line1 ?? '',
          'address2' => $address_row->field_address_address_line2 ?? '',
          'address3' => $address_row->field_address_address_line3 ?? '',
          'locality' => $address_row->field_address_locality ?? '',
          'admin_area' => $address_row->field_address_administrative_area ?? '',
          'postcode' => $address_row->field_address_postal_code ?? '',
          'country' => $address_row->field_address_country_code ?? '',
        ];
      }
    }

    if (!empty($addresses)) {
      $first = reset($addresses);
      $formatted_address = implode("\n", array_filter([
        $first['address1'], $first['address2'], $first['address3'],
        $first['locality'], $first['admin_area'], $first['postcode'], $first['country']
      ]));
    }

    $company_email = '';
    if ($client->hasField('email') && !$client->get('email')->isEmpty()) {
      $company_email = trim((string) $client->get('email')->value);
    }

    $company_phone = '';
    if ($client->hasField('telephone') && !$client->get('telephone')->isEmpty()) {
      $company_phone = trim((string) $client->get('telephone')->value);
    }

    return [
      'company_id' => $entered_id,
      'client_cid' => (int) $client->id(),
      'customer_ucr_display' => $ucr_display,
      'stored_real_ucr' => $client->getRealUcr(),
      'name' => $display_name,
      'address' => $formatted_address,
      'addresses' => $addresses,
      'email' => $company_email,
      'phone' => $company_phone,
    ];
  }

  /**
   * Whether the sample already has company wizard data saved (return from property step).
   */
  protected function sampleHasPersistedCompanyWizard(EntityInterface $sample): bool {
    if ($sample->hasField('customer_id') && !$sample->get('customer_id')->isEmpty()) {
      return TRUE;
    }
    if ($sample->hasField('company_name') && !$sample->get('company_name')->isEmpty()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds wizard review data from a previously saved sample entity.
   */
  protected function wizardCompanyDataFromSample(EntityInterface $sample): array {
    $entered_id = '';
    if ($sample->hasField('customer_id') && !$sample->get('customer_id')->isEmpty()) {
      $entered_id = trim((string) $sample->get('customer_id')->value);
    }

    if ($entered_id !== '') {
      $client = $this->lookupSentinelClientByCompanyId($entered_id);
      if ($client) {
        return $this->clientToWizardData($client, $entered_id);
      }
    }

    return [
      'company_id' => $entered_id,
      'customer_ucr_display' => $entered_id,
      'stored_real_ucr' => '',
      'name' => '',
      'address' => '',
      'addresses' => [],
      'email' => '',
      'phone' => '',
    ];
  }

  /**
   * Builds field_address value array from manual wizard fields.
   *
   * @return array
   *   Non-empty keys suitable for company_address ECK field_address.
   */
  protected function buildWizardManualCompanyFieldAddress(FormStateInterface $form_state, string $fallback_organization): array {
    $prefill = $form_state->get('company_address_prefill');
    if (!is_array($prefill)) {
      $prefill = [];
    }

    $country = strtoupper(trim((string) (
      $form_state->getValue('company_country')
      ?? $prefill['company_country']
      ?? ''
    )));
    if ($country === '') {
      $country = 'GB';
    }

    $organization = trim((string) ($form_state->getValue('company') ?? $prefill['company'] ?? ''));
    if ($organization === '') {
      $organization = trim((string) ($form_state->getValue('company_name') ?? ''));
    }
    if ($organization === '') {
      $organization = trim($fallback_organization);
    }

    $line1 = trim((string) ($form_state->getValue('company_address_1') ?? $prefill['company_address_1'] ?? ''));
    $line2 = '';
    $locality = trim((string) ($form_state->getValue('company_town_city') ?? $prefill['company_town_city'] ?? ''));
    $postal = trim((string) ($form_state->getValue('company_postcode') ?? $prefill['company_postcode'] ?? ''));

    $data = [
      'country_code' => $country,
    ];
    if ($line1 !== '') {
      $data['address_line1'] = $line1;
    }
    if ($line2 !== '') {
      $data['address_line2'] = $line2;
    }
    if ($locality !== '') {
      $data['locality'] = $locality;
    }
    if ($postal !== '') {
      $data['postal_code'] = $postal;
    }
    if ($organization !== '') {
      $data['organization'] = $organization;
    }

    $meaningful = array_filter([
      $line1,
      $line2,
      $locality,
      $postal,
    ], static function ($v) {
      return $v !== '';
    });
    if ($meaningful === []) {
      return [];
    }

    return $data;
  }

  /**
   * Finds a fetched address row (entity_id => components) matching field_address data.
   */
  protected function findMatchingFetchedAddressEntityId(array $field_address, array $addresses): ?int {
    $norm = static function ($v): string {
      return mb_strtolower(trim((string) $v));
    };

    foreach ($addresses as $entity_id => $row) {
      $fetched_line2 = trim(implode(' ', array_filter([
        trim((string) ($row['address2'] ?? '')),
        trim((string) ($row['address3'] ?? '')),
      ], static function ($v) {
        return $v !== '';
      })));

      $merged_line1 = trim((string) ($row['address1'] ?? ''));
      if ($fetched_line2 !== '') {
        $merged_line1 = $merged_line1 !== '' ? $merged_line1 . ', ' . $fetched_line2 : $fetched_line2;
      }

      $manual_line1 = $norm($field_address['address_line1'] ?? '');
      $line1_matches = $manual_line1 === $norm($merged_line1)
        || ($manual_line1 === $norm($row['address1'] ?? '')
          && $norm($field_address['address_line2'] ?? '') === $norm($fetched_line2));

      if (!$line1_matches) {
        continue;
      }

      $pairs = [
        ['k' => 'locality', 'v' => $row['locality'] ?? ''],
        ['k' => 'administrative_area', 'v' => $row['admin_area'] ?? ''],
        ['k' => 'postal_code', 'v' => $row['postcode'] ?? ''],
      ];
      $match = TRUE;
      foreach ($pairs as $pair) {
        $a = $norm($field_address[$pair['k']] ?? '');
        $b = $norm($pair['v']);
        if ($a !== $b) {
          $match = FALSE;
          break;
        }
      }
      if (!$match) {
        continue;
      }
      $fc = $norm($field_address['country_code'] ?? 'GB');
      $rc = $norm($row['country'] ?? '');
      if ($rc === '') {
        $rc = $norm('GB');
      }
      if ($fc !== $rc) {
        continue;
      }
      return (int) $entity_id;
    }

    return NULL;
  }

  /**
   * Resolves the company address entity id (dropdown, match, or create).
   */
  protected function resolveCompanyAddressEntityTargetId(
    FormStateInterface $form_state,
    array $data,
    array $scalars,
  ): ?int {
    $addresses = $data['addresses'] ?? [];
    $selected_id = $this->getCompanyAddressSelectValue($form_state);
    $manual_mode = (bool) ($form_state->get('manual_address_mode') ?? FALSE);

    // Dropdown selection: always reuse the existing address entity.
    if ($selected_id !== NULL && !$manual_mode) {
      return (int) $selected_id;
    }

    if ($selected_id !== NULL) {
      $row = $this->getFetchedAddressRow($addresses, $selected_id);
      if ($row !== NULL && $this->companyManualAddressMatchesFetchedRow($form_state, $row)) {
        return (int) $selected_id;
      }
    }

    if (empty($scalars['has_content'])) {
      return NULL;
    }

    $org_fallback = trim((string) ($form_state->getValue('company_name') ?? ($data['name'] ?? '')));
    $field_address = $this->buildWizardManualCompanyFieldAddress($form_state, $org_fallback);
    if (empty($field_address)) {
      return NULL;
    }

    $matched_fetched = $this->findMatchingFetchedAddressEntityId($field_address, $addresses);
    if ($matched_fetched !== NULL) {
      return $matched_fetched;
    }

    $existing_id = $this->findExistingCompanyAddressEntityId($field_address);
    if ($existing_id !== NULL) {
      return $existing_id;
    }

    $address_storage = $this->entityTypeManager->getStorage('address');
    $entity = $address_storage->create([
      'type' => 'company_address',
      'field_address' => $field_address,
    ]);
    $entity->save();

    return (int) $entity->id();
  }

  /**
   * Whether manual/prefill values still match a fetched dropdown address row.
   */
  protected function companyManualAddressMatchesFetchedRow(FormStateInterface $form_state, array $row): bool {
    $prefill = $form_state->get('company_address_prefill');
    if (!is_array($prefill)) {
      $prefill = [];
    }
    $norm = static function ($v): string {
      return mb_strtolower(trim((string) $v));
    };

    $line1 = trim((string) ($form_state->getValue('company_address_1') ?? $prefill['company_address_1'] ?? ''));
    $town = trim((string) ($form_state->getValue('company_town_city') ?? $prefill['company_town_city'] ?? ''));
    $postcode = trim((string) ($form_state->getValue('company_postcode') ?? $prefill['company_postcode'] ?? ''));
    $country = strtoupper(trim((string) (
      $form_state->getValue('company_country')
      ?? $prefill['company_country']
      ?? 'GB'
    )));
    if ($country === '') {
      $country = 'GB';
    }

    $extra = trim(implode(' ', array_filter([
      trim((string) ($row['address2'] ?? '')),
      trim((string) ($row['address3'] ?? '')),
    ], static function ($v) {
      return $v !== '';
    })));
    $expected_line1 = trim((string) ($row['address1'] ?? ''));
    if ($extra !== '') {
      $expected_line1 = $expected_line1 !== '' ? $expected_line1 . ', ' . $extra : $extra;
    }

    $row_country = strtoupper(trim((string) ($row['country'] ?? '')));
    if ($row_country === '') {
      $row_country = 'GB';
    }

    return $norm($line1) === $norm($expected_line1)
      && $norm($town) === $norm($row['locality'] ?? '')
      && $norm($postcode) === $norm($row['postcode'] ?? '')
      && $norm($country) === $norm($row_country);
  }

  /**
   * Address line components for legacy sentinel_sample company_* columns.
   */
  protected function resolveCompanyAddressScalarsForSample(FormStateInterface $form_state, array $data): array {
    $prefill = $form_state->get('company_address_prefill');
    if (!is_array($prefill)) {
      $prefill = [];
    }
    $manual_mode = (bool) ($form_state->get('manual_address_mode') ?? FALSE);
    $selected_id = $this->getCompanyAddressSelectValue($form_state);
    $addresses = $data['addresses'] ?? [];

    if ($selected_id !== NULL) {
      $row = $this->getFetchedAddressRow($addresses, $selected_id);
      if ($row !== NULL && (!$manual_mode || $this->companyManualAddressMatchesFetchedRow($form_state, $row))) {
        return $this->fetchedAddressRowToSampleScalars($row);
      }
    }

    $line1 = trim((string) ($form_state->getValue('company_address_1') ?? $prefill['company_address_1'] ?? ''));
    $town = trim((string) ($form_state->getValue('company_town_city') ?? $prefill['company_town_city'] ?? ''));
    $postcode = trim((string) ($form_state->getValue('company_postcode') ?? $prefill['company_postcode'] ?? ''));

    return [
      'address1' => $line1,
      'address2' => '',
      'town' => $town,
      'county' => '',
      'postcode' => $postcode,
      'has_content' => ($line1 !== '' || $town !== '' || $postcode !== ''),
    ];
  }

  /**
   * Maps a fetched address row to sentinel_sample scalar columns.
   */
  protected function fetchedAddressRowToSampleScalars(array $row): array {
    $line2 = trim(implode(' ', array_filter([
      trim((string) ($row['address2'] ?? '')),
      trim((string) ($row['address3'] ?? '')),
    ], static function ($v) {
      return $v !== '';
    })));
    $address1 = trim((string) ($row['address1'] ?? ''));
    $town = trim((string) ($row['locality'] ?? ''));
    $county = trim((string) ($row['admin_area'] ?? ''));
    $postcode = trim((string) ($row['postcode'] ?? ''));

    return [
      'address1' => $address1,
      'address2' => $line2,
      'town' => $town,
      'county' => $county,
      'postcode' => $postcode,
      'has_content' => ($address1 !== '' || $line2 !== '' || $town !== '' || $postcode !== ''),
    ];
  }

  /**
   * Writes company address scalars on the sample entity.
   */
  protected function setSampleCompanyAddressScalars(EntityInterface $sample, array $scalars): void {
    if ($sample->hasField('company_address1')) {
      $sample->set('company_address1', $scalars['address1'] ?? '');
    }
    if ($sample->hasField('company_address2')) {
      $sample->set('company_address2', $scalars['address2'] ?? '');
    }
    if ($sample->hasField('company_town')) {
      $sample->set('company_town', $scalars['town'] ?? '');
    }
    if ($sample->hasField('company_county')) {
      $sample->set('company_county', $scalars['county'] ?? '');
    }
    if ($sample->hasField('company_postcode')) {
      $sample->set('company_postcode', $scalars['postcode'] ?? '');
    }
  }

  /**
   * Finds an existing company_address entity matching field_address components.
   */
  protected function findExistingCompanyAddressEntityId(array $address_data): ?int {
    $storage = $this->entityTypeManager->getStorage('address');
    $query = $storage->getQuery()
      ->condition('type', 'company_address')
      ->accessCheck(FALSE)
      ->range(0, 1);

    $fields_to_check = [
      'country_code',
      'address_line1',
      'address_line2',
      'locality',
      'administrative_area',
      'postal_code',
      'organization',
    ];

    $has_conditions = FALSE;
    foreach ($fields_to_check as $field) {
      if (isset($address_data[$field]) && $address_data[$field] !== '') {
        $query->condition("field_address.$field", $address_data[$field]);
        $has_conditions = TRUE;
      }
    }

    if (!$has_conditions) {
      return NULL;
    }

    $existing_ids = $query->execute();
    if (!empty($existing_ids)) {
      return (int) reset($existing_ids);
    }

    return NULL;
  }

  /**
   * Goes back from company ID step to account / language selection.
   */
  public function submitBackCompanyStep1(array &$form, FormStateInterface $form_state): void {
    $prn = $form_state->get('wizard_prn') ?: $this->getAnonymousPrn();
    if ($prn !== '') {
      $form_state->setRedirect(
        'sentinel_portal_sample.anonymous_submit',
        [],
        AnonymousSampleWizardProgress::prnRedirectOptions($prn)
      );
    }
  }

  /**
   * Dropdown label for a company address (street lines, town, postcode).
   */
  protected function formatCompanyAddressSelectLabel(array $addr): string {
    $parts = array_filter([
      trim((string) ($addr['address1'] ?? '')),
      trim((string) ($addr['address2'] ?? '')),
      trim((string) ($addr['locality'] ?? '')),
      trim((string) ($addr['postcode'] ?? '')),
    ], static function ($part) {
      return $part !== '';
    });
    return implode(', ', $parts);
  }

  /**
   * Default company address: latest used on a sample for the client, else newest entity.
   */
  protected function resolveDefaultCompanyAddressId($client, array $address_map): ?int {
    if ($address_map === []) {
      return NULL;
    }
    if ($client) {
      $from_sample = $this->getLatestCompanyAddressIdFromClientSamples($client);
      if ($from_sample !== NULL && isset($address_map[$from_sample])) {
        return $from_sample;
      }
    }
    return $this->getLatestCompanyAddressEntityId($address_map);
  }

  /**
   * Company address entity id from the client's most recently saved sample.
   */
  protected function getLatestCompanyAddressIdFromClientSamples($client): ?int {
    if (!$client || !method_exists($client, 'id')) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $query = $storage->getQuery()->accessCheck(FALSE)->sort('pid', 'DESC')->range(0, 100);
    $or = $query->orConditionGroup();
    $or->condition('client_id', (int) $client->id());
    if ($client->hasField('ucr') && !$client->get('ucr')->isEmpty()) {
      $or->condition('ucr', $client->get('ucr')->value);
    }
    $query->condition($or);

    foreach ($query->execute() as $sample_id) {
      $sample = $storage->load($sample_id);
      if (!$sample) {
        continue;
      }
      if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
        return (int) $sample->get('field_company_address')->first()->target_id;
      }
      if ($sample->hasField('sentinel_company_address_target_id') && !$sample->get('sentinel_company_address_target_id')->isEmpty()) {
        return (int) $sample->get('sentinel_company_address_target_id')->value;
      }
    }

    return NULL;
  }

  /**
   * Highest address entity id from an id-keyed address list or select options.
   */
  protected function getLatestCompanyAddressEntityId(array $address_map): ?int {
    $ids = [];
    foreach (array_keys($address_map) as $key) {
      if ($key === '' || $key === NULL) {
        continue;
      }
      $ids[] = (int) $key;
    }
    return $ids ? max($ids) : NULL;
  }

}
