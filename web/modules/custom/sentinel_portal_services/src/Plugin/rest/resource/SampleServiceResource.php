<?php

namespace Drupal\sentinel_portal_services\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\sentinel_portal_entities\Exception\SentinelSampleValidationException;
use Drupal\sentinel_portal_entities\Service\SentinelSampleValidation;
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

    if (empty($sample_fields)) {
      $sample_fields = [];
      $temp_sample = $entity_type_manager->getStorage('sentinel_sample')->create([]);
      foreach ($temp_sample->getFieldDefinitions() as $field_name => $definition) {
        $sample_fields[$field_name] = [
          'type' => $definition->getType(),
          'size' => $definition->getSetting('size') ?? NULL,
          'portal_config' => [
            'access' => [
              'data' => 'user',
            ],
          ],
        ];
      }
    }

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

          $formatted_date = SentinelSampleValidation::validateDate($data_item);
          if ($formatted_date === FALSE) {
            $errors[$field_name] = SentinelSampleValidation::formatErrorMessage($field_name, 'has an invalid date.');
            continue;
          }
          $sample[$field_name] = $formatted_date;
          continue;
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
              $errors[$field_name] = SentinelSampleValidation::formatErrorMessage($field_name, 'has an invalid pass/fail value.');
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

    try {
      $api_validation_errors = SentinelSampleValidation::validateSampleInApi($sample, $client_data);
      $errors = $errors + $api_validation_errors;
    }
    catch (SentinelSampleValidationException $e) {
      $error_payload = [];
      foreach ($e->getErrors() as $field => $message) {
        $error_payload[] = [
          'error_column' => $field,
          'error_description' => $message,
        ];
      }

      $response_data = [
        'status' => Response::HTTP_NOT_ACCEPTABLE,
        'message' => $e->getMessage(),
        'error' => $error_payload,
      ];

      $response = new ResourceResponse($response_data, Response::HTTP_NOT_ACCEPTABLE);
      $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);

      return $response;
    }

    // Fix UCR (remove luhn number)
    if (isset($sample['ucr'])) {
      $sample['ucr'] = floor($sample['ucr'] / 10);
    }

    /** @var \Drupal\Core\Entity\EntityStorageInterface $sample_storage */
    $sample_storage = $entity_type_manager->getStorage('sentinel_sample');

    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 'p')
      ->fields('p', ['pid'])
      ->condition('p.pack_reference_number', $sample['pack_reference_number'], '=');

    if ($global_access == FALSE) {
      $real_ucr = method_exists($client_data, 'getRealUcr') ? $client_data->getRealUcr() : '';
      $sample['api_created_by'] = $real_ucr;
      $query->condition('p.api_created_by', $real_ucr, '=');
    }

    $existing_id = $query->orderBy('pid', 'DESC')->execute()->fetchField();

    $sample_entity = NULL;
    $sample_entity_original = NULL;
    $sample_update = FALSE;
    $message = '';

    if ($existing_id) {
      $sample_entity = $sample_storage->load($existing_id);

      if (!$sample_entity) {
        throw new NotFoundHttpException('Sample not found.');
      }

      if ($global_access == FALSE && method_exists($sample_entity, 'isReported') && $sample_entity->isReported()) {
        throw new BadRequestHttpException('Sample has already been reported and cannot be updated. Please contact Sentinel.');
      }

      $sample_entity_original = clone $sample_entity;

      // Duplicate handling.
      $duplicate_test = $sample_storage->create([]);
      SentinelSampleEntityHelper::applySampleData($duplicate_test, $sample);
      if (method_exists($sample_entity, 'id')) {
        $duplicate_test->set('duplicate_of', $sample_entity->id());
      }

      if (SentinelSampleEntityHelper::isDuplicate($duplicate_test)) {
        $duplicate_test->set('duplicate_of', $sample_entity->id());
        $duplicate_existing = SentinelSampleEntityHelper::findDuplicate($duplicate_test);

        if ($duplicate_existing) {
          $sample_entity = $duplicate_existing;
          unset($sample['pack_reference_number']);
        }
        else {
          SentinelSampleEntityHelper::renameDuplicate($duplicate_test);
          $sample['pack_reference_number'] = $duplicate_test->get('pack_reference_number')->value;
          $sample['duplicate_of'] = $sample_entity->id();
        }
      }

      SentinelSampleEntityHelper::applySampleData($sample_entity, $sample);
      $sample_entity->save();

      $sample_update = TRUE;
      $message = 'Sample updated';
    }
    else {
      if (!empty($sample['pack_reference_number']) && SentinelSampleEntityHelper::loadSampleByPackReference($sample['pack_reference_number'])) {
        throw new BadRequestHttpException('Access denied.');
      }

      $sample_entity = $sample_storage->create([]);
      SentinelSampleEntityHelper::applySampleData($sample_entity, $sample);
      $sample_entity->save();

      $sample_update = FALSE;
      $message = 'Sample created';
    }

    $date_reported_original = $sample_entity_original ? ($sample_entity_original->get('date_reported')->value ?? NULL) : NULL;
    $is_reported = method_exists($sample_entity, 'isReported') ? $sample_entity->isReported() : FALSE;

    if ($is_reported) {
      self::recalculateSampleResults($sample_entity);

      $date_reported_current = $sample_entity->get('date_reported')->value ?? NULL;
      if ($date_reported_current && $date_reported_current !== $date_reported_original && function_exists('sentinel_portal_queue_create_item')) {
        sentinel_portal_queue_create_item($sample_entity, 'sendreport');
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

  /**
   * Recalculate derived results for a sample (matches D7 behaviour).
   */
  protected static function recalculateSampleResults($sample_entity) {
    if (!is_object($sample_entity)) {
      return;
    }

    // Ensure the x100 value is always up to date.
    if (method_exists($sample_entity, 'calculateX100')) {
      $sample_entity->calculateX100();
    }

    if (function_exists('sentinel_systemcheck_certificate_populate_results')) {
      $results = new \stdClass();
      $formatted_results = new \stdClass();
      sentinel_systemcheck_certificate_populate_results($results, $sample_entity, $formatted_results);
      // Persist calculated fields.
      if (method_exists($sample_entity, 'save')) {
        $sample_entity->save();
      }
    }

    if (function_exists('sentinel_systemcheck_certificate_calculate_sentinel_sample_result')) {
      sentinel_systemcheck_certificate_calculate_sentinel_sample_result($sample_entity);
      if (method_exists($sample_entity, 'save')) {
        $sample_entity->save();
      }
    }
  }

}



