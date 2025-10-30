<?php

namespace Drupal\sentinel_migrations\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for Sentinel Company Addresses from migration tables.
 *
 * @MigrateSource(
 *   id = "sentinel_company_addresses_source",
 *   source_module = "sentinel_migrations"
 * )
 */
class SentinelCompanyAddressesSource extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('company_addresses', 'a');
    
    // Add company address fields based on AddressMappingTrait::getCompanyAddressMapping()
    $query->addField('a', 'COMPANY_NAME', 'COMPANY_NAME');
    $query->addField('a', 'COMPANY_ADDRESS1', 'COMPANY_ADDRESS1');
    $query->addField('a', 'COMPANY_ADDRESS2', 'COMPANY_ADDRESS2');
    $query->addField('a', 'COMPANY_TOWN', 'COMPANY_TOWN');
    $query->addField('a', 'COMPANY_COUNTY', 'COMPANY_COUNTY');
    $query->addField('a', 'COMPANY_POSTCODE', 'COMPANY_POSTCODE');
    
    // Join sentinel_sample table
    $query->innerJoin('sentinel_sample', 'ss', 'ss.pack_reference_number = a.pack_reference_number');
    
    // Join unique addresses table
    // Build concat expression similar to original D7 code
    $address_mapping = \Drupal\sentinel_migrations\Traits\AddressMappingTrait::getCompanyAddressMapping();
    $concat_parts = [];
    foreach (array_values($address_mapping) as $column) {
      $concat_parts[] = "CAST(a.{$column} AS CHAR)";
      $concat_parts[] = "' ,'";
    }
    // Remove the last separator
    array_pop($concat_parts);
    $concat = 'CONCAT(' . implode(', ', $concat_parts) . ')';
    
    $query->innerJoin('company_unique_addresses', 'ua', "{$concat} = ua.unique_address AND ua.unique_address != ''");
    
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
      'COMPANY_NAME' => $this->t('Company Name'),
      'COMPANY_ADDRESS1' => $this->t('Company Address 1'),
      'COMPANY_ADDRESS2' => $this->t('Company Address 2'),
      'COMPANY_TOWN' => $this->t('Company Town'),
      'COMPANY_COUNTY' => $this->t('Company County'),
      'COMPANY_POSTCODE' => $this->t('Company Postcode'),
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
