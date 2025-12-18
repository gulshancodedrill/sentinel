<?php

namespace Drupal\sentinel_portal_services\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;


/**
 * Provides a Customer Service Resource.
 *
 * @RestResource(
 *   id = "sentinel_customerservice",
 *   label = @Translation("Sentinel Customer Service"),
 *   uri_paths = {
 *     "canonical" = "/sentinel/customerservice"
 *   }
 * )
 */
class CustomerServiceResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * Returns a customer ID for given customer details. Creates one if it doesn't exist.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing customer data.
   */
  public function get(Request $request = NULL) {
    if (!$request) {
      $request = \Drupal::request();
    }

    $key = $request->query->get('key');
    $email = trim($request->query->get('email') ?? '');
    $name = trim($request->query->get('name') ?? '');
    $company = trim($request->query->get('company') ?? '');

   // Log API call.
   \Drupal::logger('sentinel_portal_services')->info('API GET /sentinel/customerservice called - Email: @email, Name: @name', [
    '@email' => isset($email) ? $email : " ",
    '@name' => isset($name) ? $name : " ",
  ]);
    // Validate the API key
    $client_data = $this->getClientByApiKey($key);
    if (!$client_data) {
      \Drupal::logger('sentinel_portal_services')->warning('API GET /sentinel/customerservice - Invalid API key');
      throw new BadRequestHttpException('API key is invalid');
    }

    // Validate email address
    if (!$this->validateEmail($email)) {
      // Try to handle multi-address (separated by semicolon)
      if (strpos($email, ';') !== FALSE) {
        $multiple_addresses = explode(';', $email);
        $email_found = FALSE;

        foreach ($multiple_addresses as $address) {
          $address = trim($address);
          if ($this->validateEmail($address)) {
            $email = $address;
            $email_found = TRUE;
            break;
          }
        }

        if (!$email_found) {
          $email = trim($multiple_addresses[0]);
        }
      }

      if (!$this->validateEmail($email)) {
        \Drupal::logger('sentinel_portal_services')->warning('API GET /sentinel/customerservice - Invalid email validation');
        $response_data = [
          'status' => 406,
          'message' => 'Not acceptable',
          'error' => [
            [
              'error_column' => 'email',
              'error_description' => 'not a valid email address',
            ],
          ],
        ];

        return new ResourceResponse($response_data, Response::HTTP_NOT_ACCEPTABLE);
      }
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('sentinel_client');

    // Query for existing client by email
    $database = \Drupal::database();
     $query = $storage->getQuery()
      ->condition('email', $email)
      ->accessCheck(FALSE);

    $result = $query->execute();

   // Execute and fetch the results
  // $result = $query->execute()->fetchAll();
  //  $query = $storage->getQuery()
    //  ->condition('email', $email)
  //    ->condition('api_key', '', '=')
   //   ->accessCheck(FALSE);

  //  $result = $query->execute();
   // dd($result);

    if (empty($result)) {
      // Client doesn't exist, create new one
      $client_data = [
        'email' => $email,
        'name' => $name,
      ];

      if (!empty($company)) {
        $client_data['company'] = $company;
      }

      $client = $storage->create($client_data);
      $client->save();
    }
    else {
      // Client exists, load it
      $client_ids = array_values($result);
      $clients = $storage->loadMultiple($client_ids);
      $client = reset($clients);

      // Update company if provided
      if (!empty($company)) {
        $client->set('company', $company);
        $client->save();
      }
    }

    // Build return array
    $return_array = [
      'name' => $client->get('name')->value ?? '',
      'email' => $client->get('email')->value ?? '',
      'customer_id' => method_exists($client, 'getUcr') ? (string) $client->getUcr() : (string) ($client->get('ucr')->value ?? ''),
    ];

    if (!empty($company)) {
      $return_array['company'] = $client->get('company')->value ?? '';
    }

    $response_data = [
      'status' => '200',
      'message' => $return_array,
    ];

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
   * Validate email address.
   *
   * @param string $email
   *   The email to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validateEmail($email) {
    if (empty($email)) {
      return FALSE;
    }

    // Use Drupal's email validator
    return \Drupal::service('email.validator')->isValid($email);
  }

}
