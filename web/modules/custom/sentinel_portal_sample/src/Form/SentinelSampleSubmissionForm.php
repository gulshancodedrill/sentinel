<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_entities\Entity\SentinelClient;
use Drupal\sentinel_portal_entities\Service\SentinelSampleValidation;
use Drupal\sentinel_portal_sample\Controller\SentinelSampleController;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sample submission form.
 */
class SentinelSampleSubmissionForm extends FormBase {

  /**
   * The entity type manager.
   *
   *354 @var \Drupal\Core\Entity\EntityTypeManagerInterface
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
      '#weight' => -50,
    ];

    // Pack Reference Number
    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The pack reference number'),
      '#description' => $this->t('This pack reference number. This can be found at the top of the main pack/certificate with your pack.'),
      '#required' => TRUE,
      '#weight' => -45,
    ];

    $form['pack_reference_number_confirm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm Pack Reference Number (Enter for validation)'),
      '#weight' => -40,
    ];

    // Company Details Section
    $form['company_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company Details'),
      '#weight' => 10,
    ];

    $form['company_details']['company_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Company Email'),
      '#description' => $this->t('Email address of the company managing installation/maintenance. A copy of the Sentinel Pack report will be made available to this email address.'),
      '#required' => TRUE,
      '#weight' => 1,
    ];

    $form['company_details']['company_telephone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Company Telephone'),
      '#description' => $this->t('Telephone number of the company managing installation/maintenance for this system.'),
      '#weight' => 2,
    ];

    $form['company_details']['sentinel_customer_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sentinel Customer ID'),
      '#description' => $this->t('Your Sentinel (Boiler/Customer) Reference number (SCR). This can be found in your account settings.'),
      '#weight' => 3,
    ];

    // Company Address Section (inside Company Details)
    $form['company_details']['company_address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company Address'),
      '#description' => $this->t('Please provide the name and address of the company managing installation/maintenance on this system.'),
      '#weight' => 4,
    ];

    $company_address_options = ['' => $this->t('Please select')];
    $client = $this->getCurrentClient();
    if ($client instanceof SentinelClient && function_exists('get_company_addresses_for_cids')) {
      $cids = function_exists('get_more_clients_based_client_cohorts') ? get_more_clients_based_client_cohorts($client) : [];
      $cids[] = $client->id();

      $addresses = get_company_addresses_for_cids($cids);
      foreach ($addresses as $address) {
        $parts = array_filter([
          $address->field_address_organization ?? '',
          $address->field_address_address_line1 ?? '',
          $address->field_address_address_line2 ?? '',
          $address->field_address_address_line3 ?? '',
          $address->field_address_locality ?? '',
          $address->field_address_postal_code ?? '',
        ]);
        $label = implode(', ', $parts);
        $company_address_options[$address->entity_id] = $label ?: $this->t('Address @id', ['@id' => $address->entity_id]);
      }
    }

    $form['company_details']['company_address']['company_address_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Select company address'),
      '#options' => $company_address_options,
      '#ajax' => [
        'callback' => '::ajaxSelectCompanyAddress',
        'event' => 'change',
      ],
      '#weight' => 1,
    ];

    $form['company_details']['company_address']['company_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => [
        'AD' => $this->t('Andorra'),
        'AE' => $this->t('United Arab Emirates'),
        'AF' => $this->t('Afghanistan'),
        'AG' => $this->t('Antigua and Barbuda'),
        'AI' => $this->t('Anguilla'),
        'AL' => $this->t('Albania'),
        'AM' => $this->t('Armenia'),
        'AO' => $this->t('Angola'),
        'AQ' => $this->t('Antarctica'),
        'AR' => $this->t('Argentina'),
        'AS' => $this->t('American Samoa'),
        'AT' => $this->t('Austria'),
        'AU' => $this->t('Australia'),
        'AW' => $this->t('Aruba'),
        'AX' => $this->t('Åland Islands'),
        'AZ' => $this->t('Azerbaijan'),
        'BA' => $this->t('Bosnia and Herzegovina'),
        'BB' => $this->t('Barbados'),
        'BD' => $this->t('Bangladesh'),
        'BE' => $this->t('Belgium'),
        'BF' => $this->t('Burkina Faso'),
        'BG' => $this->t('Bulgaria'),
        'BH' => $this->t('Bahrain'),
        'BI' => $this->t('Burundi'),
        'BJ' => $this->t('Benin'),
        'BL' => $this->t('Saint Barthélemy'),
        'BM' => $this->t('Bermuda'),
        'BN' => $this->t('Brunei'),
        'BO' => $this->t('Bolivia'),
        'BQ' => $this->t('Caribbean Netherlands'),
        'BR' => $this->t('Brazil'),
        'BS' => $this->t('Bahamas'),
        'BT' => $this->t('Bhutan'),
        'BV' => $this->t('Bouvet Island'),
        'BW' => $this->t('Botswana'),
        'BY' => $this->t('Belarus'),
        'BZ' => $this->t('Belize'),
        'CA' => $this->t('Canada'),
        'CC' => $this->t('Cocos Islands'),
        'CD' => $this->t('Democratic Republic of the Congo'),
        'CF' => $this->t('Central African Republic'),
        'CG' => $this->t('Republic of the Congo'),
        'CH' => $this->t('Switzerland'),
        'CI' => $this->t('Côte d\'Ivoire'),
        'CK' => $this->t('Cook Islands'),
        'CL' => $this->t('Chile'),
        'CM' => $this->t('Cameroon'),
        'CN' => $this->t('China'),
        'CO' => $this->t('Colombia'),
        'CR' => $this->t('Costa Rica'),
        'CU' => $this->t('Cuba'),
        'CV' => $this->t('Cape Verde'),
        'CW' => $this->t('Curaçao'),
        'CX' => $this->t('Christmas Island'),
        'CY' => $this->t('Cyprus'),
        'CZ' => $this->t('Czech Republic'),
        'DE' => $this->t('Germany'),
        'DJ' => $this->t('Djibouti'),
        'DK' => $this->t('Denmark'),
        'DM' => $this->t('Dominica'),
        'DO' => $this->t('Dominican Republic'),
        'DZ' => $this->t('Algeria'),
        'EC' => $this->t('Ecuador'),
        'EE' => $this->t('Estonia'),
        'EG' => $this->t('Egypt'),
        'EH' => $this->t('Western Sahara'),
        'ER' => $this->t('Eritrea'),
        'ES' => $this->t('Spain'),
        'ET' => $this->t('Ethiopia'),
        'FI' => $this->t('Finland'),
        'FJ' => $this->t('Fiji'),
        'FK' => $this->t('Falkland Islands'),
        'FM' => $this->t('Micronesia'),
        'FO' => $this->t('Faroe Islands'),
        'FR' => $this->t('France'),
        'GA' => $this->t('Gabon'),
        'GB' => $this->t('United Kingdom'),
        'GD' => $this->t('Grenada'),
        'GE' => $this->t('Georgia'),
        'GF' => $this->t('French Guiana'),
        'GG' => $this->t('Guernsey'),
        'GH' => $this->t('Ghana'),
        'GI' => $this->t('Gibraltar'),
        'GL' => $this->t('Greenland'),
        'GM' => $this->t('Gambia'),
        'GN' => $this->t('Guinea'),
        'GP' => $this->t('Guadeloupe'),
        'GQ' => $this->t('Equatorial Guinea'),
        'GR' => $this->t('Greece'),
        'GS' => $this->t('South Georgia and the South Sandwich Islands'),
        'GT' => $this->t('Guatemala'),
        'GU' => $this->t('Guam'),
        'GW' => $this->t('Guinea-Bissau'),
        'GY' => $this->t('Guyana'),
        'HK' => $this->t('Hong Kong'),
        'HM' => $this->t('Heard Island and McDonald Islands'),
        'HN' => $this->t('Honduras'),
        'HR' => $this->t('Croatia'),
        'HT' => $this->t('Haiti'),
        'HU' => $this->t('Hungary'),
        'ID' => $this->t('Indonesia'),
        'IE' => $this->t('Ireland'),
        'IL' => $this->t('Israel'),
        'IM' => $this->t('Isle of Man'),
        'IN' => $this->t('India'),
        'IO' => $this->t('British Indian Ocean Territory'),
        'IQ' => $this->t('Iraq'),
        'IR' => $this->t('Iran'),
        'IS' => $this->t('Iceland'),
        'IT' => $this->t('Italy'),
        'JE' => $this->t('Jersey'),
        'JM' => $this->t('Jamaica'),
        'JO' => $this->t('Jordan'),
        'JP' => $this->t('Japan'),
        'KE' => $this->t('Kenya'),
        'KG' => $this->t('Kyrgyzstan'),
        'KH' => $this->t('Cambodia'),
        'KI' => $this->t('Kiribati'),
        'KM' => $this->t('Comoros'),
        'KN' => $this->t('Saint Kitts and Nevis'),
        'KP' => $this->t('North Korea'),
        'KR' => $this->t('South Korea'),
        'KW' => $this->t('Kuwait'),
        'KY' => $this->t('Cayman Islands'),
        'KZ' => $this->t('Kazakhstan'),
        'LA' => $this->t('Laos'),
        'LB' => $this->t('Lebanon'),
        'LC' => $this->t('Saint Lucia'),
        'LI' => $this->t('Liechtenstein'),
        'LK' => $this->t('Sri Lanka'),
        'LR' => $this->t('Liberia'),
        'LS' => $this->t('Lesotho'),
        'LT' => $this->t('Lithuania'),
        'LU' => $this->t('Luxembourg'),
        'LV' => $this->t('Latvia'),
        'LY' => $this->t('Libya'),
        'MA' => $this->t('Morocco'),
        'MC' => $this->t('Monaco'),
        'MD' => $this->t('Moldova'),
        'ME' => $this->t('Montenegro'),
        'MF' => $this->t('Saint Martin'),
        'MG' => $this->t('Madagascar'),
        'MH' => $this->t('Marshall Islands'),
        'MK' => $this->t('North Macedonia'),
        'ML' => $this->t('Mali'),
        'MM' => $this->t('Myanmar'),
        'MN' => $this->t('Mongolia'),
        'MO' => $this->t('Macao'),
        'MP' => $this->t('Northern Mariana Islands'),
        'MQ' => $this->t('Martinique'),
        'MR' => $this->t('Mauritania'),
        'MS' => $this->t('Montserrat'),
        'MT' => $this->t('Malta'),
        'MU' => $this->t('Mauritius'),
        'MV' => $this->t('Maldives'),
        'MW' => $this->t('Malawi'),
        'MX' => $this->t('Mexico'),
        'MY' => $this->t('Malaysia'),
        'MZ' => $this->t('Mozambique'),
        'NA' => $this->t('Namibia'),
        'NC' => $this->t('New Caledonia'),
        'NE' => $this->t('Niger'),
        'NF' => $this->t('Norfolk Island'),
        'NG' => $this->t('Nigeria'),
        'NI' => $this->t('Nicaragua'),
        'NL' => $this->t('Netherlands'),
        'NO' => $this->t('Norway'),
        'NP' => $this->t('Nepal'),
        'NR' => $this->t('Nauru'),
        'NU' => $this->t('Niue'),
        'NZ' => $this->t('New Zealand'),
        'OM' => $this->t('Oman'),
        'PA' => $this->t('Panama'),
        'PE' => $this->t('Peru'),
        'PF' => $this->t('French Polynesia'),
        'PG' => $this->t('Papua New Guinea'),
        'PH' => $this->t('Philippines'),
        'PK' => $this->t('Pakistan'),
        'PL' => $this->t('Poland'),
        'PM' => $this->t('Saint Pierre and Miquelon'),
        'PN' => $this->t('Pitcairn Islands'),
        'PR' => $this->t('Puerto Rico'),
        'PS' => $this->t('Palestine'),
        'PT' => $this->t('Portugal'),
        'PW' => $this->t('Palau'),
        'PY' => $this->t('Paraguay'),
        'QA' => $this->t('Qatar'),
        'RE' => $this->t('Réunion'),
        'RO' => $this->t('Romania'),
        'RS' => $this->t('Serbia'),
        'RU' => $this->t('Russia'),
        'RW' => $this->t('Rwanda'),
        'SA' => $this->t('Saudi Arabia'),
        'SB' => $this->t('Solomon Islands'),
        'SC' => $this->t('Seychelles'),
        'SD' => $this->t('Sudan'),
        'SE' => $this->t('Sweden'),
        'SG' => $this->t('Singapore'),
        'SH' => $this->t('Saint Helena'),
        'SI' => $this->t('Slovenia'),
        'SJ' => $this->t('Svalbard and Jan Mayen'),
        'SK' => $this->t('Slovakia'),
        'SL' => $this->t('Sierra Leone'),
        'SM' => $this->t('San Marino'),
        'SN' => $this->t('Senegal'),
        'SO' => $this->t('Somalia'),
        'SR' => $this->t('Suriname'),
        'SS' => $this->t('South Sudan'),
        'ST' => $this->t('São Tomé and Príncipe'),
        'SV' => $this->t('El Salvador'),
        'SX' => $this->t('Sint Maarten'),
        'SY' => $this->t('Syria'),
        'SZ' => $this->t('Eswatini'),
        'TC' => $this->t('Turks and Caicos Islands'),
        'TD' => $this->t('Chad'),
        'TF' => $this->t('French Southern Territories'),
        'TG' => $this->t('Togo'),
        'TH' => $this->t('Thailand'),
        'TJ' => $this->t('Tajikistan'),
        'TK' => $this->t('Tokelau'),
        'TL' => $this->t('Timor-Leste'),
        'TM' => $this->t('Turkmenistan'),
        'TN' => $this->t('Tunisia'),
        'TO' => $this->t('Tonga'),
        'TR' => $this->t('Turkey'),
        'TT' => $this->t('Trinidad and Tobago'),
        'TV' => $this->t('Tuvalu'),
        'TW' => $this->t('Taiwan'),
        'TZ' => $this->t('Tanzania'),
        'UA' => $this->t('Ukraine'),
        'UG' => $this->t('Uganda'),
        'UM' => $this->t('United States Minor Outlying Islands'),
        'US' => $this->t('United States'),
        'UY' => $this->t('Uruguay'),
        'UZ' => $this->t('Uzbekistan'),
        'VA' => $this->t('Vatican City'),
        'VC' => $this->t('Saint Vincent and the Grenadines'),
        'VE' => $this->t('Venezuela'),
        'VG' => $this->t('British Virgin Islands'),
        'VI' => $this->t('United States Virgin Islands'),
        'VN' => $this->t('Vietnam'),
        'VU' => $this->t('Vanuatu'),
        'WF' => $this->t('Wallis and Futuna'),
        'WS' => $this->t('Samoa'),
        'YE' => $this->t('Yemen'),
        'YT' => $this->t('Mayotte'),
        'ZA' => $this->t('South Africa'),
        'ZM' => $this->t('Zambia'),
        'ZW' => $this->t('Zimbabwe'),
      ],
      '#default_value' => 'GB',
      '#weight' => 2,
    ];

    $form['company_details']['company_address']['company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company'),
      '#weight' => 3,
    ];

    $form['company_details']['company_address']['company_address_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address 1'),
      '#weight' => 4,
    ];

    $form['company_details']['company_address']['company_property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#weight' => 5,
    ];

    $form['company_details']['company_address']['company_property_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property number'),
      '#weight' => 6,
    ];

    $form['company_details']['company_address']['company_town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town/City'),
      '#weight' => 7,
    ];

    $form['company_details']['company_address']['company_postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#weight' => 8,
    ];

    // Job Details Section
    $form['job_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Job Details'),
      '#weight' => 20,
    ];

    $form['job_details']['installer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Installer Name'),
      '#description' => $this->t('Name of the accredited installer who carried out the work and commissioned the pack.'),
      '#required' => TRUE,
      '#weight' => 1,
    ];

    $form['job_details']['installer_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Installer Email'),
      '#description' => $this->t('Email address of the accredited installer who carried out the work and commissioned the pack.'),
      '#weight' => 2,
    ];

    $form['job_details']['installer_company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Installer Company'),
      '#description' => $this->t('Please provide the name of the company managing installation/maintenance on this system.'),
      '#weight' => 3,
    ];

    $form['job_details']['boiler_manufacturer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Boiler Manufacturer'),
      '#description' => $this->t('Manufacturer of the boiler.'),
      '#required' => TRUE,
      '#weight' => 4,
    ];

    $form['job_details']['system_age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('System Age'),
      '#description' => $this->t('The age of the system in months.'),
      '#weight' => 5,
    ];

    $form['job_details']['boiler_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Boiler Type'),
      '#description' => $this->t('The type of boiler (Gas/Oil, combi, system).'),
      '#weight' => 6,
    ];

    $form['job_details']['project_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project ID'),
      '#description' => $this->t('The project ID. Required for claiming boiler manufacturer support.'),
      '#weight' => 7,
    ];

    $form['job_details']['date_sent'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date Sent'),
      '#description' => $this->t('Date the boiler sample was sent to Sentinel.'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#date_timezone' => date_default_timezone_get(),
      '#date_date_format' => 'd/m/Y',
      '#weight' => 8,
    ];

    $form['job_details']['uprn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('UPRN'),
      '#description' => $this->t('Unique Property Reference Number for the boiler\'s location. Required for claiming contract support with your supplier (wholesale).'),
      '#weight' => 9,
    ];

    $form['job_details']['boiler_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Boiler ID'),
      '#description' => $this->t('Boiler ID number as provided by the boiler manufacturer.'),
      '#weight' => 10,
    ];

    $form['job_details']['date_installed'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date Installed'),
      '#description' => $this->t('Date the boiler was installed.'),
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#date_timezone' => date_default_timezone_get(),
      '#date_date_format' => 'd/m/Y',
      '#required' => TRUE,
      '#weight' => 11,
    ];

    // System Details Section
    $form['system_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('System Details'),
      '#weight' => 30,
    ];

    // Search for LA/HA Section
    $form['system_details']['landlord_selection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search for LA/HA'),
      '#description' => $this->t('Please select Landlord from the system list. If Landlord is not present, please enter in the field below.'),
      '#autocomplete_route_name' => 'sentinel_portal_sample.landlord.autocomplete',
      '#ajax' => [
        'callback' => '::ajaxSelectLandlord',
        'event' => 'change',
      ],
      '#weight' => 1,
    ];

    $form['system_details']['sample_landlord_add'] = [
      '#type' => 'button',
      '#value' => $this->t('Enter landlord manually'),
      '#weight' => 2,
      '#attributes' => [
        'class' => ['sample-landlord-add-button'],
      ],
    ];

    // Landlord field wrapper - hidden by default, shown via JavaScript
    $form['system_details']['landlord_wrapper'] = [
      '#type' => 'container',
      '#weight' => 3,
      '#attributes' => [
        'class' => ['sample-landlord-field'],
      ],
    ];

    $form['system_details']['landlord_wrapper']['landlord'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Landlord'),
      '#description' => $this->t('The full address of where this system is located.'),
      '#weight' => 1,
    ];

    // Address Section (nested inside System Details)
    $form['system_details']['address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address'),
      '#description' => $this->t('Please provide the full address of where this system is located.'),
      '#weight' => 4,
    ];

    $form['system_details']['address']['sample_address_selection'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search for property address'),
      '#description' => $this->t('Please input the property number or street name to find the full property address.'),
      '#autocomplete_route_name' => 'sentinel_portal_sample.sample_address_autocomplete',
      '#ajax' => [
        'callback' => '::ajaxSelectSampleAddress',
        'event' => 'autocompleteclose',
      ],
      '#weight' => 1,
    ];

    $form['system_details']['address']['sample_address_add'] = [
      '#type' => 'button',
      '#value' => $this->t('Enter address manually'),
      '#weight' => 2,
      '#attributes' => [
        'class' => ['sample-address-add-button'],
      ],
    ];

    // Address fields wrapper - hidden by default, shown via JavaScript
    $form['system_details']['address']['address_fields'] = [
      '#type' => 'container',
      '#weight' => 3,
      '#attributes' => [
        'class' => ['sample-address-fields'],
      ],
    ];

    $form['system_details']['address']['address_fields']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => [
        'AD' => $this->t('Andorra'),
        'AE' => $this->t('United Arab Emirates'),
        'AF' => $this->t('Afghanistan'),
        'AG' => $this->t('Antigua and Barbuda'),
        'AI' => $this->t('Anguilla'),
        'AL' => $this->t('Albania'),
        'AM' => $this->t('Armenia'),
        'AO' => $this->t('Angola'),
        'AQ' => $this->t('Antarctica'),
        'AR' => $this->t('Argentina'),
        'AS' => $this->t('American Samoa'),
        'AT' => $this->t('Austria'),
        'AU' => $this->t('Australia'),
        'AW' => $this->t('Aruba'),
        'AX' => $this->t('Åland Islands'),
        'AZ' => $this->t('Azerbaijan'),
        'BA' => $this->t('Bosnia and Herzegovina'),
        'BB' => $this->t('Barbados'),
        'BD' => $this->t('Bangladesh'),
        'BE' => $this->t('Belgium'),
        'BF' => $this->t('Burkina Faso'),
        'BG' => $this->t('Bulgaria'),
        'BH' => $this->t('Bahrain'),
        'BI' => $this->t('Burundi'),
        'BJ' => $this->t('Benin'),
        'BL' => $this->t('Saint Barthélemy'),
        'BM' => $this->t('Bermuda'),
        'BN' => $this->t('Brunei'),
        'BO' => $this->t('Bolivia'),
        'BQ' => $this->t('Caribbean Netherlands'),
        'BR' => $this->t('Brazil'),
        'BS' => $this->t('Bahamas'),
        'BT' => $this->t('Bhutan'),
        'BV' => $this->t('Bouvet Island'),
        'BW' => $this->t('Botswana'),
        'BY' => $this->t('Belarus'),
        'BZ' => $this->t('Belize'),
        'CA' => $this->t('Canada'),
        'CC' => $this->t('Cocos Islands'),
        'CD' => $this->t('Democratic Republic of the Congo'),
        'CF' => $this->t('Central African Republic'),
        'CG' => $this->t('Republic of the Congo'),
        'CH' => $this->t('Switzerland'),
        'CI' => $this->t('Côte d\'Ivoire'),
        'CK' => $this->t('Cook Islands'),
        'CL' => $this->t('Chile'),
        'CM' => $this->t('Cameroon'),
        'CN' => $this->t('China'),
        'CO' => $this->t('Colombia'),
        'CR' => $this->t('Costa Rica'),
        'CU' => $this->t('Cuba'),
        'CV' => $this->t('Cape Verde'),
        'CW' => $this->t('Curaçao'),
        'CX' => $this->t('Christmas Island'),
        'CY' => $this->t('Cyprus'),
        'CZ' => $this->t('Czech Republic'),
        'DE' => $this->t('Germany'),
        'DJ' => $this->t('Djibouti'),
        'DK' => $this->t('Denmark'),
        'DM' => $this->t('Dominica'),
        'DO' => $this->t('Dominican Republic'),
        'DZ' => $this->t('Algeria'),
        'EC' => $this->t('Ecuador'),
        'EE' => $this->t('Estonia'),
        'EG' => $this->t('Egypt'),
        'EH' => $this->t('Western Sahara'),
        'ER' => $this->t('Eritrea'),
        'ES' => $this->t('Spain'),
        'ET' => $this->t('Ethiopia'),
        'FI' => $this->t('Finland'),
        'FJ' => $this->t('Fiji'),
        'FK' => $this->t('Falkland Islands'),
        'FM' => $this->t('Micronesia'),
        'FO' => $this->t('Faroe Islands'),
        'FR' => $this->t('France'),
        'GA' => $this->t('Gabon'),
        'GB' => $this->t('United Kingdom'),
        'GD' => $this->t('Grenada'),
        'GE' => $this->t('Georgia'),
        'GF' => $this->t('French Guiana'),
        'GG' => $this->t('Guernsey'),
        'GH' => $this->t('Ghana'),
        'GI' => $this->t('Gibraltar'),
        'GL' => $this->t('Greenland'),
        'GM' => $this->t('Gambia'),
        'GN' => $this->t('Guinea'),
        'GP' => $this->t('Guadeloupe'),
        'GQ' => $this->t('Equatorial Guinea'),
        'GR' => $this->t('Greece'),
        'GS' => $this->t('South Georgia and the South Sandwich Islands'),
        'GT' => $this->t('Guatemala'),
        'GU' => $this->t('Guam'),
        'GW' => $this->t('Guinea-Bissau'),
        'GY' => $this->t('Guyana'),
        'HK' => $this->t('Hong Kong'),
        'HM' => $this->t('Heard Island and McDonald Islands'),
        'HN' => $this->t('Honduras'),
        'HR' => $this->t('Croatia'),
        'HT' => $this->t('Haiti'),
        'HU' => $this->t('Hungary'),
        'ID' => $this->t('Indonesia'),
        'IE' => $this->t('Ireland'),
        'IL' => $this->t('Israel'),
        'IM' => $this->t('Isle of Man'),
        'IN' => $this->t('India'),
        'IO' => $this->t('British Indian Ocean Territory'),
        'IQ' => $this->t('Iraq'),
        'IR' => $this->t('Iran'),
        'IS' => $this->t('Iceland'),
        'IT' => $this->t('Italy'),
        'JE' => $this->t('Jersey'),
        'JM' => $this->t('Jamaica'),
        'JO' => $this->t('Jordan'),
        'JP' => $this->t('Japan'),
        'KE' => $this->t('Kenya'),
        'KG' => $this->t('Kyrgyzstan'),
        'KH' => $this->t('Cambodia'),
        'KI' => $this->t('Kiribati'),
        'KM' => $this->t('Comoros'),
        'KN' => $this->t('Saint Kitts and Nevis'),
        'KP' => $this->t('North Korea'),
        'KR' => $this->t('South Korea'),
        'KW' => $this->t('Kuwait'),
        'KY' => $this->t('Cayman Islands'),
        'KZ' => $this->t('Kazakhstan'),
        'LA' => $this->t('Laos'),
        'LB' => $this->t('Lebanon'),
        'LC' => $this->t('Saint Lucia'),
        'LI' => $this->t('Liechtenstein'),
        'LK' => $this->t('Sri Lanka'),
        'LR' => $this->t('Liberia'),
        'LS' => $this->t('Lesotho'),
        'LT' => $this->t('Lithuania'),
        'LU' => $this->t('Luxembourg'),
        'LV' => $this->t('Latvia'),
        'LY' => $this->t('Libya'),
        'MA' => $this->t('Morocco'),
        'MC' => $this->t('Monaco'),
        'MD' => $this->t('Moldova'),
        'ME' => $this->t('Montenegro'),
        'MF' => $this->t('Saint Martin'),
        'MG' => $this->t('Madagascar'),
        'MH' => $this->t('Marshall Islands'),
        'MK' => $this->t('North Macedonia'),
        'ML' => $this->t('Mali'),
        'MM' => $this->t('Myanmar'),
        'MN' => $this->t('Mongolia'),
        'MO' => $this->t('Macao'),
        'MP' => $this->t('Northern Mariana Islands'),
        'MQ' => $this->t('Martinique'),
        'MR' => $this->t('Mauritania'),
        'MS' => $this->t('Montserrat'),
        'MT' => $this->t('Malta'),
        'MU' => $this->t('Mauritius'),
        'MV' => $this->t('Maldives'),
        'MW' => $this->t('Malawi'),
        'MX' => $this->t('Mexico'),
        'MY' => $this->t('Malaysia'),
        'MZ' => $this->t('Mozambique'),
        'NA' => $this->t('Namibia'),
        'NC' => $this->t('New Caledonia'),
        'NE' => $this->t('Niger'),
        'NF' => $this->t('Norfolk Island'),
        'NG' => $this->t('Nigeria'),
        'NI' => $this->t('Nicaragua'),
        'NL' => $this->t('Netherlands'),
        'NO' => $this->t('Norway'),
        'NP' => $this->t('Nepal'),
        'NR' => $this->t('Nauru'),
        'NU' => $this->t('Niue'),
        'NZ' => $this->t('New Zealand'),
        'OM' => $this->t('Oman'),
        'PA' => $this->t('Panama'),
        'PE' => $this->t('Peru'),
        'PF' => $this->t('French Polynesia'),
        'PG' => $this->t('Papua New Guinea'),
        'PH' => $this->t('Philippines'),
        'PK' => $this->t('Pakistan'),
        'PL' => $this->t('Poland'),
        'PM' => $this->t('Saint Pierre and Miquelon'),
        'PN' => $this->t('Pitcairn Islands'),
        'PR' => $this->t('Puerto Rico'),
        'PS' => $this->t('Palestine'),
        'PT' => $this->t('Portugal'),
        'PW' => $this->t('Palau'),
        'PY' => $this->t('Paraguay'),
        'QA' => $this->t('Qatar'),
        'RE' => $this->t('Réunion'),
        'RO' => $this->t('Romania'),
        'RS' => $this->t('Serbia'),
        'RU' => $this->t('Russia'),
        'RW' => $this->t('Rwanda'),
        'SA' => $this->t('Saudi Arabia'),
        'SB' => $this->t('Solomon Islands'),
        'SC' => $this->t('Seychelles'),
        'SD' => $this->t('Sudan'),
        'SE' => $this->t('Sweden'),
        'SG' => $this->t('Singapore'),
        'SH' => $this->t('Saint Helena'),
        'SI' => $this->t('Slovenia'),
        'SJ' => $this->t('Svalbard and Jan Mayen'),
        'SK' => $this->t('Slovakia'),
        'SL' => $this->t('Sierra Leone'),
        'SM' => $this->t('San Marino'),
        'SN' => $this->t('Senegal'),
        'SO' => $this->t('Somalia'),
        'SR' => $this->t('Suriname'),
        'SS' => $this->t('South Sudan'),
        'ST' => $this->t('São Tomé and Príncipe'),
        'SV' => $this->t('El Salvador'),
        'SX' => $this->t('Sint Maarten'),
        'SY' => $this->t('Syria'),
        'SZ' => $this->t('Eswatini'),
        'TC' => $this->t('Turks and Caicos Islands'),
        'TD' => $this->t('Chad'),
        'TF' => $this->t('French Southern Territories'),
        'TG' => $this->t('Togo'),
        'TH' => $this->t('Thailand'),
        'TJ' => $this->t('Tajikistan'),
        'TK' => $this->t('Tokelau'),
        'TL' => $this->t('Timor-Leste'),
        'TM' => $this->t('Turkmenistan'),
        'TN' => $this->t('Tunisia'),
        'TO' => $this->t('Tonga'),
        'TR' => $this->t('Turkey'),
        'TT' => $this->t('Trinidad and Tobago'),
        'TV' => $this->t('Tuvalu'),
        'TW' => $this->t('Taiwan'),
        'TZ' => $this->t('Tanzania'),
        'UA' => $this->t('Ukraine'),
        'UG' => $this->t('Uganda'),
        'UM' => $this->t('United States Minor Outlying Islands'),
        'US' => $this->t('United States'),
        'UY' => $this->t('Uruguay'),
        'UZ' => $this->t('Uzbekistan'),
        'VA' => $this->t('Vatican City'),
        'VC' => $this->t('Saint Vincent and the Grenadines'),
        'VE' => $this->t('Venezuela'),
        'VG' => $this->t('British Virgin Islands'),
        'VI' => $this->t('United States Virgin Islands'),
        'VN' => $this->t('Vietnam'),
        'VU' => $this->t('Vanuatu'),
        'WF' => $this->t('Wallis and Futuna'),
        'WS' => $this->t('Samoa'),
        'YE' => $this->t('Yemen'),
        'YT' => $this->t('Mayotte'),
        'ZA' => $this->t('South Africa'),
        'ZM' => $this->t('Zambia'),
        'ZW' => $this->t('Zimbabwe'),
      ],
      '#default_value' => 'GB',
      '#weight' => 1,
    ];

    $form['system_details']['address']['address_fields']['address_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address 1'),
      '#weight' => 2,
    ];

    $form['system_details']['address']['address_fields']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#weight' => 3,
    ];

    $form['system_details']['address']['address_fields']['property_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property number'),
      '#weight' => 4,
    ];

    $form['system_details']['address']['address_fields']['town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town/City'),
      '#weight' => 5,
    ];

    $form['system_details']['address']['address_fields']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#weight' => 6,
    ];

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
    return $this->getSampleController()->selectCompanyAddress($form, $form_state);
  }

  /**
   * AJAX callback when a sample address is selected from autocomplete.
   */
  public function ajaxSelectSampleAddress(array &$form, FormStateInterface $form_state) {
    return $this->getSampleController()->selectSampleAddress($form, $form_state);
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
    
    // Map company address fields to validation format
    if (isset($form_values['company_address']) && is_array($form_values['company_address'])) {
      if (isset($form_values['company_address']['company_address_1'])) {
        $validation_data['company_address1'] = trim($form_values['company_address']['company_address_1']);
      }
      if (isset($form_values['company_address']['company_property_name'])) {
        $validation_data['company_address2'] = trim($form_values['company_address']['company_property_name']);
      }
      if (isset($form_values['company_address']['company_town_city'])) {
        $validation_data['company_town'] = trim($form_values['company_address']['company_town_city']);
      }
      if (isset($form_values['company_address']['company_postcode'])) {
        $validation_data['company_postcode'] = trim($form_values['company_address']['company_postcode']);
      }
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
    
    // Map system address fields from nested structure
    if (isset($form_values['system_details']['address']['address_fields']) && is_array($form_values['system_details']['address']['address_fields'])) {
      $address_fields = $form_values['system_details']['address']['address_fields'];

      if (isset($address_fields['property_number'])) {
        $validation_data['property_number'] = trim($address_fields['property_number']);
      }
      if (isset($address_fields['address_1'])) {
        $validation_data['address_1'] = trim($address_fields['address_1']);
        $validation_data['street'] = trim($address_fields['address_1']);
      }
      if (isset($address_fields['property_name'])) {
        $validation_data['property_name'] = trim($address_fields['property_name']);
      }
      if (isset($address_fields['town_city'])) {
        $validation_data['town_city'] = trim($address_fields['town_city']);
      }
      if (isset($address_fields['county'])) {
        $validation_data['county'] = trim($address_fields['county']);
      }
      if (isset($address_fields['postcode'])) {
        $validation_data['postcode'] = trim($address_fields['postcode']);
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
    if (isset($values['system_6_months'])) {
      $values['system_6_months'] = $values['system_6_months']; // already at top-level
    }
    // address_fields inside system_details > address > address_fields
    if (
      isset($values['address']) &&
      isset($values['address']['address_fields']) && 
      is_array($values['address']['address_fields'])
    ) {
      $addr = $values['address']['address_fields'];
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
        'company_address1' => 'company_property_name',
        'company_address2' => 'company_address_1',  // Use company_property_name for address2
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

  /**
   * Ensure company and sample address entities exist and are referenced.
   */
  protected function ensureAddressEntities(ContentEntityInterface $sample, array $values, array $original_values, array $form): void {
    \Drupal::logger('sentinel_portal_sample')->debug('ensureAddressEntities invoked.');

    $address_storage = \Drupal::entityTypeManager()->getStorage('address');

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

    if (!$company_target_id) {
      $company_address_data = $this->buildCompanyAddressFieldValues($values, $original_values);
      \Drupal::logger('sentinel_portal_sample')->info('Company address data: <pre>@data</pre>', ['@data' => print_r($company_address_data, TRUE)]);
      if (!empty($company_address_data)) {
        $company_entity = $address_storage->create([
          'type' => 'company_address',
          'field_address' => $company_address_data,
        ]);
        $company_entity->save();
        $company_target_id = (int) $company_entity->id();
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

    $sample_selection = $values['sample_address_selection']
      ?? $this->getArrayPathValue($values, ['system_details', 'address', 'sample_address_selection'])
      ?? $this->getArrayPathValue($original_values, ['system_details', 'address', 'sample_address_selection'])
      ?? NULL;
    $sample_target_id = $this->parseAddressSelection($sample_selection);

    if ($sample_target_id) {
      $sample_entity = $address_storage->load($sample_target_id);
      if (!$sample_entity) {
        $sample_target_id = NULL;
      }
    }

    if (!$sample_target_id) {
      $sample_address_data = $this->buildSampleAddressFieldValues($values, $original_values);
      \Drupal::logger('sentinel_portal_sample')->info('Sample address data: <pre>@data</pre>', ['@data' => print_r($sample_address_data, TRUE)]);
      if (!empty($sample_address_data)) {
        $sample_entity = $address_storage->create([
          'type' => 'address',
          'field_address' => $sample_address_data,
        ]);
        $sample_entity->save();
        $sample_target_id = (int) $sample_entity->id();
      }
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
   * Parse an address selection value (dropdown or autocomplete) to an ID.
   */
  protected function parseAddressSelection($selection): ?int {
    if (empty($selection)) {
      return NULL;
    }
    if (is_numeric($selection)) {
      return (int) $selection;
    }
    if (is_string($selection) && preg_match('/^\((\d+)\)/', trim($selection), $matches)) {
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

    $country = strtoupper(trim((string) ($company_section['company_country'] ?? '')));
    if ($country === '') {
      $country = 'GB';
    }

    $address_line1 = trim((string) ($company_section['company_address_1'] ?? ''));
    $address_line2_parts = array_filter([
      trim((string) ($company_section['company_property_number'] ?? '')),
      trim((string) ($company_section['company_property_name'] ?? '')),
      trim((string) ($company_section['company_address_2'] ?? '')),
    ]);
    $address_line2 = trim(implode(' ', $address_line2_parts));
    $locality = trim((string) ($company_section['company_town_city'] ?? ''));
    $postal_code = trim((string) ($company_section['company_postcode'] ?? ''));
    $administrative_area = trim((string) ($company_section['company_county'] ?? ''));
    $organization = trim((string) ($values['company'] ?? ($company_section['company'] ?? '')));

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
   * Build address field values for the sample address bundle.
   */
  protected function buildSampleAddressFieldValues(array $values, array $original_values = []): array {
    $address_section = $values['address']['address_fields']
      ?? $this->getArrayPathValue($values, ['system_details', 'address', 'address_fields'])
      ?? $this->getArrayPathValue($original_values, ['system_details', 'address', 'address_fields'])
      ?? [];

    $country = strtoupper(trim((string) ($address_section['country'] ?? '')));
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

}