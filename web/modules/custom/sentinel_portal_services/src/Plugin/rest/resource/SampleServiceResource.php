<?php

namespace Drupal\sentinel_portal_services\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a Sample Service Resource.
 *
 * @RestResource(
 *   id = "sentinel_sampleservice",
 *   label = @Translation("Sentinel Sample Service"),
 *   uri_paths = {
 *     "canonical" = "/sentinel/sampleservice",
 *     "create" = "/sentinel/sampleservice"
 *   }
 * )
 */
class SampleServiceResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing sample data.
   */
  public function get(Request $request = NULL) {
    if (!$request) {
      $request = \Drupal::request();
    }

    $key = $request->query->get('key');
    $pack_reference_number = $request->query->get('pack_reference_number');
    $ucr = $request->query->get('ucr');

    // Validate the API key
    $client_data = $this->getClientByApiKey($key);
    if (!$client_data) {
      throw new BadRequestHttpException('API key is invalid');
    }

    // Validate pack reference number
    if (empty($pack_reference_number)) {
      throw new BadRequestHttpException('pack_reference_number is missing');
    }

    if (function_exists('valid_pack_reference_number') && !valid_pack_reference_number($pack_reference_number)) {
      throw new BadRequestHttpException('pack_reference_number is not valid');
    }

    // Validate UCR if provided
    if (!empty($ucr)) {
      $entity_type_manager = \Drupal::entityTypeManager();
      $storage = $entity_type_manager->getStorage('sentinel_client');
      $client = $storage->create([]);
      
      if (method_exists($client, 'validateUcr') && !$client->validateUcr($ucr)) {
        throw new BadRequestHttpException('UCR is not valid');
      }
    }

    // Query for the sample
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 'p')
      ->fields('p', ['pid'])
      ->condition('p.pack_reference_number', $pack_reference_number, '=');

    $global_access = $client_data->get('global_access')->value ?? FALSE;

    if ($global_access == FALSE) {
      $real_ucr = method_exists($client_data, 'getRealUcr') ? $client_data->getRealUcr() : '';
      $query->condition('p.api_created_by', $real_ucr, '=');
    }
    elseif (!empty($ucr)) {
      $ucr = floor($ucr / 10);
      $query->condition('p.ucr', $ucr, '=');
    }

    $query->orderBy('pid', 'DESC');
    $result = $query->execute();
    $row = $result->fetchAssoc();

    if (isset($row['pid'])) {
      $entity_type_manager = \Drupal::entityTypeManager();
      $storage = $entity_type_manager->getStorage('sentinel_sample');
      $sample = $storage->load($row['pid']);

      $return_array = method_exists($sample, 'toArray') ? 
        $sample->toArray($global_access) : 
        [];

      $response_data = [
        'status' => '200',
        'message' => $return_array,
      ];

      $response = new ResourceResponse($response_data, Response::HTTP_OK);
      $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
      
      return $response;
    }

    throw new NotFoundHttpException('Sample not found');
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The data array.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function post(array $data, Request $request = NULL) {
    if (!$request) {
      $request = \Drupal::request();
    }

    $key = $request->query->get('key') ?? ($data['key'] ?? '');

    // Validate the API key
    $client_data = $this->getClientByApiKey($key);
    if (!$client_data) {
      throw new BadRequestHttpException('API key is invalid');
    }

    // Validate pack reference number
    if (!isset($data['pack_reference_number'])) {
      throw new BadRequestHttpException('pack_reference_number is missing');
    }

    if (function_exists('valid_pack_reference_number') && !valid_pack_reference_number($data['pack_reference_number'])) {
      throw new BadRequestHttpException('pack_reference_number is not valid');
    }

    // Validate UCR
    if (!isset($data['ucr'])) {
      throw new BadRequestHttpException('ucr is missing');
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('sentinel_client');
    $client = $storage->create([]);
    
    if (method_exists($client, 'validateUcr') && !$client->validateUcr($data['ucr'])) {
      throw new BadRequestHttpException('ucr is not valid');
    }

    // Get sample fields
    $sample_fields = function_exists('sentinel_portal_entities_get_sample_fields') ? 
      sentinel_portal_entities_get_sample_fields() : 
      [];

    $sample = [];
    $errors = [];
    $global_access = $client_data->get('global_access')->value ?? FALSE;

    // Process each field
    foreach ($sample_fields as $field_name => $field) {
      // Skip admin fields
      if (in_array($field_name, ['updated', 'created'])) {
        continue;
      }

      if (isset($data[$field_name])) {
        if (!isset($field['portal_config']['access']['data'])) {
          continue;
        }

        // Check access
        if (($field['portal_config']['access']['data'] == 'admin' && $global_access == TRUE) || 
            $field['portal_config']['access']['data'] == 'user') {
          $data_item = trim($data[$field_name]);
        }
        else {
          continue;
        }

        // Validate datetime fields
        if ($field['type'] == 'datetime') {
          if ($data_item == '') {
            continue;
          }

          if (function_exists('sentinel_portal_validate_date')) {
            $formatted_date = sentinel_portal_validate_date($data_item);
            if ($formatted_date == FALSE) {
              $errors[$field_name] = 'invalid date time value found';
              continue;
            }
            $sample[$field_name] = $formatted_date;
          }
        }

        // Validate pass/fail fields
        if ($field['type'] == 'int' && (isset($field['size']) && $field['size'] == 'tiny')) {
          if (stripos($field_name, 'pass_fail') !== FALSE) {
            $data_item = strtolower($data_item);
            if ($data_item == 'f') {
              $data_item = 0;
            }
            elseif ($data_item == 'p') {
              $data_item = 1;
            }
            elseif ($data_item == '') {
              $data_item = NULL;
            }
            else {
              $errors[$field_name] = 'invalid pass_fail value found';
              continue;
            }
          }
        }

        // Skip NULL values for specific fields
        if (in_array($field_name, ['mains_cl_result', 'sys_cl_result'])) {
          if ($data[$field_name] == 'NULL' || $data[$field_name] == '') {
            continue;
          }
        }

        $sample[$field_name] = $data_item;
      }
    }

    // Fix UCR (remove luhn number)
    $sample['ucr'] = floor($sample['ucr'] / 10);

    // Check if sample exists
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 'p')
      ->fields('p', ['pid'])
      ->condition('p.pack_reference_number', $sample['pack_reference_number'], '=');

    if ($global_access == FALSE) {
      $real_ucr = method_exists($client_data, 'getRealUcr') ? $client_data->getRealUcr() : '';
      $sample['api_created_by'] = $real_ucr;
      $query->condition('p.api_created_by', $real_ucr, '=');
    }

    $query->orderBy('pid', 'DESC');
    $result = $query->execute();
    $row = $result->fetchAssoc();

    $sample_update = FALSE;
    $message = '';

    if (isset($row['pid'])) {
      // Update existing sample
      if (function_exists('sentinel_portal_entities_update_sample')) {
        $sample_entity_storage = $entity_type_manager->getStorage('sentinel_sample');
        $sample_entity = $sample_entity_storage->load($row['pid']);
        
        $sample_entity = sentinel_portal_entities_update_sample($sample_entity, $sample);
        $sample_update = TRUE;
        $message = 'Sample updated';
      }
    }
    else {
      // Create new sample
      if (function_exists('sentinel_portal_entities_create_sample')) {
        $sample_entity = sentinel_portal_entities_create_sample($sample);
        $message = 'Sample created';
      }
    }

    if (count($errors) > 0) {
      $error_payload = [];
      foreach ($errors as $error_field => $error_description) {
        $error_payload[] = [
          'error_column' => $error_field,
          'error_description' => $error_description,
        ];
      }

      $response_data = [
        'status' => '200',
        'message' => $message . ' (with errors).',
        'error' => $error_payload,
      ];
    }
    else {
      $response_data = [
        'status' => '200',
        'message' => $message . '.',
      ];
    }

    $response = new ResourceResponse($response_data, Response::HTTP_OK);
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    
    return $response;
  }

  /**
   * Gets client data by API key.
   *
   * @param string $key
   *   The API key.
   *
   * @return mixed
   *   The client entity or FALSE.
   */
  protected function getClientByApiKey($key) {
    if (empty($key)) {
      return FALSE;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('sentinel_client');
    
    $query = $storage->getQuery()
      ->condition('api_key', $key)
      ->accessCheck(FALSE);

    $result = $query->execute();

    if (!empty($result)) {
      $client_ids = array_values($result);
      $clients = $storage->loadMultiple($client_ids);
      return reset($clients);
    }

    return FALSE;
  }

}



