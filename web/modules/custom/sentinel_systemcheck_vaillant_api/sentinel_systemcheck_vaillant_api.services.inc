<?php

/**
 * @file
 * Service functions for Sentinel Vaillant API module.
 */

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\RequestException;

/**
 * Function that makes POST request to the Vaillant API.
 *
 * @param \Drupal\Core\Entity\EntityInterface $sample_entity
 *   The sample entity.
 *
 * @throws \Exception
 *   If the API request fails.
 */
function sentinel_systemcheck_vaillant_api_send($sample_entity) {
  // Get endpoint - try environment variable first, then config.
  $endpoint = getenv('VAILLANT_API_ENDPOINT') ?: (isset($_ENV['VAILLANT_API_ENDPOINT']) ? $_ENV['VAILLANT_API_ENDPOINT'] : NULL);
 
  if (empty($endpoint)) {
    $config = \Drupal::config('sentinel_systemcheck_vaillant_api.settings');
    $endpoint = $config->get('endpoint');
  }
  
  if (empty($endpoint)) {
    // Default endpoint (same as D7).
    $endpoint = 'https://vaillant.sparkitsupport.co.uk/api/new-watersample';
  }
  
  if (empty($endpoint)) {
    throw new \Exception('Vaillant API endpoint not configured.');
  }

  $sample_data = [];
  $api_key = sentinel_systemcheck_vaillant_api_get_key();
  
  // Debug: Check what we're getting
  $config_debug = \Drupal::config('sentinel_systemcheck_vaillant_api.settings');
  $config_value = $config_debug->get('api_key');
  $raw_config = $config_debug->getRawData();
  
  \Drupal::logger('vaillant_api')->notice('API Key Debug - Function returned: @func, Config get: @config, Raw: @raw', [
    '@func' => var_export($api_key, TRUE),
    '@config' => var_export($config_value, TRUE),
    '@raw' => var_export($raw_config['api_key'] ?? 'NOT SET', TRUE),
  ]);
  
  // Ensure API key is set - if empty, throw exception
  if (empty($api_key)) {
    throw new \Exception('Vaillant API key is not configured. Please set it at /admin/config/system/sentinel-vaillant-api or via VAILLANT_API environment variable.');
  }
  
  $sample_data['api_key'] = $api_key;
  $data = sentinel_systemcheck_vaillant_api_format_data($sample_entity);
  $data = array_merge($sample_data, $data);
  
  // API expects a single object (not an array) - matching D7 behavior and Postman tests
  $payload = $data;
  
  // Debug: Verify API key is in payload
  \Drupal::logger('vaillant_api')->notice('Payload API Key: @key', [
    '@key' => var_export($payload['api_key'] ?? 'NOT IN PAYLOAD', TRUE),
  ]);
  
  try {
    $client = \Drupal::httpClient();
    
    // Debug: Log the actual JSON being sent
    $json_payload = json_encode($payload);
    \Drupal::logger('vaillant_api')->notice('Sending JSON payload: @json', [
      '@json' => $json_payload,
    ]);
    
    $response = $client->post($endpoint, [
      'headers' => [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
        'Content-Type' => 'application/json',
      ],
      'json' => $payload,
      'timeout' => 15,
    ]);
    
    $status_code = $response->getStatusCode();
    $response_body = $response->getBody()->getContents();
    //dd($response_body);
    // Debug: Log the response
    \Drupal::logger('vaillant_api')->notice('API Response - Status: @status, Body: @body', [
      '@status' => $status_code,
      '@body' => $response_body,
    ]);
    
    if ($status_code === 200) {
      \Drupal::messenger()->addStatus(t('Report sent to Vaillant API.'));
    }
    elseif ($status_code === 400) {
      $response_data = Json::decode($response->getBody());
      $error_message = isset($response_data['message']) ? $response_data['message'] : 'Bad request';
      throw new \Exception($error_message);
    }
    else {
      throw new \Exception(t('API request failed with status code: @code', ['@code' => $status_code]));
    }
  }
  catch (RequestException $e) {
    $message = $e->getMessage();
   
    if ($e->hasResponse()) {
      try {
        $response_body = $e->getResponse()->getBody()->getContents();
        $response_data = Json::decode($response_body);
        
        // Log the full error response for debugging
        \Drupal::logger('vaillant_api')->error('API Error Response: @body', [
          '@body' => $response_body,
        ]);
        
        if (isset($response_data['message'])) {
          $message = $response_data['message'];
        }
        // Check for error array in response
        if (isset($response_data['error']) && is_array($response_data['error'])) {
          $error_messages = [];
          foreach ($response_data['error'] as $error) {
            if (is_array($error) && isset($error['error_description'])) {
              $error_messages[] = $error['error_description'];
            } elseif (is_string($error)) {
              $error_messages[] = $error;
            }
          }
          if (!empty($error_messages)) {
            $message = implode('; ', $error_messages);
          }
        }
      }
      catch (\Exception $decode_exception) {
        // Could not decode response, use original message.
        \Drupal::logger('vaillant_api')->error('Could not decode API error response: @error', [
          '@error' => $decode_exception->getMessage(),
        ]);
      }
    }
    throw new \Exception($message);
  }
}

/**
 * Function to correctly store & format data to be sent to the API.
 *
 * @param \Drupal\Core\Entity\EntityInterface $sample_entity
 *   The sample entity.
 *
 * @return array
 *   Formatted data array.
 */
function sentinel_systemcheck_vaillant_api_format_data($sample_entity) {
  $data = [];

  // Ensure project_id and customer_id are integers, not strings
  $project_id = $sample_entity->get('project_id')->value ?? '';
  $data['project_id'] = !empty($project_id) ? (int) $project_id : 0;
  
  $customer_id = $sample_entity->get('customer_id')->value ?? '';
  $data['customer_id'] = !empty($customer_id) ? (int) $customer_id : 0;
  $data['lab_reference'] = $sample_entity->get('pack_reference_number')->value ?? '';
  $data['notes'] = sentinel_systemcheck_vaillant_api_get_optional_value($sample_entity, 'notes');
  $data['boiler_serial_id'] = $sample_entity->get('boiler_id')->value ?? '';
  $data['customer_name'] = sentinel_systemcheck_vaillant_api_get_optional_value($sample_entity, 'client_name');
  $data['customer_email'] = sentinel_systemcheck_vaillant_api_get_optional_value($sample_entity, 'customer_email');
  $data['uprn'] = $sample_entity->get('uprn')->value ?? '';
  $data['landlord'] = $sample_entity->get('landlord')->value ?? '';
  $data['property_number'] = $sample_entity->get('property_number')->value ?? '';
  $data['street'] = $sample_entity->get('street')->value ?? '';
  $data['town'] = $sample_entity->get('town_city')->value ?? '';
  $data['county'] = $sample_entity->get('county')->value ?? '';
  $data['postcode'] = $sample_entity->get('postcode')->value ?? '';

  $pass_fail = $sample_entity->get('pass_fail')->value ?? 0;
  switch ($pass_fail) {
    case 0:
      $data['result'] = 'F';
      break;
    case 1:
      $data['result'] = 'P';
      break;
    default:
      $data['result'] = '';
      break;
  }

  // Dates must be in the format "YYYY-MM-DD".
  $date_installed = $sample_entity->get('date_installed')->value;
  if (!empty($date_installed)) {
    try {
      $date_obj = new \DateTime($date_installed);
      $data['date_installed'] = $date_obj->format('Y-m-d');
    }
    catch (\Exception $e) {
      $data['date_installed'] = '';
    }
  }
  else {
    $data['date_installed'] = '';
  }

  // Dates must be in the format "YYYY-MM-DD".
  $date_sent = $sample_entity->get('date_sent')->value;
  if (!empty($date_sent)) {
    try {
      $date_obj = new \DateTime($date_sent);
      $data['date_tested'] = $date_obj->format('Y-m-d');
    }
    catch (\Exception $e) {
      $data['date_tested'] = '';
    }
  }
  else {
    $data['date_tested'] = '';
  }

  return $data;
}

/**
 * Function to get API key from configuration.
 *
 * This function reads from environment variables first (like D7),
 * then falls back to configuration.
 *
 * @return string
 *   The API key.
 */
function sentinel_systemcheck_vaillant_api_get_key() {
  // Try environment variable first (same approach as D7 settings.php).
  $api_key = getenv('VAILLANT_API');
  if (!$api_key && isset($_ENV['VAILLANT_API'])) {
    $api_key = $_ENV['VAILLANT_API'];
  }
  
  // If not in environment, check configuration.
  if (!$api_key) {
    $config = \Drupal::config('sentinel_systemcheck_vaillant_api.settings');
    // Get the raw data to ensure we get the actual value
    $raw_data = $config->getRawData();
    if (isset($raw_data['api_key']) && $raw_data['api_key'] !== NULL && $raw_data['api_key'] !== '') {
      $api_key = $raw_data['api_key'];
    } else {
      // Fallback to get() method
      $api_key = $config->get('api_key');
    }
  }
  
  // Return the API key as string, or empty string if not found
  return $api_key ? trim((string) $api_key) : '';
}

/**
 * Safely fetches optional field values from a sentinel sample entity.
 *
 * @param \Drupal\Core\Entity\EntityInterface $sample_entity
 *   The sample entity.
 * @param string $field_name
 *   The field machine name.
 * @param string $default
 *   The default value to return when the field is missing or empty.
 *
 * @return string
 *   The field value or the default.
 */
function sentinel_systemcheck_vaillant_api_get_optional_value($sample_entity, $field_name, $default = '') {
  if ($sample_entity->hasField($field_name)) {
    $field = $sample_entity->get($field_name);
    if ($field && !$field->isEmpty()) {
      $item = $field->first();
      if ($item && isset($item->value)) {
        return $item->value;
      }
    }
  }

  return $default;
}
