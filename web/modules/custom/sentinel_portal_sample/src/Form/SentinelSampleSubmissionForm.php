<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_sample\PortalSampleCountryOptions;
use Drupal\sentinel_portal_entities\Entity\SentinelClient;
use Drupal\sentinel_portal_entities\Service\SentinelSampleValidation;
use Drupal\sentinel_portal_entities\Utility\PackTypeFilter;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\sentinel_portal_sample\Controller\SentinelSampleController;
use Drupal\sentinel_portal_sample\GoAddressClient;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sample submission form.
 */
class SentinelSampleSubmissionForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Cached sentinel client for the current user.
   *
   * @var \Drupal\sentinel_portal_entities\Entity\SentinelClient|null
   */
  protected $currentClient;

  /**
   * Tracks whether the client lookup has been performed.
   *
   * @var bool
   */
  protected $clientLoaded = FALSE;

  /**
   * Constructs a new SentinelSampleSubmissionForm.
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
    return 'sentinel_portal_sample_submission_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Submit a Pack');
    
    $form['help_text'] = [
      '#markup' => $this->t('Enter details below and click \'Save\' to submit the pack information. Mandatory fields are marked with a red asterisk (*). Please note that some fields may only be relevant for specific projects/contracts.'),
      // '#weight' => -50,
    ];

    // Pack Reference Number
    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The pack reference number'),
      '#description' => $this->t('This pack reference number. This can be found at the top of the main pack/certificate with your pack.'),
      '#required' => TRUE,
      // '#weight' => -45,
    ];

    $form['pack_reference_number_confirm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm Pack Reference Number (Enter for validation)'),
      // '#weight' => -40,
    ];

    // Company Details Section
    // $form['company_details'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Company Details'),
    //   '#weight' => 10,
    //   '#attributes' => ['id' => 'company-details-wrapper'],
    // ];

    $form['sentinel_customer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sentinel Company ID'),
      '#description' => $this->t('Enter your Sentinel UCR number (Unique Customer Reference Number). This number is provided by Sentinel'),
      // '#weight' => -2,
    ];

    $form['fetch_details'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fetch Details'),
      '#submit' => ['::submitFetchCompanyDetails'],
      '#ajax' => [
        'callback' => '::ajaxCompanyDetailsRefresh',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Fetching details...'),
        ],
      ],
      '#limit_validation_errors' => [['sentinel_customer_id']],
      // '#weight' => -1,
    ];
 $form['company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      // '#weight' => 3,
    ];
    $form['company_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Company Email'),
      '#description' => $this->t('Email address of the company managing installation/maintenance. A copy of the Sentinel Pack report will be made available to this email address.'),
      '#required' => FALSE,
      // '#weight' => 1,
    ];

    $form['company_telephone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Company Telephone'),
      '#description' => $this->t('Telephone number of the company managing installation/maintenance for this system.'),
      // '#weight' => 2,
    ];

    // $form['company_details']['sentinel_customer_id'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Sentinel Customer ID'),
    //   '#description' => $this->t('Your Sentinel (Boiler/Customer) Reference number (SCR). This can be found in your account settings.'),
    //   '#weight' => 3,
    // ];

    // Company Address Section (inside Company Details)
    // $form['company_address'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Company Address'),
    //   '#description' => $this->t('Please provide the name and address of the company managing installation/maintenance on this system.'),
    //   '#weight' => 4,
    // ];

    $company_address_options = ['' => $this->t('Please select')];
    $company_address_map = [];
    $fetched_addresses = $form_state->get('company_fetched_addresses');
    if (is_array($fetched_addresses)) {
      $company_address_map = $fetched_addresses;
      foreach ($fetched_addresses as $entity_id => $addr) {
        $label = $this->formatCompanyAddressSelectLabel($addr);
        $company_address_options[$entity_id] = $label !== '' ? $label : $this->t('Address @id', ['@id' => $entity_id]);
      }
    } else {
      $client = $this->getCurrentClient();
      if ($client instanceof SentinelClient && function_exists('get_company_addresses_for_cids')) {
        $cids = function_exists('get_more_clients_based_client_cohorts') ? get_more_clients_based_client_cohorts($client) : [];
        $cids[] = $client->id();

        $addresses = get_company_addresses_for_cids($cids);
        foreach ($addresses as $address) {
          $company_address_map[$address->entity_id] = [
            'address1' => $address->field_address_address_line1 ?? '',
            'address2' => $address->field_address_address_line2 ?? '',
            'locality' => $address->field_address_locality ?? '',
            'postcode' => $address->field_address_postal_code ?? '',
          ];
          $label = $this->formatCompanyAddressSelectLabel($company_address_map[$address->entity_id]);
          $company_address_options[$address->entity_id] = $label !== '' ? $label : $this->t('Address @id', ['@id' => $address->entity_id]);
        }
      }
    }

    $company_address_default = $form_state->getValue('company_address_selection');
    if (($company_address_default === NULL || $company_address_default === '') && count($company_address_options) > 1) {
      $latest_id = $this->resolveDefaultCompanyAddressId($this->getCurrentClient(), $company_address_map);
      if ($latest_id !== NULL) {
        $company_address_default = (string) $latest_id;
      }
    }

    $form['company_address_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Select company address'),
      '#options' => $company_address_options,
      '#default_value' => $company_address_default,
      '#ajax' => [
        'callback' => '::ajaxSelectCompanyAddress',
        'event' => 'change',
      ],
      // '#weight' => 1,
      '#prefix' => '<div id="company-address-selection-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($company_address_default !== NULL && $company_address_default !== '') {
      $this->applyPortalCompanyAddressFieldDefaults($form, $form_state, (int) $company_address_default);
    }

    $form['company_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => PortalSampleCountryOptions::options(fn (string $label) => $this->t($label)),
      '#default_value' => 'GB',
      // '#weight' => 2,
    ];

    $form['company_address_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address 1'),
      // '#weight' => 4,
    ];

    // $form['company_property_name'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Property name'),
    //   // '#weight' => 5,
    // ];

    // $form['company_property_number'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Property number'),
    //   // '#weight' => 6,
    // ];

    $form['company_town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town/City'),
      // '#weight' => 7,
    ];

    $form['company_postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      // '#weight' => 8,
    ];

    // Job Details Section
    $form['job_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Property Details'),
      // '#weight' => 20,
    ];
//  $form['job_details']['address'] = [
//       '#type' => 'fieldset',
//       '#title' => $this->t('Address'),
//       '#description' => $this->t('Please provide the full address of where this system is located.'),
//      //#weight' => 1,
//     ];

    $form['job_details']['goaddress_search'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'portal-goaddress-search'],
      // '#weight' => 2,
    ];
    $form['job_details']['goaddress_search']['property_house_no'] = [
      '#type' => 'textfield',
      '#title' => $this->t('House number'),
      '#parents' => ['portal_property_house_no'],
      '#default_value' => GoAddressClient::formStateString($form_state, [
        ['portal_property_house_no'],
        ['job_details', 'goaddress_search', 'property_house_no'],
      ]),
      '#size' => 12,
      '#weight' => -14,
    ];
    $form['job_details']['goaddress_search']['property_postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#parents' => ['portal_property_postcode'],
      '#default_value' => GoAddressClient::formStateString($form_state, [
        ['portal_property_postcode'],
        ['job_details', 'goaddress_search', 'property_postcode'],
      ]),
      '#size' => 16,
      '#weight' => -13,
    ];
    $form['job_details']['goaddress_search']['portal_goaddress_search_btn'] = [
      '#type' => 'button',
      '#name' => 'portal_goaddress_search_btn',
      '#value' => $this->t('Search address'),
      '#executes_submit_callback' => TRUE,
      '#submit' => ['::submitPortalGoAddressSearch'],
      '#ajax' => [
        'callback' => '::ajaxPortalGoAddressSearch',
        'wrapper' => 'portal-sample-address-fields',
        'progress' => ['type' => 'throbber'],
      ],
      // Skip full-form validation (pack reference, etc.) so search always runs.
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--small']],
      '#weight' => -12,
    ];
    $portal_goaddress_message = $form_state->get('portal_goaddress_message');
    if (is_string($portal_goaddress_message) && $portal_goaddress_message !== '') {
      $form['job_details']['goaddress_search']['goaddress_search_status'] = [
        '#markup' => '<p class="goaddress-search-status messages messages--warning">' . htmlspecialchars($portal_goaddress_message, ENT_QUOTES, 'UTF-8') . '</p>',
        '#weight' => -10,
      ];
    }

    $form['job_details']['sample_address_add'] = [
      '#type' => 'button',
      '#value' => $this->t('Enter address'),
      // '#weight' => 3,
      '#attributes' => [
        'class' => ['sample-address-add-button'],
      ],
    ];

    $property_prefill = $form_state->get('portal_goaddress_prefill');
    if (!is_array($property_prefill)) {
      $property_prefill = [];
    }
    $show_property_address_fields = trim((string) ($property_prefill['address_1'] ?? '')) !== '';

    // Address fields wrapper - hidden by default, shown after GoAddress search.
    $form['job_details']['address_fields'] = [
      '#type' => 'container',
      // '#weight' => 4,
      '#attributes' => [
        'id' => 'portal-sample-address-fields',
        'class' => ['sample-address-fields'],
      ],
    ];
    if ($show_property_address_fields) {
      $form['job_details']['address_fields']['#attributes']['style'] = 'display: block;';
      $form['job_details']['sample_address_add']['#attributes']['style'] = 'display: none;';
    }

    $form['job_details']['address_fields']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => PortalSampleCountryOptions::options(fn (string $label) => $this->t($label)),
      '#default_value' => $property_prefill['country'] ?? 'GB',
      // '#weight' => 5,
    ];

    $form['job_details']['address_fields']['address_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address 1'),
      '#default_value' => $property_prefill['address_1'] ?? '',
      // '#weight' => 6,
    ];

    // $form['job_details']['address_fields']['property_name'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Property name'),
    //   // '#weight' => 7,
    // ];

    // $form['job_details']['address_fields']['property_number'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Property number'),
    //   // '#weight' => 8,
    // ];

    $form['job_details']['address_fields']['town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town/City'),
      '#default_value' => $property_prefill['town_city'] ?? '',
      // '#weight' => 9,
    ];

    $form['job_details']['address_fields']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#default_value' => $property_prefill['postcode'] ?? '',
      // '#weight' => 10,
    ];

    $form['job_details']['address_fields']['sample_address_close'] = [
      '#type' => 'button',
      '#value' => $this->t('Close address'),
      // '#weight' => 11,
      '#attributes' => [
        'class' => ['sample-address-close-button'],
      ],
    ];
    $form['job_details']['system_age'] = [
      '#type' => 'radios',
      '#title' => $this->t('Age of system'),
      // '#required' => TRUE,
      '#options' => [
        'LESS6' => $this->t('Less than 6 months'),
        'MORE6' => $this->t('More than 6 months'),
      ],
      '#default_value' => 'LESS6',
      // '#weight' => 16
    ]; 
    $form['job_details']['boiler_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Boiler Serial Number  '),
      '#description' => $this->t('Boiler serial number as provided by the boiler manufacturer.'),
      // '#weight' => 18
    ];
    $form['job_details']['boiler_manufacturer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Boiler Manufacturer'),
      '#description' => $this->t('Manufacturer of the boiler.'),
    ];

    // $form['job_details']['system_age'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('System Age'),
    //   '#description' => $this->t('The age of the system in months.'),
    //   '#weight' => 5,
    // ];
                                    
   

    // $form['job_details']['project_id'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Project ID'),
    //   '#description' => $this->t('The project ID. Required for claiming boiler manufacturer support.'),
    //   '#weight' => 7,
    // ];

    $form['job_details']['date_sent'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date Sent'),
      '#description' => $this->t('Date the boiler sample was sent to Sentinel.'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#date_timezone' => date_default_timezone_get(),
      '#date_date_format' => 'd/m/Y',
      // '#weight' => 19
    ];

    // $form['job_details']['uprn'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('UPRN'),
    //   '#description' => $this->t('Unique Property Reference Number for the boiler\'s location. Required for claiming contract support with your supplier (wholesale).'),
    //   '#weight' => 9,
    // ];

 

    $form['job_details']['date_installed'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Boiler Install Date'),
      '#description' => $this->t('Date the boiler was installed.'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#date_timezone' => date_default_timezone_get(),
      '#date_date_format' => 'd/m/Y',
      // '#weight' => 20
    ];
$form['job_details']['installer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Installer Name'),
      '#description' => $this->t('Name of the accredited installer who carried out the work and commissioned the pack.'),
      '#required' => FALSE,
      // '#weight' => 12,
    ];

    $form['job_details']['installer_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Installer Email'),
      '#description' => $this->t('Email address of the accredited installer who carried out the work and commissioned the pack.'),
      // '#weight' => 13,
    ];

    $form['job_details']['installer_company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Installer Company'),
      '#description' => $this->t('Please provide the name of the company managing installation/maintenance on this system.'),
      // '#weight' => 14,
    ];
    // System Details Section
    // $form['system_details'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('System Details'),
    //   '#weight' => 30,
    // ];

    // // Search for LA/HA Section
    // $form['system_details']['landlord_selection'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Search for LA/HA'),
    //   '#description' => $this->t('Please select Landlord from the system list. If Landlord is not present, please enter in the field below.'),
    //   '#autocomplete_route_name' => 'sentinel_portal_sample.landlord.autocomplete',
    //   '#ajax' => [
    //     'callback' => '::ajaxSelectLandlord',
    //     'event' => 'change',
    //   ],
    //   '#weight' => 1,
    // ];

    // $form['system_details']['sample_landlord_add'] = [
    //   '#type' => 'button',
    //   '#value' => $this->t('Enter landlord manually'),
    //   '#weight' => 2,
    //   '#attributes' => [
    //     'class' => ['sample-landlord-add-button'],
    //   ],
    // ];

    // // Landlord field wrapper - hidden by default, shown via JavaScript
    // $form['system_details']['landlord_wrapper'] = [
    //   '#type' => 'container',
    //   '#weight' => 3,
    //   '#attributes' => [
    //     'class' => ['sample-landlord-field'],
    //   ],
    // ];

    // $form['system_details']['landlord_wrapper']['landlord'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Landlord'),
    //   '#description' => $this->t('The full address of where this system is located.'),
    //   '#weight' => 1,
    // ];  

    // Address Section (nested inside System Details)
    // $form['system_details']['address'] = [
    //   '#type' => 'fieldset',
    //   '#title' => $this->t('Address'),
    //   '#description' => $this->t('Please provide the full address of where this system is located.'),
    //   '#weight' => 4,
    // ];

    // $form['system_details']['address']['sample_address_selection'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Search for property address'),
    //   '#description' => $this->t('Please input the property number or street name to find the full property address.'),
    //   '#autocomplete_route_name' => 'sentinel_portal_sample.property_address_autocomplete',
    //   '#ajax' => [
    //     'callback' => '::ajaxSelectSampleAddress',
    //     'event' => 'autocompleteclose',
    //   ],
    //   '#weight' => 1,
    // ];

    // $form['system_details']['address']['sample_address_add'] = [
    //   '#type' => 'button',
    //   '#value' => $this->t('Enter address manually'),
    //   '#weight' => 2,
    //   '#attributes' => [
    //     'class' => ['sample-address-add-button'],
    //   ],
    // ];

    // // Address fields wrapper - hidden by default, shown via JavaScript
    // $form['system_details']['address']['address_fields'] = [
    //   '#type' => 'container',
    //   '#weight' => 3,
    //   '#attributes' => [
    //     'class' => ['sample-address-fields'],
    //   ],
    // ];

    // $form['system_details']['address']['address_fields']['country'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Country'),
    //   '#options' => PortalSampleCountryOptions::options(fn (string $label) => $this->t($label)),
    //   '#default_value' => 'GB',
    //   '#weight' => 1,
    // ];

    // $form['system_details']['address']['address_fields']['address_1'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Address 1'),
    //   '#weight' => 2,
    // ];

    // $form['system_details']['address']['address_fields']['property_name'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Property name'),
    //   '#weight' => 3,
    // ];

    // $form['system_details']['address']['address_fields']['property_number'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Property number'),
    //   '#weight' => 4,
    // ];

    // $form['system_details']['address']['address_fields']['town_city'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Town/City'),
    //   '#weight' => 5,
    // ];

    // $form['system_details']['address']['address_fields']['postcode'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Postcode'),
    //   '#weight' => 6,
    // ];

    // $form['system_details']['address']['address_fields']['sample_address_close'] = [
    //   '#type' => 'button',
    //   '#value' => $this->t('Close address manually'),
    //   '#weight' => 7,
    //   '#attributes' => [
    //     'class' => ['sample-address-close-button'],
    //   ],
    // ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['btn-primary']],
      '#weight' => 200,
    ];

    // Attach CSS and JS libraries
    $form['#attached']['library'][] = 'sentinel_portal_sample/sample-form';
    $form['#attached']['library'][] = 'core/drupal.date';

    return $form;
  }

  /**
   * AJAX callback when a landlord is selected from autocomplete.
   */
  public function ajaxSelectLandlord(array &$form, FormStateInterface $form_state) {
    return $this->getSampleController()->selectLandlord($form, $form_state);
  }

  /**
   * AJAX callback when a company address is selected from the dropdown.
   */
  public function ajaxSelectCompanyAddress(array &$form, FormStateInterface $form_state) {
    $response = new \Drupal\Core\Ajax\AjaxResponse();

    $selection = $form_state->getValue('company_address_selection');
    if (empty($selection)) {
      return $response;
    }

    foreach ($this->buildCompanyAddressFieldInvokeCommands((int) $selection, $form_state) as $command) {
      $response->addCommand($command);
    }

    return $response;
  }

  /**
   * Submit handler: portal GoAddress property search.
   */
  public function submitPortalGoAddressSearch(array &$form, FormStateInterface $form_state): void {
    $this->performPortalGoAddressSearch($form_state);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Calls GoAddress API and stores portal property address prefill.
   */
  protected function performPortalGoAddressSearch(FormStateInterface $form_state): void {
    $house_no = GoAddressClient::formStateString($form_state, [
      ['portal_property_house_no'],
      ['job_details', 'goaddress_search', 'property_house_no'],
    ]);
    $postcode = GoAddressClient::formStateString($form_state, [
      ['portal_property_postcode'],
      ['job_details', 'goaddress_search', 'property_postcode'],
    ]);

    $form_state->set('portal_goaddress_message', NULL);
    $form_state->set('portal_goaddress_prefill', []);

    if ($house_no === '' || $postcode === '') {
      $form_state->set('portal_goaddress_message', (string) $this->t('Please enter both house number and postcode.'));
      return;
    }

    $results = GoAddressClient::search(\Drupal::httpClient(), $house_no, $postcode);
    if ($results === []) {
      $form_state->set('portal_goaddress_message', (string) $this->t('No addresses found for that house number and postcode.'));
      return;
    }

    $first = reset($results);
    $fields = $first['fields'] ?? [];
    if (!is_array($fields) || $fields === []) {
      $form_state->set('portal_goaddress_message', (string) $this->t('No addresses found for that house number and postcode.'));
      return;
    }

    unset($fields['goaddress_id']);
    $form_state->set('portal_goaddress_prefill', $fields);
    $this->portalGoAddressSyncJobDetailsAddressFields($form_state, $fields);
  }

  /**
   * AJAX callback: populate sample property address fields after GoAddress search.
   */
  public function ajaxPortalGoAddressSearch(array &$form, FormStateInterface $form_state) {
    $message = $form_state->get('portal_goaddress_message');
    if (is_string($message) && $message !== '') {
      $response = new AjaxResponse();
      $response->addCommand(new MessageCommand($message, NULL, ['type' => 'warning']));
      return $response;
    }

    $prefill = $form_state->get('portal_goaddress_prefill');
    if (!is_array($prefill) || trim((string) ($prefill['address_1'] ?? '')) === '') {
      return new AjaxResponse();
    }

    $response = new AjaxResponse();
    foreach ($this->buildPortalSampleAddressInvokeCommands($prefill) as $command) {
      $response->addCommand($command);
    }
    return $response;
  }

  /**
   * Invoke commands for portal sample address fields (flat name="address_1", etc.).
   *
   * @return \Drupal\Core\Ajax\InvokeCommand[]
   */
  protected function buildPortalSampleAddressInvokeCommands(array $prefill): array {
    $clean = static function ($val) {
      return preg_replace('/[\r\n]+/', ' ', trim((string) $val));
    };
    $country = strtoupper($clean($prefill['country'] ?? '')) ?: 'GB';
    $scope = '#portal-sample-address-fields';

    return [
      new InvokeCommand($scope . ' select[name="country"]', 'val', [$country]),
      new InvokeCommand($scope . ' input[name="address_1"]', 'val', [$clean($prefill['address_1'] ?? '')]),
      new InvokeCommand($scope . ' input[name="town_city"]', 'val', [$clean($prefill['town_city'] ?? '')]),
      new InvokeCommand($scope . ' input[name="postcode"]', 'val', [$clean($prefill['postcode'] ?? '')]),
      new InvokeCommand($scope, 'show'),
      new InvokeCommand('.sample-address-add-button', 'hide'),
    ];
  }

  /**
   * Writes GoAddress result into portal sample address field form state.
   */
  /**
   * Merges GoAddress prefill into flattened submit values when fields are empty.
   */
  protected function applyPortalGoAddressPrefillToValues(array &$values, FormStateInterface $form_state): void {
    $prefill = $form_state->get('portal_goaddress_prefill');
    if (!is_array($prefill) || $prefill === []) {
      return;
    }

    foreach (['country', 'address_1', 'town_city', 'postcode', 'county'] as $key) {
      if (trim((string) ($values[$key] ?? '')) === '' && trim((string) ($prefill[$key] ?? '')) !== '') {
        $values[$key] = trim((string) $prefill[$key]);
      }
    }

    if (trim((string) ($values['street'] ?? '')) === '' && trim((string) ($values['address_1'] ?? '')) !== '') {
      $values['street'] = $values['address_1'];
    }

    if (isset($values['address_fields']) && is_array($values['address_fields'])) {
      foreach (['country', 'address_1', 'town_city', 'postcode', 'county'] as $key) {
        if (trim((string) ($values['address_fields'][$key] ?? '')) === '' && trim((string) ($prefill[$key] ?? '')) !== '') {
          $values['address_fields'][$key] = trim((string) $prefill[$key]);
        }
      }
    }
  }

  protected function portalGoAddressSyncJobDetailsAddressFields(FormStateInterface $form_state, array $fields): void {
    $country = strtoupper(trim((string) ($fields['country'] ?? ''))) ?: 'GB';
    $address_values = [
      'country' => $country,
      'address_1' => trim((string) ($fields['address_1'] ?? '')),
      'town_city' => trim((string) ($fields['town_city'] ?? '')),
      'postcode' => trim((string) ($fields['postcode'] ?? '')),
      'county' => trim((string) ($fields['county'] ?? '')),
    ];
    foreach ($address_values as $key => $value) {
      $form_state->setValue($key, $value);
    }

    $input = $form_state->getUserInput();
    foreach ($address_values as $key => $value) {
      $input[$key] = $value;
    }
    $form_state->setUserInput($input);
  }

  /**
   * Helper to resolve the sample controller.
   */
  protected function getSampleController(): SentinelSampleController {
    return \Drupal::classResolver()->getInstanceFromDefinition(SentinelSampleController::class);
  }

  /**
   * {@inheritdoc}
   * 
   * Validation matches D7 sentinel_portal_sample_submission_form_validate()
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $client = $this->getCurrentClient();
    if (!$client instanceof SentinelClient || !$client->getUcr()) {
      $form_state->setErrorByName('', $this->t('Your client number was not found, please contact the site administrator.'));
    }

    // Get the pack reference number
    $pack_reference_number = trim($form_state->getValue('pack_reference_number'));
    
    // Check for a valid pack reference number format
    if (!empty($pack_reference_number) && !$this->validPackReferenceNumber($pack_reference_number)) {
      $form_state->setErrorByName('pack_reference_number', 
        $this->t('Please enter a valid pack reference number. It needs to be in either one of the following formats: NNN:NNNA, NNN:NNNNA, NNN:NNNNNA, NNN:NNNNNNA or NNN:NNNNNNNA. A is an optional alpha character. N is a numeric character')
      );
    }
    
    // Validate pack reference number confirmation
    $pack_reference_number_confirm = trim($form_state->getValue('pack_reference_number_confirm'));
    if (!empty($pack_reference_number_confirm) && $pack_reference_number !== $pack_reference_number_confirm) {
      $form_state->setErrorByName('pack_reference_number', 
        $this->t('That pack reference numbers you entered do not match.')
      );
      $form_state->setErrorByName('pack_reference_number_confirm');
    }
    
    // Check if pack reference number already exists
    if (!empty($pack_reference_number)) {
      $storage = $this->entityTypeManager->getStorage('sentinel_sample');
      $existing = $storage->getQuery()
        ->condition('pack_reference_number', $pack_reference_number)
        ->range(0, 1)
        ->accessCheck(FALSE)
        ->execute();
      
      if (!empty($existing)) {
        $form_state->setErrorByName('pack_reference_number', 
          $this->t('That pack reference number already exists.')
        );
      }
    }
    
    // Validate sample using entity validation (matches D7 $sample->validateSample())
    // Prepare form values similar to D7 structure for validation
    $validation_data = [];
    $form_values = $form_state->getValues();

    $date_fields = ['date_sent', 'date_installed'];
    $normalized_dates = [];
    foreach ($date_fields as $date_field) {
      $normalized_dates[$date_field] = $this->normalizeDateFormValue($form_state->getValue($date_field));
    }

    // Flatten fieldset values to match D7 structure
    foreach (['company_details', 'system_details', 'job_details'] as $fieldset) {
      if (isset($form_values[$fieldset]) && is_array($form_values[$fieldset])) {
        foreach ($form_values[$fieldset] as $key => $val) {
          if ($key !== '#type' && $key !== '#title' && $key !== '#weight') {
            if (in_array($key, $date_fields, TRUE)) {
              $validation_data[$key] = $normalized_dates[$key];
            }
            else {
              $validation_data[$key] = is_string($val) ? trim($val) : $val;
            }
          }
        }
      }
    }

    // Map top-level fields that are already flattened
    $top_level_fields = ['pack_reference_number', 'company', 'company_address_1', 'company_property_name',
                         'company_town_city', 'company_postcode', 'company_telephone', 'company_email',
                         'sentinel_customer_id', 'address_1', 'property_name', 'property_number',
                         'town_city', 'postcode', 'county', 'landlord', 'installer_name', 'installer_email',
                         'installer_company', 'boiler_manufacturer', 'system_age', 'boiler_type',
                         'project_id', 'date_sent', 'uprn', 'boiler_id', 'date_installed'];
    foreach ($top_level_fields as $field) {
      if (in_array($field, $date_fields, TRUE)) {
        $validation_data[$field] = $normalized_dates[$field];
        continue;
      }

      if (array_key_exists($field, $form_values)) {
        $value = $form_values[$field];

        if ($value instanceof DrupalDateTime) {
          $value = $this->normalizeDateFormValue($value);
        }

        if (is_array($value)) {
          continue;
        }

        $validation_data[$field] = is_string($value) ? trim($value) : $value;
      }
    }

    $this->applyPortalGoAddressPrefillToValues($validation_data, $form_state);
    
    // Ensure property number propagates even if the form values are flattened.
    $property_number_value = $form_state->getValue([
      'job_details',
      'address_fields',
      'property_number',
    ]);
    if ($property_number_value === NULL) {
      $property_number_value = $form_state->getValue('property_number');
    }
    if (is_string($property_number_value)) {
      $property_number_value = trim($property_number_value);
    }
    if ($property_number_value !== NULL && $property_number_value !== '') {
      $validation_data['property_number'] = $property_number_value;
    }

    // Map company address fields to validation format
    if (isset($form_values['company_address_1'])) {
      $validation_data['company_address1'] = trim($form_values['company_address_1']);
    }
    if (isset($form_values['company_property_name'])) {
      $validation_data['company_address2'] = trim($form_values['company_property_name']);
    }
    if (isset($form_values['company_town_city'])) {
      $validation_data['company_town'] = trim($form_values['company_town_city']);
    }
    if (isset($form_values['company_postcode'])) {
      $validation_data['company_postcode'] = trim($form_values['company_postcode']);
    }
    
    // Map company fields
    if (isset($validation_data['company'])) {
      $validation_data['company_name'] = $validation_data['company'];
    }
    if (isset($validation_data['company_telephone'])) {
      $validation_data['company_tel'] = $validation_data['company_telephone'];
    }
    if (isset($validation_data['sentinel_customer_id'])) {
      $validation_data['customer_id'] = $validation_data['sentinel_customer_id'];
    }
    
    // Map system address fields (stored flat: address_1, town_city, etc.).
    $sample_address = [];
    if (isset($form_values['job_details']['address_fields']) && is_array($form_values['job_details']['address_fields'])) {
      $sample_address = $form_values['job_details']['address_fields'];
    }
    foreach (['address_1', 'town_city', 'postcode', 'property_number', 'property_name', 'county', 'country'] as $key) {
      if (($sample_address[$key] ?? '') === '' && isset($form_values[$key])) {
        $sample_address[$key] = $form_values[$key];
      }
    }
    if ($sample_address !== []) {
      if (isset($sample_address['property_number'])) {
        $validation_data['property_number'] = trim($sample_address['property_number']);
      }
      if (isset($sample_address['address_1'])) {
        $validation_data['address_1'] = trim($sample_address['address_1']);
        $validation_data['street'] = trim($sample_address['address_1']);
      }
      if (isset($sample_address['property_name'])) {
        $validation_data['property_name'] = trim($sample_address['property_name']);
      }
      if (isset($sample_address['town_city'])) {
        $validation_data['town_city'] = trim($sample_address['town_city']);
      }
      if (isset($sample_address['county'])) {
        $validation_data['county'] = trim($sample_address['county']);
      }
      if (isset($sample_address['postcode'])) {
        $validation_data['postcode'] = trim($sample_address['postcode']);
      }

      // If sentinel_addresses module is enabled, mimic nested address structure expected by legacy validation.
      $module_handler = \Drupal::moduleHandler();
      if ($module_handler->moduleExists('sentinel_addresses')) {
        $legacy_address =& $validation_data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0];
        if (isset($validation_data['property_number'])) {
          $legacy_address['sub_premise'] = $validation_data['property_number'];
        }
        if (isset($validation_data['street'])) {
          $legacy_address['thoroughfare'] = $validation_data['street'];
        }
        if (isset($validation_data['town_city'])) {
          $legacy_address['locality'] = $validation_data['town_city'];
        }
        if (isset($validation_data['county'])) {
          $legacy_address['administrative_area'] = $validation_data['county'];
        }
        if (isset($validation_data['postcode'])) {
          $legacy_address['postal_code'] = $validation_data['postcode'];
        }
      }
    }

    if (!empty($validation_data['property_number'])) {
      $module_handler = \Drupal::moduleHandler();
      if ($module_handler->moduleExists('sentinel_addresses')) {
        $legacy_address =& $validation_data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0];
        if (empty($legacy_address['sub_premise'])) {
          $legacy_address['sub_premise'] = $validation_data['property_number'];
        }
      }
    }
    
    // Add form_id for sentinel_addresses module detection (matches D7)
    $validation_data['form_id'] = 'sentinel_portal_sample_submission_form';
    
    // Call validation service directly (matches D7 SentinelSampleEntityValidation::validateSample)
    try {
      if ($client instanceof SentinelClient && $client->getUcr()) {
        $validation_data['ucr'] = $client->getUcr();
      }

      $invalid_fields = SentinelSampleValidation::validateSample($validation_data);
      
      // Remove company_name from validation errors (matches D7 behavior)
      if (isset($invalid_fields['company_name'])) {
        unset($invalid_fields['company_name']);
      }

      foreach (['boiler_manufacturer', 'boiler_id', 'date_installed'] as $optional_field) {
        unset($invalid_fields[$optional_field]);
      }
      
      // Set form errors for invalid fields (matches D7 form_set_error logic)
      foreach ($invalid_fields as $field_name => $error_text) {
        $form_error_target_field = $field_name;
        
        // Handle deprecated address fields mapping (matches D7 logic)
        $module_handler = \Drupal::moduleHandler();
        if ($module_handler->moduleExists('sentinel_addresses')) {
          // Map deprecated field names to form field paths (matches D7)
          $normal_address_mapping = [
            'sub_premise' => 'property_number',
            'thoroughfare' => 'street',
            'dependent_locality' => 'ADDRESS_3',
            'sub_administrative_area' => 'ADDRESS_4',
            'locality' => 'town_city',
            'administrative_area' => 'county',
            'postal_code' => 'postcode',
          ];
          
          // Check if this field should map to an address field
          $mapping = array_flip(array_map('strtolower', $normal_address_mapping));
          if (isset($mapping[strtolower($field_name)])) {
            // Map to form field path if sentinel_addresses is active
            // For now, we'll use the field name directly since form structure may differ
          }
        }
        
        // Set the error on the appropriate form field
        $form_state->setErrorByName($form_error_target_field, $error_text);
      }
    } catch (\Exception $e) {
      // Log validation error but don't break form submission
      \Drupal::logger('sentinel_portal_sample')->warning('Error during form validation: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }
  
  /**
   * Validates a pack reference number format (matches D7 valid_pack_reference_number).
   *
   * @param string $packref
   *   The pack reference number to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validPackReferenceNumber($packref) {
    $pattern = '/^([0-9]{3})[:\s-]?([0-9]{3,10}[a-zA-Z]?)$/';
    $matches = [];
    preg_match_all($pattern, trim($packref), $matches);
    
    if ((isset($matches[1][0]) || isset($matches[2][0])) && strpos($packref, ':') == 3) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $client = $this->getCurrentClient();
    if (!$client instanceof SentinelClient || !$client->getUcr()) {
      $this->messenger()->addError($this->t('Unable to create sample: your client number was not found. Please contact the site administrator.'));
      return;
    }
    $original_values = $form_state->getValues();
    $values = $original_values;
    $date_fields = ['date_sent', 'date_installed'];
    $normalized_dates = [];
    foreach ($date_fields as $date_field) {
      $normalized_dates[$date_field] = $this->normalizeDateFormValue($form_state->getValue($date_field));
    }
    // --- FLATTEN fieldset values (D11 mimic D7) ---
    foreach ([
        'company_details', 'system_details', 'job_details', 'result_details'
      ] as $fieldset) {
      if (isset($values[$fieldset]) && is_array($values[$fieldset])) {
        foreach ($values[$fieldset] as $key => $val) {
          // Only take non #type keys
          if ($key !== '#type' && $key !== '#title' && $key !== '#weight') {
            $values[$key] = $val;
          }
        }
      }
    }
    // Override flattened date values with normalized strings
    foreach ($normalized_dates as $field => $normalized_value) {
      if ($normalized_value !== '') {
        $values[$field] = $normalized_value;
        if (isset($values['job_details'][$field])) {
          $values['job_details'][$field] = $normalized_value;
        }
      }
      else {
        unset($values[$field]);
        if (isset($values['job_details'][$field])) {
          $values['job_details'][$field] = '';
        }
      }
    }

    $this->applyPortalGoAddressPrefillToValues($values, $form_state);

    // Fallback mappings so validation always sees street/town/county/postcode.
    if (empty($validation_data['street']) && !empty($validation_data['address_1'])) {
      $validation_data['street'] = $validation_data['address_1'];
    }
    if (empty($validation_data['town_city']) && !empty($validation_data['town'])) {
      $validation_data['town_city'] = $validation_data['town'];
    }

    // Note: Fields are already flattened to top level by the loop above
    // Company address fields use 'company_' prefix (company_address_1, company_property_name, etc.)
    // System address fields are at top level (address_1, property_name, town_city, etc.)
    // company_tel was 'company_telephone'
    if (isset($values['company_telephone'])) {
      $values['company_tel'] = $values['company_telephone'];
    }
    // system_details fields
    if (isset($values['system_location'])) {
      $values['system_location'] = $values['system_location']; // already at top-level
    }
    if (isset($values['system_age'])) {
      $values['system_6_months'] = $values['system_age']; // already at top-level
    }
    // address_fields 
    if (
      isset($values['address_fields']) && 
      is_array($values['address_fields'])
    ) {
      $addr = $values['address_fields'];
      if (isset($addr['street'])) {
        $values['street'] = $addr['street'];
      }
      if (!isset($values['street']) && isset($addr['address_1'])) {
        // Fallback to address_1 for street.
        $values['street'] = $addr['address_1'];
      }
      if (isset($addr['county'])) {
        $values['county'] = $addr['county'];
      }
      if (isset($addr['town_city'])) {
        $values['town_city'] = $addr['town_city'];
      }
      if (isset($addr['postcode'])) {
        $values['postcode'] = $addr['postcode'];
      }
      if (empty($values['system_location'])) {
        // Build a readable system_location similar to D7 deprecated computed field.
        $parts = [];
        foreach (['property_name', 'property_number', 'address_1', 'town_city', 'postcode'] as $p) {
          if (!empty($addr[$p])) {
            $parts[] = $addr[$p];
          }
        }
        if (!empty($parts)) {
          $values['system_location'] = implode(', ', $parts);
        }
      }
    }

    // Fallback street mapping for saves as well.
    if (empty($values['street']) && !empty($values['address_1'])) {
      $values['street'] = $values['address_1'];
    }

    // Create sample entity (matches D7 sentinel_portal_entities_create_sample)
    try {
      $storage = $this->entityTypeManager->getStorage('sentinel_sample');
      // Get UCR from client record
      $ucr_value = $client->get('ucr')->value;
      $sample = $storage->create([]);
      // Set UCR first
      if ($ucr_value && $sample->hasField('ucr')) {
        $sample->set('ucr', $ucr_value);
      }

      // Get client_id and client_name from sentinel_client table by UCR
      $client_id = NULL;
      $client_name = NULL;
      if ($ucr_value) {
        $client_storage = $this->entityTypeManager->getStorage('sentinel_client');
        $client_query = $client_storage->getQuery()
          ->condition('ucr', $ucr_value)
          ->accessCheck(FALSE)
          ->range(0, 1);
        $client_ids = $client_query->execute();
        
        if (!empty($client_ids)) {
          $client_entity = $client_storage->load(reset($client_ids));
          if ($client_entity) {
            $client_id = $client_entity->id();
            if ($client_entity->hasField('name') && !$client_entity->get('name')->isEmpty()) {
              $client_name = $client_entity->get('name')->value;
            }
          }
        }
      }

      // Get Sentinel Sentinel Customer ID from form
      $sentinel_customer_id = NULL;
      $customer_id_value = $form_state->getValue(['company_details', 'sentinel_customer_id']);
      if (empty($customer_id_value)) {
        // Try flattened value
        $customer_id_value = $form_state->getValue('sentinel_customer_id');
      }
      if (!empty($customer_id_value)) {
        $sentinel_customer_id = trim($customer_id_value);
      }

      // Determine pack_type from pack_reference_number
      $pack_type = NULL;
      $pack_reference_number = NULL;
      $pack_ref_value = $form_state->getValue('pack_reference_number');
      if (!empty($pack_ref_value)) {
        $pack_reference_number = trim($pack_ref_value);
        $pack_type = \Drupal\sentinel_portal_entities\Entity\SentinelSample::getPackType([
          'pack_reference_number' => $pack_reference_number,
        ]);
      }

      // Explicit legacy field name mapping from D7 -> values in this D11 form
      // Only map when present; do not overwrite with empty values.
      $legacyMappings = [
        // Company details
        'company_name' => ['company_details', 'company_address', 'company'],
        'company_address1' => ['company_details', 'company_address', 'company_address_1'],
        'company_address2' => ['company_details', 'company_address', 'company_address_2'],
        'company_town' => ['company_details', 'company_address', 'company_town_city'],
        'company_county' => ['company_details', 'company_address', 'county'],
        'company_postcode' => ['company_details', 'company_address', 'company_postcode'],

        // System address/location
        'system_location' => ['system_details', 'address', 'address_fields', 'address_1'],
        'property_number' => ['system_details', 'address', 'address_fields', 'property_number'],
        'street' => ['system_details', 'address', 'address_fields', 'address_1'],
        'town_city' => ['system_details', 'address', 'address_fields', 'town_city'],
        'county' => ['system_details', 'address', 'address_fields', 'county'],
        'postcode' => ['system_details', 'address', 'address_fields', 'postcode'],
        'landlord' => ['system_details', 'landlord_wrapper', 'landlord'],

        // Contact details
        'company_tel' => ['company_details', 'company_telephone'],

        // Dates (only if provided in this form)
        'date_sent' => ['job_details', 'date_sent'],
        'date_installed' => ['job_details', 'date_installed'],

        // Misc identifiers
        'boiler_id' => ['job_details', 'boiler_id'],
        'project_id' => ['job_details', 'project_id'],
        'uprn' => ['job_details', 'uprn'],
      ];

      foreach ($legacyMappings as $fieldName => $path) {
        $val = $this->getArrayPathValue($values, $path);
        if ($val !== NULL && $val !== '') {
          if ($sample->hasField($fieldName)) {
            $sample->set($fieldName, $val);
          }
        }
      }

      // Flat (top-level) mappings - values are already flattened from fieldsets above
      $legacyFlat = [
        'company_name' => 'company',
        // 'company_address1' => 'company_property_name',
        // 'company_address2' => 'company_address_1',  // Use company_property_name for address2
        'company_address1' => 'company_address_1',
        'company_address2' => 'company_property_name', 
        'company_town' => 'company_town_city',
        'company_county' => 'company_county',  // May not exist in form, but mapped if present
        'company_postcode' => 'company_postcode',
        'company_tel' => 'company_telephone',
        'property_number' => 'property_number',  // System address property_number
        'street' => 'address_1',  // System address street
        'town_city' => 'town_city',  // System address town_city
        'county' => 'county',  // System address county (may not exist)
        'postcode' => 'postcode',  // System address postcode
        'landlord' => 'landlord',
        'date_sent' => 'date_sent',
        'date_installed' => 'date_installed',
        'boiler_id' => 'boiler_id',
        'project_id' => 'project_id',
        'uprn' => 'uprn',
      ];

      foreach ($legacyFlat as $fieldName => $sourceKey) {
        if (isset($values[$sourceKey]) && $values[$sourceKey] !== '' && $values[$sourceKey] !== NULL) {
          if ($sample->hasField($fieldName)) {
            $sample->set($fieldName, $values[$sourceKey]);
          }
        }
      }

      // Compose system_location if not set but we have address pieces.
      if ($sample->hasField('system_location')) {
        $current = $sample->get('system_location')->value ?? NULL;
        if (empty($current)) {
          $parts = [];
          foreach (['property_name', 'property_number', 'address_1', 'town_city', 'postcode'] as $p) {
            if (!empty($values[$p])) {
              $parts[] = $values[$p];
            }
          }
          if (!empty($parts)) {
            $sample->set('system_location', implode(', ', $parts));
          }
        }
      }

      // Set all form values to the entity fields
      $sample_fields = [];
      $field_definitions = $sample->getFieldDefinitions();
      foreach ($field_definitions as $field_name => $field_definition) {
        if (isset($values[$field_name]) && $values[$field_name] !== '' && $values[$field_name] !== NULL) {
          $sample_fields[$field_name] = $values[$field_name];
        }
      }
      // Handle flattened values from nested fieldsets
      $this->mapFormValuesToEntity($sample, $values, $form);
      $this->ensureAddressEntities($sample, $values, $original_values, $form);
      $this->setLegacyAddressTargetIds($sample);

      // Set customer_id if provided
      if ($sentinel_customer_id !== NULL && $sample->hasField('customer_id')) {
        $sample->set('customer_id', $sentinel_customer_id);
      }

      // Set client_id and client_name if available
      if ($client_id !== NULL && $sample->hasField('client_id')) {
        $sample->set('client_id', $client_id);
      }
      if ($client_name !== NULL && $sample->hasField('client_name')) {
        $sample->set('client_name', $client_name);
      }

      // Set pack_type if available (map to short DB value: SEN/VAL)
      if ($pack_type !== NULL && $sample->hasField('pack_type')) {
        $sample->set('pack_type', PackTypeFilter::getPackTypeDbValue($pack_type));
      }

      if ($sample->hasField('boiler_manufacturer') && trim((string) ($sample->get('boiler_manufacturer')->value ?? '')) === '') {
        $manufacturer = trim((string) ($values['boiler_manufacturer'] ?? ''));
        $sample->set('boiler_manufacturer', $manufacturer !== '' ? $manufacturer : 'Not specified');
      }
      if ($sample->hasField('date_installed') && $sample->get('date_installed')->isEmpty()) {
        $installed = $this->normalizeDateFormValue($values['date_installed'] ?? '');
        $sample->set('date_installed', $installed !== '' ? $installed : NULL);
      }

      $sample->save();

      $this->messenger()->addMessage($this->t('Your sample has been added.'));
      if ($sample->id()) {
        $form_state->setRedirect('entity.sentinel_sample.canonical', [
          'sentinel_sample' => $sample->id(),
        ]);
      } else {
        $form_state->setRedirect('sentinel_portal.portal');
      }
    } catch (\Exception $e) {
      \Drupal::logger('sentinel_portal_sample')->error('Error creating sample: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while saving the sample. Please try again or contact support.'));
    }
  }

  /**
   * Loads the sentinel client entity for the current user.
   *
   * @return \Drupal\sentinel_portal_entities\Entity\SentinelClient|null
   *   The client entity or NULL if none is associated with the user.
   */
  protected function getCurrentClient() {
    if (!$this->clientLoaded) {
      $this->clientLoaded = TRUE;
      $this->currentClient = NULL;

      $current_user = \Drupal::currentUser();
      if ($current_user->isAuthenticated()) {
        $storage = $this->entityTypeManager->getStorage('sentinel_client');

        // First, attempt to load by user ID.
        $ids = $storage->getQuery()
          ->condition('uid', $current_user->id())
          ->range(0, 1)
          ->accessCheck(FALSE)
          ->execute();

        if (!empty($ids)) {
          $this->currentClient = $storage->load(reset($ids));
        }

        // Fallback to matching by email address if no client matched on UID.
        if (!$this->currentClient) {
          $account = User::load($current_user->id());
          if ($account && $account->getEmail()) {
            $ids = $storage->getQuery()
              ->condition('email', $account->getEmail())
              ->range(0, 1)
              ->accessCheck(FALSE)
              ->execute();
            if (!empty($ids)) {
              $this->currentClient = $storage->load(reset($ids));
            }
          }
        }
      }
    }

    return $this->currentClient;
  }

  /**
   * Normalize a date value from the form to D7-compatible string.
   *
   * @param mixed $value
   *   The form value which may be a DrupalDateTime, array or string.
   *
   * @return string
   *   Normalized date string in 'Y-m-d\TH:i:00' format or empty string.
   */
  protected function normalizeDateFormValue($value) {
    $date_string = '';

    if ($value instanceof DrupalDateTime) {
      $date_string = $value->format('Y-m-d');
    }
    elseif (is_array($value) && isset($value['date'])) {
      $date_string = trim($value['date']);
    }
    elseif (is_string($value)) {
      $date_string = trim($value);
    }

    if ($date_string === '') {
      return '';
    }

    $validated = SentinelSampleValidation::validateDate($date_string);
    if ($validated !== FALSE) {
      return $validated;
    }

    return $date_string;
  }

  /**
   * Maps form values to entity fields, handling nested structures.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to set values on.
   * @param array $values
   *   Flattened form values. (fieldsets have already been flattened)
   * @param array $form
   *   The form array.
   */
  protected function mapFormValuesToEntity($entity, array $values, array $form) {
    // Map direct field values
    foreach ($values as $key => $value) {
      // Skip system fields and non-field values
      if (in_array($key, ['form_build_id', 'form_token', 'form_id', 'op', 'submit', 'pack_reference_number_confirm', 'description', 'help_text'])) {
        continue;
      }
      // Skip empty values (matches D7 array_filter behavior)
      if ($value === '' || $value === NULL) {
        continue;
      }
      // Only process non-array (or simple field, not a structure)
      if (is_array($value) && isset($value['#type'])) {
        continue;
      }
      // Regular field values
      if ($entity->hasField($key)) {
        $entity->set($key, $value);
      }
    }
  }

  /**
   * Safely get a nested value from an array by path.
   *
   * @param array $source
   *   The source array.
   * @param array $path
   *   List of keys to traverse.
   *
   * @return mixed|null
   *   The value if found, otherwise NULL.
   */
  private function getArrayPathValue(array $source, array $path) {
    $cursor = $source;
    foreach ($path as $segment) {
      if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
        return NULL;
      }
      $cursor = $cursor[$segment];
    }
    return $cursor;
  }

  /**
   * Updates legacy address target ID fields on the sample entity.
   */
  protected function setLegacyAddressTargetIds(ContentEntityInterface $sample) {
    try {
      $address_storage = \Drupal::entityTypeManager()->getStorage('address');

      if ($sample->hasField('sentinel_company_address_target_id') && $sample->hasField('field_company_address')) {
        $company_target_id = NULL;
        $company_item = $sample->get('field_company_address')->first();
        if ($company_item && !empty($company_item->target_id)) {
          $company_address = $address_storage->load($company_item->target_id);
          if ($company_address) {
            $company_target_id = (int) $company_address->id();
          }
        }
        if ($company_target_id !== NULL) {
          $sample->set('sentinel_company_address_target_id', $company_target_id);
        }
      }

      if ($sample->hasField('sentinel_sample_address_target_id') && $sample->hasField('field_sentinel_sample_address')) {
        $sample_target_id = NULL;
        $sample_item = $sample->get('field_sentinel_sample_address')->first();
        if ($sample_item && !empty($sample_item->target_id)) {
          $sample_address = $address_storage->load($sample_item->target_id);
          if ($sample_address) {
            $sample_target_id = (int) $sample_address->id();
          }
        }
        if ($sample_target_id !== NULL) {
          $sample->set('sentinel_sample_address_target_id', $sample_target_id);
        }
      }
    }
    catch (\Throwable $t) {
      \Drupal::logger('sentinel_portal_sample')->error('setLegacyAddressTargetIds failed: @message<br/><pre>@trace</pre>', [
        '@message' => $t->getMessage(),
        '@trace' => $t->getTraceAsString(),
      ]);
      throw $t;
    }
  }

  /**
   * Ensure company and sample address entities exist and are referenced.
   */
  protected function ensureAddressEntities(ContentEntityInterface $sample, array $values, array $original_values, array $form): void {
    \Drupal::logger('sentinel_portal_sample')->debug('ensureAddressEntities invoked.');

    try {
      $address_storage = \Drupal::entityTypeManager()->getStorage('address');

      $skip_company_address = $values['skip_company_address'] ?? $original_values['skip_company_address'] ?? FALSE;
      $company_target_id = NULL;

      if (!$skip_company_address) {
        $company_selection = $values['company_address_selection']
          ?? $this->getArrayPathValue($values, ['company_details', 'company_address', 'company_address_selection'])
          ?? $this->getArrayPathValue($original_values, ['company_details', 'company_address', 'company_address_selection'])
          ?? NULL;
        $company_target_id = $this->parseAddressSelection($company_selection);

        if ($company_target_id) {
          $company_entity = $address_storage->load($company_target_id);
          if (!$company_entity) {
            $company_target_id = NULL;
          }
        }

        $company_address_data = $this->buildCompanyAddressFieldValues($values, $original_values);

        // Reuse the sample's existing company address when the submitted values
        // still describe the same address (e.g. wizard set field_company_address
        // but the details step does not post a dropdown selection).
        if (!$company_target_id && !empty($company_address_data)) {
          if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
            $reuse_id = (int) $sample->get('field_company_address')->first()->target_id;
            if ($this->companyAddressDataMatchesEntity($reuse_id, $company_address_data)) {
              $company_target_id = $reuse_id;
            }
          }
        }

        if (!$company_target_id && !empty($company_address_data)) {
          $existing_id = $this->findExistingAddress('company_address', $company_address_data);
          if ($existing_id) {
            $company_target_id = $existing_id;
          }
          else {
            $company_entity = $address_storage->create([
              'type' => 'company_address',
              'field_address' => $company_address_data,
            ]);
            $company_entity->save();
            $company_target_id = (int) $company_entity->id();
          }
        }

        // Preserve an existing reference when no address lines were submitted
        // (e.g. company fields disabled after the anonymous wizard).
        if (!$company_target_id && empty($company_address_data)) {
          if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
            $preserve_id = (int) $sample->get('field_company_address')->first()->target_id;
            if ($address_storage->load($preserve_id)) {
              $company_target_id = $preserve_id;
            }
          }
        }
      }

      if ($sample->hasField('field_company_address')) {
        if ($company_target_id) {
          $sample->set('field_company_address', ['target_id' => $company_target_id]);
        }
        else {
          $sample->set('field_company_address', NULL);
        }
      }

      $sample->set('sentinel_company_address_target_id', $company_target_id ?: NULL);

      $sample_target_id = NULL;
      $sample_address_data = $this->buildSampleAddressFieldValues($values, $original_values);
      if (!empty($sample_address_data)) {
        $sample_entity = $address_storage->create([
          'type' => 'address',
          'field_address' => $sample_address_data,
        ]);
        $sample_entity->save();
        $sample_target_id = (int) $sample_entity->id();
      }

      if ($sample->hasField('field_sentinel_sample_address')) {
        if ($sample_target_id) {
          $sample->set('field_sentinel_sample_address', ['target_id' => $sample_target_id]);
        }
        else {
          $sample->set('field_sentinel_sample_address', NULL);
        }
      }

      $sample->set('sentinel_sample_address_target_id', $sample_target_id ?: NULL);
    }
    catch (\Throwable $t) {
      \Drupal::logger('sentinel_portal_sample')->error('ensureAddressEntities failed: @message<br/><pre>@trace</pre>', [
        '@message' => $t->getMessage(),
        '@trace' => $t->getTraceAsString(),
      ]);
      throw $t;
    }
  }

  /**
   * Get the referenced address ID for a field if present.
   */
  protected function getReferencedAddressId(ContentEntityInterface $sample, string $field_name): ?int {
    if (!$sample->hasField($field_name)) {
      return NULL;
    }
    $item = $sample->get($field_name)->first();
    if ($item && !empty($item->target_id)) {
      return (int) $item->target_id;
    }
    return NULL;
  }

  /**
   * Finds an existing ECK address entity matching the given data.
   */
  protected function findExistingAddress(string $bundle, array $address_data): ?int {
    $storage = \Drupal::entityTypeManager()->getStorage('address');
    $query = $storage->getQuery()
      ->condition('type', $bundle)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $fields_to_check = ['country_code', 'address_line1', 'address_line2', 'locality', 'administrative_area', 'postal_code', 'organization'];
    
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
   * Whether an address entity's field_address values match submitted data.
   *
   * Used to avoid creating a duplicate company_address when the sample already
   * references the correct entity (e.g. after the anonymous company wizard).
   */
  protected function companyAddressDataMatchesEntity(int $entity_id, array $data): bool {
    $storage = \Drupal::entityTypeManager()->getStorage('address');
    $entity = $storage->load($entity_id);
    if (!$entity || !$entity->hasField('field_address') || $entity->get('field_address')->isEmpty()) {
      return FALSE;
    }
    $row = $entity->get('field_address')->first()->getValue();
    $fields = [
      'country_code',
      'address_line1',
      'address_line2',
      'locality',
      'administrative_area',
      'postal_code',
      'organization',
    ];
    foreach ($fields as $field) {
      $a = isset($data[$field]) ? trim((string) $data[$field]) : '';
      $b = isset($row[$field]) ? trim((string) $row[$field]) : '';
      if ($field === 'organization' && $a === '') {
        continue;
      }
      if (mb_strtolower($a) !== mb_strtolower($b)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Parse an address selection value (dropdown or autocomplete) to an ID.
   */
  protected function parseAddressSelection($selection): ?int {
    if (empty($selection)) {
      return NULL;
    }
    if (is_numeric($selection)) {
      return (int) $selection;
    }
    $trimmed = trim((string) $selection);
    // Sample address autocomplete: "(123) Street, Town"
    if (preg_match('/^\((\d+)\)/', $trimmed, $matches)) {
      return (int) $matches[1];
    }
    // Property address autocomplete: "Street, Postcode, GB (123)"
    if (preg_match('/\((\d+)\)\s*$/', $trimmed, $matches)) {
      return (int) $matches[1];
    }
    return NULL;
  }

  /**
   * Build address field values for the company address bundle.
   */
  protected function buildCompanyAddressFieldValues(array $values, array $original_values = []): array {
    $company_section = $values['company_address']
      ?? $this->getArrayPathValue($values, ['company_details', 'company_address'])
      ?? $this->getArrayPathValue($original_values, ['company_details', 'company_address'])
      ?? [];

    $country = strtoupper(trim((string) ($company_section['company_country'] ?? ($values['company_country'] ?? ''))));
    if ($country === '') {
      $country = 'GB';
    }

    $address_line1 = trim((string) (
      $company_section['company_address_1']
      ?? $values['company_address_1']
      ?? ''
    ));

    $property_name = trim((string) (
      $values['company_property_name']
      ?? $company_section['company_property_name']
      ?? ''
    ));

    $property_number = trim((string) (
      $values['company_property_number']
      ?? $company_section['company_property_number']
      ?? ''
    ));

    $additional_line = trim((string) (
      $values['company_address_2']
      ?? $company_section['company_address_2']
      ?? ''
    ));

    $address_line2_parts = [];
    if ($property_name !== '') {
      $address_line2_parts[] = $property_name;
    }
    if ($property_number !== '') {
      $address_line2_parts[] = $property_number;
    }
    if ($additional_line !== '') {
      $address_line2_parts[] = $additional_line;
    }
    $address_line2 = trim(implode(' ', $address_line2_parts));

    $locality = trim((string) (
      $company_section['company_town_city']
      ?? $values['company_town_city']
      ?? ''
    ));

    $postal_code = trim((string) (
      $company_section['company_postcode']
      ?? $values['company_postcode']
      ?? ''
    ));

    $administrative_area = trim((string) (
      $company_section['company_county']
      ?? $values['company_county']
      ?? ''
    ));

    $organization = trim((string) (
      $values['company']
      ?? $company_section['company']
      ?? ''
    ));

    $sample_fallback = $this->buildSampleAddressFieldValues($values, $original_values);

    if ($address_line1 === '' && $address_line2 === '' && $locality === '' && $postal_code === '' && $organization === '') {
      if (!empty($sample_fallback)) {
        if ($organization !== '') {
          $sample_fallback['organization'] = $organization;
        }
        return $sample_fallback;
      }
      return [];
    }

    $data = [
      'country_code' => $country,
    ];
    if ($address_line1 !== '') {
      $data['address_line1'] = $address_line1;
    }
    if ($address_line2 !== '') {
      $data['address_line2'] = $address_line2;
    }
    if ($locality !== '') {
      $data['locality'] = $locality;
    }
    if ($administrative_area !== '') {
      $data['administrative_area'] = $administrative_area;
    }
    if ($postal_code !== '') {
      $data['postal_code'] = $postal_code;
    }
    if ($organization !== '') {
      $data['organization'] = $organization;
    }

    if (!empty($sample_fallback)) {
      foreach (['address_line1', 'address_line2', 'locality', 'administrative_area', 'postal_code'] as $key) {
        if ((!isset($data[$key]) || $data[$key] === '') && isset($sample_fallback[$key]) && $sample_fallback[$key] !== '') {
          $data[$key] = $sample_fallback[$key];
        }
      }
      if (!isset($data['country_code']) || $data['country_code'] === '') {
        $data['country_code'] = $sample_fallback['country_code'] ?? 'GB';
      }
      if (!isset($data['organization']) && isset($sample_fallback['organization'])) {
        $data['organization'] = $sample_fallback['organization'];
      }
    }

    return $data;
  }

  /**
   * Build address field values for the sample address bundle.
   */
  protected function buildSampleAddressFieldValues(array $values, array $original_values = []): array {
    $address_section = $values['address']['address_fields']
      ?? $values['address_fields']
      ?? $this->getArrayPathValue($values, ['system_details', 'address', 'address_fields'])
      ?? $this->getArrayPathValue($original_values, ['system_details', 'address', 'address_fields'])
      ?? [];

    $country = strtoupper(trim((string) ($address_section['country'] ?? ($values['country'] ?? ''))));
    if ($country === '') {
      $country = 'GB';
    }

    $address_line1 = trim((string) ($address_section['address_1'] ?? ($values['address_1'] ?? '')));
    $address_line2_parts = array_filter([
      trim((string) ($address_section['address_2'] ?? '')),
      trim((string) ($address_section['property_name'] ?? ($values['property_name'] ?? ''))),
      trim((string) ($address_section['property_number'] ?? ($values['property_number'] ?? ''))),
    ]);
    $address_line2 = trim(implode(' ', $address_line2_parts));
    $locality = trim((string) ($address_section['town_city'] ?? ($values['town_city'] ?? '')));
    $postal_code = trim((string) ($address_section['postcode'] ?? ($values['postcode'] ?? '')));
    $administrative_area = trim((string) ($address_section['county'] ?? ($values['county'] ?? '')));
    $organization = trim((string) ($values['landlord'] ?? ''));

    if ($address_line1 === '' && $address_line2 === '' && $locality === '' && $postal_code === '' && $organization === '') {
      return [];
    }

    $data = [
      'country_code' => $country,
    ];
    if ($address_line1 !== '') {
      $data['address_line1'] = $address_line1;
    }
    if ($address_line2 !== '') {
      $data['address_line2'] = $address_line2;
    }
    if ($locality !== '') {
      $data['locality'] = $locality;
    }
    if ($administrative_area !== '') {
      $data['administrative_area'] = $administrative_area;
    }
    if ($postal_code !== '') {
      $data['postal_code'] = $postal_code;
    }
    if ($organization !== '') {
      $data['organization'] = $organization;
    }

    return $data;
  }

  /**
   * AJAX callback to refresh the company details wrapper.
   */
  public function ajaxCompanyDetailsRefresh(array &$form, FormStateInterface $form_state) {
  //  return $form;
    $response = new \Drupal\Core\Ajax\AjaxResponse();

    $email = $form_state->getValue('company_email');
    $phone = $form_state->getValue('company_telephone');
    $company = $form_state->getValue('company');

    $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_email"]', 'val', [$email]));
    $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_telephone"]', 'val', [$phone]));
    $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company"]', 'val', [$company]));
    
    // Replace the address selection dropdown to show the newly fetched addresses
    if (isset($form['company_address_selection'])) {
      $response->addCommand(new \Drupal\Core\Ajax\ReplaceCommand('#company-address-selection-wrapper', $form['company_address_selection']));
    }

    $selection = $form_state->getValue('company_address_selection');
    if ($selection === NULL || $selection === '') {
      $fetched = $form_state->get('company_fetched_addresses');
      if (is_array($fetched) && $fetched !== []) {
        $latest_id = $this->resolveDefaultCompanyAddressId($this->getCurrentClient(), $fetched);
        if ($latest_id !== NULL) {
          $selection = (string) $latest_id;
          $form_state->setValue('company_address_selection', $selection);
        }
      }
    }

    if ($selection !== NULL && $selection !== '') {
      foreach ($this->buildCompanyAddressFieldInvokeCommands((int) $selection, $form_state) as $command) {
        $response->addCommand($command);
      }
    }
    else {
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_address_1"]', 'val', ['']));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_property_name"]', 'val', ['']));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_property_number"]', 'val', ['']));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_town_city"]', 'val', ['']));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('input[name="company_postcode"]', 'val', ['']));
    }

    return $response;
  }

  /**
   * Submits Sentinel Customer ID fetch request to find Sentinel client and retrieve data.
   */
  public function submitFetchCompanyDetails(array &$form, FormStateInterface $form_state) {
    $customer_id = trim((string) $form_state->getValue('sentinel_customer_id'));
    if ($customer_id === '') {
      $this->messenger()->addWarning($this->t('Please enter a Sentinel Customer ID.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $client = $this->lookupSentinelClientByCompanyId($customer_id);
    if ($client) {
      $company = trim((string) ($client->get('company')->value ?? ''));
      $email = '';
      if ($client->hasField('email') && !$client->get('email')->isEmpty()) {
        $email = trim((string) $client->get('email')->value);
      }
      $phone = '';
      if ($client->hasField('telephone') && !$client->get('telephone')->isEmpty()) {
        $phone = trim((string) $client->get('telephone')->value);
      }

      $form_state->setValue('company_email', $email);
      $form_state->setValue('company_telephone', $phone);
      $form_state->setValue('company', $company);
      $input = $form_state->getUserInput();
      $input['company_email'] = $email;
      $input['company_telephone'] = $phone;
      $input['company'] = $company;

      // Load addresses and store in form_state
      $addresses = $this->getCompanyAddressesForClient($client);
      $form_state->set('company_fetched_addresses', $addresses);
      $latest_id = $this->resolveDefaultCompanyAddressId($client, $addresses);
      $selection = $latest_id !== NULL ? (string) $latest_id : '';
      $form_state->setValue('company_address_selection', $selection);
      $input['company_address_selection'] = $selection;

      $form_state->setUserInput($input);
    } else {
      $this->messenger()->addWarning($this->t('Sentinel Customer ID is not valid.'));
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * Resolve a portal client from a Sentinel Customer ID.
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

    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_client');
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
   * Fetch company addresses based on the client company organization.
   */
  protected function getCompanyAddressesForClient(SentinelClient $client): array {
    $company = trim((string) ($client->get('company')->value ?? ''));
    $addresses = [];
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
    return $addresses;
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
  protected function resolveDefaultCompanyAddressId(?SentinelClient $client, array $address_map): ?int {
    if ($address_map === []) {
      return NULL;
    }
    if ($client instanceof SentinelClient) {
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
  protected function getLatestCompanyAddressIdFromClientSamples(SentinelClient $client): ?int {
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

  /**
   * Sets company address field #default_value from a selected address id.
   */
  protected function applyPortalCompanyAddressFieldDefaults(array &$form, FormStateInterface $form_state, int $selection_id): void {
    $address = $this->resolveCompanyAddressRowById($selection_id, $form_state);
    if (!$address) {
      return;
    }
    $clean = static function ($val) {
      return preg_replace('/[\r\n]+/', ' ', trim((string) $val));
    };
    if (isset($form['company_country'])) {
      $form['company_country']['#default_value'] = $clean($address['country'] ?: 'GB');
    }
    if (isset($form['company_address_1'])) {
      $form['company_address_1']['#default_value'] = $clean($address['address1']);
    }
    if (isset($form['company_property_name'])) {
      $form['company_property_name']['#default_value'] = $clean($address['address2']);
    }
    if (isset($form['company_property_number'])) {
      $form['company_property_number']['#default_value'] = $clean($address['address3']);
    }
    if (isset($form['company_town_city'])) {
      $form['company_town_city']['#default_value'] = $clean($address['locality']);
    }
    if (isset($form['company_postcode'])) {
      $form['company_postcode']['#default_value'] = $clean($address['postcode']);
    }
  }

  /**
   * Resolves company address components for AJAX or form defaults.
   */
  protected function resolveCompanyAddressRowById(int $selection_id, FormStateInterface $form_state): ?array {
    $fetched_addresses = $form_state->get('company_fetched_addresses');
    if (is_array($fetched_addresses) && isset($fetched_addresses[$selection_id])) {
      $row = $fetched_addresses[$selection_id];
      return [
        'country' => $row['country'] ?? '',
        'organization' => $row['organization'] ?? '',
        'address1' => $row['address1'] ?? '',
        'address2' => $row['address2'] ?? '',
        'address3' => $row['address3'] ?? '',
        'locality' => $row['locality'] ?? '',
        'postcode' => $row['postcode'] ?? '',
      ];
    }

    $cids = [];
    $client = $this->getCurrentClient();
    if ($client instanceof SentinelClient) {
      $cids = function_exists('get_more_clients_based_client_cohorts') ? get_more_clients_based_client_cohorts($client) : [];
      $cids[] = $client->id();
    }
    $addresses = function_exists('get_company_addresses_for_cids') ? get_company_addresses_for_cids($cids, $selection_id) : [];
    $db_addr = $addresses ? reset($addresses) : FALSE;
    if (!$db_addr) {
      return NULL;
    }

    return [
      'country' => $db_addr->field_address_country_code ?? '',
      'organization' => $db_addr->field_address_organization ?? '',
      'address1' => $db_addr->field_address_address_line1 ?? '',
      'address2' => $db_addr->field_address_address_line2 ?? '',
      'address3' => $db_addr->field_address_address_line3 ?? '',
      'locality' => $db_addr->field_address_locality ?? '',
      'postcode' => $db_addr->field_address_postal_code ?? '',
    ];
  }

  /**
   * AJAX invoke commands to populate company address fields from a selection.
   *
   * @return \Drupal\Core\Ajax\InvokeCommand[]
   */
  protected function buildCompanyAddressFieldInvokeCommands(int $selection_id, FormStateInterface $form_state): array {
    $address = $this->resolveCompanyAddressRowById($selection_id, $form_state);
    if (!$address) {
      return [];
    }

    $clean = static function ($val) {
      return preg_replace('/[\r\n]+/', ' ', trim((string) $val));
    };

    return [
      new \Drupal\Core\Ajax\InvokeCommand('select[name="company_country"]', 'val', [$clean($address['country'] ?: 'GB')]),
      new \Drupal\Core\Ajax\InvokeCommand('input[name="company_address_1"]', 'val', [$clean($address['address1'])]),
      new \Drupal\Core\Ajax\InvokeCommand('input[name="company_property_name"]', 'val', [$clean($address['address2'])]),
      new \Drupal\Core\Ajax\InvokeCommand('input[name="company_property_number"]', 'val', [$clean($address['address3'])]),
      new \Drupal\Core\Ajax\InvokeCommand('input[name="company_town_city"]', 'val', [$clean($address['locality'])]),
      new \Drupal\Core\Ajax\InvokeCommand('input[name="company_postcode"]', 'val', [$clean($address['postcode'])]),
    ];
  }

}