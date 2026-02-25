<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Drupal\sentinel_portal_entities\Service\SentinelSampleValidation;

/**
 * Anonymous sample details form - allows adding details to existing sample.
 * 
 * This form is similar to SentinelSampleSubmissionForm but:
 * - Excludes: installer_name, installer_email, pack_reference_number, pack_reference_number_confirm
 * - Loads existing sample and pre-fills values
 * - Updates the sample instead of creating new one
 */
class AnonymousSampleDetailsForm extends SentinelSampleSubmissionForm {

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
    return 'anonymous_sample_details_form';
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
    $this->sample = $storage->load($sample_id);

    if (!$this->sample) {
      $this->messenger()->addError($this->t('Sample not found.'));
      return $form;
    }

    // Check if user is in same session (submitted PRN in this session)
    // This allows bypassing verification code if they click "Add details" immediately
    $session = $this->getRequest()->getSession();
    $session_whitelist_key = 'sentinel_sample_add_details_whitelist_' . $sample_id;
    $session_whitelist_timestamp = $session->get($session_whitelist_key);
    
    // Maximum age for session flag: 30 minutes (1800 seconds)
    $max_age = 1800;
    $is_same_session = FALSE;
    if ($session_whitelist_timestamp !== NULL) {
      $age = \Drupal::time()->getRequestTime() - $session_whitelist_timestamp;
      if ($age >= 0 && $age <= $max_age) {
        $is_same_session = TRUE;
        // Mark as verified for this session (so isSampleVerified returns TRUE)
        $session_verified_key = 'sentinel_sample_verified_' . $sample_id;
        $session->set($session_verified_key, TRUE);
        
        \Drupal::logger('sentinel_portal_sample')->info('Same-session bypass: User accessing details form for sample @sample_id without verification code (session age: @age seconds)', [
          '@sample_id' => $sample_id,
          '@age' => $age,
        ]);
      }
      else {
        // Flag is too old, remove it
        $session->remove($session_whitelist_key);
      }
    }

    $is_verified = $this->isSampleVerified($sample_id);

    if (!$is_verified) {
      $form['#title'] = $this->t('Enter Verification Code');
      $form['verification_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Verification code'),
        '#required' => TRUE,
        '#maxlength' => 7,
        '#description' => $this->t('Enter the verification code sent to your email.'),
      ];
      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Verify'),
        '#submit' => ['::submitVerificationCode'],
      ];
      return $form;
    }

    // Check if sample already has both addresses (new or legacy fields)
    $has_company_address = FALSE;
    if ($this->sample->hasField('field_company_address') && !$this->sample->get('field_company_address')->isEmpty()) {
      $has_company_address = TRUE;
    }
    // Fall back to legacy field
    elseif ($this->sample->hasField('sentinel_company_address_target_id')) {
      $legacy_company_id = $this->sample->get('sentinel_company_address_target_id')->value;
      $has_company_address = !empty($legacy_company_id);
    }

    $has_system_address = FALSE;
    if ($this->sample->hasField('field_sentinel_sample_address') && !$this->sample->get('field_sentinel_sample_address')->isEmpty()) {
      $has_system_address = TRUE;
    }
    // Fall back to legacy field
    elseif ($this->sample->hasField('sentinel_sample_address_target_id')) {
      $legacy_system_id = $this->sample->get('sentinel_sample_address_target_id')->value;
      $has_system_address = !empty($legacy_system_id);
    }

    // If both addresses exist, show message instead of form
    if ($has_company_address && $has_system_address) {
      $prn = $this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()
        ? $this->sample->get('pack_reference_number')->value
        : $this->t('N/A');
      
      $form['#title'] = $this->t('Sample Already Submitted');
      $form['message'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<p><strong>' . $this->t('This record already exists.') . '</strong></p>' .
          '<p>' . $this->t('A sample with Packet Reference Number @prn has already been submitted with complete details.', [
            '@prn' => $prn,
          ]) . '</p>' .
          '</div>',
        '#weight' => -10,
      ];
      return $form;
    }

    // Build the form using parent method
    $form = parent::buildForm($form, $form_state);

    // Remove pack reference confirm field for anonymous details.
    unset($form['pack_reference_number_confirm']);

    // Disable pack reference and installer fields.
    if (isset($form['pack_reference_number'])) {
      $form['pack_reference_number']['#disabled'] = TRUE;
    }
    if (isset($form['job_details']['installer_name'])) {
      $form['job_details']['installer_name']['#disabled'] = TRUE;
    }
    if (isset($form['job_details']['installer_email'])) {
      $form['job_details']['installer_email']['#disabled'] = TRUE;
    }

    // Update title
    $form['#title'] = $this->t('Add Sample Details');

    // Update help text
    $form['help_text'] = [
      '#markup' => $this->t('Please complete the details below for your sample. Mandatory fields are marked with a red asterisk (*).'),
      '#weight' => -50,
    ];

    // Pre-fill form values from existing sample
    $this->prefillFormFromSample($form, $form_state);

    return $form;
  }

  /**
   * Submit handler for verification code gate.
   */
  public function submitVerificationCode(array &$form, FormStateInterface $form_state) {
    $sample_id = $this->getRequest()->attributes->get('sample_id');
    if (!$sample_id) {
      $this->messenger()->addError($this->t('Invalid sample.'));
      return;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $sample = $storage->load($sample_id);
    if (!$sample) {
      $this->messenger()->addError($this->t('Sample not found.'));
      return;
    }

    $entered = trim((string) $form_state->getValue('verification_code'));
    $stored = '';
    if ($sample->hasField('verification_code') && !$sample->get('verification_code')->isEmpty()) {
      $stored = (string) $sample->get('verification_code')->value;
    }

    if ($stored === '' || $entered !== $stored) {
      $form_state->setErrorByName('verification_code', $this->t('Verification code is incorrect.'));
      return;
    }

    $session_key = 'sentinel_sample_verified_' . $sample_id;
    $this->getRequest()->getSession()->set($session_key, TRUE);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Check if the current session is verified for this sample.
   *
   * @param int|string $sample_id
   *   The sample ID.
   *
   * @return bool
   *   TRUE when verified, FALSE otherwise.
   */
  protected function isSampleVerified($sample_id) {
    $session = $this->getRequest()->getSession();
    $session_key = 'sentinel_sample_verified_' . $sample_id;
    return $session->has($session_key) && $session->get($session_key) === TRUE;
  }

  /**
   * {@inheritdoc}
   * 
   * Override validation to skip client check and PRN validation for anonymous users.
   * The PRN already exists in the sample, so we just need to include it in validation data.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $sample_id = $this->getRequest()->attributes->get('sample_id');
    $trigger = $form_state->getTriggeringElement();
    $is_verification_submit = $trigger && !empty($trigger['#submit']) && in_array('::submitVerificationCode', $trigger['#submit'], TRUE);

    if ($is_verification_submit || ($sample_id && !$this->isSampleVerified($sample_id))) {
      $entered = trim((string) $form_state->getValue('verification_code'));
      if ($entered === '') {
        $form_state->setErrorByName('verification_code', $this->t('Verification code is required.'));
      }
      return;
    }

    // Skip client check for anonymous users - they don't have a client
    // Skip PRN validation since it already exists in the sample
    
    // Get the pack reference number from the existing sample
    $pack_reference_number = '';
    if ($this->sample && $this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()) {
      $pack_reference_number = trim($this->sample->get('pack_reference_number')->value);
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
    $top_level_fields = ['company', 'company_address_1', 'company_property_name',
                         'company_town_city', 'company_postcode', 'company_telephone', 'company_email',
                         'sentinel_customer_id', 'address_1', 'property_name', 'property_number',
                         'town_city', 'postcode', 'county', 'landlord', 'installer_company', 'boiler_manufacturer', 'system_age', 'boiler_type',
                         'project_id', 'date_sent', 'uprn', 'boiler_id', 'date_installed'];
    foreach ($top_level_fields as $field) {
      if (in_array($field, $date_fields, TRUE)) {
        $validation_data[$field] = $normalized_dates[$field];
        continue;
      }

      if (array_key_exists($field, $form_values)) {
        $value = $form_values[$field];

        if ($value instanceof \Drupal\Core\Datetime\DrupalDateTime) {
          $value = $this->normalizeDateFormValue($value);
        }

        if (is_array($value)) {
          continue;
        }

        $validation_data[$field] = is_string($value) ? trim($value) : $value;
      }
    }
    
    // Ensure property number propagates even if the form values are flattened.
    $property_number_value = $form_state->getValue([
      'system_details',
      'address',
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
      if (isset($form_values['company_address']['county'])) {
        $validation_data['company_county'] = trim($form_values['company_address']['county']);
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

    if (!empty($validation_data['property_number'])) {
      $module_handler = \Drupal::moduleHandler();
      if ($module_handler->moduleExists('sentinel_addresses')) {
        $legacy_address =& $validation_data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0];
        if (empty($legacy_address['sub_premise'])) {
          $legacy_address['sub_premise'] = $validation_data['property_number'];
        }
      }
    }

    // Add PRN from existing sample to validation data
    if (!empty($pack_reference_number)) {
      $validation_data['pack_reference_number'] = $pack_reference_number;
    }

    // Add customer_id from existing sample if not already set from form
    if (empty($validation_data['customer_id']) && $this->sample && $this->sample->hasField('customer_id') && !$this->sample->get('customer_id')->isEmpty()) {
      $validation_data['customer_id'] = $this->sample->get('customer_id')->value;
    }

    // Validate using the validation service (static method)
    $errors = SentinelSampleValidation::validateSample($validation_data);

    // Display validation errors
    foreach ($errors as $field => $message) {
      // Map field names to form element paths if needed
      $form_field = $field;
      
      // Handle nested fields
      if ($field === 'pack_reference_number') {
        // PRN is not in the form, so set a general error
        $form_state->setErrorByName('', $message);
      }
      elseif ($field === 'customer_id') {
        $form_state->setErrorByName('company_details][sentinel_customer_id', $message);
      }
      else {
        $form_state->setErrorByName($form_field, $message);
      }
    }
  }

  /**
   * Pre-fill form values from the existing sample entity.
   */
  protected function prefillFormFromSample(array &$form, FormStateInterface $form_state) {
    if (!$this->sample) {
      return;
    }

    // Pack reference number (and confirm).
    if ($this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()) {
      $pack_reference_number = $this->sample->get('pack_reference_number')->value;
      if (isset($form['pack_reference_number'])) {
        $form['pack_reference_number']['#default_value'] = $pack_reference_number;
      }
      if (isset($form['pack_reference_number_confirm'])) {
        $form['pack_reference_number_confirm']['#default_value'] = $pack_reference_number;
      }
    }

    // Installer details.
    if ($this->sample->hasField('installer_name') && !$this->sample->get('installer_name')->isEmpty()) {
      if (isset($form['job_details']['installer_name'])) {
        $form['job_details']['installer_name']['#default_value'] = $this->sample->get('installer_name')->value;
      }
    }
    if ($this->sample->hasField('installer_email') && !$this->sample->get('installer_email')->isEmpty()) {
      if (isset($form['job_details']['installer_email'])) {
        $form['job_details']['installer_email']['#default_value'] = $this->sample->get('installer_email')->value;
      }
    }

    // Company Details
    if ($this->sample->hasField('company_email') && !$this->sample->get('company_email')->isEmpty()) {
      $form['company_details']['company_email']['#default_value'] = $this->sample->get('company_email')->value;
    }
    if ($this->sample->hasField('company_tel') && !$this->sample->get('company_tel')->isEmpty()) {
      $form['company_details']['company_telephone']['#default_value'] = $this->sample->get('company_tel')->value;
    }
    if ($this->sample->hasField('customer_id') && !$this->sample->get('customer_id')->isEmpty()) {
      $form['company_details']['sentinel_customer_id']['#default_value'] = $this->sample->get('customer_id')->value;
    }

    // Company Address - load from address entity if available
    $company_address_id = $this->getReferencedAddressId($this->sample, 'field_company_address');
    if ($company_address_id) {
      $address_storage = $this->entityTypeManager->getStorage('address');
      $address_entity = $address_storage->load($company_address_id);
      if ($address_entity && $address_entity->hasField('field_address')) {
        $address_field = $address_entity->get('field_address')->first();
        if ($address_field) {
          $address = $address_field->getValue();
          if (isset($address['organization'])) {
            $form['company_details']['company_address']['company']['#default_value'] = $address['organization'];
          }
          if (isset($address['address_line1'])) {
            $form['company_details']['company_address']['company_address_1']['#default_value'] = $address['address_line1'];
          }
          if (isset($address['address_line2'])) {
            $form['company_details']['company_address']['company_address_2']['#default_value'] = $address['address_line2'];
          }
          if (isset($address['locality'])) {
            $form['company_details']['company_address']['company_town_city']['#default_value'] = $address['locality'];
          }
          if (isset($address['postal_code'])) {
            $form['company_details']['company_address']['company_postcode']['#default_value'] = $address['postal_code'];
          }
          if (isset($address['administrative_area'])) {
            $form['company_details']['company_address']['company_county']['#default_value'] = $address['administrative_area'];
          }
          if (isset($address['country_code'])) {
            $form['company_details']['company_address']['company_country']['#default_value'] = $address['country_code'];
          }
        }
      }
    } else {
      // Fallback to direct field values
      if ($this->sample->hasField('company_name') && !$this->sample->get('company_name')->isEmpty()) {
        $form['company_details']['company_address']['company']['#default_value'] = $this->sample->get('company_name')->value;
      }
      if ($this->sample->hasField('company_address1') && !$this->sample->get('company_address1')->isEmpty()) {
        $form['company_details']['company_address']['company_address_1']['#default_value'] = $this->sample->get('company_address1')->value;
      }
      if ($this->sample->hasField('company_town') && !$this->sample->get('company_town')->isEmpty()) {
        $form['company_details']['company_address']['company_town_city']['#default_value'] = $this->sample->get('company_town')->value;
      }
      if ($this->sample->hasField('company_postcode') && !$this->sample->get('company_postcode')->isEmpty()) {
        $form['company_details']['company_address']['company_postcode']['#default_value'] = $this->sample->get('company_postcode')->value;
      }
    }

    // Job Details
    if ($this->sample->hasField('installer_company') && !$this->sample->get('installer_company')->isEmpty()) {
      $form['job_details']['installer_company']['#default_value'] = $this->sample->get('installer_company')->value;
    }
    if ($this->sample->hasField('boiler_manufacturer') && !$this->sample->get('boiler_manufacturer')->isEmpty()) {
      $form['job_details']['boiler_manufacturer']['#default_value'] = $this->sample->get('boiler_manufacturer')->value;
    }
    if ($this->sample->hasField('system_age') && !$this->sample->get('system_age')->isEmpty()) {
      $form['job_details']['system_age']['#default_value'] = $this->sample->get('system_age')->value;
    }
    if ($this->sample->hasField('boiler_type') && !$this->sample->get('boiler_type')->isEmpty()) {
      $form['job_details']['boiler_type']['#default_value'] = $this->sample->get('boiler_type')->value;
    }
    if ($this->sample->hasField('project_id') && !$this->sample->get('project_id')->isEmpty()) {
      $form['job_details']['project_id']['#default_value'] = $this->sample->get('project_id')->value;
    }
    if ($this->sample->hasField('uprn') && !$this->sample->get('uprn')->isEmpty()) {
      $form['job_details']['uprn']['#default_value'] = $this->sample->get('uprn')->value;
    }
    if ($this->sample->hasField('boiler_id') && !$this->sample->get('boiler_id')->isEmpty()) {
      $form['job_details']['boiler_id']['#default_value'] = $this->sample->get('boiler_id')->value;
    }

    // Date fields
    if ($this->sample->hasField('date_sent') && !$this->sample->get('date_sent')->isEmpty()) {
      $date_sent = $this->sample->get('date_sent')->value;
      if ($date_sent) {
        try {
          $date = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s', $date_sent);
          if ($date) {
            $form['job_details']['date_sent']['#default_value'] = $date;
          }
        }
        catch (\Exception $e) {
          // Try alternative format
          try {
            $date = DrupalDateTime::createFromFormat('Y-m-d', $date_sent);
            if ($date) {
              $form['job_details']['date_sent']['#default_value'] = $date;
            }
          }
          catch (\Exception $e2) {
            // Ignore
          }
        }
      }
    }
    if ($this->sample->hasField('date_installed') && !$this->sample->get('date_installed')->isEmpty()) {
      $date_installed = $this->sample->get('date_installed')->value;
      if ($date_installed) {
        try {
          $date = DrupalDateTime::createFromFormat('Y-m-d\TH:i:s', $date_installed);
          if ($date) {
            $form['job_details']['date_installed']['#default_value'] = $date;
          }
        }
        catch (\Exception $e) {
          try {
            $date = DrupalDateTime::createFromFormat('Y-m-d', $date_installed);
            if ($date) {
              $form['job_details']['date_installed']['#default_value'] = $date;
            }
          }
          catch (\Exception $e2) {
            // Ignore
          }
        }
      }
    }

    // System Details - Landlord
    if ($this->sample->hasField('landlord') && !$this->sample->get('landlord')->isEmpty()) {
      $form['system_details']['landlord_wrapper']['landlord']['#default_value'] = $this->sample->get('landlord')->value;
    }

    // System Address - load from address entity if available
    $sample_address_id = $this->getReferencedAddressId($this->sample, 'field_sentinel_sample_address');
    if ($sample_address_id) {
      $address_storage = $this->entityTypeManager->getStorage('address');
      $address_entity = $address_storage->load($sample_address_id);
      if ($address_entity && $address_entity->hasField('field_address')) {
        $address_field = $address_entity->get('field_address')->first();
        if ($address_field) {
          $address = $address_field->getValue();
          if (isset($address['address_line1'])) {
            $form['system_details']['address']['address_fields']['address_1']['#default_value'] = $address['address_line1'];
          }
          if (isset($address['address_line2'])) {
            // Try to parse property name/number from address_line2
            $parts = explode(' ', $address['address_line2']);
            foreach ($parts as $part) {
              if (is_numeric($part)) {
                $form['system_details']['address']['address_fields']['property_number']['#default_value'] = $part;
              } else {
                $form['system_details']['address']['address_fields']['property_name']['#default_value'] = $part;
              }
            }
          }
          if (isset($address['locality'])) {
            $form['system_details']['address']['address_fields']['town_city']['#default_value'] = $address['locality'];
          }
          if (isset($address['postal_code'])) {
            $form['system_details']['address']['address_fields']['postcode']['#default_value'] = $address['postal_code'];
          }
          if (isset($address['administrative_area'])) {
            $form['system_details']['address']['address_fields']['county']['#default_value'] = $address['administrative_area'];
          }
          if (isset($address['country_code'])) {
            $form['system_details']['address']['address_fields']['country']['#default_value'] = $address['country_code'];
          }
        }
      }
    } else {
      // Fallback to direct field values
      if ($this->sample->hasField('property_number') && !$this->sample->get('property_number')->isEmpty()) {
        $form['system_details']['address']['address_fields']['property_number']['#default_value'] = $this->sample->get('property_number')->value;
      }
      if ($this->sample->hasField('street') && !$this->sample->get('street')->isEmpty()) {
        $form['system_details']['address']['address_fields']['address_1']['#default_value'] = $this->sample->get('street')->value;
      }
      if ($this->sample->hasField('town_city') && !$this->sample->get('town_city')->isEmpty()) {
        $form['system_details']['address']['address_fields']['town_city']['#default_value'] = $this->sample->get('town_city')->value;
      }
      if ($this->sample->hasField('postcode') && !$this->sample->get('postcode')->isEmpty()) {
        $form['system_details']['address']['address_fields']['postcode']['#default_value'] = $this->sample->get('postcode')->value;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->sample) {
      $this->messenger()->addError($this->t('Sample not found.'));
      return;
    }

    $original_values = $form_state->getValues();
    $values = $original_values;
    $date_fields = ['date_sent', 'date_installed'];
    $normalized_dates = [];
    foreach ($date_fields as $date_field) {
      $normalized_dates[$date_field] = $this->normalizeDateFormValue($form_state->getValue($date_field));
    }

    // Flatten fieldset values
    foreach (['company_details', 'system_details', 'job_details', 'result_details'] as $fieldset) {
      if (isset($values[$fieldset]) && is_array($values[$fieldset])) {
        foreach ($values[$fieldset] as $key => $val) {
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
      }
    }

    try {
      // Get Sentinel Customer ID from form
      $sentinel_customer_id = NULL;
      $customer_id_value = $form_state->getValue(['company_details', 'sentinel_customer_id']);
      if (!empty($customer_id_value)) {
        $sentinel_customer_id = trim($customer_id_value);
      }

      // Get UCR from existing sample
      $ucr = NULL;
      if ($this->sample->hasField('ucr') && !$this->sample->get('ucr')->isEmpty()) {
        $ucr = $this->sample->get('ucr')->value;
      }

      // Get client_id and client_name from sentinel_client table by UCR
      $client_id = NULL;
      $client_name = NULL;
      if ($ucr) {
        $client_storage = $this->entityTypeManager->getStorage('sentinel_client');
        $client_query = $client_storage->getQuery()
          ->condition('ucr', $ucr)
          ->accessCheck(FALSE)
          ->range(0, 1);
        $client_ids = $client_query->execute();
        
        if (!empty($client_ids)) {
          $client = $client_storage->load(reset($client_ids));
          if ($client) {
            $client_id = $client->id();
            if ($client->hasField('name') && !$client->get('name')->isEmpty()) {
              $client_name = $client->get('name')->value;
            }
          }
        }
      }

      // Determine pack_type from pack_reference_number
      $pack_type = NULL;
      if ($this->sample->hasField('pack_reference_number') && !$this->sample->get('pack_reference_number')->isEmpty()) {
        $pack_reference_number = $this->sample->get('pack_reference_number')->value;
        $pack_type = SentinelSample::getPackType([
          'pack_reference_number' => $pack_reference_number,
        ]);
      }

      // Use the same field mapping logic as parent
      $legacyMappings = [
        'company_name' => ['company_details', 'company_address', 'company'],
        'company_address1' => ['company_details', 'company_address', 'company_address_1'],
        'company_address2' => ['company_details', 'company_address', 'company_address_2'],
        'company_town' => ['company_details', 'company_address', 'company_town_city'],
        'company_county' => ['company_details', 'company_address', 'county'],
        'company_postcode' => ['company_details', 'company_address', 'company_postcode'],
        'system_location' => ['system_details', 'address', 'address_fields', 'address_1'],
        'property_number' => ['system_details', 'address', 'address_fields', 'property_number'],
        'street' => ['system_details', 'address', 'address_fields', 'address_1'],
        'town_city' => ['system_details', 'address', 'address_fields', 'town_city'],
        'county' => ['system_details', 'address', 'address_fields', 'county'],
        'postcode' => ['system_details', 'address', 'address_fields', 'postcode'],
        'landlord' => ['system_details', 'landlord_wrapper', 'landlord'],
        'company_tel' => ['company_details', 'company_telephone'],
        'date_sent' => ['job_details', 'date_sent'],
        'date_installed' => ['job_details', 'date_installed'],
        'boiler_id' => ['job_details', 'boiler_id'],
        'project_id' => ['job_details', 'project_id'],
        'uprn' => ['job_details', 'uprn'],
      ];

      foreach ($legacyMappings as $fieldName => $path) {
        $val = $this->getArrayPathValue($values, $path);
        if ($val !== NULL && $val !== '') {
          if ($this->sample->hasField($fieldName)) {
            $this->sample->set($fieldName, $val);
          }
        }
      }

      // Flat mappings
      $legacyFlat = [
        'company_name' => 'company',
        'company_address1' => 'company_property_name',
        'company_address2' => 'company_address_1',
        'company_town' => 'company_town_city',
        'company_county' => 'company_county',
        'company_postcode' => 'company_postcode',
        'company_tel' => 'company_telephone',
        'property_number' => 'property_number',
        'street' => 'address_1',
        'town_city' => 'town_city',
        'county' => 'county',
        'postcode' => 'postcode',
        'landlord' => 'landlord',
        'date_sent' => 'date_sent',
        'date_installed' => 'date_installed',
        'boiler_id' => 'boiler_id',
        'project_id' => 'project_id',
        'uprn' => 'uprn',
      ];

      foreach ($legacyFlat as $fieldName => $sourceKey) {
        if (isset($values[$sourceKey]) && $values[$sourceKey] !== '' && $values[$sourceKey] !== NULL) {
          if ($this->sample->hasField($fieldName)) {
            $this->sample->set($fieldName, $values[$sourceKey]);
          }
        }
      }

      // Map sentinel_customer_id to customer_id (special handling)
      if (isset($values['sentinel_customer_id']) && $this->sample->hasField('customer_id')) {
        $customer_id_value = trim($values['sentinel_customer_id']);
        // Set customer_id even if empty (to allow clearing the field)
        $this->sample->set('customer_id', $customer_id_value);
      }

      // Map other direct field values
      $this->mapFormValuesToEntity($this->sample, $values, $form);
      $this->ensureAddressEntities($this->sample, $values, $original_values, $form);
      $this->setLegacyAddressTargetIds($this->sample);

      // Ensure customer_id is set from sentinel_customer_id (set again after mapFormValuesToEntity to prevent overwrite)
      if ($sentinel_customer_id !== NULL && $this->sample->hasField('customer_id')) {
        $this->sample->set('customer_id', $sentinel_customer_id);
      }
      elseif (isset($values['sentinel_customer_id']) && $this->sample->hasField('customer_id')) {
        // Fallback: get from flattened values if not already set
        $customer_id_value = trim($values['sentinel_customer_id']);
        $this->sample->set('customer_id', $customer_id_value);
      }

      // Set client_id and client_name if available
      if ($client_id !== NULL && $this->sample->hasField('client_id')) {
        $this->sample->set('client_id', $client_id);
      }
      if ($client_name !== NULL && $this->sample->hasField('client_name')) {
        $this->sample->set('client_name', $client_name);
      }

      // Set pack_type if available
      if ($pack_type !== NULL && $this->sample->hasField('pack_type')) {
        $this->sample->set('pack_type', $pack_type);
      }

      // Save the updated sample
      $this->sample->save();

      $this->messenger()->addMessage($this->t('Sample details have been updated successfully.'));
      
      // Redirect to thank you page
      $form_state->setRedirect('sentinel_portal_sample.anonymous_thank_you');

    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_portal_sample')->error('Error updating sample: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while updating the sample. Please try again or contact support.'));
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
  protected function getArrayPathValue(array $source, array $path) {
    $cursor = $source;
    foreach ($path as $segment) {
      if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
        return NULL;
      }
      $cursor = $cursor[$segment];
    }
    return $cursor;
  }

}
