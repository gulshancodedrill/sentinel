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
  $sample_data['api_key'] = sentinel_systemcheck_vaillant_api_get_key();
  $data = sentinel_systemcheck_vaillant_api_format_data($sample_entity);
  $data = array_merge($sample_data, $data);
  
  try {
    $client = \Drupal::httpClient();
    $response = $client->post($endpoint, [
      'headers' => [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
        'Content-Type' => 'application/json',
      ],
      'json' => $data,
      'timeout' => 15,
    ]);

    $status_code = $response->getStatusCode();
    
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
        $response_data = Json::decode($e->getResponse()->getBody());
        if (isset($response_data['message'])) {
          $message = $response_data['message'];
        }
      }
      catch (\Exception $decode_exception) {
        // Could not decode response, use original message.
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

  $data['project_id'] = $sample_entity->get('project_id')->value ?? '';
  $data['customer_id'] = $sample_entity->get('customer_id')->value ?? '';
  $data['lab_reference'] = $sample_entity->get('pack_reference_number')->value ?? '';
  $data['notes'] = $sample_entity->get('notes')->value ?? '';
  $data['boiler_serial_id'] = $sample_entity->get('boiler_id')->value ?? '';
  $data['customer_name'] = $sample_entity->get('client_name')->value ?? '';
  $data['customer_email'] = $sample_entity->get('customer_email')->value ?? '';
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
  $api_key = getenv('VAILLANT_API') ?: (isset($_ENV['VAILLANT_API']) ? $_ENV['VAILLANT_API'] : NULL);
  
  // If not in environment, check configuration.
  if (empty($api_key)) {
    $config = \Drupal::config('sentinel_systemcheck_vaillant_api.settings');
    $api_key = $config->get('api_key');
  }
  
  return $api_key ?? '';
}
