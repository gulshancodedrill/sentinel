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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

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

    // Fieldset: Company Details (start)
    $form['company_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Company Details'),
      '#weight' => -98,
      '#after_build' => [[$this, 'reorderCompanyDetailsFields']],
    ];

    // Company Email field will be handled in reorderCompanyDetailsFields after_build callback
    // This ensures it's properly placed inside Company Details fieldset and avoids duplicates

    // Company Telephone - weight 2 (second) - Always show even if empty
    if ($entity->hasField('company_tel')) {
      // Get the field value (handle empty values)
      $tel_value = '';
      if (!$entity->get('company_tel')->isEmpty()) {
        $tel_value = $entity->get('company_tel')->value;
      }
      
      // If field doesn't exist in form, create it explicitly
      if (!isset($form['company_tel'])) {
        $form['company_tel'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Company Telephone'),
          '#default_value' => $tel_value,
          '#maxlength' => 255,
          '#required' => $entity->get('company_tel')->getFieldDefinition()->isRequired(),
        ];
      }
      
      // Always ensure the field is configured and visible
      $form['company_tel']['#group'] = 'company_details';
      $form['company_tel']['#weight'] = 2;
      $form['company_tel']['#title'] = $this->t('Company Telephone');
      $form['company_tel']['#description'] = $this->t('Telephone number of the company managing installation/maintenance for the system.');
      $form['company_tel']['#access'] = TRUE;
      $form['company_tel']['#default_value'] = $tel_value;
      
      // Remove any access restrictions that might hide the field
      unset($form['company_tel']['#access_callback']);
      
      // Ensure widget is accessible and properly weighted
      if (isset($form['company_tel']['widget'])) {
        $form['company_tel']['widget']['#access'] = TRUE;
        unset($form['company_tel']['widget']['#access_callback']);
        if (isset($form['company_tel']['widget'][0])) {
          $form['company_tel']['widget'][0]['#weight'] = 2;
          $form['company_tel']['widget'][0]['#access'] = TRUE;
          if (isset($form['company_tel']['widget'][0]['value'])) {
            $form['company_tel']['widget'][0]['value']['#default_value'] = $tel_value;
          }
          unset($form['company_tel']['widget'][0]['#access_callback']);
        }
      }
      // Ensure parent weights are overridden
      if (isset($form['company_tel'][0])) {
        $form['company_tel'][0]['#weight'] = 2;
        $form['company_tel'][0]['#access'] = TRUE;
        if (isset($form['company_tel'][0]['value'])) {
          $form['company_tel'][0]['value']['#default_value'] = $tel_value;
        }
        unset($form['company_tel'][0]['#access_callback']);
      }
    }

    // Sentinel Customer ID - weight 3 (third)
    if ($entity->hasField('customer_id')) {
      $form['customer_id']['#group'] = 'company_details';
      $form['customer_id']['#weight'] = 3;
      $form['customer_id']['#title'] = $this->t('Sentinel Customer ID');
      $form['customer_id']['#description'] = $this->t('Your Sentinel Unique Customer Reference number (UCR). This can be found in your account settings.');
      // Ensure parent weights are overridden
      if (isset($form['customer_id'][0])) {
        $form['customer_id'][0]['#weight'] = 3;
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
    ];

    // Order: Country (1), Company (2), Address 1 (3), Property name (4), Property number (5), Town/City (6), Postcode (7)
    // All fields should always be visible

    // Country field - check if it exists as company_country or in address entity reference
    // Note: field_company_address might be an entity reference with nested address fields
    if ($entity->hasField('field_company_address') && isset($form['field_company_address'])) {
      // If using entity reference field, ensure it's grouped correctly
      // The address widget might show country field inside it
      $form['field_company_address']['#group'] = 'company_address';
      $form['field_company_address']['#weight'] = 0;
      $form['field_company_address']['#access'] = TRUE;
      unset($form['field_company_address']['#access_callback']);
    }
    // Also check for simple country field if it exists (company_country or company_county)
    if ($entity->hasField('company_country')) {
      if (!isset($form['company_country'])) {
        $country_value = '';
        if (!$entity->get('company_country')->isEmpty()) {
          $country_value = $entity->get('company_country')->value ?? '';
        }
        $form['company_country'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Country'),
          '#default_value' => $country_value,
          '#required' => FALSE,
        ];
      }
      $form['company_country']['#group'] = 'company_address';
      $form['company_country']['#weight'] = 1;
      $form['company_country']['#title'] = $this->t('Country');
      $form['company_country']['#access'] = TRUE;
      $form['company_country']['#description_display'] = 'after';
      unset($form['company_country']['#access_callback']);
    }
    // Also handle company_county field (may be used as Country)
    if ($entity->hasField('company_county')) {
      if (!isset($form['company_county'])) {
        $county_value = '';
        if (!$entity->get('company_county')->isEmpty()) {
          $county_value = $entity->get('company_county')->value ?? '';
        }
        $form['company_county'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Country'),
          '#default_value' => $county_value,
          '#required' => FALSE,
        ];
      }
      $form['company_county']['#group'] = 'company_address';
      $form['company_county']['#weight'] = 1;
      $form['company_county']['#title'] = $this->t('Country');
      $form['company_county']['#access'] = TRUE;
      $form['company_county']['#description_display'] = 'after';
      unset($form['company_county']['#access_callback']);
    }

    // Company field - weight 2
    if ($entity->hasField('company_name')) {
      if (!isset($form['company_name'])) {
        $company_value = '';
        if (!$entity->get('company_name')->isEmpty()) {
          $company_value = $entity->get('company_name')->value ?? '';
        }
        $form['company_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Company'),
          '#default_value' => $company_value,
          '#maxlength' => 255,
          '#required' => FALSE,
        ];
      }
      $form['company_name']['#group'] = 'company_address';
      $form['company_name']['#weight'] = 2;
      $form['company_name']['#title'] = $this->t('Company');
      $form['company_name']['#access'] = TRUE;
      $form['company_name']['#description_display'] = 'after';
      unset($form['company_name']['#access_callback']);
    }

    // Address 1 - weight 3
    if ($entity->hasField('company_address1')) {
      if (!isset($form['company_address1'])) {
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
        ];
      }
      $form['company_address1']['#group'] = 'company_address';
      $form['company_address1']['#weight'] = 3;
      $form['company_address1']['#title'] = $this->t('Address 1');
      $form['company_address1']['#access'] = TRUE;
      $form['company_address1']['#description_display'] = 'after';
      unset($form['company_address1']['#access_callback']);
    }

    // Property name - weight 4
    if ($entity->hasField('company_address2')) {
      if (!isset($form['company_address2'])) {
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
        ];
      }
      $form['company_address2']['#group'] = 'company_address';
      $form['company_address2']['#weight'] = 4;
      $form['company_address2']['#title'] = $this->t('Property name');
      $form['company_address2']['#access'] = TRUE;
      $form['company_address2']['#description_display'] = 'after';
      unset($form['company_address2']['#access_callback']);
    }

    // Property number - weight 5
    if ($entity->hasField('company_property_number')) {
      if (!isset($form['company_property_number'])) {
        $prop_num_value = '';
        if (!$entity->get('company_property_number')->isEmpty()) {
          $prop_num_value = $entity->get('company_property_number')->value ?? '';
        }
        $form['company_property_number'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Property number'),
          '#default_value' => $prop_num_value,
          '#maxlength' => 255,
          '#required' => FALSE,
        ];
      }
      $form['company_property_number']['#group'] = 'company_address';
      $form['company_property_number']['#weight'] = 5;
      $form['company_property_number']['#title'] = $this->t('Property number');
      $form['company_property_number']['#access'] = TRUE;
      $form['company_property_number']['#description_display'] = 'after';
      unset($form['company_property_number']['#access_callback']);
    }

    // Town/City - weight 6
    if ($entity->hasField('company_town')) {
      if (!isset($form['company_town'])) {
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
        ];
      }
      $form['company_town']['#group'] = 'company_address';
      $form['company_town']['#weight'] = 6;
      $form['company_town']['#title'] = $this->t('Town/City');
      $form['company_town']['#access'] = TRUE;
      $form['company_town']['#description_display'] = 'after';
      unset($form['company_town']['#access_callback']);
    }

    // Postcode - weight 7
    if ($entity->hasField('company_postcode')) {
      if (!isset($form['company_postcode'])) {
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
        ];
      }
      $form['company_postcode']['#group'] = 'company_address';
      $form['company_postcode']['#weight'] = 7;
      $form['company_postcode']['#title'] = $this->t('Postcode');
      $form['company_postcode']['#access'] = TRUE;
      $form['company_postcode']['#description_display'] = 'after';
      unset($form['company_postcode']['#access_callback']);
    }

    // Created field - show after Company Details fieldset - Always visible - Simple text field showing only date
    if ($entity->hasField('created')) {
      // Get the created value and format as date only (d-m-Y)
      $created_date = '';
      $field_item = $entity->get('created');
      if (!$field_item->isEmpty()) {
        $created_value = $field_item->value ?? '';
        if (!empty($created_value)) {
          try {
            // Try to parse the datetime value and format as date only
            $date = new \DateTime($created_value);
            $created_date = $date->format('d-m-Y');
          } catch (\Exception $e) {
            // If parsing fails, try to extract just the date part
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $created_value, $matches)) {
              $date = new \DateTime($matches[1]);
              $created_date = $date->format('d-m-Y');
            } else {
              $created_date = $created_value;
            }
          }
        }
      }
      
      // Always create as simple textfield
      $form['created'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Created'),
        '#default_value' => $created_date,
        '#description' => $this->t('E.g., 16-11-2025 When this record was created.'),
        '#weight' => -97, // After Company Details (weight -98)
        '#disabled' => TRUE,
        '#required' => FALSE,
        '#access' => TRUE,
        '#description_display' => 'after',
        '#attributes' => ['placeholder' => $this->t('E.g., 16-11-2025')],
      ];
    }

    // Updated field - show after Created field - Always visible - Simple text field showing only date
    if ($entity->hasField('updated')) {
      // Get the updated value and format as date only (d-m-Y)
      $updated_date = '';
      $field_item = $entity->get('updated');
      if (!$field_item->isEmpty()) {
        $updated_value = $field_item->value ?? '';
        if (!empty($updated_value)) {
          try {
            // Try to parse the datetime value and format as date only
            $date = new \DateTime($updated_value);
            $updated_date = $date->format('d-m-Y');
          } catch (\Exception $e) {
            // If parsing fails, try to extract just the date part
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $updated_value, $matches)) {
              $date = new \DateTime($matches[1]);
              $updated_date = $date->format('d-m-Y');
            } else {
              $updated_date = $updated_value;
            }
          }
        }
      }
      
      // Always create as simple textfield
      $form['updated'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Updated'),
        '#default_value' => $updated_date,
        '#description' => $this->t('E.g., 16-11-2025 When this record was last updated.'),
        '#weight' => -96, // After Created field (weight -97)
        '#disabled' => TRUE,
        '#required' => FALSE,
        '#access' => TRUE,
        '#description_display' => 'after',
        '#attributes' => ['placeholder' => $this->t('E.g., 16-11-2025')],
      ];
    }

    // The UCR
    if ($entity->hasField('ucr')) {
      $form['ucr']['#title'] = $this->t('The UCR');
      $form['ucr']['#description'] = $this->t('The unique customer record.');
      $form['ucr']['#weight'] = -95;
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

    // Fieldset: Job Details (start)
    $form['job_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Job Details'),
      '#weight' => -90,
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
      $form['installer_name']['#group'] = 'job_details';
      $form['installer_name']['#weight'] = 4;
      $form['installer_name']['#title'] = $this->t('Installer Name');
      $form['installer_name']['#description'] = $this->t('Name of the individual engineer who conducted the work and subsequent SystemCheck.');
    }

    // Installer Email
    if ($entity->hasField('installer_email')) {
      $form['installer_email']['#group'] = 'job_details';
      $form['installer_email']['#weight'] = 5;
      $form['installer_email']['#title'] = $this->t('Installer Email');
      $form['installer_email']['#description'] = $this->t('The email address of the installer who conducted the work and subsequent SystemCheck.');
    }

    // Installer Company
    if ($entity->hasField('installer_company')) {
      $form['installer_company']['#group'] = 'job_details';
      $form['installer_company']['#weight'] = 6;
      $form['installer_company']['#title'] = $this->t('Installer Company');
      $form['installer_company']['#description'] = $this->t('Please provide the name of the company managing installation/maintenance on this system.');
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
      $form['project_id']['#group'] = 'job_details';
      $form['project_id']['#weight'] = 10;
      $form['project_id']['#title'] = $this->t('Project ID');
      $form['project_id']['#description'] = $this->t('The project ID. Required for claiming boiler manufacturer contract support.');
    }

    // Date Sent
    if ($entity->hasField('date_sent')) {
      $form['date_sent']['#group'] = 'job_details';
      $form['date_sent']['#weight'] = 11;
      $form['date_sent']['#title'] = $this->t('Date Sent');
      $form['date_sent']['#description'] = $this->t('E.g., 16-11-2025 Date that the water sample was sent to Sentinel.');
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

    // Date Installed
    if ($entity->hasField('date_installed')) {
      $form['date_installed']['#group'] = 'job_details';
      $form['date_installed']['#weight'] = 14;
      $form['date_installed']['#title'] = $this->t('Date Installed');
      $form['date_installed']['#description'] = $this->t('E.g., 16-11-2025 Date that the boiler was installed.');
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

    // Fieldset: Address (nested in System Details)
    $form['address'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address'),
      '#description' => $this->t('The full address of where the system is located.'),
      '#weight' => 3,
      '#group' => 'system_details',
      '#after_build' => [[$this, 'reorderAddressFields']],
    ];

    // Order: Country (1), Property number (2), Address 1 (3), Property name (4), Town/City (5), Postcode (6)
    // Note: These may use entity reference fields - for now using simple fields
    
    // Country - check if it exists in address entity reference or as a simple field
    if ($entity->hasField('field_sentinel_sample_address')) {
      // If using entity reference field, ensure it's grouped correctly
      $form['field_sentinel_sample_address']['#group'] = 'address';
      $form['field_sentinel_sample_address']['#weight'] = 0;
    }
    if ($entity->hasField('county')) {
      // Note: county field might be used for country, or there might be a country field
      $form['county']['#group'] = 'address';
      $form['county']['#weight'] = 1;
      $form['county']['#title'] = $this->t('Country');
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

    // Fieldset: Result Details (start)
    $form['result_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Result Details'),
      '#weight' => -70,
    ];

    // Date Booked In
    if ($entity->hasField('date_booked')) {
      $form['date_booked']['#group'] = 'result_details';
      $form['date_booked']['#weight'] = 1;
      $form['date_booked']['#title'] = $this->t('Date Booked In');
      $form['date_booked']['#description'] = $this->t('E.g., 16-11-2025 The date the sample was booked in at the test facility.');
      $form['date_booked']['#disabled'] = TRUE;
    }

    // Date Processed
    if ($entity->hasField('date_processed')) {
      $form['date_processed']['#group'] = 'result_details';
      $form['date_processed']['#weight'] = 2;
      $form['date_processed']['#title'] = $this->t('Date Processed');
      $form['date_processed']['#description'] = $this->t('E.g., 16-11-2025 The date the sample was processed.');
      $form['date_processed']['#disabled'] = TRUE;
    }

    // Date Reported
    if ($entity->hasField('date_reported')) {
      $form['date_reported']['#group'] = 'result_details';
      $form['date_reported']['#weight'] = 3;
      $form['date_reported']['#title'] = $this->t('Date Reported');
      $form['date_reported']['#description'] = $this->t('E.g., 16-11-2025 The date the results were reported.');
      $form['date_reported']['#disabled'] = TRUE;
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

    // Lab Ref
    if ($entity->hasField('lab_ref')) {
      $form['lab_ref']['#group'] = 'result_details';
      $form['lab_ref']['#weight'] = 8;
      $form['lab_ref']['#title'] = $this->t('Lab Ref');
      $form['lab_ref']['#description'] = $this->t('The lab reference of the sample (used by testing lab).');
      $form['lab_ref']['#disabled'] = TRUE;
    }

    // Pack Type
    if ($entity->hasField('pack_type')) {
      $form['pack_type']['#group'] = 'result_details';
      $form['pack_type']['#weight'] = 9;
      $form['pack_type']['#title'] = $this->t('Pack Type');
      $form['pack_type']['#description'] = $this->t('The type of the pack (dictates the type of test being run).');
      $form['pack_type']['#disabled'] = TRUE;
    }

    // Card Complete
    if ($entity->hasField('card_complete')) {
      $form['card_complete']['#group'] = 'result_details';
      $form['card_complete']['#weight'] = 10;
      $form['card_complete']['#title'] = $this->t('Card Complete');
      $form['card_complete']['#description'] = $this->t('If the card is complete.');
      $form['card_complete']['#disabled'] = TRUE;
    }

    // On Hold
    if ($entity->hasField('on_hold')) {
      $form['on_hold']['#group'] = 'result_details';
      $form['on_hold']['#weight'] = 11;
      $form['on_hold']['#title'] = $this->t('On Hold');
      $form['on_hold']['#description'] = $this->t('If the sample is on hold.');
      $form['on_hold']['#disabled'] = TRUE;
    }

    // Overall Pass/Fail
    if ($entity->hasField('pass_fail')) {
      $form['pass_fail']['#group'] = 'result_details';
      $form['pass_fail']['#weight'] = 12;
      $form['pass_fail']['#title'] = $this->t('Overall Pass/Fail');
      $form['pass_fail']['#description'] = $this->t('The overall pass or fail mark of the sample.');
      $form['pass_fail']['#disabled'] = TRUE;
    }

    // Appearance Result
    if ($entity->hasField('appearance_result')) {
      $form['appearance_result']['#group'] = 'result_details';
      $form['appearance_result']['#weight'] = 13;
      $form['appearance_result']['#title'] = $this->t('Appearance Result');
      $form['appearance_result']['#description'] = $this->t('The result of the appearance test.');
      $form['appearance_result']['#disabled'] = TRUE;
    }

    // Appearance Pass/Fail
    if ($entity->hasField('appearance_pass_fail')) {
      $form['appearance_pass_fail']['#group'] = 'result_details';
      $form['appearance_pass_fail']['#weight'] = 14;
      $form['appearance_pass_fail']['#title'] = $this->t('Appearance Pass/Fail');
      $form['appearance_pass_fail']['#description'] = $this->t('The pass and fail mark for the appearance test.');
      $form['appearance_pass_fail']['#disabled'] = TRUE;
    }

    // Mains Conductivity Result
    if ($entity->hasField('mains_cond_result')) {
      $form['mains_cond_result']['#group'] = 'result_details';
      $form['mains_cond_result']['#weight'] = 15;
      $form['mains_cond_result']['#title'] = $this->t('Mains Conductivity Result');
      $form['mains_cond_result']['#description'] = $this->t('The result of the mains conductivity test.');
      $form['mains_cond_result']['#disabled'] = TRUE;
    }

    // System Conductivity Result
    if ($entity->hasField('sys_cond_result')) {
      $form['sys_cond_result']['#group'] = 'result_details';
      $form['sys_cond_result']['#weight'] = 16;
      $form['sys_cond_result']['#title'] = $this->t('System Conductivity Result');
      $form['sys_cond_result']['#description'] = $this->t('The result of the system conductivity test.');
      $form['sys_cond_result']['#disabled'] = TRUE;
    }

    // Conductivity Pass/Fail
    if ($entity->hasField('cond_pass_fail')) {
      $form['cond_pass_fail']['#group'] = 'result_details';
      $form['cond_pass_fail']['#weight'] = 17;
      $form['cond_pass_fail']['#title'] = $this->t('Conductivity Pass/Fail');
      $form['cond_pass_fail']['#description'] = $this->t('The pass and fail mark for the conductivity test.');
      $form['cond_pass_fail']['#disabled'] = TRUE;
    }

    // Mains Chlorine Result
    if ($entity->hasField('mains_cl_result')) {
      $form['mains_cl_result']['#group'] = 'result_details';
      $form['mains_cl_result']['#weight'] = 18;
      $form['mains_cl_result']['#title'] = $this->t('Mains Chlorine Result');
      $form['mains_cl_result']['#description'] = $this->t('The result of the chlorine test.');
      $form['mains_cl_result']['#disabled'] = TRUE;
    }

    // System Chlorine Result
    if ($entity->hasField('sys_cl_result')) {
      $form['sys_cl_result']['#group'] = 'result_details';
      $form['sys_cl_result']['#weight'] = 19;
      $form['sys_cl_result']['#title'] = $this->t('System Chlorine Result');
      $form['sys_cl_result']['#description'] = $this->t('The result of the chlorine test.');
      $form['sys_cl_result']['#disabled'] = TRUE;
    }

    // Chlorine Pass/Fail
    if ($entity->hasField('cl_pass_fail')) {
      $form['cl_pass_fail']['#group'] = 'result_details';
      $form['cl_pass_fail']['#weight'] = 20;
      $form['cl_pass_fail']['#title'] = $this->t('Chlorine Pass/Fail');
      $form['cl_pass_fail']['#description'] = $this->t('The pass and fail mark for the chlorine test.');
      $form['cl_pass_fail']['#disabled'] = TRUE;
    }

    // Iron Result
    if ($entity->hasField('iron_result')) {
      $form['iron_result']['#group'] = 'result_details';
      $form['iron_result']['#weight'] = 21;
      $form['iron_result']['#title'] = $this->t('Iron Result');
      $form['iron_result']['#description'] = $this->t('The result of the iron test.');
      $form['iron_result']['#disabled'] = TRUE;
    }

    // Iron Pass/Fail
    if ($entity->hasField('iron_pass_fail')) {
      $form['iron_pass_fail']['#group'] = 'result_details';
      $form['iron_pass_fail']['#weight'] = 22;
      $form['iron_pass_fail']['#title'] = $this->t('Iron Pass/Fail');
      $form['iron_pass_fail']['#description'] = $this->t('The pass and fail mark for the iron test.');
      $form['iron_pass_fail']['#disabled'] = TRUE;
    }

    // Copper Result
    if ($entity->hasField('copper_result')) {
      $form['copper_result']['#group'] = 'result_details';
      $form['copper_result']['#weight'] = 23;
      $form['copper_result']['#title'] = $this->t('Copper Result');
      $form['copper_result']['#description'] = $this->t('The result of the copper test.');
      $form['copper_result']['#disabled'] = TRUE;
    }

    // Copper Pass/Fail
    if ($entity->hasField('copper_pass_fail')) {
      $form['copper_pass_fail']['#group'] = 'result_details';
      $form['copper_pass_fail']['#weight'] = 24;
      $form['copper_pass_fail']['#title'] = $this->t('Copper Pass/Fail');
      $form['copper_pass_fail']['#description'] = $this->t('The pass and fail mark for the copper test.');
      $form['copper_pass_fail']['#disabled'] = TRUE;
    }

    // Aluminium Result
    if ($entity->hasField('aluminium_result')) {
      $form['aluminium_result']['#group'] = 'result_details';
      $form['aluminium_result']['#weight'] = 25;
      $form['aluminium_result']['#title'] = $this->t('Aluminium Result');
      $form['aluminium_result']['#description'] = $this->t('The result of the aluminium test.');
      $form['aluminium_result']['#disabled'] = TRUE;
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

    // System Calcium Result
    if ($entity->hasField('sys_calcium_result')) {
      $form['sys_calcium_result']['#group'] = 'result_details';
      $form['sys_calcium_result']['#weight'] = 28;
      $form['sys_calcium_result']['#title'] = $this->t('System Calcium Result');
      $form['sys_calcium_result']['#description'] = $this->t('The result of the system calcium test.');
      $form['sys_calcium_result']['#disabled'] = TRUE;
    }

    // Calcium Pass/Fail
    if ($entity->hasField('calcium_pass_fail')) {
      $form['calcium_pass_fail']['#group'] = 'result_details';
      $form['calcium_pass_fail']['#weight'] = 29;
      $form['calcium_pass_fail']['#title'] = $this->t('Calcium Pass/Fail');
      $form['calcium_pass_fail']['#description'] = $this->t('The pass and fail mark for the calcium test.');
      $form['calcium_pass_fail']['#disabled'] = TRUE;
    }

    // pH Result
    if ($entity->hasField('ph_result')) {
      $form['ph_result']['#group'] = 'result_details';
      $form['ph_result']['#weight'] = 30;
      $form['ph_result']['#title'] = $this->t('pH Result');
      $form['ph_result']['#description'] = $this->t('The result of the pH test.');
      $form['ph_result']['#disabled'] = TRUE;
    }

    // pH Pass/Fail
    if ($entity->hasField('ph_pass_fail')) {
      $form['ph_pass_fail']['#group'] = 'result_details';
      $form['ph_pass_fail']['#weight'] = 31;
      $form['ph_pass_fail']['#title'] = $this->t('pH Pass/Fail');
      $form['ph_pass_fail']['#description'] = $this->t('The pass and fail mark for the pH test.');
      $form['ph_pass_fail']['#disabled'] = TRUE;
    }

    // Inhibitor Result
    if ($entity->hasField('sentinel_x100_result')) {
      $form['sentinel_x100_result']['#group'] = 'result_details';
      $form['sentinel_x100_result']['#weight'] = 32;
      $form['sentinel_x100_result']['#title'] = $this->t('Inhibitor Result');
      $form['sentinel_x100_result']['#description'] = $this->t('The result of the Inhibitor test.');
      $form['sentinel_x100_result']['#disabled'] = TRUE;
    }

    // Inhibitor Pass/Fail
    if ($entity->hasField('sentinel_x100_pass_fail')) {
      $form['sentinel_x100_pass_fail']['#group'] = 'result_details';
      $form['sentinel_x100_pass_fail']['#weight'] = 33;
      $form['sentinel_x100_pass_fail']['#title'] = $this->t('Inhibitor Pass/Fail');
      $form['sentinel_x100_pass_fail']['#description'] = $this->t('The pass and fail mark for the Inhibitor test.');
      $form['sentinel_x100_pass_fail']['#disabled'] = TRUE;
    }

    // Molybdenum Result
    if ($entity->hasField('molybdenum_result')) {
      $form['molybdenum_result']['#group'] = 'result_details';
      $form['molybdenum_result']['#weight'] = 34;
      $form['molybdenum_result']['#title'] = $this->t('Molybdenum Result');
      $form['molybdenum_result']['#description'] = $this->t('The result of the Molybdenum test.');
      $form['molybdenum_result']['#disabled'] = TRUE;
    }

    // Molybdenum Pass/Fail
    if ($entity->hasField('molybdenum_pass_fail')) {
      $form['molybdenum_pass_fail']['#group'] = 'result_details';
      $form['molybdenum_pass_fail']['#weight'] = 35;
      $form['molybdenum_pass_fail']['#title'] = $this->t('Molybdenum Pass/Fail');
      $form['molybdenum_pass_fail']['#description'] = $this->t('The pass and fail mark for the Molybdenum test.');
      $form['molybdenum_pass_fail']['#disabled'] = TRUE;
    }

    // XXX Result (boron_result)
    if ($entity->hasField('boron_result')) {
      $form['boron_result']['#group'] = 'result_details';
      $form['boron_result']['#weight'] = 36;
      $form['boron_result']['#title'] = $this->t('XXX Result');
      $form['boron_result']['#description'] = $this->t('The result of the XXX test.');
      $form['boron_result']['#disabled'] = TRUE;
    }

    // XXX Pass/Fail (boron_pass_fail)
    if ($entity->hasField('boron_pass_fail')) {
      $form['boron_pass_fail']['#group'] = 'result_details';
      $form['boron_pass_fail']['#weight'] = 37;
      $form['boron_pass_fail']['#title'] = $this->t('XXX Pass/Fail');
      $form['boron_pass_fail']['#description'] = $this->t('The pass and fail mark for the XXX test.');
      $form['boron_pass_fail']['#disabled'] = TRUE;
    }

    // Manganese Result
    if ($entity->hasField('manganese_result')) {
      $form['manganese_result']['#group'] = 'result_details';
      $form['manganese_result']['#weight'] = 38;
      $form['manganese_result']['#title'] = $this->t('Manganese Result');
      $form['manganese_result']['#description'] = $this->t('The result of the Manganese test.');
      $form['manganese_result']['#disabled'] = TRUE;
    }

    // Manganese Pass/Fail
    if ($entity->hasField('manganese_pass_fail')) {
      $form['manganese_pass_fail']['#group'] = 'result_details';
      $form['manganese_pass_fail']['#weight'] = 39;
      $form['manganese_pass_fail']['#title'] = $this->t('Manganese Pass/Fail');
      $form['manganese_pass_fail']['#description'] = $this->t('The pass and fail mark for the Manganese test.');
      $form['manganese_pass_fail']['#disabled'] = TRUE;
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

    // Duplicate Of
    if ($entity->hasField('duplicate_of')) {
      $form['duplicate_of']['#group'] = 'result_details';
      $form['duplicate_of']['#weight'] = 42;
      $form['duplicate_of']['#title'] = $this->t('Duplciate Of');
      $form['duplicate_of']['#description'] = $this->t('This sample is a duplicate of.');
      $form['duplicate_of']['#disabled'] = TRUE;
    }

    // Legacy Sample
    if ($entity->hasField('legacy')) {
      $form['legacy']['#group'] = 'result_details';
      $form['legacy']['#weight'] = 43;
      $form['legacy']['#title'] = $this->t('Legacy Sample');
      $form['legacy']['#description'] = $this->t('If this is a legacy sample or not.');
      $form['legacy']['#disabled'] = TRUE;
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Update Pack Reference Number
    $pack_ref = $form_state->getValue('pack_reference_number');
    if ($pack_ref !== NULL) {
      $this->entity->set('pack_reference_number', (string) $pack_ref);
    }

    // Update Company Email (handle both widget and direct field structures)
    if ($this->entity->hasField('company_email')) {
      $email_value = $form_state->getValue('company_email');
      if (is_array($email_value) && isset($email_value[0]['value'])) {
        $email_value = $email_value[0]['value'];
      }
      if ($email_value !== NULL) {
        $this->entity->set('company_email', $email_value);
      }
    }

    // Update Company Telephone (handle both widget and direct field structures)
    if ($this->entity->hasField('company_tel')) {
      $tel_value = $form_state->getValue('company_tel');
      if (is_array($tel_value) && isset($tel_value[0]['value'])) {
        $tel_value = $tel_value[0]['value'];
      }
      if ($tel_value !== NULL) {
        $this->entity->set('company_tel', $tel_value);
      }
    }

    // Update Company Address fields
    $company_address_fields = [
      'company_country',
      'company_county',
      'company_name',
      'company_address1',
      'company_address2',
      'company_property_number',
      'company_town',
      'company_postcode',
    ];
    
    foreach ($company_address_fields as $field_name) {
      if ($this->entity->hasField($field_name)) {
        $field_value = $form_state->getValue($field_name);
        if (is_array($field_value) && isset($field_value[0]['value'])) {
          $field_value = $field_value[0]['value'];
        }
        if ($field_value !== NULL) {
          $this->entity->set($field_name, $field_value);
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

    $form_state->setRedirectUrl($entity->toUrl('canonical'));
  }

  /**
   * After build callback to ensure Company Details fields are in correct order.
   */
  public function reorderCompanyDetailsFields(array $element, FormStateInterface $form_state) {
    // Ensure correct order: Company Email (1), Company Telephone (2), Sentinel Customer ID (3)
    // Always ensure company_email is visible and inside Company Details fieldset
    // Get the value from the entity's company_email field
    $entity = $this->entity;
    $email_value = '';
    if ($entity && $entity->hasField('company_email')) {
      $field_item = $entity->get('company_email');
      if (!$field_item->isEmpty()) {
        $email_value = $field_item->value ?? '';
      }
    }
    
    if (isset($element['company_email'])) {
      $element['company_email']['#weight'] = 1;
      $element['company_email']['#group'] = 'company_details';
      $element['company_email']['#access'] = TRUE;
      $element['company_email']['#title'] = $this->t('Company Email');
      $element['company_email']['#description'] = $this->t('Email address of the company managing installation/maintenance. A copy of the SystemCheck report will be made available to this email address.');
      $element['company_email']['#description_display'] = 'after';
      $element['company_email']['#required'] = FALSE;
      $element['company_email']['#default_value'] = $email_value;
      unset($element['company_email']['#access_callback']);
      
      // Ensure widget is accessible and has the correct value
      if (isset($element['company_email']['widget'])) {
        $element['company_email']['widget']['#access'] = TRUE;
        unset($element['company_email']['widget']['#access_callback']);
        if (isset($element['company_email']['widget'][0])) {
          $element['company_email']['widget'][0]['#weight'] = 1;
          $element['company_email']['widget'][0]['#access'] = TRUE;
          if (isset($element['company_email']['widget'][0]['value'])) {
            $element['company_email']['widget'][0]['value']['#default_value'] = $email_value;
            $element['company_email']['widget'][0]['value']['#access'] = TRUE;
            $element['company_email']['widget'][0]['value']['#required'] = FALSE;
          }
          unset($element['company_email']['widget'][0]['#access_callback']);
        }
      }
      if (isset($element['company_email'][0])) {
        $element['company_email'][0]['#weight'] = 1;
        $element['company_email'][0]['#access'] = TRUE;
        if (isset($element['company_email'][0]['value'])) {
          $element['company_email'][0]['value']['#default_value'] = $email_value;
          $element['company_email'][0]['value']['#access'] = TRUE;
          $element['company_email'][0]['value']['#required'] = FALSE;
        }
        unset($element['company_email'][0]['#access_callback']);
      }
    }
    else {
      // Field doesn't exist - create it in after_build as fallback
      $entity = $this->entity;
      if ($entity && $entity->hasField('company_email')) {
        $email_value = '';
        if (!$entity->get('company_email')->isEmpty()) {
          $email_value = $entity->get('company_email')->value;
        }
        $element['company_email'] = [
          '#type' => 'email',
          '#title' => $this->t('Company Email'),
          '#default_value' => $email_value,
          '#required' => FALSE,
          '#weight' => 1,
          '#group' => 'company_details',
          '#description' => $this->t('Email address of the company managing installation/maintenance. A copy of the SystemCheck report will be made available to this email address.'),
          '#description_display' => 'after',
          '#access' => TRUE,
        ];
      }
    }
    
    // Ensure company_email is always grouped inside Company Details
    if (isset($element['company_email'])) {
      $element['company_email']['#group'] = 'company_details';
    }
    if (isset($element['company_tel'])) {
      $element['company_tel']['#weight'] = 2;
      $element['company_tel']['#access'] = TRUE;
      // Ensure widget is accessible
      if (isset($element['company_tel']['widget'])) {
        $element['company_tel']['widget']['#access'] = TRUE;
        if (isset($element['company_tel']['widget'][0])) {
          $element['company_tel']['widget'][0]['#weight'] = 2;
          $element['company_tel']['widget'][0]['#access'] = TRUE;
        }
      }
      if (isset($element['company_tel'][0])) {
        $element['company_tel'][0]['#weight'] = 2;
        $element['company_tel'][0]['#access'] = TRUE;
      }
    }
    if (isset($element['customer_id'])) {
      $element['customer_id']['#weight'] = 3;
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
    // Ensure correct order: Country (1), Company (2), Address 1 (3), Property name (4), Property number (5), Town/City (6), Postcode (7)
    // All fields should always be visible
    
    // Country - could be field_company_address (entity ref) or company_country
    if (isset($element['field_company_address'])) {
      $element['field_company_address']['#weight'] = 0;
      $element['field_company_address']['#access'] = TRUE;
      unset($element['field_company_address']['#access_callback']);
    }
    if (isset($element['company_country'])) {
      $element['company_country']['#weight'] = 1;
      $element['company_country']['#access'] = TRUE;
      $element['company_country']['#description_display'] = 'after';
      unset($element['company_country']['#access_callback']);
    }
    if (isset($element['company_county'])) {
      $element['company_county']['#weight'] = 1;
      $element['company_county']['#access'] = TRUE;
      $element['company_county']['#title'] = $this->t('Country');
      $element['company_county']['#description_display'] = 'after';
      unset($element['company_county']['#access_callback']);
    }
    
    // Company - weight 2
    if (isset($element['company_name'])) {
      $element['company_name']['#weight'] = 2;
      $element['company_name']['#access'] = TRUE;
      $element['company_name']['#description_display'] = 'after';
      unset($element['company_name']['#access_callback']);
    }
    
    // Address 1 - weight 3
    if (isset($element['company_address1'])) {
      $element['company_address1']['#weight'] = 3;
      $element['company_address1']['#access'] = TRUE;
      $element['company_address1']['#description_display'] = 'after';
      unset($element['company_address1']['#access_callback']);
    }
    
    // Property name - weight 4
    if (isset($element['company_address2'])) {
      $element['company_address2']['#weight'] = 4;
      $element['company_address2']['#access'] = TRUE;
      $element['company_address2']['#description_display'] = 'after';
      unset($element['company_address2']['#access_callback']);
    }
    
    // Property number - weight 5
    if (isset($element['company_property_number'])) {
      $element['company_property_number']['#weight'] = 5;
      $element['company_property_number']['#access'] = TRUE;
      $element['company_property_number']['#description_display'] = 'after';
      unset($element['company_property_number']['#access_callback']);
    }
    
    // Town/City - weight 6
    if (isset($element['company_town'])) {
      $element['company_town']['#weight'] = 6;
      $element['company_town']['#access'] = TRUE;
      $element['company_town']['#description_display'] = 'after';
      unset($element['company_town']['#access_callback']);
    }
    
    // Postcode - weight 7
    if (isset($element['company_postcode'])) {
      $element['company_postcode']['#weight'] = 7;
      $element['company_postcode']['#access'] = TRUE;
      $element['company_postcode']['#description_display'] = 'after';
      unset($element['company_postcode']['#access_callback']);
    }
    
    return $element;
  }

  /**
   * After build callback to ensure Address fields (in System Details) are in correct order.
   */
  public function reorderAddressFields(array $element, FormStateInterface $form_state) {
    // Ensure correct order: Country (1), Property number (2), Address 1 (3), Property name (4), Town/City (5), Postcode (6)
    
    // Country - could be field_sentinel_sample_address (entity ref) or county field
    if (isset($element['field_sentinel_sample_address'])) {
      $element['field_sentinel_sample_address']['#weight'] = 0;
    }
    if (isset($element['county'])) {
      $element['county']['#weight'] = 1;
    }
    
    // Property number - weight 2
    if (isset($element['property_number'])) {
      $element['property_number']['#weight'] = 2;
    }
    
    // Address 1 - weight 3
    if (isset($element['street'])) {
      $element['street']['#weight'] = 3;
    }
    
    // Property name - weight 4
    if (isset($element['property_name'])) {
      $element['property_name']['#weight'] = 4;
    }
    
    // Town/City - weight 5
    if (isset($element['town_city'])) {
      $element['town_city']['#weight'] = 5;
    }
    
    // Postcode - weight 6
    if (isset($element['postcode'])) {
      $element['postcode']['#weight'] = 6;
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
