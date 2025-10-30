<?php

namespace Drupal\sentinel_migrations\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for Sentinel Addresses from migration tables.
 *
 * @MigrateSource(
 *   id = "sentinel_addresses_source",
 *   source_module = "sentinel_migrations"
 * )
 */
class SentinelAddressesSource extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('addresses', 'a');
    
    // Add address fields based on AddressMappingTrait::getNormalAddressMapping()
    $query->addField('a', 'property_number', 'property_number');
    $query->addField('a', 'Street', 'Street');
    $query->addField('a', 'ADDRESS_3', 'ADDRESS_3');
    $query->addField('a', 'ADDRESS_4', 'ADDRESS_4');
    $query->addField('a', 'TOWN_CITY', 'TOWN_CITY');
    $query->addField('a', 'COUNTY', 'COUNTY');
    $query->addField('a', 'POSTCODE', 'POSTCODE');
    
    // Join sentinel_sample table
    $query->innerJoin('sentinel_sample', 'ss', 'ss.pack_reference_number = a.pack_reference_number');
    
    // Join unique addresses table
    // Build concat expression similar to original D7 code
    $address_mapping = \Drupal\sentinel_migrations\Traits\AddressMappingTrait::getNormalAddressMapping();
    $concat_parts = [];
    foreach (array_values($address_mapping) as $column) {
      $concat_parts[] = "CAST(a.{$column} AS CHAR)";
      $concat_parts[] = "' ,'";
    }
    // Remove the last separator
    array_pop($concat_parts);
    $concat = 'CONCAT(' . implode(', ', $concat_parts) . ')';
    
    $query->innerJoin('addresses_unique_addresses', 'ua', "{$concat} = ua.unique_address AND ua.unique_address != ''");
    
    // Group by unique address
    $query->groupBy('ua.unique_address');
    
    // Use unique_address_id as source ID
    $query->addField('ua', 'unique_address_id', 'id');
    
    // Group sample IDs together (for linking back to samples)
    $query->addExpression('GROUP_CONCAT(ss.id)', 'sample_ids');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('Unique Address ID'),
      'property_number' => $this->t('Property Number'),
      'Street' => $this->t('Street'),
      'ADDRESS_3' => $this->t('Address 3'),
      'ADDRESS_4' => $this->t('Address 4'),
      'TOWN_CITY' => $this->t('Town/City'),
      'COUNTY' => $this->t('County'),
      'POSTCODE' => $this->t('Postcode'),
      'sample_ids' => $this->t('Sample IDs'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
      ],
    ];
  }

}
