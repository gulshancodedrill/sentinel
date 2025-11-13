<?php

namespace Drupal\sentinel_data_import;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles importing legacy address entities and linking them to samples.
 */
class AddressImporter {

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
  ) {
    $this->logger = $loggerFactory->get('sentinel_data_import');
  }

  /**
   * Process a single queue item.
   *
   * @param array $item
   *   The queued row data.
   *
   * @throws \RuntimeException|\Drupal\Core\Entity\EntityStorageException
   */
  public function processItem(array $item): void {
    $id = isset($item['id']) ? (int) $item['id'] : 0;
    if ($id <= 0) {
      $this->logger->warning('Skipping address import with invalid id (@id).', [
        '@id' => $item['id'] ?? 'missing',
      ]);
      return;
    }

    $bundle = $item['type'] ?? 'address';
    if (!in_array($bundle, ['address', 'company_address'], TRUE)) {
      $this->logger->warning('Skipping address @id due to unexpected bundle (@bundle).', [
        '@id' => $id,
        '@bundle' => $bundle,
      ]);
      return;
    }

    $storage = $this->entityTypeManager->getStorage('address');
    if ($storage->load($id)) {
      $this->logger->info('Address @id already exists, skipping.', ['@id' => $id]);
      return;
    }

    $address_values = [
      'id' => $id,
      'type' => $bundle,
    ];

    $field_address = $this->buildAddressFieldValues($item, $bundle);
    if (!empty($field_address)) {
      $address_values['field_address'] = [$field_address];
    }

    try {
      /** @var \Drupal\address\Entity\AddressInterface $address */
      $address = $storage->create($address_values);
      $address->enforceIsNew();
      $address->save();
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to import address @id: @message', [
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }

    $linked = $this->linkSamples($address->id(), $bundle, $item);
    $this->logger->notice('Imported address @id (@bundle) and linked @count samples.', [
      '@id' => $address->id(),
      '@bundle' => $bundle,
      '@count' => $linked,
    ]);
  }

  /**
   * Build address field values from the queued row.
   */
  protected function buildAddressFieldValues(array $item, string $bundle): array {
    $country = strtoupper(trim((string) ($item['country'] ?? '')));
    if ($country === '') {
      $country = 'GB';
    }

    $line1 = $this->buildAddressLine1($item, $bundle);
    $line2 = $this->buildAddressLine2($item, $bundle);
    $line3 = $this->buildAddressLine3($item, $bundle);

    $values = [
      'country_code' => $country,
      'administrative_area' => $this->cleanValue($item['administrative_area'] ?? ''),
      'locality' => $this->cleanValue($item['locality'] ?? ''),
      'dependent_locality' => $this->cleanValue($item['dependent_locality'] ?? ''),
      'postal_code' => $this->cleanValue($item['postal_code'] ?? ''),
      'address_line1' => $line1,
      'address_line2' => $line2,
      'address_line3' => $line3,
      'organization' => $this->cleanValue($item['organisation_name'] ?? ''),
      'given_name' => $this->cleanValue($item['first_name'] ?? ''),
      'additional_name' => $this->cleanValue($item['name_line'] ?? ''),
      'family_name' => $this->cleanValue($item['last_name'] ?? ''),
    ];

    // Remove empty values.
    return array_filter($values, static function ($value) {
      return $value !== NULL && $value !== '';
    });
  }

  /**
   * Build the primary address line.
   */
  protected function buildAddressLine1(array $item, string $bundle): ?string {
    if ($bundle === 'company_address') {
      $line = $this->cleanValue($item['premise'] ?? '');
      if ($line !== '') {
        return $line;
      }
      return $this->cleanValue($item['thoroughfare'] ?? '');
    }

    $parts = [
      $this->cleanValue($item['sub_premise'] ?? ''),
      $this->cleanValue($item['thoroughfare'] ?? ''),
    ];
    $parts = array_filter($parts, static fn($value) => $value !== '');
    $line = trim(implode(' ', $parts));

    return $line !== '' ? $line : NULL;
  }

  /**
   * Build the secondary address line.
   */
  protected function buildAddressLine2(array $item, string $bundle): ?string {
    if ($bundle === 'company_address') {
      return $this->cleanValue($item['thoroughfare'] ?? '');
    }

    return $this->cleanValue($item['dependent_locality'] ?? '');
  }

  /**
   * Build the tertiary address line.
   */
  protected function buildAddressLine3(array $item, string $bundle): ?string {
    if ($bundle === 'company_address') {
      return $this->cleanValue($item['sub_premise'] ?? '');
    }

    return $this->cleanValue($item['sub_administrative_area'] ?? '');
  }

  /**
   * Normalize a value by trimming whitespace.
   */
  protected function cleanValue(?string $value): string {
    return trim((string) $value);
  }

  /**
   * Link imported addresses to sentinel samples.
   *
   * @return int
   *   Number of samples updated.
   */
  protected function linkSamples(int $address_id, string $bundle, array $item): int {
    $sample_ids = $this->parseSampleIds($item['sample_ids'] ?? '');
    if (empty($sample_ids)) {
      return 0;
    }

    $field_name = $bundle === 'company_address' ? 'field_company_address' : 'field_sentinel_sample_address';
    $sample_storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $samples = $sample_storage->loadMultiple($sample_ids);

    $updated = 0;
    foreach ($samples as $sample) {
      if (!$sample instanceof EntityInterface) {
        continue;
      }
      if (!$sample->hasField($field_name)) {
        continue;
      }

      $sample->set($field_name, ['target_id' => $address_id]);
      $this->updateSampleFields($sample, $bundle, $item);

      try {
        $sample->save();
        $updated++;
      }
      catch (EntityStorageException $e) {
        $this->logger->error('Failed to link address @address to sample @sample: @message', [
          '@address' => $address_id,
          '@sample' => $sample->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $missing = array_diff($sample_ids, array_map(static fn($sample) => (int) $sample->id(), $samples));
    if (!empty($missing)) {
      $this->logger->warning('Address @address references missing samples: @ids', [
        '@address' => $address_id,
        '@ids' => implode(', ', $missing),
      ]);
    }

    return $updated;
  }

  /**
   * Update sample entity fields based on the imported address.
   */
  protected function updateSampleFields(EntityInterface $sample, string $bundle, array $item): void {
    if ($bundle === 'company_address') {
      $this->setSampleField($sample, 'company_name', $item['organisation_name'] ?? '');
      $this->setSampleField($sample, 'company_address1', $item['premise'] ?? '');
      $this->setSampleField($sample, 'company_address2', $item['thoroughfare'] ?? '');
      $this->setSampleField($sample, 'company_town', $item['locality'] ?? '');
      $this->setSampleField($sample, 'company_county', $item['administrative_area'] ?? '');
      $this->setSampleField($sample, 'company_postcode', $item['postal_code'] ?? '');
    }
    else {
      $this->setSampleField($sample, 'property_number', $item['sub_premise'] ?? '');
      $this->setSampleField($sample, 'street', $item['thoroughfare'] ?? '');
      $this->setSampleField($sample, 'town_city', $item['locality'] ?? '');
      $this->setSampleField($sample, 'county', $item['administrative_area'] ?? '');
      $this->setSampleField($sample, 'postcode', $item['postal_code'] ?? '');
    }
  }

  /**
   * Helper to set a field value when the destination field exists.
   */
  protected function setSampleField(EntityInterface $sample, string $field_name, string $value): void {
    $value = $this->cleanValue($value);
    if ($value === '' || !$sample->hasField($field_name)) {
      return;
    }
    $sample->set($field_name, $value);
  }

  /**
   * Convert the sample_ids column into an array of integers.
   *
   * @param string $value
   *   Raw CSV value.
   *
   * @return int[]
   *   Parsed sample IDs.
   */
  protected function parseSampleIds(string $value): array {
    if ($value === '') {
      return [];
    }

    $parts = explode(',', $value);
    $ids = [];
    foreach ($parts as $part) {
      $id = (int) trim($part);
      if ($id > 0) {
        $ids[] = $id;
      }
    }
    return array_values(array_unique($ids));
  }

}



