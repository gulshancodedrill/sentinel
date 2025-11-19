<?php

namespace Drupal\sentinel_portal_services\Plugin\rest\resource;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\sentinel_portal_entities\Exception\SentinelSampleValidationException;
use Drupal\sentinel_portal_entities\Service\SentinelSampleValidation;
use Drupal\sentinel_portal_services\Helper\SentinelSampleEntityHelper;
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

    // Validate UCR (required)
    if (empty($ucr)) {
      throw new BadRequestHttpException('ucr is missing');
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('sentinel_client');
    $client = $storage->create([]);

    if (method_exists($client, 'validateUcr')) {
      $ucr_int = (int) $ucr;
      if (!$client->validateUcr($ucr_int)) {
        $generated_ucr = $client->generateUcr($ucr_int);
        if ($client->validateUcr($generated_ucr)) {
          $ucr = $generated_ucr;
        }
        else {
          throw new BadRequestHttpException('UCR is not valid');
        }
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

      // Normalize the response to match Drupal 7 format
      $normalized_data = $this->normalizeResponseData($return_array);

      $response_data = [
        'status' => '200',
        'message' => $normalized_data,
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

    if (method_exists($client, 'validateUcr')) {
      $provided_ucr = (int) $data['ucr'];
      if (!$client->validateUcr($provided_ucr)) {
        $generated_ucr = $client->generateUcr($provided_ucr);
        if ($client->validateUcr($generated_ucr)) {
          $data['ucr'] = $generated_ucr;
        }
        else {
          throw new BadRequestHttpException('ucr is not valid');
        }
      }
    }

    // Process and validate the incoming data
    $sample = [];
    $errors = [];
    $global_access = $client_data->get('global_access')->value ?? FALSE;

    // Skip admin/system fields
    $skip_fields = ['updated', 'created', 'pid', 'vid', 'uuid', 'changed'];

    // Process each field in the incoming data
    foreach ($data as $field_name => $value) {
      // Skip admin fields
      if (in_array($field_name, $skip_fields)) {
        continue;
      }

      $data_item = is_string($value) ? trim($value) : $value;

      // Validate datetime fields
      if (in_array($field_name, ['date_installed', 'date_sent', 'date_booked', 'date_processed', 'date_reported'])) {
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
      if (stripos($field_name, 'pass_fail') !== FALSE) {
        if ($data_item === '' || $data_item === NULL) {
          $data_item = NULL;
        }
        else {
          switch (strtoupper((string) $data_item)) {
            case 'P':
              $data_item = SentinelSampleValidation::PASS_VALUE;
              break;

            case 'F':
              $data_item = SentinelSampleValidation::FAIL_VALUE;
              break;

            case '1':
            case '0':
              $data_item = (int) $data_item;
              break;

            default:
              $errors[$field_name] = SentinelSampleValidation::formatErrorMessage($field_name, 'has an invalid pass/fail value.');
              continue 2;
          }
        }
      }

      // Skip NULL values for chloride fields
      if (in_array($field_name, ['mains_cl_result', 'sys_cl_result'])) {
        if ($data_item === 'NULL' || $data_item === '') {
          continue;
        }
      }

      $sample[$field_name] = $data_item;
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
    
    // Only validate on CREATE, not on UPDATE
    $is_new_sample = empty($existing_id);

    if ($existing_id) {
      // UPDATE existing sample
      $sample_entity = $sample_storage->load($existing_id);

      if (!$sample_entity) {
        throw new NotFoundHttpException('Sample not found.');
      }

      if ($global_access == FALSE && method_exists($sample_entity, 'isReported') && $sample_entity->isReported()) {
        throw new BadRequestHttpException('Sample has already been reported and cannot be updated. Please contact Sentinel.');
      }

      $sample_entity_original = clone $sample_entity;

      // Apply sample data to existing entity (merge)
      foreach ($sample as $field_name => $value) {
        if ($sample_entity->hasField($field_name)) {
          $sample_entity->set($field_name, $value);
        }
      }

      $this->ensureAddressEntities($sample_entity, $sample);

      // Create a new revision on each update.
      $sample_entity->setNewRevision(TRUE);
      $sample_entity->save();

      $sample_update = TRUE;
      $message = 'Sample updated';
    }
    else {
      // CREATE new sample - validate required fields
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
      
      // Check if pack reference already exists (shouldn't at this point)
      if (!empty($sample['pack_reference_number']) && SentinelSampleEntityHelper::loadSampleByPackReference($sample['pack_reference_number'])) {
        throw new BadRequestHttpException('Access denied.');
      }

      $sample_entity = $sample_storage->create([]);
      foreach ($sample as $field_name => $value) {
        if ($sample_entity->hasField($field_name)) {
          $sample_entity->set($field_name, $value);
        }
      }

      $this->ensureAddressEntities($sample_entity, $sample);

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
   * Ensure company and sample address entities exist and are referenced.
   */
  protected function ensureAddressEntities(ContentEntityInterface $sample_entity, array $data): void {
    try {
      $entity_type_manager = \Drupal::entityTypeManager();
      $address_storage = $entity_type_manager->getStorage('address');

      // Company address.
      $company_target_id = $this->getExistingAddressId($sample_entity, 'field_company_address', 'sentinel_company_address_target_id');
      $company_entity = NULL;
      $existing_company_values = [];
      if ($company_target_id) {
        $company_entity = $address_storage->load($company_target_id);
        if (!$company_entity) {
          $company_target_id = NULL;
        }
        else {
          $existing_company_values = $this->extractAddressFieldValues($company_entity);
        }
      }

      $company_address_data = $this->buildCompanyAddressFieldValues($data, $existing_company_values);

      if (!empty($company_address_data)) {
        if ($company_entity) {
          $company_entity->set('field_address', [$company_address_data]);
          $company_entity->save();
        }
        else {
          $company_entity = $address_storage->create([
            'type' => 'company_address',
            'field_address' => [$company_address_data],
          ]);
          $company_entity->save();
        }

        $company_target_id = (int) $company_entity->id();

        if ($sample_entity->hasField('field_company_address')) {
          $sample_entity->set('field_company_address', ['target_id' => $company_target_id]);
        }
        if ($sample_entity->hasField('sentinel_company_address_target_id')) {
          $sample_entity->set('sentinel_company_address_target_id', $company_target_id);
        }
      }

      // Sample address.
      $sample_target_id = $this->getExistingAddressId($sample_entity, 'field_sentinel_sample_address', 'sentinel_sample_address_target_id');
      $sample_entity_ref = NULL;
      $existing_sample_values = [];
      if ($sample_target_id) {
        $sample_entity_ref = $address_storage->load($sample_target_id);
        if (!$sample_entity_ref) {
          $sample_target_id = NULL;
        }
        else {
          $existing_sample_values = $this->extractAddressFieldValues($sample_entity_ref);
        }
      }

      $sample_address_data = $this->buildSampleAddressFieldValues($data, $existing_sample_values);

      if (!empty($sample_address_data)) {
        if ($sample_entity_ref) {
          $sample_entity_ref->set('field_address', [$sample_address_data]);
          $sample_entity_ref->save();
        }
        else {
          $sample_entity_ref = $address_storage->create([
            'type' => 'address',
            'field_address' => [$sample_address_data],
          ]);
          $sample_entity_ref->save();
        }

        $sample_target_id = (int) $sample_entity_ref->id();

        if ($sample_entity->hasField('field_sentinel_sample_address')) {
          $sample_entity->set('field_sentinel_sample_address', ['target_id' => $sample_target_id]);
        }
        if ($sample_entity->hasField('sentinel_sample_address_target_id')) {
          $sample_entity->set('sentinel_sample_address_target_id', $sample_target_id);
        }
      }
    }
    catch (\Throwable $throwable) {
      \Drupal::logger('sentinel_portal_services')->error('Failed ensuring API sample addresses: @message', [
        '@message' => $throwable->getMessage(),
      ]);
      throw $throwable;
    }
  }

  /**
   * Build address field values for the company address bundle.
   */
  protected function buildCompanyAddressFieldValues(array $data, array $existing_values = [], bool $allow_fallback = TRUE): array {
    $values = $existing_values;

    $country = strtoupper(trim((string) ($data['company_country'] ?? '')));
    if ($country !== '') {
      $values['country_code'] = $country;
    }
    elseif (array_key_exists('company_country', $data)) {
      unset($values['country_code']);
    }

    $address_line1 = trim((string) ($data['company_address1'] ?? $data['company_address_1'] ?? ''));
    if ($address_line1 === '' && $existing_values === []) {
      $address_line1 = trim((string) ($data['company_property_name'] ?? $data['company_property_number'] ?? ''));
    }
    if ($address_line1 !== '') {
      $values['address_line1'] = $address_line1;
    }
    elseif (array_key_exists('company_address1', $data) || array_key_exists('company_address_1', $data)) {
      unset($values['address_line1']);
    }

    $additional_line = trim((string) ($data['company_address2'] ?? $data['company_address_2'] ?? ''));
    $organization = trim((string) (
      $data['company'] ??
      $data['company_name'] ??
      ''
    ));
    if ($organization !== '') {
      $values['organization'] = $organization;
    }
    elseif (array_key_exists('company', $data) || array_key_exists('company_name', $data)) {
      unset($values['organization']);
    }

    $property_name = trim((string) ($data['company_property_name'] ?? ''));
    $property_number = trim((string) ($data['company_property_number'] ?? ''));

    $address_line2_parts = [];
    if ($property_name !== '') {
      $address_line2_parts[] = $property_name;
    }
    if ($property_number !== '') {
      $address_line2_parts[] = $property_number;
    }
    if ($additional_line !== '') {
      $address_line2_parts[] = $additional_line;
    }
    $address_line2 = trim(implode(' ', $address_line2_parts));
    if ($address_line2 !== '') {
      $values['address_line2'] = $address_line2;
    }
    elseif (
      array_key_exists('company_address2', $data) ||
      array_key_exists('company_address_2', $data) ||
      array_key_exists('company_property_name', $data) ||
      array_key_exists('company_property_number', $data)
    ) {
      unset($values['address_line2']);
    }

    $locality = trim((string) ($data['company_town'] ?? $data['company_town_city'] ?? $data['company_city'] ?? ''));
    if ($locality !== '') {
      $values['locality'] = $locality;
    }
    elseif (array_key_exists('company_town', $data) || array_key_exists('company_town_city', $data) || array_key_exists('company_city', $data)) {
      unset($values['locality']);
    }

    $postal_code = trim((string) ($data['company_postcode'] ?? ''));
    if ($postal_code !== '') {
      $values['postal_code'] = $postal_code;
    }
    elseif (array_key_exists('company_postcode', $data)) {
      unset($values['postal_code']);
    }

    $administrative_area = trim((string) ($data['company_county'] ?? ''));
    if ($administrative_area !== '') {
      $values['administrative_area'] = $administrative_area;
    }
    elseif (array_key_exists('company_county', $data)) {
      unset($values['administrative_area']);
    }

    if ($address_line1 === '' && $address_line2 === '' && $locality === '' && $postal_code === '' && $organization === '') {
      if ($allow_fallback) {
        $fallback = $this->buildSampleAddressFieldValues($data);
        if (!empty($fallback)) {
          if ($organization !== '') {
            $fallback['organization'] = $organization;
          }
          return $fallback;
        }
      }
      return $values;
    }

    if (!isset($values['country_code']) || $values['country_code'] === '') {
      if (!empty($values)) {
        $values['country_code'] = 'GB';
      }
    }

    return $values;
  }

  /**
   * Build address field values for the sample address bundle.
   */
  protected function buildSampleAddressFieldValues(array $data, array $existing_values = []): array {
    $values = $existing_values;

    $country = strtoupper(trim((string) ($data['country'] ?? $data['sample_country'] ?? '')));
    if ($country !== '') {
      $values['country_code'] = $country;
    }
    elseif (array_key_exists('country', $data) || array_key_exists('sample_country', $data)) {
      unset($values['country_code']);
    }

    $address_line1 = trim((string) (
      $data['address_1'] ??
      $data['sample_address_1'] ??
      $data['street'] ??
      ''
    ));
    if ($address_line1 !== '') {
      $values['address_line1'] = $address_line1;
    }
    elseif (
      array_key_exists('address_1', $data) ||
      array_key_exists('sample_address_1', $data) ||
      array_key_exists('street', $data)
    ) {
      unset($values['address_line1']);
    }

    $address_line2_parts = array_filter([
      trim((string) ($data['address_2'] ?? $data['sample_address_2'] ?? '')),
      trim((string) ($data['property_name'] ?? '')),
      trim((string) ($data['property_number'] ?? '')),
    ]);
    $address_line2 = trim(implode(' ', $address_line2_parts));
    if ($address_line2 !== '') {
      $values['address_line2'] = $address_line2;
    }
    elseif (
      array_key_exists('address_2', $data) ||
      array_key_exists('sample_address_2', $data) ||
      array_key_exists('property_name', $data) ||
      array_key_exists('property_number', $data)
    ) {
      unset($values['address_line2']);
    }

    $locality = trim((string) (
      $data['town_city'] ??
      $data['city'] ??
      ''
    ));
    if ($locality !== '') {
      $values['locality'] = $locality;
    }
    elseif (array_key_exists('town_city', $data) || array_key_exists('city', $data)) {
      unset($values['locality']);
    }

    $postal_code = trim((string) ($data['postcode'] ?? $data['postal_code'] ?? ''));
    if ($postal_code !== '') {
      $values['postal_code'] = $postal_code;
    }
    elseif (array_key_exists('postcode', $data) || array_key_exists('postal_code', $data)) {
      unset($values['postal_code']);
    }

    $administrative_area = trim((string) ($data['county'] ?? $data['region'] ?? ''));
    if ($administrative_area !== '') {
      $values['administrative_area'] = $administrative_area;
    }
    elseif (array_key_exists('county', $data) || array_key_exists('region', $data)) {
      unset($values['administrative_area']);
    }

    $organization = trim((string) ($data['landlord'] ?? ''));
    if ($organization !== '') {
      $values['organization'] = $organization;
    }
    elseif (array_key_exists('landlord', $data)) {
      unset($values['organization']);
    }

    if (!isset($values['country_code']) || $values['country_code'] === '') {
      if (!empty($values)) {
        $values['country_code'] = 'GB';
      }
    }

    return $values;
  }

  protected function extractAddressFieldValues(?ContentEntityInterface $address_entity): array {
    if (!$address_entity || !$address_entity->hasField('field_address')) {
      return [];
    }

    $item = $address_entity->get('field_address')->first();
    if (!$item) {
      return [];
    }

    $values = $item->getValue();
    foreach ($values as $key => $value) {
      if (is_string($value)) {
        $values[$key] = trim($value);
      }
    }

    return $values;
  }

  protected function getExistingAddressId(ContentEntityInterface $sample_entity, string $reference_field, string $legacy_field): ?int {
    if ($sample_entity->hasField($reference_field)) {
      $item = $sample_entity->get($reference_field)->first();
      if ($item && !empty($item->target_id)) {
        return (int) $item->target_id;
      }
    }

    if ($sample_entity->hasField($legacy_field)) {
      $legacy_value = $sample_entity->get($legacy_field)->value;
      if (!empty($legacy_value)) {
        return (int) $legacy_value;
      }
    }

    return NULL;
  }
 
   /**
    * Normalize response data to match Drupal 7 format.
    *
    * Converts Drupal 11's nested field arrays into simple key-value pairs
   */
  protected function normalizeResponseData(array $data) {
    // First, extract and normalize all values
    $temp_normalized = [];
    
    foreach ($data as $field_name => $field_value) {
      // Skip system fields and internal fields we don't need in API response
      if (in_array($field_name, ['pid', 'uuid', 'vid', 'revision_default', 'changed', 'old_pack_reference_number', 'duplicate_of', 'legacy', 'api_created_by', 'ucr'])) {
        continue;
      }
      
      // Handle different field value structures
      if (is_array($field_value)) {
        // Check if it's a field array with 'value' keys
        if (isset($field_value[0]) && is_array($field_value[0])) {
          if (isset($field_value[0]['value'])) {
            // Single or multi-value field with 'value' key
            $temp_normalized[$field_name] = $field_value[0]['value'];
          }
          elseif (isset($field_value[0]['target_id'])) {
            // Entity reference field
            $temp_normalized[$field_name] = $field_value[0]['target_id'];
          }
          else {
            // Complex field, take first item
            $temp_normalized[$field_name] = $field_value[0];
          }
        }
        elseif (empty($field_value)) {
          // Empty array = null
          $temp_normalized[$field_name] = null;
        }
        else {
          // Direct array value
          $temp_normalized[$field_name] = $field_value;
        }
      }
      else {
        // Direct value
        $temp_normalized[$field_name] = $field_value;
      }
    }
    
    // Convert pass_fail numeric values to "P" or "F" to match D7
    if (isset($temp_normalized['pass_fail'])) {
      $temp_normalized['pass_fail'] = $temp_normalized['pass_fail'] == 1 ? 'P' : ($temp_normalized['pass_fail'] == 0 ? 'F' : null);
    }
    
    // Convert all *_pass_fail fields
    foreach ($temp_normalized as $key => $value) {
      if (strpos($key, '_pass_fail') !== false && $value !== null) {
        $temp_normalized[$key] = $value == 1 ? 'P' : ($value == 0 ? 'F' : null);
      }
    }
    
    // Format dates to match D7 format (remove seconds)
    $date_fields = ['date_installed', 'date_sent', 'date_booked', 'date_processed', 'date_reported', 'created', 'updated'];
    foreach ($date_fields as $date_field) {
      if (isset($temp_normalized[$date_field]) && !empty($temp_normalized[$date_field])) {
        // Convert from "2015-06-07 00:00:00" to "2015-06-07T00:00"
        $temp_normalized[$date_field] = str_replace(' ', 'T', substr($temp_normalized[$date_field], 0, 16));
      }
    }
    
    // Ensure nitrate_result is string if it exists
    if (isset($temp_normalized['nitrate_result']) && is_numeric($temp_normalized['nitrate_result'])) {
      $temp_normalized['nitrate_result'] = (string)$temp_normalized['nitrate_result'];
    }
    
    // Define the exact field order to match Drupal 7
    $field_order = [
      'pack_reference_number',
      'project_id',
      'installer_name',
      'installer_email',
      'company_name',
      'company_email',
      'company_address1',
      'company_address2',
      'company_town',
      'company_county',
      'company_postcode',
      'company_tel',
      'system_location',
      'system_age',
      'system_6_months',
      'uprn',
      'property_number',
      'street',
      'town_city',
      'county',
      'postcode',
      'landlord',
      'boiler_manufacturer',
      'boiler_id',
      'boiler_type',
      'engineers_code',
      'service_call_id',
      'date_installed',
      'date_sent',
      'date_booked',
      'date_processed',
      'date_reported',
      'fileid',
      'filename',
      'client_id',
      'client_name',
      'customer_id',
      'lab_ref',
      'pack_type',
      'card_complete',
      'on_hold',
      'pass_fail',
      'appearance_result',
      'appearance_pass_fail',
      'mains_cond_result',
      'sys_cond_result',
      'cond_pass_fail',
      'mains_cl_result',
      'sys_cl_result',
      'cl_pass_fail',
      'iron_result',
      'iron_pass_fail',
      'copper_result',
      'copper_pass_fail',
      'aluminium_result',
      'aluminium_pass_fail',
      'mains_calcium_result',
      'sys_calcium_result',
      'calcium_pass_fail',
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
      'nitrate_result',
      'mob_ratio',
      'created',
      'updated',
      'installer_company',
    ];
    
    // Build the final array in the correct order
    $normalized = [];
    foreach ($field_order as $field_name) {
      if (array_key_exists($field_name, $temp_normalized)) {
        $normalized[$field_name] = $temp_normalized[$field_name];
      }
    }
    
    // Add any remaining fields that weren't in the order list (for future compatibility)
    foreach ($temp_normalized as $field_name => $value) {
      if (!isset($normalized[$field_name])) {
        $normalized[$field_name] = $value;
      }
    }
    
    return $normalized;
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



