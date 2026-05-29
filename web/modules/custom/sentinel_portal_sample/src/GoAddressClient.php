<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/**
 * GoAddress.io property lookup (house number + postcode).
 */
final class GoAddressClient {

  private const API_URL = 'https://portal.goaddress.io/api/address/search';

  /**
   * Searches GoAddress and returns keyed results from new_address_res.
   *
   * @return array<string, array{label: string, fields: array<string, string>, raw: array}>
   *   Keys are GoAddress addressid values.
   */
  public static function search(ClientInterface $http, string $house_no, string $postcode): array {
    $house_no = trim($house_no);
    $postcode = trim($postcode);
    if ($house_no === '' || $postcode === '') {
      return [];
    }

    $token = Settings::get('goaddress_api_token');
    if (!is_string($token) || trim($token) === '') {
      \Drupal::logger('sentinel_portal_sample')->error('GoAddress API token is not configured.');
      return [];
    }

    try {
      $response = $http->request('GET', self::API_URL, [
        'query' => [
          'q' => $house_no,
          'postcode' => $postcode,
        ],
        'headers' => [
          'Authorization' => 'Bearer ' . trim($token),
        ],
        'timeout' => 15,
      ]);
      $body = (string) $response->getBody();
      if ($body === '') {
        return [];
      }
      $data = json_decode($body, TRUE);
      if (!is_array($data)) {
        return [];
      }
      return self::parseResponse($data);
    }
    catch (\Throwable $e) {
      \Drupal::logger('sentinel_portal_sample')->error('GoAddress search failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Maps a new_address_res item to portal address field values.
   */
  public static function mapNewAddressRes(array $item): array {
    $town = trim((string) ($item['town'] ?? $item['city'] ?? $item['post_town'] ?? ''));
    return [
      'country' => 'GB',
      'address_1' => trim((string) ($item['raw_address'] ?? '')),
      'town_city' => $town,
      'postcode' => trim((string) ($item['postcode'] ?? '')),
      'county' => trim((string) ($item['county'] ?? '')),
      'goaddress_id' => trim((string) ($item['addressid'] ?? '')),
    ];
  }

  /**
   * @param array $data
   *   Decoded API JSON.
   *
   * @return array<string, array{label: string, fields: array<string, string>, raw: array}>
   */
  public static function parseResponse(array $data): array {
    $labels = [];
    if (!empty($data['results']) && is_array($data['results'])) {
      foreach ($data['results'] as $row) {
        if (!is_array($row)) {
          continue;
        }
        $id = trim((string) ($row['addressid'] ?? ''));
        if ($id === '') {
          continue;
        }
        $label = trim((string) ($row['label'] ?? $row['address'] ?? ''));
        if ($label !== '') {
          $labels[$id] = $label;
        }
      }
    }

    $out = [];
    $items = $data['new_address_res'] ?? [];
    if (!is_array($items)) {
      return [];
    }

    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $fields = self::mapNewAddressRes($item);
      $id = $fields['goaddress_id'];
      if ($id === '') {
        continue;
      }
      $label = $labels[$id] ?? '';
      if ($label === '') {
        $parts = array_filter([
          $fields['address_1'],
          $fields['town_city'],
          $fields['postcode'],
        ]);
        $label = implode(', ', $parts);
      }
      $out[$id] = [
        'label' => $label,
        'fields' => $fields,
        'raw' => $item,
      ];
    }

    return $out;
  }

}
