<?php

namespace Drupal\sentinel_data_import;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Psr\Log\LoggerInterface;
use DateTime;
use DateTimeZone;

/**
 * Handles importing Drupal 7 sentinel_sample records into Drupal 11.
 */
class SentinelSampleImporter {

  /**
   * String-based field names.
   *
   * @var string[]
   */
  protected const STRING_FIELDS = [
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
    'fileid',
    'filename',
    'client_name',
    'customer_id',
    'lab_ref',
    'pack_type',
    'appearance_result',
    'mains_cond_result',
    'sys_cond_result',
    'mains_cl_result',
    'sys_cl_result',
    'iron_result',
    'copper_result',
    'aluminium_result',
    'mains_calcium_result',
    'sys_calcium_result',
    'ph_result',
    'sentinel_x100_result',
    'molybdenum_result',
    'boron_result',
    'manganese_result',
    'nitrate_result',
    'mob_ratio',
    'installer_company',
    'old_pack_reference_number',
    'duplicate_of',
  ];

  /**
   * Integer-based field names.
   *
   * @var string[]
   */
  protected const INTEGER_FIELDS = [
    'client_id',
    'ucr',
    'api_created_by',
  ];

  /**
   * Boolean-based field names (values converted to 0/1).
   *
   * @var string[]
   */
  protected const BOOLEAN_FIELDS = [
    'card_complete',
    'on_hold',
    'pass_fail',
    'appearance_pass_fail',
    'cond_pass_fail',
    'cl_pass_fail',
    'iron_pass_fail',
    'copper_pass_fail',
    'aluminium_pass_fail',
    'calcium_pass_fail',
    'ph_pass_fail',
    'sentinel_x100_pass_fail',
    'molybdenum_pass_fail',
    'boron_pass_fail',
    'manganese_pass_fail',
    'legacy',
  ];

  /**
   * Datetime field names.
   *
   * @var string[]
   */
  protected const DATETIME_FIELDS = [
    'date_installed',
    'date_sent',
    'date_booked',
    'date_processed',
    'date_reported',
    'created',
    'changed',
    'updated',
  ];

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the importer.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
    protected TimeInterface $time,
  ) {
    $this->logger = $loggerFactory->get('sentinel_data_import');
  }

  /**
   * Processes a single queue item.
   *
   * @param array $item
   *   Data representing a sentinel_sample row.
   */
  public function processItem(array $item): void {
    $pid = isset($item['pid']) ? (int) $item['pid'] : 0;
    if ($pid <= 0) {
      $this->logger->warning('Skipping sentinel_sample with invalid pid (@pid).', [
        '@pid' => $item['pid'] ?? 'missing',
      ]);
      return;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if ($storage->load($pid)) {
      $this->logger->info('Sentinel sample @pid already exists, skipping.', ['@pid' => $pid]);
      return;
    }

    $values = [
      'pid' => $pid,
    ];

    foreach (self::STRING_FIELDS as $field) {
      if (!array_key_exists($field, $item)) {
        continue;
      }
      $normalized = $this->normalizeText($item[$field]);
      if ($normalized !== '') {
        $values[$field] = ['value' => $normalized];
      }
    }

    foreach (self::INTEGER_FIELDS as $field) {
      if (!array_key_exists($field, $item)) {
        continue;
      }
      $raw = $item[$field];
      if ($raw === '' || $raw === NULL) {
        continue;
      }
      $values[$field] = ['value' => (int) $raw];
    }

    foreach (self::BOOLEAN_FIELDS as $field) {
      if (!array_key_exists($field, $item)) {
        continue;
      }
      $values[$field] = ['value' => $this->toBoolean($item[$field])];
    }

    foreach (self::DATETIME_FIELDS as $field) {
      if (!array_key_exists($field, $item)) {
        continue;
      }
      $normalized = $this->normalizeDate($item[$field]);
      if ($normalized !== NULL) {
        $values[$field] = ['value' => $normalized];
      }
    }

    // Ensure created/changed values exist even if the CSV was empty.
    if (empty($values['created'])) {
      $values['created'] = ['value' => $this->formatTimestamp($this->time->getRequestTime())];
    }
    if (empty($values['changed'])) {
      $values['changed'] = ['value' => $this->formatTimestamp($this->time->getRequestTime())];
    }
    if (!empty($values['updated']) && empty($values['changed'])) {
      $values['changed'] = $values['updated'];
    }

    try {
      /** @var \Drupal\sentinel_portal_entities\Entity\SentinelSample $entity */
      $entity = $storage->create($values);
      $entity->enforceIsNew();

      $this->applyAddressReferences($entity, $item);
      $entity->save();

      $this->logger->notice('Imported sentinel_sample @pid.', ['@pid' => $pid]);
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed importing sentinel_sample @pid: @message', [
        '@pid' => $pid,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Attach address references and sync related fields.
   */
  protected function applyAddressReferences(EntityInterface $sample, array $item): void {
    $sample_address_id = isset($item['sample_address_id']) ? (int) $item['sample_address_id'] : 0;
    if ($sample_address_id > 0 && $sample->hasField('field_sentinel_sample_address')) {
      $sample->set('field_sentinel_sample_address', ['target_id' => $sample_address_id]);
    }

    $company_address_id = isset($item['company_address_id']) ? (int) $item['company_address_id'] : 0;
    if ($company_address_id > 0 && $sample->hasField('field_company_address')) {
      $sample->set('field_company_address', ['target_id' => $company_address_id]);
    }
  }

  /**
   * Normalize text by collapsing whitespace and trimming.
   */
  protected function normalizeText($value): string {
    if ($value === NULL) {
      return '';
    }
    $value = str_replace(["\r\n", "\n", "\r"], ' ', (string) $value);
    $value = str_replace(';', ', ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
  }

  /**
   * Normalize a datetime string to storage format.
   */
  protected function normalizeDate($value): ?string {
    $value = $this->normalizeText($value);
    if ($value === '') {
      return NULL;
    }

    try {
      $date = new DateTime($value);
      return $date->format('Y-m-d H:i:s');
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Convert mixed raw values to boolean 0/1.
   */
  protected function toBoolean($raw): int {
    if (is_numeric($raw)) {
      return ((int) $raw) !== 0 ? 1 : 0;
    }

    $normalized = strtolower($this->normalizeText($raw));
    if ($normalized === '') {
      return 0;
    }
    return in_array($normalized, ['1', 'true', 'yes', 'y', 'pass', 'p'], TRUE) ? 1 : 0;
  }

  /**
   * Format a timestamp integer to the storage format.
   */
  protected function formatTimestamp(int $timestamp): string {
    $date = new DateTime('@' . $timestamp);
    $date->setTimezone(new DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $date->format('Y-m-d H:i:s');
  }

}


