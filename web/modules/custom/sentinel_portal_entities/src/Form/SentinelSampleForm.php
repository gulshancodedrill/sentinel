<?php

namespace Drupal\sentinel_portal_entities\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Form controller for Sentinel Sample edit forms.
 */
class SentinelSampleForm extends ContentEntityForm {

  /**
   * Get country options for select field.
   *
   * @return array
   *   Array of country code => country name.
   */
  protected function getCountryOptions() {
    return [
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    // Remove date fields from form display processing to prevent widget extraction errors
    $date_fields = ['created', 'updated', 'date_sent', 'date_installed', 'date_booked', 'date_processed', 'date_reported'];
    $form_display = $this->getFormDisplay($form_state);
    if ($form_display) {
      foreach ($date_fields as $field_name) {
        if ($form_display->getComponent($field_name)) {
          $form_display->removeComponent($field_name);
        }
      }
      $this->setFormDisplay($form_display, $form_state);
    }

    // Remove date fields from form if they exist as separate fieldsets
    // These are now handled directly in their respective fieldsets
    // date_sent and date_installed in job_details fieldset
    // date_booked, date_processed, date_reported in result_details fieldset
    // Also check for widget structures that might contain these fields
    $fields_to_remove = ['date_sent', 'date_installed', 'date_booked', 'date_processed', 'date_reported'];
    foreach ($fields_to_remove as $field_name) {
      if (isset($form[$field_name])) {
        unset($form[$field_name]);
      }
      // Also check in widget structure if it exists
      if (isset($form[$field_name . '_widget'])) {
        unset($form[$field_name . '_widget']);
      }
      // Check for nested widget structures
      if (isset($form[$field_name]['widget'])) {
        unset($form[$field_name]);
      }
    }


    // Pack ID (vid) - The sample revision
    if ($entity->hasField('vid')) {
      $form['vid'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Pack ID'),
        '#default_value' => $entity->get('vid')->value ?: '',
        '#description' => $this->t('The sample revision'),
        '#weight' => -100,
        '#disabled' => TRUE,
      ];
    }

    // The pack reference number
    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The pack reference number'),
      '#default_value' => $entity->get('pack_reference_number')->value ?: '',
      '#maxlength' => 30,
      '#required' => TRUE,
      '#weight' => -99,
      '#description' => $this->t('The pack reference number. This can be found at the top of the insert provided with your pack.'),
    ];

    // Get Company Email value
    $email_value = '';
    if ($entity->hasField('company_email') && !$entity->get('company_email')->isEmpty()) {
      $email_value = $entity->get('company_email')->value ?? '';
    }

    // Fieldset: Company Details (start)
    $form['company_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company Details'),
      '#weight' => -98,
      '#after_build' => [[$this, 'reorderCompanyDetailsFields']],
      // Add Company Email directly to the fieldset
      'company_email' => [
        '#type' => 'email',
        '#title' => $this->t('Company Email'),
        '#default_value' => $email_value,
        '#maxlength' => 200,
        '#required' => FALSE,
        '#weight' => 1,
        '#description' => $this->t('Email address of the company managing installation/maintenance. A copy of the SystemCheck report will be made available to this email address.'),
        '#access' => $entity->hasField('company_email'),
      ],
    ];

    // Company Telephone - weight 2 (second)
    if ($entity->hasField('company_tel')) {
      if (!isset($form['company_tel'])) {
        $tel_value = '';
        if (!$entity->get('company_tel')->isEmpty()) {
          $tel_value = $entity->get('company_tel')->value ?? '';
        }
        $form['company_tel'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Company Telephone'),
          '#default_value' => $tel_value,
          '#maxlength' => 200,
          '#required' => FALSE,
        ];
      }
      $form['company_tel']['#group'] = 'company_details';
      $form['company_tel']['#weight'] = 2;
      $form['company_tel']['#title'] = $this->t('Company Telephone');
      $form['company_tel']['#description'] = $this->t('Telephone number of the company managing installation/maintenance for the system.');
      $form['company_tel']['#access'] = TRUE;
      unset($form['company_tel']['#access_callback']);
    }

    // Sentinel Customer ID - weight 3 (third)
    if ($entity->hasField('customer_id')) {
      if (!isset($form['customer_id'])) {
        $customer_id_value = '';
        if (!$entity->get('customer_id')->isEmpty()) {
          $customer_id_value = $entity->get('customer_id')->value ?? '';
        }
        $form['customer_id'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Sentinel Customer ID'),
          '#default_value' => $customer_id_value,
          '#maxlength' => 200,
          '#required' => FALSE,
        ];
      }
      $form['customer_id']['#group'] = 'company_details';
      $form['customer_id']['#weight'] = 3;
      $form['customer_id']['#title'] = $this->t('Sentinel Customer ID');
      $form['customer_id']['#description'] = $this->t('Your Sentinel Unique Customer Reference number (UCR). This can be found in your account settings.');
      $form['customer_id']['#access'] = TRUE;
      unset($form['customer_id']['#access_callback']);
    }

    // Country - Get from entity reference field or database
    $company_country_value = 'GB'; // Default to GB
    if ($entity->hasField('field_company_address') && !$entity->get('field_company_address')->isEmpty()) {
      $address_entity = $entity->get('field_company_address')->entity;
      if ($address_entity && $address_entity->hasField('field_address')) {
        $address_item = $address_entity->get('field_address')->first();
        if ($address_item && !empty($address_item->country_code)) {
          $company_country_value = $address_item->country_code;
        }
      }
    }
    // If not found, try database
    if ($company_country_value === 'GB' && !$entity->isNew()) {
      $database = \Drupal::database();
      $pid = $entity->id();
      try {
        // Try to get from address entity via field_company_address
        $result = $database->select('sentinel_sample__field_company_address', 'sca')
          ->fields('sca', ['field_company_address_target_id'])
          ->condition('sca.entity_id', $pid)
          ->execute()
          ->fetchField();
        if ($result) {
          $address_result = $database->select('address__field_address', 'a')
            ->fields('a', ['field_address_country_code'])
            ->condition('a.entity_id', $result)
            ->execute()
            ->fetchField();
          if ($address_result) {
            $company_country_value = $address_result;
          }
        }
      } catch (\Exception $e) {
        // Database query failed, use default
      }
    }

    // Fieldset: Company Address (nested in Company Details)
    $form['company_address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company Address'),
      '#description' => $this->t('Please provide the name and address of the company managing installation/maintenance on this system.'),
      '#weight' => 4,
      '#group' => 'company_details',
      '#after_build' => [[$this, 'reorderCompanyAddressFields']],
      // Add country field directly to the fieldset
      'company_country' => [
        '#type' => 'select',
        '#title' => $this->t('Company Address Country'),
        '#options' => $this->getCountryOptions(),
        '#default_value' => $company_country_value,
        '#weight' => 0,
        '#required' => FALSE,
      ],
    ];

    // Create direct input fields for company address based on entity definition
    // Company Name
    if ($entity->hasField('company_name')) {
      $company_name_value = '';
      if (!$entity->get('company_name')->isEmpty()) {
        $company_name_value = $entity->get('company_name')->value ?? '';
      }
      $form['company_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Company'),
        '#default_value' => $company_name_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#group' => 'company_address',
        '#weight' => 2,
      ];
    }

    // Company Address 1
    if ($entity->hasField('company_address1')) {
      $address1_value = '';
      if (!$entity->get('company_address1')->isEmpty()) {
        $address1_value = $entity->get('company_address1')->value ?? '';
      }
      $form['company_address1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Address 1'),
        '#default_value' => $address1_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#group' => 'company_address',
        '#weight' => 3,
      ];
    }

    // Company Address 2 (Property name)
    if ($entity->hasField('company_address2')) {
      $address2_value = '';
      if (!$entity->get('company_address2')->isEmpty()) {
        $address2_value = $entity->get('company_address2')->value ?? '';
      }
      $form['company_address2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Property name'),
        '#default_value' => $address2_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#group' => 'company_address',
        '#weight' => 4,
      ];
    }

    // Company Town/City
    if ($entity->hasField('company_town')) {
      $town_value = '';
      if (!$entity->get('company_town')->isEmpty()) {
        $town_value = $entity->get('company_town')->value ?? '';
      }
      $form['company_town'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Town/City'),
        '#default_value' => $town_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#group' => 'company_address',
        '#weight' => 5,
      ];
    }

    // Company County
    if ($entity->hasField('company_county')) {
      $county_value = '';
      if (!$entity->get('company_county')->isEmpty()) {
        $county_value = $entity->get('company_county')->value ?? '';
      }
      $form['company_county'] = [
        '#type' => 'textfield',
        '#title' => $this->t('County'),
        '#default_value' => $county_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#group' => 'company_address',
        '#weight' => 6,
      ];
    }

    // Company Postcode
    if ($entity->hasField('company_postcode')) {
      $postcode_value = '';
      if (!$entity->get('company_postcode')->isEmpty()) {
        $postcode_value = $entity->get('company_postcode')->value ?? '';
      }
      $form['company_postcode'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Postcode'),
        '#default_value' => $postcode_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#group' => 'company_address',
        '#weight' => 7,
      ];
    }

    // Also handle entity reference field if it exists (for backward compatibility)
    if ($entity->hasField('field_company_address') && isset($form['field_company_address'])) {
      $form['field_company_address']['#group'] = 'company_address';
      $form['field_company_address']['#weight'] = 7;
      $form['field_company_address']['#access'] = TRUE;
      unset($form['field_company_address']['#access_callback']);
    }

    // Created field
    if ($entity->hasField('created')) {
      // Remove existing field structure completely
      unset($form['created']);
      
      // Get value from entity or database
      $date_value = NULL;
      if (!$entity->get('created')->isEmpty()) {
        $date_value = $entity->get('created')->value;
      }
      if (!$date_value && !$entity->isNew()) {
        $date_value = $this->getDateValueFromDatabase('created', $entity->id());
      }
      $formatted_date = $this->formatDateValue($date_value);

      // Create simple date input
      $form['created'] = [
        '#type' => 'date',
        '#title' => $this->t('Created'),
        '#description' => $this->t('E.g., 16-11-2025 When this record was created.'),
        '#default_value' => $formatted_date,
        '#weight' => -97,
        '#disabled' => TRUE,
      ];
    }

    // Updated field
    if ($entity->hasField('updated')) {
      // Remove existing field structure completely
      unset($form['updated']);
      
      // Get value from entity or database
      $date_value = NULL;
      if (!$entity->get('updated')->isEmpty()) {
        $date_value = $entity->get('updated')->value;
      }
      if (!$date_value && !$entity->isNew()) {
        $date_value = $this->getDateValueFromDatabase('updated', $entity->id());
      }
      $formatted_date = $this->formatDateValue($date_value);

      // Create simple date input
      $form['updated'] = [
        '#type' => 'date',
        '#title' => $this->t('Updated'),
        '#description' => $this->t('E.g., 16-11-2025 When this record was last updated.'),
        '#default_value' => $formatted_date,
        '#weight' => -96,
        '#disabled' => TRUE,
      ];
    }

    // The UCR - directly placed between updated and sample hold state
    if ($entity->hasField('ucr')) {
      $form['ucr']['#title'] = $this->t('The UCR');
      $form['ucr']['#description'] = $this->t('The unique customer record.');
    }

    // Sample hold state
    $form['sentinel_sample_hold_state_target_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Sample hold state'),
      '#options' => $this->getHoldStateOptions(),
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $entity->get('sentinel_sample_hold_state_target_id')->value ?: NULL,
      '#weight' => -94,
    ];

    // Hide Old Pack Reference Number field (not pack_reference_number)
    // Use #access => FALSE instead of unset to avoid widget errors
    $old_pack_field_names = [
      'old_pack_reference_number',
      'field_old_pack_reference_number',
      'old_pack_reference',
      'field_old_pack_reference',
    ];
    foreach ($old_pack_field_names as $field_name) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = FALSE;
      }
    }

    // Get values for fields that will be added directly to job_details fieldset
    // Installer Email value
    $installer_email_value = '';
    if ($entity->hasField('installer_email') && !$entity->get('installer_email')->isEmpty()) {
      $installer_email_value = $entity->get('installer_email')->value ?? '';
    }
    
    // Date Sent value
    $date_sent_value = NULL;
    $date_sent_formatted = NULL;
    if ($entity->hasField('date_sent')) {
      if (!$entity->get('date_sent')->isEmpty()) {
        $date_sent_value = $entity->get('date_sent')->value;
      }
      if (!$date_sent_value && !$entity->isNew()) {
        $date_sent_value = $this->getDateValueFromDatabase('date_sent', $entity->id());
      }
      $date_sent_formatted = $this->formatDateValue($date_sent_value);
    }
    
    // Date Installed value
    $date_installed_value = NULL;
    $date_installed_formatted = NULL;
    if ($entity->hasField('date_installed')) {
      if (!$entity->get('date_installed')->isEmpty()) {
        $date_installed_value = $entity->get('date_installed')->value;
      }
      if (!$date_installed_value && !$entity->isNew()) {
        $date_installed_value = $this->getDateValueFromDatabase('date_installed', $entity->id());
      }
      $date_installed_formatted = $this->formatDateValue($date_installed_value);
    }

    // Fieldset: Job Details (start)
    $form['job_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Job Details'),
      '#weight' => -90,
      // Add Installer Email directly to the fieldset
      'installer_email' => [
        '#type' => 'email',
        '#title' => $this->t('Installer Email'),
        '#default_value' => $installer_email_value,
        '#maxlength' => 255,
        '#required' => FALSE,
        '#weight' => 5,
        '#description' => $this->t('The email address of the installer who conducted the work and subsequent SystemCheck.'),
        '#access' => $entity->hasField('installer_email'),
      ],
      // Add Date Sent directly to the fieldset
      'date_sent' => [
        '#type' => 'date',
        '#title' => $this->t('Date Sent'),
        '#description' => $this->t('E.g., 16-11-2025 Date that the water sample was sent to Sentinel.'),
        '#default_value' => $date_sent_formatted,
        '#weight' => 11,
        '#access' => $entity->hasField('date_sent'),
      ],
      // Add Date Installed directly to the fieldset
      'date_installed' => [
        '#type' => 'date',
        '#title' => $this->t('Date Installed'),
        '#description' => $this->t('E.g., 16-11-2025 Date that the boiler was installed.'),
        '#default_value' => $date_installed_formatted,
        '#weight' => 14,
        '#access' => $entity->hasField('date_installed'),
      ],
    ];

    // System > 6 Months Old?
    if ($entity->hasField('system_6_months')) {
      $form['system_6_months']['#group'] = 'job_details';
      $form['system_6_months']['#weight'] = 1;
      $form['system_6_months']['#title'] = $this->t('System > 6 Months Old?');
      $form['system_6_months']['#description'] = $this->t('Is the system older than 6 months?');
    }

    // Engineers Code
    if ($entity->hasField('engineers_code')) {
      $form['engineers_code']['#group'] = 'job_details';
      $form['engineers_code']['#weight'] = 2;
      $form['engineers_code']['#title'] = $this->t('Engineers Code');
      $form['engineers_code']['#description'] = $this->t('The engineers code of the boiler.');
    }

    // Service Call ID
    if ($entity->hasField('service_call_id')) {
      $form['service_call_id']['#group'] = 'job_details';
      $form['service_call_id']['#weight'] = 3;
      $form['service_call_id']['#title'] = $this->t('Service Call ID');
      $form['service_call_id']['#description'] = $this->t('The service call ID of the boiler.');
    }

    // Installer Name
    if ($entity->hasField('installer_name')) {
      if (!isset($form['installer_name'])) {
        $installer_name_value = '';
        if (!$entity->get('installer_name')->isEmpty()) {
          $installer_name_value = $entity->get('installer_name')->value ?? '';
        }
        $form['installer_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Installer Name'),
          '#default_value' => $installer_name_value,
          '#maxlength' => 255,
          '#required' => FALSE,
        ];
      }
      $form['installer_name']['#group'] = 'job_details';
      $form['installer_name']['#weight'] = 4;
      $form['installer_name']['#title'] = $this->t('Installer Name');
      $form['installer_name']['#description'] = $this->t('Name of the individual engineer who conducted the work and subsequent SystemCheck.');
      $form['installer_name']['#access'] = TRUE;
      unset($form['installer_name']['#access_callback']);
    }


    // Installer Company
    if ($entity->hasField('installer_company')) {
      if (!isset($form['installer_company'])) {
        $installer_company_value = '';
        if (!$entity->get('installer_company')->isEmpty()) {
          $installer_company_value = $entity->get('installer_company')->value ?? '';
        }
        $form['installer_company'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Installer Company'),
          '#default_value' => $installer_company_value,
          '#maxlength' => 255,
          '#required' => FALSE,
        ];
      }
      $form['installer_company']['#group'] = 'job_details';
      $form['installer_company']['#weight'] = 6;
      $form['installer_company']['#title'] = $this->t('Installer Company');
      $form['installer_company']['#description'] = $this->t('Please provide the name of the company managing installation/maintenance on this system.');
      $form['installer_company']['#access'] = TRUE;
      unset($form['installer_company']['#access_callback']);
    }

    // Boiler Manufacturer
    if ($entity->hasField('boiler_manufacturer')) {
      $form['boiler_manufacturer']['#group'] = 'job_details';
      $form['boiler_manufacturer']['#weight'] = 7;
      $form['boiler_manufacturer']['#title'] = $this->t('Boiler Manufacturer');
      $form['boiler_manufacturer']['#description'] = $this->t('Manufacturer of the boiler.');
    }

    // System Age
    if ($entity->hasField('system_age')) {
      $form['system_age']['#group'] = 'job_details';
      $form['system_age']['#weight'] = 8;
      $form['system_age']['#title'] = $this->t('System Age');
      $form['system_age']['#description'] = $this->t('The age of the system in months.');
    }

    // Boiler Type
    if ($entity->hasField('boiler_type')) {
      $form['boiler_type']['#group'] = 'job_details';
      $form['boiler_type']['#weight'] = 9;
      $form['boiler_type']['#title'] = $this->t('Boiler Type');
      $form['boiler_type']['#description'] = $this->t('The type of boiler fitted (ie. combi, system)');
    }

    // Project ID
    if ($entity->hasField('project_id')) {
      if (!isset($form['project_id'])) {
        $project_id_value = '';
        if (!$entity->get('project_id')->isEmpty()) {
          $project_id_value = $entity->get('project_id')->value ?? '';
        }
        $form['project_id'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Project ID'),
          '#default_value' => $project_id_value,
          '#maxlength' => 255,
          '#required' => FALSE,
        ];
      }
      $form['project_id']['#group'] = 'job_details';
      $form['project_id']['#weight'] = 10;
      $form['project_id']['#title'] = $this->t('Project ID');
      $form['project_id']['#description'] = $this->t('The project ID. Required for claiming boiler manufacturer contract support.');
      $form['project_id']['#access'] = TRUE;
      unset($form['project_id']['#access_callback']);
    }


    // UPRN
    if ($entity->hasField('uprn')) {
      $form['uprn']['#group'] = 'job_details';
      $form['uprn']['#weight'] = 12;
      $form['uprn']['#title'] = $this->t('UPRN');
      $form['uprn']['#description'] = $this->t('Unique Property Reference Number for the system location. Required for claiming contract support and asset register compatibility.');
    }

    // Boiler ID
    if ($entity->hasField('boiler_id')) {
      $form['boiler_id']['#group'] = 'job_details';
      $form['boiler_id']['#weight'] = 13;
      $form['boiler_id']['#title'] = $this->t('Boiler ID');
      $form['boiler_id']['#description'] = $this->t('Boiler ID number as provided by the boiler manufacturer.');
    }


    // Fieldset: System Details (start)
    $form['system_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('System Details'),
      '#weight' => -80,
    ];

    // System Location
    if ($entity->hasField('system_location')) {
      $form['system_location']['#group'] = 'system_details';
      $form['system_location']['#weight'] = 1;
      $form['system_location']['#title'] = $this->t('System Location');
      $form['system_location']['#description'] = $this->t('A string showing the address, used by some pack types.');
    }

    // Landlord
    if ($entity->hasField('landlord')) {
      $form['landlord']['#group'] = 'system_details';
      $form['landlord']['#weight'] = 2;
      $form['landlord']['#title'] = $this->t('Landlord');
      $form['landlord']['#description'] = $this->t('Name of the landlord/owner of the property. This may be an organisation or an individual.');
    }

    // Order: Country (0), Property number (2), Address 1 (3), Property name (4), Town/City (5), Postcode (6)
    // Note: These may use entity reference fields - for now using simple fields
    
    // Country - Get from entity reference field or database
    $address_country_value = 'GB'; // Default to GB
    if ($entity->hasField('field_sentinel_sample_address') && !$entity->get('field_sentinel_sample_address')->isEmpty()) {
      $address_entity = $entity->get('field_sentinel_sample_address')->entity;
      if ($address_entity && $address_entity->hasField('field_address')) {
        $address_item = $address_entity->get('field_address')->first();
        if ($address_item && !empty($address_item->country_code)) {
          $address_country_value = $address_item->country_code;
        }
      }
    }
    // If not found, try database
    if ($address_country_value === 'GB' && !$entity->isNew()) {
      $database = \Drupal::database();
      $pid = $entity->id();
      try {
        // Try to get from address entity via field_sentinel_sample_address
        $result = $database->select('sentinel_sample__field_sentinel_sample_address', 'ssa')
          ->fields('ssa', ['field_sentinel_sample_address_target_id'])
          ->condition('ssa.entity_id', $pid)
          ->execute()
          ->fetchField();
        if ($result) {
          $address_result = $database->select('address__field_address', 'a')
            ->fields('a', ['field_address_country_code'])
            ->condition('a.entity_id', $result)
            ->execute()
            ->fetchField();
          if ($address_result) {
            $address_country_value = $address_result;
          }
        }
      } catch (\Exception $e) {
        // Database query failed, use default
      }
    }

    // Fieldset: Address (nested in System Details)
    $form['address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address'),
      '#description' => $this->t('The full address of where the system is located.'),
      '#weight' => 3,
      '#group' => 'system_details',
      '#after_build' => [[$this, 'reorderAddressFields']],
      // Add country field directly to the fieldset
      'address_country' => [
        '#type' => 'select',
        '#title' => $this->t('Address Country'),
        '#options' => $this->getCountryOptions(),
        '#default_value' => $address_country_value,
        '#weight' => 0,
        '#required' => FALSE,
      ],
    ];

    // Also handle entity reference field if it exists (for backward compatibility)
    if ($entity->hasField('field_sentinel_sample_address') && isset($form['field_sentinel_sample_address'])) {
      $form['field_sentinel_sample_address']['#group'] = 'address';
      $form['field_sentinel_sample_address']['#weight'] = 1;
    }
    
    // County field (if exists, keep it separate from country)
    if ($entity->hasField('county')) {
      $form['county']['#group'] = 'address';
      $form['county']['#weight'] = 7;
      $form['county']['#title'] = $this->t('County');
    }

    // Property number - weight 2
    if ($entity->hasField('property_number')) {
      $form['property_number']['#group'] = 'address';
      $form['property_number']['#weight'] = 2;
      $form['property_number']['#title'] = $this->t('Property number');
    }

    // Address 1 - weight 3
    if ($entity->hasField('street')) {
      $form['street']['#group'] = 'address';
      $form['street']['#weight'] = 3;
      $form['street']['#title'] = $this->t('Address 1');
    }

    // Property name - weight 4
    // Note: Property name might not exist as a separate field, may need to check address entity
    // For now, checking if there's a field for this
    if ($entity->hasField('property_name')) {
      $form['property_name']['#group'] = 'address';
      $form['property_name']['#weight'] = 4;
      $form['property_name']['#title'] = $this->t('Property name');
    }

    // Town/City - weight 5
    if ($entity->hasField('town_city')) {
      $form['town_city']['#group'] = 'address';
      $form['town_city']['#weight'] = 5;
      $form['town_city']['#title'] = $this->t('Town/City');
    }

    // Postcode - weight 6
    if ($entity->hasField('postcode')) {
      $form['postcode']['#group'] = 'address';
      $form['postcode']['#weight'] = 6;
      $form['postcode']['#title'] = $this->t('Postcode');
    }

    // Get values for fields that will be added directly to result_details fieldset
    // Date Booked In value
    $date_booked_value = NULL;
    $date_booked_formatted = NULL;
    if ($entity->hasField('date_booked')) {
      if (!$entity->get('date_booked')->isEmpty()) {
        $date_booked_value = $entity->get('date_booked')->value;
      }
      if (!$date_booked_value && !$entity->isNew()) {
        $date_booked_value = $this->getDateValueFromDatabase('date_booked', $entity->id());
      }
      $date_booked_formatted = $this->formatDateValue($date_booked_value);
    }
    
    // Date Processed value
    $date_processed_value = NULL;
    $date_processed_formatted = NULL;
    if ($entity->hasField('date_processed')) {
      if (!$entity->get('date_processed')->isEmpty()) {
        $date_processed_value = $entity->get('date_processed')->value;
      }
      if (!$date_processed_value && !$entity->isNew()) {
        $date_processed_value = $this->getDateValueFromDatabase('date_processed', $entity->id());
      }
      $date_processed_formatted = $this->formatDateValue($date_processed_value);
    }
    
    // Date Reported value
    $date_reported_value = NULL;
    $date_reported_formatted = NULL;
    if ($entity->hasField('date_reported')) {
      if (!$entity->get('date_reported')->isEmpty()) {
        $date_reported_value = $entity->get('date_reported')->value;
      }
      if (!$date_reported_value && !$entity->isNew()) {
        $date_reported_value = $this->getDateValueFromDatabase('date_reported', $entity->id());
      }
      $date_reported_formatted = $this->formatDateValue($date_reported_value);
    }

    // Determine Legacy Sample value (used for display-only select)
    $has_legacy_field = $entity->hasField('legacy');
    $legacy_value = '';
    if ($has_legacy_field && !$entity->get('legacy')->isEmpty()) {
      $legacy_value = (string) $entity->get('legacy')->value;
    }

    // Get values for fields to be added directly to result_details fieldset
    // Helper function to get boolean value for select
    $getBooleanValue = function($field_name) use ($entity) {
      if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
        $value = $entity->get($field_name)->value;
        return $value ? '1' : '0';
      }
      return '';
    };

    // Get values for all fields
    $duplicate_of_value = '';
    if ($entity->hasField('duplicate_of') && !$entity->get('duplicate_of')->isEmpty()) {
      $duplicate_of_value = $entity->get('duplicate_of')->value ?? '';
    }

    $ph_result_value = '';
    if ($entity->hasField('ph_result') && !$entity->get('ph_result')->isEmpty()) {
      $ph_result_value = $entity->get('ph_result')->value ?? '';
    }
    $ph_pass_fail_value = $getBooleanValue('ph_pass_fail');

    $sentinel_x100_result_value = '';
    if ($entity->hasField('sentinel_x100_result') && !$entity->get('sentinel_x100_result')->isEmpty()) {
      $sentinel_x100_result_value = $entity->get('sentinel_x100_result')->value ?? '';
    }
    $sentinel_x100_pass_fail_value = $getBooleanValue('sentinel_x100_pass_fail');

    $molybdenum_result_value = '';
    if ($entity->hasField('molybdenum_result') && !$entity->get('molybdenum_result')->isEmpty()) {
      $molybdenum_result_value = $entity->get('molybdenum_result')->value ?? '';
    }
    $molybdenum_pass_fail_value = $getBooleanValue('molybdenum_pass_fail');

    $boron_result_value = '';
    if ($entity->hasField('boron_result') && !$entity->get('boron_result')->isEmpty()) {
      $boron_result_value = $entity->get('boron_result')->value ?? '';
    }
    $boron_pass_fail_value = $getBooleanValue('boron_pass_fail');

    $manganese_result_value = '';
    if ($entity->hasField('manganese_result') && !$entity->get('manganese_result')->isEmpty()) {
      $manganese_result_value = $entity->get('manganese_result')->value ?? '';
    }
    $manganese_pass_fail_value = $getBooleanValue('manganese_pass_fail');

    // Additional fields for Result Details
    $cond_pass_fail_value = $getBooleanValue('cond_pass_fail');
    
    $mains_cl_result_value = '';
    if ($entity->hasField('mains_cl_result') && !$entity->get('mains_cl_result')->isEmpty()) {
      $mains_cl_result_value = $entity->get('mains_cl_result')->value ?? '';
    }
    
    $sys_cl_result_value = '';
    if ($entity->hasField('sys_cl_result') && !$entity->get('sys_cl_result')->isEmpty()) {
      $sys_cl_result_value = $entity->get('sys_cl_result')->value ?? '';
    }
    $cl_pass_fail_value = $getBooleanValue('cl_pass_fail');
    
    $iron_result_value = '';
    if ($entity->hasField('iron_result') && !$entity->get('iron_result')->isEmpty()) {
      $iron_result_value = $entity->get('iron_result')->value ?? '';
    }
    $iron_pass_fail_value = $getBooleanValue('iron_pass_fail');
    
    $copper_result_value = '';
    if ($entity->hasField('copper_result') && !$entity->get('copper_result')->isEmpty()) {
      $copper_result_value = $entity->get('copper_result')->value ?? '';
    }
    $copper_pass_fail_value = $getBooleanValue('copper_pass_fail');
    
    $aluminium_result_value = '';
    if ($entity->hasField('aluminium_result') && !$entity->get('aluminium_result')->isEmpty()) {
      $aluminium_result_value = $entity->get('aluminium_result')->value ?? '';
    }
    
    $sys_calcium_result_value = '';
    if ($entity->hasField('sys_calcium_result') && !$entity->get('sys_calcium_result')->isEmpty()) {
      $sys_calcium_result_value = $entity->get('sys_calcium_result')->value ?? '';
    }
    $calcium_pass_fail_value = $getBooleanValue('calcium_pass_fail');

    // Additional fields for Result Details
    $sys_cond_result_value = '';
    if ($entity->hasField('sys_cond_result') && !$entity->get('sys_cond_result')->isEmpty()) {
      $sys_cond_result_value = $entity->get('sys_cond_result')->value ?? '';
    }
    
    $appearance_result_value = '';
    if ($entity->hasField('appearance_result') && !$entity->get('appearance_result')->isEmpty()) {
      $appearance_result_value = $entity->get('appearance_result')->value ?? '';
    }
    $appearance_pass_fail_value = $getBooleanValue('appearance_pass_fail');
    
    $pass_fail_value = $getBooleanValue('pass_fail');
    
    $on_hold_value = $getBooleanValue('on_hold');
    
    $card_complete_value = $getBooleanValue('card_complete');
    
    $pack_type_value = '';
    if ($entity->hasField('pack_type') && !$entity->get('pack_type')->isEmpty()) {
      $pack_type_value = $entity->get('pack_type')->value ?? '';
    }
    
    $lab_ref_value = '';
    if ($entity->hasField('lab_ref') && !$entity->get('lab_ref')->isEmpty()) {
      $lab_ref_value = $entity->get('lab_ref')->value ?? '';
    }

    // Fieldset: Result Details (start)
    $form['result_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Result Details'),
      '#weight' => -70,
      // Add Date Booked In directly to the fieldset
      'date_booked' => [
        '#type' => 'date',
        '#title' => $this->t('Date Booked In'),
        '#description' => $this->t('E.g., 16-11-2025 The date the sample was booked in at the test facility.'),
        '#default_value' => $date_booked_formatted,
        '#weight' => 1,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('date_booked'),
      ],
      // Add Date Processed directly to the fieldset
      'date_processed' => [
        '#type' => 'date',
        '#title' => $this->t('Date Processed'),
        '#description' => $this->t('E.g., 16-11-2025 The date the sample was processed.'),
        '#default_value' => $date_processed_formatted,
        '#weight' => 2,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('date_processed'),
      ],
      // Add Date Reported directly to the fieldset
      'date_reported' => [
        '#type' => 'date',
        '#title' => $this->t('Date Reported'),
        '#description' => $this->t('E.g., 16-11-2025 The date the results were reported.'),
        '#default_value' => $date_reported_formatted,
        '#weight' => 3,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('date_reported'),
      ],
      // Legacy Sample display select
      'legacy_display' => [
        '#type' => 'select',
        '#title' => $this->t('Legacy Sample'),
        '#description' => $this->t('If this is a legacy sample or not.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $legacy_value,
        '#weight' => 43,
        '#disabled' => TRUE,
        '#access' => $has_legacy_field,
      ],
      // Duplicate Of
      'duplicate_of_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Duplicate Of'),
        '#description' => $this->t('This sample is a duplicate of.'),
        '#default_value' => $duplicate_of_value,
        '#weight' => 42,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('duplicate_of'),
      ],
      // pH Result
      'ph_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('pH Result'),
        '#description' => $this->t('The result of the pH test.'),
        '#default_value' => $ph_result_value,
        '#weight' => 30,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('ph_result'),
      ],
      // pH Pass/Fail (boolean as On/Off)
      'ph_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('pH Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the pH test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $ph_pass_fail_value,
        '#weight' => 31,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('ph_pass_fail'),
      ],
      // Inhibitor Result
      'sentinel_x100_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Inhibitor Result'),
        '#description' => $this->t('The result of the Inhibitor test.'),
        '#default_value' => $sentinel_x100_result_value,
        '#weight' => 32,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('sentinel_x100_result'),
      ],
      // Inhibitor Pass/Fail (boolean as On/Off)
      'sentinel_x100_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Inhibitor Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the Inhibitor test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $sentinel_x100_pass_fail_value,
        '#weight' => 33,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('sentinel_x100_pass_fail'),
      ],
      // Molybdenum Result
      'molybdenum_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Molybdenum Result'),
        '#description' => $this->t('The result of the Molybdenum test.'),
        '#default_value' => $molybdenum_result_value,
        '#weight' => 34,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('molybdenum_result'),
      ],
      // Molybdenum Pass/Fail (boolean as On/Off)
      'molybdenum_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Molybdenum Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the Molybdenum test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $molybdenum_pass_fail_value,
        '#weight' => 35,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('molybdenum_pass_fail'),
      ],
      // XXX Result
      'boron_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('XXX Result'),
        '#description' => $this->t('The result of the XXX test.'),
        '#default_value' => $boron_result_value,
        '#weight' => 36,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('boron_result'),
      ],
      // XXX Pass/Fail (boolean as On/Off)
      'boron_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('XXX Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the XXX test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $boron_pass_fail_value,
        '#weight' => 37,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('boron_pass_fail'),
      ],
      // Manganese Result
      'manganese_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Manganese Result'),
        '#description' => $this->t('The result of the Manganese test.'),
        '#default_value' => $manganese_result_value,
        '#weight' => 38,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('manganese_result'),
      ],
      // Manganese Pass/Fail (boolean as On/Off)
      'manganese_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Manganese Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the Manganese test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $manganese_pass_fail_value,
        '#weight' => 39,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('manganese_pass_fail'),
      ],
      // Conductivity Pass/Fail (boolean as On/Off)
      'cond_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Conductivity Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the conductivity test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $cond_pass_fail_value,
        '#weight' => 17,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('cond_pass_fail'),
      ],
      // Mains Chlorine Result
      'mains_cl_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Mains Chlorine Result'),
        '#description' => $this->t('The result of the chlorine test.'),
        '#default_value' => $mains_cl_result_value,
        '#weight' => 18,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('mains_cl_result'),
      ],
      // System Chlorine Result
      'sys_cl_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('System Chlorine Result'),
        '#description' => $this->t('The result of the chlorine test.'),
        '#default_value' => $sys_cl_result_value,
        '#weight' => 19,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('sys_cl_result'),
      ],
      // Chlorine Pass/Fail (boolean as On/Off)
      'cl_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Chlorine Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the chlorine test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $cl_pass_fail_value,
        '#weight' => 20,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('cl_pass_fail'),
      ],
      // Iron Result
      'iron_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Iron Result'),
        '#description' => $this->t('The result of the iron test.'),
        '#default_value' => $iron_result_value,
        '#weight' => 21,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('iron_result'),
      ],
      // Iron Pass/Fail (boolean as On/Off)
      'iron_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Iron Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the iron test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $iron_pass_fail_value,
        '#weight' => 22,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('iron_pass_fail'),
      ],
      // Copper Result
      'copper_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Copper Result'),
        '#description' => $this->t('The result of the copper test.'),
        '#default_value' => $copper_result_value,
        '#weight' => 23,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('copper_result'),
      ],
      // Copper Pass/Fail (boolean as On/Off)
      'copper_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Copper Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the copper test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $copper_pass_fail_value,
        '#weight' => 24,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('copper_pass_fail'),
      ],
      // Aluminium Result
      'aluminium_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Aluminium Result'),
        '#description' => $this->t('The result of the aluminium test.'),
        '#default_value' => $aluminium_result_value,
        '#weight' => 25,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('aluminium_result'),
      ],
      // System Calcium Result
      'sys_calcium_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('System Calcium Result'),
        '#description' => $this->t('The result of the system calcium test.'),
        '#default_value' => $sys_calcium_result_value,
        '#weight' => 28,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('sys_calcium_result'),
      ],
      // Calcium Pass/Fail (boolean as On/Off)
      'calcium_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Calcium Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the calcium test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $calcium_pass_fail_value,
        '#weight' => 29,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('calcium_pass_fail'),
      ],
      // System Conductivity Result
      'sys_cond_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('System Conductivity Result'),
        '#description' => $this->t('The result of the system conductivity test.'),
        '#default_value' => $sys_cond_result_value,
        '#weight' => 16,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('sys_cond_result'),
      ],
      // Appearance Result
      'appearance_result_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Appearance Result'),
        '#description' => $this->t('The result of the appearance test.'),
        '#default_value' => $appearance_result_value,
        '#weight' => 13,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('appearance_result'),
      ],
      // Appearance Pass/Fail (boolean as On/Off)
      'appearance_pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Appearance Pass/Fail'),
        '#description' => $this->t('The pass and fail mark for the appearance test.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $appearance_pass_fail_value,
        '#weight' => 14,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('appearance_pass_fail'),
      ],
      // Overall Pass/Fail (boolean as On/Off)
      'pass_fail_display' => [
        '#type' => 'select',
        '#title' => $this->t('Overall Pass/Fail'),
        '#description' => $this->t('The overall pass or fail mark of the sample.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $pass_fail_value,
        '#weight' => 12,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('pass_fail'),
      ],
      // On Hold (boolean as On/Off)
      'on_hold_display' => [
        '#type' => 'select',
        '#title' => $this->t('On Hold'),
        '#description' => $this->t('If the sample is on hold.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $on_hold_value,
        '#weight' => 11,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('on_hold'),
      ],
      // Card Complete (boolean as On/Off)
      'card_complete_display' => [
        '#type' => 'select',
        '#title' => $this->t('Card Complete'),
        '#description' => $this->t('If the card is complete.'),
        '#options' => [
          '0' => $this->t('Off'),
          '1' => $this->t('On'),
        ],
        '#empty_option' => $this->t('-- Please Select --'),
        '#default_value' => $card_complete_value,
        '#weight' => 10,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('card_complete'),
      ],
      // Pack Type
      'pack_type_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Pack Type'),
        '#description' => $this->t('The type of the pack (dictates the type of test being run).'),
        '#default_value' => $pack_type_value,
        '#weight' => 9,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('pack_type'),
      ],
      // Lab Ref
      'lab_ref_display' => [
        '#type' => 'textfield',
        '#title' => $this->t('Lab Ref'),
        '#description' => $this->t('The lab reference of the sample (used by testing lab).'),
        '#default_value' => $lab_ref_value,
        '#weight' => 8,
        '#disabled' => TRUE,
        '#access' => $entity->hasField('lab_ref'),
      ],
    ];

    // Hide the original legacy widget (checkbox) so Drupal can process values without rendering it.
    if (isset($form['legacy'])) {
      $form['legacy']['#access'] = FALSE;
    }

    // Hide original fields to avoid widget errors - use #access => FALSE instead of unset
    $fields_to_hide = [
      'duplicate_of',
      'ph_result',
      'ph_pass_fail',
      'sentinel_x100_result',
      'sentinel_x100_pass_fail',
      'molybdenum_result',
      'molybdenum_pass_fail',
      'boron_result',
      'boron_pass_fail',
      'manganese_result',
      'manganese_pass_fail',
      'cond_pass_fail',
      'mains_cl_result',
      'sys_cl_result',
      'cl_pass_fail',
      'iron_result',
      'iron_pass_fail',
      'copper_result',
      'copper_pass_fail',
      'aluminium_result',
      'sys_calcium_result',
      'calcium_pass_fail',
      'sys_cond_result',
      'appearance_result',
      'appearance_pass_fail',
      'pass_fail',
      'on_hold',
      'card_complete',
      'pack_type',
      'lab_ref',
    ];
    foreach ($fields_to_hide as $field_name) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = FALSE;
      }
    }

    // File ID
    if ($entity->hasField('fileid')) {
      $form['fileid']['#group'] = 'result_details';
      $form['fileid']['#weight'] = 4;
      $form['fileid']['#title'] = $this->t('File ID');
      $form['fileid']['#description'] = $this->t('The file id of the results file.');
      $form['fileid']['#disabled'] = TRUE;
    }

    // Filename
    if ($entity->hasField('filename')) {
      $form['filename']['#group'] = 'result_details';
      $form['filename']['#weight'] = 5;
      $form['filename']['#title'] = $this->t('Filename');
      $form['filename']['#description'] = $this->t('The filename of the results file.');
      $form['filename']['#disabled'] = TRUE;
    }

    // The Client ID
    if ($entity->hasField('client_id')) {
      $form['client_id']['#group'] = 'result_details';
      $form['client_id']['#weight'] = 6;
      $form['client_id']['#title'] = $this->t('The Client ID');
      $form['client_id']['#description'] = $this->t('The ID of the client (used internally).');
      $form['client_id']['#disabled'] = TRUE;
    }

    // Client Name
    if ($entity->hasField('client_name')) {
      $form['client_name']['#group'] = 'result_details';
      $form['client_name']['#weight'] = 7;
      $form['client_name']['#title'] = $this->t('Client Name');
      $form['client_name']['#description'] = $this->t('The name of the client (added for legacy purposes).');
      $form['client_name']['#disabled'] = TRUE;
    }


    // Mains Conductivity Result
    if ($entity->hasField('mains_cond_result')) {
      $form['mains_cond_result']['#group'] = 'result_details';
      $form['mains_cond_result']['#weight'] = 15;
      $form['mains_cond_result']['#title'] = $this->t('Mains Conductivity Result');
      $form['mains_cond_result']['#description'] = $this->t('The result of the mains conductivity test.');
      $form['mains_cond_result']['#disabled'] = TRUE;
    }



    // Aluminium Pass/Fail
    if ($entity->hasField('aluminium_pass_fail')) {
      $form['aluminium_pass_fail']['#group'] = 'result_details';
      $form['aluminium_pass_fail']['#weight'] = 26;
      $form['aluminium_pass_fail']['#title'] = $this->t('Aluminium Pass/Fail');
      $form['aluminium_pass_fail']['#description'] = $this->t('The pass and fail mark for the aluminium test.');
      $form['aluminium_pass_fail']['#disabled'] = TRUE;
    }

    // Mains Calcium Result
    if ($entity->hasField('mains_calcium_result')) {
      $form['mains_calcium_result']['#group'] = 'result_details';
      $form['mains_calcium_result']['#weight'] = 27;
      $form['mains_calcium_result']['#title'] = $this->t('Mains Calcium Result');
      $form['mains_calcium_result']['#description'] = $this->t('The result of the mains calcium test.');
      $form['mains_calcium_result']['#disabled'] = TRUE;
    }



    // Nitrate Result
    if ($entity->hasField('nitrate_result')) {
      $form['nitrate_result']['#group'] = 'result_details';
      $form['nitrate_result']['#weight'] = 40;
      $form['nitrate_result']['#title'] = $this->t('Nitrate Result');
      $form['nitrate_result']['#description'] = $this->t('The result of the Nitrate test.');
      $form['nitrate_result']['#disabled'] = TRUE;
    }

    // Molybdenum and XXX Ratio
    if ($entity->hasField('mob_ratio')) {
      $form['mob_ratio']['#group'] = 'result_details';
      $form['mob_ratio']['#weight'] = 41;
      $form['mob_ratio']['#title'] = $this->t('Molybdenum and XXX Ratio');
      $form['mob_ratio']['#description'] = $this->t('The ratio of the Molybdenum and XXX concentrations.');
      $form['mob_ratio']['#disabled'] = TRUE;
    }


    // API Created By
    if ($entity->hasField('api_created_by')) {
      $form['api_created_by']['#group'] = 'result_details';
      $form['api_created_by']['#weight'] = 44;
      $form['api_created_by']['#title'] = $this->t('API Created By');
      $form['api_created_by']['#description'] = $this->t('API user who created this sample.');
      $form['api_created_by']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(\Drupal\Core\Entity\EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Exclude date fields from parent processing - we handle them manually
    $date_fields = ['created', 'updated', 'date_sent', 'date_installed', 'date_booked', 'date_processed', 'date_reported'];
    
    // Temporarily remove date fields from form to prevent widget extraction
    $removed_fields = [];
    foreach ($date_fields as $field_name) {
      if (isset($form[$field_name])) {
        $removed_fields[$field_name] = $form[$field_name];
        unset($form[$field_name]);
      }
    }
    
    // Call parent to process other fields
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    
    // Restore fields (they won't be processed by parent)
    foreach ($removed_fields as $field_name => $field_value) {
      $form[$field_name] = $field_value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Store original email values before any updates to detect changes
    $original_installer_email = '';
    $original_company_email = '';
    
    if ($this->entity->hasField('installer_email') && !$this->entity->get('installer_email')->isEmpty()) {
      $original_installer_email = $this->entity->get('installer_email')->value ?? '';
    }
    
    if ($this->entity->hasField('company_email') && !$this->entity->get('company_email')->isEmpty()) {
      $original_company_email = $this->entity->get('company_email')->value ?? '';
    }
    
    // Store in form state for later use in save()
    $form_state->set('original_installer_email', $original_installer_email);
    $form_state->set('original_company_email', $original_company_email);
    
    parent::submitForm($form, $form_state);

    // Update Pack Reference Number
    $pack_ref = $form_state->getValue('pack_reference_number');
    if ($pack_ref !== NULL) {
      $this->entity->set('pack_reference_number', (string) $pack_ref);
    }

    // Update Company Email (now nested in company_details fieldset)
    if ($this->entity->hasField('company_email')) {
      $company_details_values = $form_state->getValue('company_details');
      $company_email = isset($company_details_values['company_email']) ? $company_details_values['company_email'] : NULL;
      if ($company_email !== NULL) {
        $this->entity->set('company_email', (string) $company_email);
      }
    }

    // Update Company Telephone
    if ($this->entity->hasField('company_tel')) {
      $company_tel = $form_state->getValue('company_tel');
      if ($company_tel !== NULL) {
        $this->entity->set('company_tel', (string) $company_tel);
      }
    }

    // Update Customer ID
    if ($this->entity->hasField('customer_id')) {
      $customer_id = $form_state->getValue('customer_id');
      if ($customer_id !== NULL) {
        $this->entity->set('customer_id', (string) $customer_id);
      }
    }

    // Update Company Address fields
    if ($this->entity->hasField('company_name')) {
      $company_name = $form_state->getValue('company_name');
      if ($company_name !== NULL) {
        $this->entity->set('company_name', (string) $company_name);
      }
    }

    if ($this->entity->hasField('company_address1')) {
      $company_address1 = $form_state->getValue('company_address1');
      if ($company_address1 !== NULL) {
        $this->entity->set('company_address1', (string) $company_address1);
      }
    }

    if ($this->entity->hasField('company_address2')) {
      $company_address2 = $form_state->getValue('company_address2');
      if ($company_address2 !== NULL) {
        $this->entity->set('company_address2', (string) $company_address2);
      }
    }

    if ($this->entity->hasField('company_town')) {
      $company_town = $form_state->getValue('company_town');
      if ($company_town !== NULL) {
        $this->entity->set('company_town', (string) $company_town);
      }
    }

    if ($this->entity->hasField('company_county')) {
      $company_county = $form_state->getValue('company_county');
      if ($company_county !== NULL) {
        $this->entity->set('company_county', (string) $company_county);
      }
    }

    if ($this->entity->hasField('company_postcode')) {
      $company_postcode = $form_state->getValue('company_postcode');
      if ($company_postcode !== NULL) {
        $this->entity->set('company_postcode', (string) $company_postcode);
      }
    }

    // Update Installer Name
    if ($this->entity->hasField('installer_name')) {
      $installer_name = $form_state->getValue('installer_name');
      if ($installer_name !== NULL) {
        $this->entity->set('installer_name', (string) $installer_name);
      }
    }

    // Update Installer Email (now nested in job_details fieldset)
    if ($this->entity->hasField('installer_email')) {
      $job_details_values = $form_state->getValue('job_details');
      $installer_email = isset($job_details_values['installer_email']) ? $job_details_values['installer_email'] : NULL;
      if ($installer_email !== NULL) {
        $this->entity->set('installer_email', (string) $installer_email);
      }
    }

    // Update Installer Company
    if ($this->entity->hasField('installer_company')) {
      $installer_company = $form_state->getValue('installer_company');
      if ($installer_company !== NULL) {
        $this->entity->set('installer_company', (string) $installer_company);
      }
    }

    // Update Project ID
    if ($this->entity->hasField('project_id')) {
      $project_id = $form_state->getValue('project_id');
      if ($project_id !== NULL) {
        $this->entity->set('project_id', (string) $project_id);
      }
    }

    // Update Company Country (now nested in company_address fieldset)
    $company_address_values = $form_state->getValue('company_address');
    $company_country = isset($company_address_values['company_country']) ? $company_address_values['company_country'] : NULL;
    if ($company_country !== NULL && $company_country !== '') {
      // Update the address entity reference if it exists
      if ($this->entity->hasField('field_company_address') && !$this->entity->get('field_company_address')->isEmpty()) {
        $address_entity = $this->entity->get('field_company_address')->entity;
        if ($address_entity && $address_entity->hasField('field_address')) {
          $address_item = $address_entity->get('field_address')->first();
          if ($address_item) {
            $address_item->country_code = strtoupper(trim((string) $company_country));
            $address_entity->save();
          }
        }
      }
    }

    // Update Address Country (now nested in address fieldset)
    $address_values = $form_state->getValue('address');
    $address_country = isset($address_values['address_country']) ? $address_values['address_country'] : NULL;
    if ($address_country !== NULL && $address_country !== '') {
      // Update the address entity reference if it exists
      if ($this->entity->hasField('field_sentinel_sample_address') && !$this->entity->get('field_sentinel_sample_address')->isEmpty()) {
        $address_entity = $this->entity->get('field_sentinel_sample_address')->entity;
        if ($address_entity && $address_entity->hasField('field_address')) {
          $address_item = $address_entity->get('field_address')->first();
          if ($address_item) {
            $address_item->country_code = strtoupper(trim((string) $address_country));
            $address_entity->save();
          }
        }
      }
    }

    // Update date fields - convert Y-m-d to datetime format (now nested in job_details fieldset)
    $job_details_values = $form_state->getValue('job_details');
    $date_fields = ['date_sent', 'date_installed'];
    foreach ($date_fields as $field_name) {
      if ($this->entity->hasField($field_name)) {
        $date_value = isset($job_details_values[$field_name]) ? $job_details_values[$field_name] : NULL;
        if ($date_value !== NULL && $date_value !== '') {
          // Convert Y-m-d to datetime format
          try {
            $date = new \Drupal\Core\Datetime\DrupalDateTime($date_value . ' 00:00:00', 'UTC');
            $this->entity->set($field_name, $date->format('Y-m-d H:i:s'));
          } catch (\Exception $e) {
            // If conversion fails, set as is
            $this->entity->set($field_name, $date_value);
          }
        }
      }
    }

    // Update hold state
    $hold_state = $form_state->getValue('sentinel_sample_hold_state_target_id');
    if ($hold_state === '' || $hold_state === NULL) {
      $this->entity->set('sentinel_sample_hold_state_target_id', NULL);
    }
    else {
      $this->entity->set('sentinel_sample_hold_state_target_id', (int) $hold_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    
    // Set updated field to current date/time when updating (not creating new)
    if (!$entity->isNew() && $entity->hasField('updated')) {
      $current_time = date('Y-m-d H:i:s');
      $entity->set('updated', $current_time);
    }
    
    // Get original email values before save
    $original_installer_email = $form_state->get('original_installer_email') ?? '';
    $original_company_email = $form_state->get('original_company_email') ?? '';
    
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Sentinel sample.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Sentinel sample.', [
          '%label' => $entity->label(),
        ]));
    }

    // Check if emails changed and send report if they did
    if (!$entity->isNew()) {
      $new_installer_email = '';
      $new_company_email = '';
      
      if ($entity->hasField('installer_email') && !$entity->get('installer_email')->isEmpty()) {
        $new_installer_email = $entity->get('installer_email')->value ?? '';
      }
      
      if ($entity->hasField('company_email') && !$entity->get('company_email')->isEmpty()) {
        $new_company_email = $entity->get('company_email')->value ?? '';
      }
      
      // Normalize empty strings for comparison
      $original_installer_email = $original_installer_email ?? '';
      $original_company_email = $original_company_email ?? '';
      $new_installer_email = $new_installer_email ?? '';
      $new_company_email = $new_company_email ?? '';
      
      // Check if either email changed
      $installer_email_changed = ($original_installer_email !== $new_installer_email);
      $company_email_changed = ($original_company_email !== $new_company_email);
      
      if ($installer_email_changed || $company_email_changed) {
        // Reload entity to ensure we have the latest saved version
        $entity = \Drupal::entityTypeManager()->getStorage('sentinel_sample')->load($entity->id());
        
        if ($entity && function_exists('_sentinel_portal_queue_process_email')) {
          try {
            $result = _sentinel_portal_queue_process_email($entity, 'report');
            
            if ($result) {
              $this->getLogger('sentinel_portal_entities')->info('Sample entity mail sent automatically after email update! @id', [
                '@id' => $entity->id(),
              ]);
              $this->messenger()->addStatus($this->t('Report emailed to updated email address(es).'));
            }
            else {
              $this->getLogger('sentinel_portal_entities')->warning('Failed to send entity mail automatically after email update! @id', [
                '@id' => $entity->id(),
              ]);
              $this->messenger()->addError($this->t('Report failed to email.'));
            }
          }
          catch (\Throwable $e) {
            $this->getLogger('sentinel_portal_entities')->error('Failed to send sample report email automatically after email update for @id: @message', [
              '@id' => $entity->id(),
              '@message' => $e->getMessage(),
            ]);
            $this->messenger()->addError($this->t('Report failed to email.'));
          }
        }
      }
    }

    $form_state->setRedirectUrl($entity->toUrl('canonical'));
  }


  /**
   * Get date value from database for a field.
   */
  protected function getDateValueFromDatabase($field_name, $pid) {
    $database = \Drupal::database();
    try {
      // Try sentinel_samples table first (with 's')
      $result = $database->select('sentinel_samples', 's')
        ->fields('s', [$field_name])
        ->condition('s.pid', $pid)
        ->execute()
        ->fetchField();
      if ($result) {
        return $result;
      }
    } catch (\Exception $e) {
      // If sentinel_samples doesn't work, try sentinel_sample (without 's')
      try {
        $result = $database->select('sentinel_sample', 's')
          ->fields('s', [$field_name])
          ->condition('s.pid', $pid)
          ->execute()
          ->fetchField();
        if ($result) {
          return $result;
        }
      } catch (\Exception $e2) {
        // Both queries failed
      }
    }
    return NULL;
  }

  /**
   * Format date value to Y-m-d format.
   */
  protected function formatDateValue($date_value) {
    if (!$date_value) {
      return NULL;
    }

    try {
      // If it's already a DrupalDateTime object, format it
      if ($date_value instanceof \Drupal\Core\Datetime\DrupalDateTime) {
        return $date_value->format('Y-m-d');
      }
      // If it's a string, try to parse it
      elseif (is_string($date_value) && !empty($date_value)) {
        // Try parsing as datetime string
        $date = new \Drupal\Core\Datetime\DrupalDateTime($date_value, 'UTC');
        return $date->format('Y-m-d');
      }
    } catch (\Exception $e) {
      // If parsing fails, try to extract date part from string
      if (is_string($date_value) && preg_match('/(\d{4}-\d{2}-\d{2})/', $date_value, $matches)) {
        return $matches[1];
      }
    }
    return NULL;
  }

  /**
   * After build callback to ensure Company Details fields are in correct order.
   */
  public function reorderCompanyDetailsFields(array $element, FormStateInterface $form_state) {
    // Ensure correct order: Company Email (1), Company Telephone (2), Sentinel Customer ID (3)
    if (isset($element['company_email'])) {
      $element['company_email']['#weight'] = 1;
      $element['company_email']['#access'] = TRUE;
      unset($element['company_email']['#access_callback']);
    }
    if (isset($element['company_tel'])) {
      $element['company_tel']['#weight'] = 2;
      $element['company_tel']['#access'] = TRUE;
      unset($element['company_tel']['#access_callback']);
    }
    if (isset($element['customer_id'])) {
      $element['customer_id']['#weight'] = 3;
      $element['customer_id']['#access'] = TRUE;
      unset($element['customer_id']['#access_callback']);
    }
    if (isset($element['company_address'])) {
      $element['company_address']['#weight'] = 4;
    }
    return $element;
  }

  /**
   * After build callback to ensure Company Address fields are in correct order.
   */
  public function reorderCompanyAddressFields(array $element, FormStateInterface $form_state) {
    // Ensure company_country field is properly included and ordered
    if (isset($element['company_country'])) {
      $element['company_country']['#weight'] = 0;
      $element['company_country']['#access'] = TRUE;
      unset($element['company_country']['#access_callback']);
    }
    
    // Company Name - weight 2
    if (isset($element['company_name'])) {
      $element['company_name']['#weight'] = 2;
      $element['company_name']['#access'] = TRUE;
      unset($element['company_name']['#access_callback']);
    }
    
    // Company Address 1 - weight 3
    if (isset($element['company_address1'])) {
      $element['company_address1']['#weight'] = 3;
      $element['company_address1']['#access'] = TRUE;
      unset($element['company_address1']['#access_callback']);
    }
    
    // Company Address 2 (Property name) - weight 4
    if (isset($element['company_address2'])) {
      $element['company_address2']['#weight'] = 4;
      $element['company_address2']['#access'] = TRUE;
      unset($element['company_address2']['#access_callback']);
    }
    
    // Company Town/City - weight 5
    if (isset($element['company_town'])) {
      $element['company_town']['#weight'] = 5;
      $element['company_town']['#access'] = TRUE;
      unset($element['company_town']['#access_callback']);
    }
    
    // Company County - weight 6
    if (isset($element['company_county'])) {
      $element['company_county']['#weight'] = 6;
      $element['company_county']['#access'] = TRUE;
      unset($element['company_county']['#access_callback']);
    }
    
    // Company Postcode - weight 7
    if (isset($element['company_postcode'])) {
      $element['company_postcode']['#weight'] = 7;
      $element['company_postcode']['#access'] = TRUE;
      unset($element['company_postcode']['#access_callback']);
    }
    
    // The company address fields are nested inside field_company_address entity reference
    // Just ensure the entity reference field has the correct weight and is accessible
    if (isset($element['field_company_address'])) {
      $element['field_company_address']['#weight'] = 8;
      $element['field_company_address']['#access'] = TRUE;
      unset($element['field_company_address']['#access_callback']);
      
      // Also ensure nested elements are accessible
      if (isset($element['field_company_address']['widget'])) {
        $element['field_company_address']['widget']['#access'] = TRUE;
        unset($element['field_company_address']['widget']['#access_callback']);
      }
      if (isset($element['field_company_address'][0])) {
        $element['field_company_address'][0]['#access'] = TRUE;
        unset($element['field_company_address'][0]['#access_callback']);
      }
    }
    
    return $element;
  }

  /**
   * After build callback to ensure Address fields (in System Details) are in correct order.
   */
  public function reorderAddressFields(array $element, FormStateInterface $form_state) {
    // Ensure correct order: Country (0), Property number (2), Address 1 (3), Property name (4), Town/City (5), Postcode (6)
    
    // Address Country - weight 0 (first)
    if (isset($element['address_country'])) {
      $element['address_country']['#weight'] = 0;
      $element['address_country']['#access'] = TRUE;
      unset($element['address_country']['#access_callback']);
    }
    
    // Country - could be field_sentinel_sample_address (entity ref) - weight 1
    if (isset($element['field_sentinel_sample_address'])) {
      $element['field_sentinel_sample_address']['#weight'] = 1;
    }
    
    // Property number - weight 2
    if (isset($element['property_number'])) {
      $element['property_number']['#weight'] = 2;
      $element['property_number']['#access'] = TRUE;
      unset($element['property_number']['#access_callback']);
    }
    
    // Address 1 - weight 3
    if (isset($element['street'])) {
      $element['street']['#weight'] = 3;
      $element['street']['#access'] = TRUE;
      unset($element['street']['#access_callback']);
    }
    
    // Property name - weight 4
    if (isset($element['property_name'])) {
      $element['property_name']['#weight'] = 4;
      $element['property_name']['#access'] = TRUE;
      unset($element['property_name']['#access_callback']);
    }
    
    // Town/City - weight 5
    if (isset($element['town_city'])) {
      $element['town_city']['#weight'] = 5;
      $element['town_city']['#access'] = TRUE;
      unset($element['town_city']['#access_callback']);
    }
    
    // Postcode - weight 6
    if (isset($element['postcode'])) {
      $element['postcode']['#weight'] = 6;
      $element['postcode']['#access'] = TRUE;
      unset($element['postcode']['#access_callback']);
    }
    
    // County - weight 7
    if (isset($element['county'])) {
      $element['county']['#weight'] = 7;
      $element['county']['#access'] = TRUE;
      unset($element['county']['#access_callback']);
    }
    
    return $element;
  }



  /**
   * Builds the options list for the hold state vocabulary terms.
   */
  protected function getHoldStateOptions(): array {
    $options = [];
    $vocabulary = Vocabulary::load('hold_state_values');
    if (!$vocabulary) {
      return $options;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadTree($vocabulary->id());

    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }
  
  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Only remove Delete on the portal admin edit path:
    // /portal/admin/samples/manage/{sentinel_sample}/edit
    $current_path = \Drupal::service('path.current')->getPath();
    $normalized = '/' . ltrim($current_path, '/');
    if (strpos($normalized, '/portal/admin/samples/manage/') === 0) {
      if (isset($actions['delete'])) {
        unset($actions['delete']);
      }
    }
    return $actions;
  }

}