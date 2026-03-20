<?php

namespace Drupal\sentinel_portal_entities\Utility;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Helper for handling pack type filter options and query conditions.
 */
class PackTypeFilter {

  /**
   * Returns the pack type definitions.
   *
   * Each definition contains:
   * - label: The label shown to users.
   * - pack_type: The pack_type column value.
   * - prefix: The pack_reference_number prefix to match.
   *
   * @return array
   *   The definitions keyed by filter value.
   */
  public static function getDefinitions(): array {
    return [
      'vaillant' => [
        'label' => '001 - UK Vaillant',
        'pack_type' => 'VAL',
        'prefix' => '001',
      ],
      'worcesterbosch_contract' => [
        'label' => '005 - UK Worcesterbosch Contract',
        'pack_type' => 'SEN',
        'prefix' => '005',
      ],
      'worcesterbosch_service' => [
        'label' => '006 - UK Worcesterbosch Service',
        'pack_type' => 'SEN',
        'prefix' => '006',
      ],
      'standard' => [
        'label' => '102 - UK Standard',
        'pack_type' => 'SEN',
        'prefix' => '102',
      ],
      'german' => [
        'label' => '110 - German',
        'pack_type' => 'SEN',
        'prefix' => '110',
      ],
      'italian' => [
        'label' => '120 - Italian',
        'pack_type' => 'SEN',
        'prefix' => '120',
      ],
      'french' => [
        'label' => '130 - French',
        'pack_type' => 'SEN',
        'prefix' => '130',
      ],
    ];
  }

  /**
   * Returns a single definition by key.
   *
   * @param string $key
   *   The filter key.
   *
   * @return array|null
   *   The definition or NULL if it does not exist.
   */
  public static function getDefinition(string $key): ?array {
    $definitions = static::getDefinitions();
    return $definitions[$key] ?? NULL;
  }

  /**
   * Returns the pack_type database value for a sample type key.
   *
   * Case-insensitive. Vaillant maps to VAL; all other types (standard,
   * worcesterbosch_contract, worcesterbosch_service, german, italian, french,
   * or unknown) map to SEN.
   *
   * @param string $sampleTypeKey
   *   The key from getPackType() or CSV (e.g. worcesterbosch_service, vaillant,
   *   UK Standard, german).
   *
   * @return string
   *   The DB value: 'VAL' or 'SEN'.
   */
  public static function getPackTypeDbValue(string $sampleTypeKey): string {
    $normalized = strtolower(trim($sampleTypeKey));
    if ($normalized === 'vaillant') {
      return 'VAL';
    }
    return 'SEN';
  }

  /**
   * Applies the corresponding conditions to a query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The select query using the alias "ss" for sentinel_sample.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection (for escapeLike).
   * @param string $key
   *   The selected filter key.
   */
  public static function applyFilterConditions(SelectInterface $query, Connection $connection, string $key): void {
    $definition = static::getDefinition($key);
    if (!$definition) {
      return;
    }

    // Only filter by pack_reference_number prefix, not by pack_type
    // This allows searching for all pack types that match the prefix pattern
    if (!empty($definition['prefix'])) {
      $query->condition('ss.pack_reference_number', $connection->escapeLike($definition['prefix']) . '%', 'LIKE');
    }
  }

}

