<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Sample submission form.
 */
class SentinelSampleSubmissionForm extends FormBase {

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

    $form['company_details']['company_address']['address_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Select company address'),
      '#options' => ['' => $this->t('Please select')],
      '#weight' => 1,
    ];

    $form['company_details']['company_address']['country'] = [
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

    $form['company_details']['company_address']['address_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address 1'),
      '#weight' => 4,
    ];

    $form['company_details']['company_address']['property_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#weight' => 5,
    ];

    $form['company_details']['company_address']['property_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property number'),
      '#weight' => 6,
    ];

    $form['company_details']['company_address']['town_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town/City'),
      '#weight' => 7,
    ];

    $form['company_details']['company_address']['postcode'] = [
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
      '#type' => 'textfield',
      '#title' => $this->t('Date Sent'),
      '#placeholder' => $this->t('e.g. 28.11.2020'),
      '#description' => $this->t('Date the boiler sample was sent to Sentinel.'),
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
      '#type' => 'textfield',
      '#title' => $this->t('Date Installed'),
      '#placeholder' => $this->t('e.g. 28.11.2020'),
      '#description' => $this->t('Date the boiler was installed.'),
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
      '#autocomplete_route_name' => 'sentinel_portal_sample.landlord.autocomplete',
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $pack_ref = $form_state->getValue('pack_reference_number');
    $pack_ref_confirm = $form_state->getValue('pack_reference_number_confirm');
    
    // Validate pack reference number confirmation
    if (!empty($pack_ref_confirm) && $pack_ref !== $pack_ref_confirm) {
      $form_state->setErrorByName('pack_reference_number_confirm', $this->t('Pack reference numbers do not match.'));
    }
    
    // Validate email format
    $company_email = $form_state->getValue(['company_details', 'company_email']);
    if (!empty($company_email) && !filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName(['company_details', 'company_email'], $this->t('Please enter a valid email address.'));
    }
    
    $installer_email = $form_state->getValue(['job_details', 'installer_email']);
    if (!empty($installer_email) && !filter_var($installer_email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName(['job_details', 'installer_email'], $this->t('Please enter a valid email address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    // Log the form submission for now
    \Drupal::logger('sentinel_portal_sample')->info('Sample submission form submitted with values: @values', [
      '@values' => print_r($values, TRUE)
    ]);
    
    $this->messenger()->addMessage($this->t('Sample submitted successfully. Pack reference: @pack_ref', [
      '@pack_ref' => $values['pack_reference_number']
    ]));
    
    // TODO: Save to database/entity when entities are ready
    // For now, just redirect to a success page or back to portal
    $form_state->setRedirect('sentinel_portal.portal');
  }

}
