<?php

namespace Drupal\sentinel_portal_entities\Service;

use Drupal\sentinel_portal_entities\Exception\SentinelSampleValidationException;

/**
 * Validation service for Sentinel Sample entities.
 *
 * Matches D7 SentinelSampleEntityValidation class logic.
 */
class SentinelSampleValidation {

  /**
   * Get pack type from data (matches D7 PackTypeTrait::getPackType).
   *
   * @param array $data
   *   The sample data array.
   *
   * @return string
   *   The pack type: 'vaillant', 'worcesterbosch_contract', 'worcesterbosch_service', or 'standard'.
   */
  public static function getPackType(array $data) {
    if (empty($data['pack_reference_number'])) {
      return 'standard';
    }

    $pack_ref_prefix = substr($data['pack_reference_number'], 0, 3);

    switch ($pack_ref_prefix) {
      case '001':
        // Vaillant Systemcheck Pack
        return 'vaillant';

      case '005':
        // Worcester Bosch Contract Form
        return 'worcesterbosch_contract';

      case '006':
        // Worcester Bosch Service Form
        return 'worcesterbosch_service';

      case '102':
        // Standard Systemcheck Pack
        // Special case: if customer_id, project_id, and boiler_id are present, it's vaillant
        // (matches D7 SentinelSampleEntity::getSampleType() logic)
        if (!empty($data['customer_id']) && !empty($data['project_id']) && !empty($data['boiler_id'])) {
          return 'vaillant';
        }
        // Deliberate fall through.

      default:
        // Standard Systemcheck Pack
        return 'standard';
    }
  }

  /**
   * Validate sample data coming from API requests.
   *
   * Matches D7 SentinelSampleEntityValidation::validateSampleInApi().
   *
   * @param array $sample
   *   The sample data.
   * @param object|null $client_data
   *   The sentinel client data (may be entity or stdClass).
   *
   * @return array
   *   Array of validation errors keyed by field name.
   *
   * @throws \Drupal\sentinel_portal_entities\Exception\SentinelSampleValidationException
   *   When a client without global access submits invalid data (matches D7).
   */
  public static function validateSampleInApi(array $sample, $client_data = NULL) {
    $errors = [];

    $sample_type = self::getPackType($sample);

    $company_email = isset($sample['company_email']) ? trim($sample['company_email']) : '';
    if ($company_email === '' || !filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
      $errors['company_email'] = self::formatErrorMessage('company_email', 'has an invalid email address.');
    }

    // Required fields for API submissions (matches D7).
    $required_fields = [
      'boiler_manufacturer',
      'installer_name',
      'installer_email',
      'company_name',
      'company_tel',
      'property_number',
      'postcode',
    ];

    foreach ($required_fields as $field) {
      $value = isset($sample[$field]) ? trim((string) $sample[$field]) : '';
      if ($value === '') {
        if (!isset($errors[$field])) {
          $errors[$field] = self::formatErrorMessage($field, 'field is empty.');
        }
      }
    }

    if (isset($sample['system_6_months']) && trim((string) $sample['system_6_months']) !== '') {
      $value = trim((string) $sample['system_6_months']);
      if (!in_array($value, ['MORE6', 'LESS6'], TRUE)) {
        if (!isset($errors['system_6_months'])) {
          $errors['system_6_months'] = self::formatErrorMessage('system_6_months', 'contains an invalid value. Must be either LESS6 or MORE6.');
        }
      }
    }

    if ($sample_type === 'worcesterbosch_contract') {
      $street = isset($sample['street']) ? trim((string) $sample['street']) : '';
      // if ($street === '') {
      //   $errors['street'] = self::formatErrorMessage('street', 'field is empty. This pack is a worcester pack.');
      // }

      $town_city = isset($sample['town_city']) ? trim((string) $sample['town_city']) : '';
      if ($town_city === '') {
        $errors['town_city'] = self::formatErrorMessage('town_city', 'field is empty. This pack is a worcester pack.');
      }

      $county = isset($sample['county']) ? trim((string) $sample['county']) : '';
      if ($county === '') {
        $errors['county'] = self::formatErrorMessage('county', 'field is empty. This pack is a worcester pack.');
      }
    }

    // Determine if the API client has global access (matches D7 behaviour).
    $global_access = FALSE;
    if (is_object($client_data)) {
      if (isset($client_data->global_access)) {
        $global_access = (bool) $client_data->global_access;
      }
      elseif (method_exists($client_data, 'hasField') && $client_data->hasField('global_access')) {
        $global_access = (bool) ($client_data->get('global_access')->value ?? FALSE);
      }
      elseif (method_exists($client_data, 'get') && $client_data->get('global_access')) {
        $global_access = (bool) $client_data->get('global_access')->value;
      }
    }

    if ($global_access === FALSE && count($errors) > 0) {
      throw new SentinelSampleValidationException($errors, 'Sample not saved. Missing sample data found.');
    }

    return $errors;
  }

  /**
   * Validate a sample based on the type of sample that it is.
   *
   * Matches D7 SentinelSampleEntityValidation::validateSample().
   *
   * @param array $data
   *   The sample data array.
   *
   * @return array
   *   An array of error messages for the fields that failed validation.
   *   Keyed by field name, value is error message.
   */
  public static function validateSample(array $data) {
    $errors = [];
    $sample_type = self::getPackType($data);

    switch ($sample_type) {
      case 'vaillant':
        // Vaillant Validation Fields
        if (empty($data['pack_reference_number'])) {
          $errors['pack_reference_number'] = self::formatErrorMessage('pack_reference_number', 'field is empty.');
        }

        if (empty($data['customer_id'])) {
          $errors['customer_id'] = self::formatErrorMessage('customer_id', 'field is empty.');
        }
        if (empty($data['boiler_id'])) {
          $errors['boiler_id'] = self::formatErrorMessage('boiler_id', 'field is empty.');
        }
        if (empty($data['project_id'])) {
          $errors['project_id'] = self::formatErrorMessage('project_id', 'field is empty.');
        }
        if (empty($data['company_name'])) {
          $errors['company_name'] = self::formatErrorMessage('company_name', 'field is empty.');
        }
        if (empty($data['company_tel'])) {
          $errors['company_tel'] = self::formatErrorMessage('company_tel', 'field is empty.');
        }

        // Check if sentinel_addresses module is active for address field validation
        $module_handler = \Drupal::moduleHandler();
        if ($module_handler->moduleExists('sentinel_addresses') && isset($data['form_id'])) {
          if (empty($data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0]['sub_premise'])) {
            $errors['property_number'] = self::formatErrorMessage('property_number', 'field is empty.');
          }
          // if (empty($data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0]['thoroughfare'])) {
          //   $errors['street'] = self::formatErrorMessage('street', 'field is empty.');
          // }
        }
        else {
          if (empty($data['property_number'])) {
            $errors['property_number'] = self::formatErrorMessage('property_number', 'field is empty.');
          }
          // if (empty($data['street'])) {
          //   $errors['street'] = self::formatErrorMessage('street', 'field is empty.');
          // }
        }

        if (empty($data['date_installed'])) {
          $errors['date_installed'] = self::formatErrorMessage('date_installed', 'field is empty.');
        }
        elseif (!self::validateDate($data['date_installed'])) {
          $errors['date_installed'] = self::formatErrorMessage('date_installed', 'is an invalid date.');
        }

        break;

      case 'worcesterbosch_contract':
        $errors = self::getDefaultFormErrors($data);

        // Check if sentinel_addresses module is active
        $module_handler = \Drupal::moduleHandler();
        if ($module_handler->moduleExists('sentinel_addresses') && isset($data['form_id'])) {
          if (empty($data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0]['postal_code'])) {
            $errors['postcode'] = self::formatErrorMessage('postcode', 'field is empty.');
          }
        }
        else {
          if (empty($data['postcode'])) {
            $errors['postcode'] = self::formatErrorMessage('postcode', 'field is empty.');
          }
        }
        break;

      case 'standard':
        $errors = self::getDefaultFormErrors($data);
        // Remove project_id and installer_email from standard validation
        unset($errors['project_id']);
        unset($errors['installer_email']);
        // Remove date_installed if it says 'missing' (not required for standard)
        if (isset($errors['date_installed']) && strpos($errors['date_installed'], 'missing') !== FALSE) {
          unset($errors['date_installed']);
        }
        break;

      default:
        $errors = self::getDefaultFormErrors($data);
        break;
    }

    return $errors;
  }

  /**
   * Gets the default errors for packs.
   *
   * Matches D7 SentinelSampleEntityValidation::getDefaultFormErrors().
   *
   * @param array $data
   *   The sample data array.
   *
   * @return array
   *   Array of validation errors.
   */
  private static function getDefaultFormErrors(array $data) {
    $errors = [];

    if (empty($data['pack_reference_number'])) {
      $errors['pack_reference_number'] = self::formatErrorMessage('pack_reference_number', 'field is empty.');
    }
    if (empty($data['installer_email'])) {
      $errors['installer_email'] = self::formatErrorMessage('installer_email', 'field is empty.');
    }
    if (empty($data['project_id'])) {
      $errors['project_id'] = self::formatErrorMessage('project_id', 'field is empty.');
    }

    // Check if sentinel_addresses module is active
    $module_handler = \Drupal::moduleHandler();
    if ($module_handler->moduleExists('sentinel_addresses') && isset($data['form_id'])) {
      $sub_premise = $data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0]['sub_premise'] ?? NULL;
      if ($sub_premise === NULL || trim((string) $sub_premise) === '') {
        $sub_premise = $data['property_number'] ?? '';
      }
      if (trim((string) $sub_premise) === '') {
        $errors['property_number'] = self::formatErrorMessage('property_number', 'field is empty.');
      }
      // if (empty($data['field_sentinel_sample_address']['und']['form']['field_address']['und'][0]['thoroughfare'])) {
      //   $errors['street'] = self::formatErrorMessage('street', 'field is empty.');
      // }
    }
    else {
      if (empty($data['property_number'])) {
        $errors['property_number'] = self::formatErrorMessage('property_number', 'field is empty.');
      }
      // if (empty($data['street'])) {
      //   $errors['street'] = self::formatErrorMessage('street', 'field is empty.');
      // }
    }

    if (empty($data['date_installed']) || is_array($data['date_installed'])) {
      $errors['date_installed'] = self::formatErrorMessage('date_installed', 'field is empty.');
    }
    elseif (!self::validateDate($data['date_installed'])) {
      $errors['date_installed'] = self::formatErrorMessage('date_installed', 'is an invalid date.');
    }

    return $errors;
  }

  /**
   * Get a human readable label for a field.
   */
  public static function getFieldLabel(string $field_name): string {
    static $labels = NULL;

    if ($labels === NULL) {
      $labels = [];
      $entity_type_manager = \Drupal::entityTypeManager();
      $storage = $entity_type_manager->getStorage('sentinel_sample');
      $sample = $storage->create([]);
      foreach ($sample->getFieldDefinitions() as $name => $definition) {
        $labels[$name] = (string) $definition->getLabel();
      }
    }

    if (isset($labels[$field_name])) {
      return $labels[$field_name];
    }

    return ucwords(str_replace('_', ' ', $field_name));
  }

  /**
   * Format an error message with field label.
   */
  public static function formatErrorMessage(string $field_name, string $message): string {
    $label = self::getFieldLabel($field_name);
    $message = trim($message);

    if ($message === '') {
      $message = 'is invalid.';
    }

    return trim($label . ' ' . $message);
  }

  /**
   * Validates a date string (matches D7 sentinel_portal_validate_date).
   *
   * @param string $date
   *   The date string to validate.
   *
   * @return bool|string
   *   FALSE if invalid, otherwise the formatted date string.
   */
  public static function validateDate($date) {
    $formats = [
      'Y-m-d H:i:00', // API format
      'Y-m-d\TH:i', // ISO 8601
      'Y-m-d\TH:i:00', // ISO 8601 with seconds.
      'Ymd\TH:i', // MySQL
      'Ymd\TH:i:00', // MySQL with seconds
      'Y-m-d',
      'Ymd',
      'd/m/Y', // Excel default format
      'd-m-Y H:i:s', // Another Excel format
    ];

    // Attempt to create a date from each of the formats.
    foreach ($formats as $format) {
      $obj_date = \DateTime::createFromFormat($format, $date);

      $errors = \DateTime::getLastErrors();
      if ($errors && $errors['warning_count'] > 0) {
        continue;
      }

      if ($obj_date !== FALSE) {
        return $obj_date->format('Y-m-d\TH:i:00');
      }
    }

    // If we reach this point then no valid date is found.
    return FALSE;
  }

}
