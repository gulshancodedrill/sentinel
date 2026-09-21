<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Calls /sentinel/customerservice and extracts UCR + optional portal client id.
 */
final class SentinelCustomerServiceLookup {

  /**
   * Fetches customer data from the customer-service endpoint.
   *
   * @return array{ucr: ?string, client_cid: ?int, customer_id_raw: ?string}
   *   ucr is the first 4 digits of the numeric customer_id from the API when present.
   */
  public static function fetch(ClientInterface $http, Request $request, string $email, string $name, string $company): array {
    $out = [
      'ucr' => NULL,
      'client_cid' => NULL,
      'customer_id_raw' => NULL,
    ];

    $email = trim($email);
    $name = trim($name);
    $company = trim($company);
    if ($email === '' || $name === '') {
      return $out;
    }

    $key = Settings::get('sentinel_customer_service_key', '99754106633f94d350db34d548d6091a');
    if (!is_string($key) || trim($key) === '') {
      return $out;
    }

    $base = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');
    $url = $base . '/sentinel/customerservice';

    try {
      $response = $http->request('GET', $url, [
        'query' => [
          'key' => $key,
          'email' => $email,
          'name' => $name,
          'company' => $company,
        ],
        'timeout' => 15,
      ]);
      $body = trim((string) $response->getBody());
      if ($body === '') {
        return $out;
      }

      $parsed = self::parseResponseBody($body);
      $out['customer_id_raw'] = $parsed['customer_id_raw'];
      if ($parsed['client_cid'] !== NULL) {
        $out['client_cid'] = $parsed['client_cid'];
      }
      if ($parsed['customer_id_raw'] !== NULL && $parsed['customer_id_raw'] !== '') {
        $digits = preg_replace('/\D+/', '', (string) $parsed['customer_id_raw']);
        if (is_string($digits) && strlen($digits) >= 4) {
          $out['ucr'] = substr($digits, 0, 4);
        }
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('sentinel_portal_sample')->warning('Customer service lookup failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $out;
  }

  /**
   * @return array{customer_id_raw: ?string, client_cid: ?int}
   */
  public static function parseResponseBody(string $body): array {
    $customer_id_raw = NULL;
    $client_cid = NULL;

    $data = json_decode($body, TRUE);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
      $customer_id_raw = self::firstScalarString($data, [
        'customer_id',
        'customerId',
        'CustomerId',
        'CUSTOMER_ID',
        'scr',
        'SCR',
      ]);
      $cid_raw = self::firstScalarString($data, [
        'cid',
        'client_id',
        'clientId',
        'ClientId',
        'portal_client_id',
        'portalClientId',
      ]);
      if ($cid_raw !== NULL && $cid_raw !== '') {
        $digits = preg_replace('/\D+/', '', $cid_raw);
        if ($digits !== '' && ctype_digit($digits)) {
          $client_cid = (int) $digits;
        }
      }
    }

    if ($customer_id_raw === NULL && preg_match('/"customer_id"\s*:\s*"?(\d+)/i', $body, $m)) {
      $customer_id_raw = $m[1];
    }
    if ($client_cid === NULL && preg_match('/"(?:cid|client_id)"\s*:\s*"?(\d+)/i', $body, $m)) {
      $client_cid = (int) $m[1];
    }

    return [
      'customer_id_raw' => $customer_id_raw,
      'client_cid' => $client_cid,
    ];
  }

  /**
   * Finds first non-empty scalar under known keys (top-level or one-level data).
   */
  private static function firstScalarString(array $data, array $keys): ?string {
    foreach ($keys as $key) {
      if (!empty($data[$key]) && (is_string($data[$key]) || is_numeric($data[$key]))) {
        return trim((string) $data[$key]);
      }
    }
    if (isset($data['data']) && is_array($data['data'])) {
      foreach ($keys as $key) {
        if (!empty($data['data'][$key]) && (is_string($data['data'][$key]) || is_numeric($data['data'][$key]))) {
          return trim((string) $data['data'][$key]);
        }
      }
    }
    return NULL;
  }

}
