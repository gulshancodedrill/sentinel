<?php

namespace Drupal\sentinel_migrations\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for Sentinel Samples from legacy database.
 *
 * @MigrateSource(
 *   id = "sentinel_samples_source",
 *   source_module = "sentinel_migrations"
 * )
 */
class SentinelSamplesSource extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This would query the legacy 'sentinel_legacy' database.
    // Note: In D11, database connections are handled differently.
    // You may need to configure an additional database connection in settings.php.
    
    $query = $this->select('vaillant_samples', 'vs')
      ->fields('vs')
      ->leftJoin('vaillant_lab_data', 'vld', 'vld.sample_id = vs.sample_id AND vld.DT_ANALYSED > \'1900-01-01 00:00:00\'')
      ->groupBy('vs.sample_id');

    // Add expressions similar to original query.
    $query->addExpression('vs.PACK_REFERENCE_NUMBER', 'PACK_REFERENCE_NUMBER');
    $query->addExpression('vs.PROJECT_ID', 'PROJECT_ID');
    $query->addExpression('vs.INSTALLER_COMPANY', 'INSTALLER_NAME');
    $query->addExpression('vs.INSTALLER_EMAIL', 'INSTALLER_EMAIL');
    $query->addExpression('vs.INSTALLER_COMPANY', 'COMPANY_NAME');
    $query->addExpression('NULL', 'COMPANY_ADDRESS1');
    $query->addExpression('NULL', 'COMPANY_ADDRESS2');
    $query->addExpression('NULL', 'COMPANY_TOWN');
    $query->addExpression('NULL', 'COMPANY_COUNTY');
    $query->addExpression('NULL', 'COMPANY_POSTCODE');
    $query->addExpression('vs.INSTALLER_MOBILE', 'COMPANY_TEL');
    $query->addExpression('vs.INSTALLER_EMAIL', 'COMPANY_EMAIL');
    $query->addExpression('CONCAT(vs.PROPERTY_NUMBER, \' ,\', vs.STREET, \' ,\', vs.TOWN_CITY, \' ,\', vs.COUNTY, \' ,\', vs.POSTCODE)', 'SYSTEM_LOCATION');
    $query->addExpression('vs.SYSTEM_AGE', 'SYSTEM_6_MONTHS');
    $query->addExpression('NULL', 'SYSTEM_POSTCODE');
    $query->addExpression('vs.UPRN', 'UPRN');
    $query->addExpression('vs.PROPERTY_NUMBER', 'PROPERTY_NUMBER');
    $query->addExpression('vs.STREET', 'STREET');
    $query->addExpression('vs.TOWN_CITY', 'TOWN_CITY');
    $query->addExpression('vs.COUNTY', 'COUNTY');
    $query->addExpression('vs.POSTCODE', 'POSTCODE');
    $query->addExpression('vs.LANDLORD', 'LANDLORD');
    $query->addExpression('\'Vaillant\'', 'BOILER_MANUFACTURER');
    $query->addExpression('vs.BOILER_ID', 'BOILER_ID');
    $query->addExpression('NULL', 'BOILER_TYPE');
    $query->addExpression('NULL', 'ENGINEERS_CODE');
    $query->addExpression('NULL', 'SERVICE_CALL_ID');
    $query->addExpression('vs.DT_INSTALLED', 'DATE_INSTALLED');
    $query->addExpression('vs.DT_BOOKED_IN', 'DATE_BOOKED');
    $query->addExpression('vs.DT_REPORTED', 'DATE_REPORTED');
    $query->addExpression('SUBSTRING_INDEX(vs.report_id, "\\\\", -1)', 'FILENAME');
    $query->addExpression('vs.LANDLORD', 'CLIENT_NAME');
    $query->addExpression('vs.CUSTOMER_ID', 'CUSTOMER_ID');
    $query->addExpression('vs.PACK_TYPE', 'PACK_TYPE');
    $query->addExpression('REPLACE(REPLACE(vs.CARD_COMPLETE, \'N\', 0), \'Y\', 1)', 'CARD_COMPLETE');
    $query->addExpression('REPLACE(REPLACE(vs.ON_HOLD, \'N\', 0), \'Y\', 1)', 'ON_HOLD');
    $query->addExpression('REPLACE(REPLACE(vs.PASS_FAIL, \'F\', 0), \'P\', 1)', 'PASS_FAIL');
    $query->addExpression('vs.SAMPLE_ID', 'SAMPLE_ID');
    $query->addExpression('vs.INSTALLER_COMPANY', 'INSTALLER_COMPANY');
    $query->addExpression('vs.DT_REPORTED', 'CREATED');
    $query->addExpression('group_concat(DISTINCT CONCAT(REPLACE(LOWER(vld.SUBSAMPLE_ID), LOWER(vs.SAMPLE_ID), \'\'), \'|\', LOWER(vld.ANALYTE)))', 'test');
    $query->addExpression('group_concat(DISTINCT CONCAT(LEFT(vld.ANALYTE, 1), REPLACE(LOWER(vld.SUBSAMPLE_ID), LOWER(vs.SAMPLE_ID), \'\'), \'|\', vld.RESULT))', 'test_result');
    $query->addExpression('group_concat(vld.DT_ANALYSED ORDER BY vld.DT_ANALYSED DESC)', 'dates_analysed');

    // Build second query for standard_samples.
    $query2 = $this->select('standard_samples', 'ss')
      ->fields('ss')
      ->leftJoin('standard_lab_data', 'sld', 'sld.sample_id = ss.sample_id AND sld.DT_ANALYSED > \'1900-01-01 00:00:00\'')
      ->groupBy('ss.sample_id');

    // Add expressions for standard samples...
    // (Similar pattern as above)

    // Union queries.
    // Note: D11's query builder doesn't support UNION directly.
    // This may need to be done via raw SQL or separate source plugins.

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'pack_reference_number' => $this->t('Pack Reference Number'),
      'project_id' => $this->t('Project ID'),
      'installer_name' => $this->t('Installer Name'),
      // Add all other fields...
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'SAMPLE_ID' => [
        'type' => 'string',
        'length' => 255,
      ],
    ];
  }

}
