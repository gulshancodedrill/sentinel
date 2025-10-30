<?php

namespace Drupal\sentinel_portal_services\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing customer ID.
   */
  public function get(Request $request = NULL) {
    if (!$request) {
      $request = \Drupal::request();
    }

    $key = $request->query->get('key');
    $email = $request->query->get('email');
    $name = $request->query->get('name');
    $company = $request->query->get('company');

    // Validate the API key
    if (!$this->validateApiKey($key)) {
      throw new BadRequestHttpException('API key is invalid');
    }

    // Normalize the strings
    $email = trim($email);
    $name = trim($name);

    // Validate email
    if (!\Drupal::service('email.validator')->isValid($email)) {
      // Check for multi-address value
      if (strpos($email, ';') !== FALSE) {
        $multiple_addresses = preg_split('/;/', $email);
        
        $config = \Drupal::config('sentinel_portal_module.settings');
        $stop_emails_string = $config->get('stop_emails') ?? '';
        $stop_emails = explode(PHP_EOL, $stop_emails_string);

        $email_found = FALSE;

        foreach ($multiple_addresses as $address) {
          $domain = strtolower(trim(substr($address, strpos($address, '@') + 1)));
          if (!in_array($domain, $stop_emails)) {
            $email = $address;
            $email_found = TRUE;
            break;
          }
        }

        if ($email_found === FALSE) {
          $email = array_shift($multiple_addresses);
        }
      }

      if (!\Drupal::service('email.validator')->isValid($email)) {
        throw new BadRequestHttpException('Email parameter is not a valid email address');
      }
    }

    // Query for existing client
    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('sentinel_client');
    
    $query = $storage->getQuery()
      ->condition('email', $email)
      ->condition('api_key', '', '=')
      ->accessCheck(FALSE);

    if (!is_null($company) && trim($company) != '') {
      $company = trim($company);
    }
    else {
      $company = '';
    }

    $result = $query->execute();

    if (empty($result)) {
      // Create new client
      $client_data = [
        'email' => $email,
        'name' => $name,
      ];

      if ($company != '') {
        $client_data['company'] = $company;
      }

      $client = $storage->create($client_data);
      $client->save();
    }
    else {
      $client_ids = array_values($result);
      $clients = $storage->loadMultiple($client_ids);
      $client = reset($clients);

      if ($company != '') {
        $client->set('company', $company);
        $client->save();
      }
    }

    $return_array = [
      'name' => $client->get('name')->value,
      'email' => $client->get('email')->value,
      'customer_id' => method_exists($client, 'getUcr') ? $client->getUcr() : '',
    ];

    if ($company != '') {
      $return_array['company'] = $client->get('company')->value;
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
   * Validates the API key.
   *
   * @param string $key
   *   The API key.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validateApiKey($key) {
    if (empty($key)) {
      return FALSE;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage('sentinel_client');
    
    $query = $storage->getQuery()
      ->condition('api_key', $key)
      ->accessCheck(FALSE);

    $result = $query->execute();

    return !empty($result);
  }

}



