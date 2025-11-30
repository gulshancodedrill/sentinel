<?php

namespace Drupal\sentinel_data_import\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\sentinel_data_import\AddressImporter;
use Drupal\sentinel_data_import\ConditionEntityImporter;
use Drupal\sentinel_data_import\FileManagedImporter;
use Drupal\sentinel_data_import\SentinelSampleImporter;
use Drupal\sentinel_data_import\TestEntityImporter;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for Sentinel Data Import.
 */
class SentinelDataImportCommands extends DrushCommands {

  /**
   * Drush command constructor.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
    protected FileSystemInterface $fileSystem,
    protected ConfigFactoryInterface $configFactory,
    protected FileManagedImporter $importer,
    protected ModuleExtensionList $moduleExtensionList,
    protected TestEntityImporter $testEntityImporter,
    protected AddressImporter $addressImporter,
    protected SentinelSampleImporter $sentinelSampleImporter,
    protected ConditionEntityImporter $conditionEntityImporter,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('queue'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('sentinel_data_import.file_managed_importer'),
      $container->get('extension.list.module'),
      $container->get('sentinel_data_import.test_entity_importer'),
      $container->get('sentinel_data_import.address_importer'),
      $container->get('sentinel_data_import.sentinel_sample_importer'),
      $container->get('sentinel_data_import.condition_entity_importer'),
    );
  }

  /**
   * Enqueue sentinel sample entities from a CSV export.
   *
   * @param string $csv_path
   *   Absolute path to the CSV (use "default" for module CSV).
   * @param array $options
   *   CLI options.
   *
   * @command sentinel-data-import:enqueue-samples
   *
   * @option start
   *   Zero-based row index to start from (default 0).
   * @option limit
   *   Maximum rows to enqueue (0 = no limit).
   *
   * @validate-module-enabled sentinel_data_import
   */
  public function enqueueSamples(string $csv_path, array $options = ['start' => 0, 'limit' => 0]): void {
    $csv_path = trim($csv_path);
    $queue = $this->queueFactory->get('sentinel_data_import.sentinel_sample');

    if ($csv_path === '' || $csv_path === 'default') {
      $module_path = $this->moduleExtensionList->getPath('sentinel_data_import');
      $csv_path = DRUPAL_ROOT . '/' . trim($module_path . '/csv/sentinel_samples_d7.csv', '/');
    }
    elseif ($csv_path[0] !== '/') {
      $csv_path = DRUPAL_ROOT . '/' . ltrim($csv_path, '/');
    }

    $csv_path = $this->fileSystem->realpath($csv_path) ?: $csv_path;
    if (!is_readable($csv_path)) {
      throw new \RuntimeException(sprintf('CSV file not readable: %s', $csv_path));
    }

    $start = (int) ($options['start'] ?? 0);
    $limit = (int) ($options['limit'] ?? 0);

    $file = new \SplFileObject($csv_path);
    $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

    $headers = $file->fgetcsv();
    if ($headers === FALSE) {
      throw new \RuntimeException('Unable to read CSV header.');
    }
    $headers = array_map('trim', $headers);

    $required_headers = ['pid', 'pack_reference_number'];
    $missing_headers = array_diff($required_headers, $headers);
    if (!empty($missing_headers)) {
      throw new \RuntimeException(sprintf('CSV missing required columns: %s', implode(', ', $missing_headers)));
    }

    $processed = 0;
    $enqueued = 0;
    $skipped = 0;

    foreach ($file as $row_index => $row) {
      if ($row === [NULL] || $row === FALSE) {
        continue;
      }
      if ($row_index - 1 < $start) {
        continue;
      }
      if ($limit > 0 && $enqueued >= $limit) {
        break;
      }

      $record = [];
      foreach ($headers as $position => $column) {
        if ($column === '') {
          continue;
        }
        $raw = $row[$position] ?? '';
        $record[$column] = $this->normalizeCsvValue(is_string($raw) ? $raw : (string) $raw);
      }

      $pid = isset($record['pid']) ? (int) $record['pid'] : 0;
      if ($pid <= 0) {
        $skipped++;
        continue;
      }

      $queue->createItem($record);
      $processed++;
      $enqueued++;
    }

    $this->logger()->success(dt('Processed @processed rows, enqueued @enqueued sentinel samples (skipped @skipped).', [
      '@processed' => $processed,
      '@enqueued' => $enqueued,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Enqueue legacy address entities from a CSV export.
   *
   * @param string $csv_path
   *   Absolute path to the CSV (use "default" for module CSV).
   * @param array $options
   *   CLI options.
   *
   * @command sentinel-data-import:enqueue-addresses
   *
   * @option start
   *   Zero-based row index to start from (default 0).
   * @option limit
   *   Maximum rows to enqueue (0 = no limit).
   *
   * @validate-module-enabled sentinel_data_import
   */
  public function enqueueAddresses(string $csv_path, array $options = ['start' => 0, 'limit' => 0]): void {
    $csv_path = trim($csv_path);
    $queue = $this->queueFactory->get('sentinel_data_import.address');

    if ($csv_path === '' || $csv_path === 'default') {
      $module_path = $this->moduleExtensionList->getPath('sentinel_data_import');
      $csv_path = DRUPAL_ROOT . '/' . trim($module_path . '/csv/address_entities_d7.csv', '/');
    }
    elseif ($csv_path[0] !== '/') {
      $csv_path = DRUPAL_ROOT . '/' . ltrim($csv_path, '/');
    }

    $csv_path = $this->fileSystem->realpath($csv_path) ?: $csv_path;
    if (!is_readable($csv_path)) {
      throw new \RuntimeException(sprintf('CSV file not readable: %s', $csv_path));
    }

    $start = (int) ($options['start'] ?? 0);
    $limit = (int) ($options['limit'] ?? 0);

    $file = new \SplFileObject($csv_path);
    $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

    $headers = $file->fgetcsv();
    if ($headers === FALSE) {
      throw new \RuntimeException('Unable to read CSV header.');
    }

    $headers = array_map('trim', $headers);
    $required_headers = [
      'id',
      'type',
      'country',
      'administrative_area',
      'locality',
      'postal_code',
      'sample_ids',
    ];
    $missing_headers = array_diff($required_headers, $headers);
    if (!empty($missing_headers)) {
      throw new \RuntimeException(sprintf('CSV missing required columns: %s', implode(', ', $missing_headers)));
    }

    $processed = 0;
    $enqueued = 0;
    $skipped = 0;

    foreach ($file as $row_index => $row) {
      if ($row === [NULL] || $row === FALSE) {
        continue;
      }
      if ($row_index - 1 < $start) {
        continue;
      }
      if ($limit > 0 && $enqueued >= $limit) {
        break;
      }

      $processed++;
      $record = [];
      foreach ($headers as $position => $column) {
        if ($column === '') {
          continue;
        }
        $raw = $row[$position] ?? '';
        $record[$column] = $this->normalizeCsvValue(is_string($raw) ? $raw : (string) $raw);
      }

      $id = isset($record['id']) ? (int) $record['id'] : 0;
      if ($id <= 0 || ($record['type'] ?? '') === '') {
        $skipped++;
        continue;
      }
      $record['id'] = $id;

      if (isset($record['sample_count']) && is_numeric($record['sample_count'])) {
        $record['sample_count'] = (int) $record['sample_count'];
      }

      $queue->createItem($record);
      $enqueued++;
    }

    $this->logger()->success(dt('Processed @processed rows, enqueued @enqueued items (skipped @skipped).', [
      '@processed' => $processed,
      '@enqueued' => $enqueued,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Enqueue all legacy file_managed records from a CSV.
   *
   * @param string $csv_path
   *   Absolute path to the CSV file (e.g. /var/www/html/sentinel11/file_managed_d7.csv).
   * @param array $options
   *   CLI options.
   *
   * @command sentinel-data-import:enqueue-files
   *
   * @option start
   *   Zero-based row to start processing (default: 0).
   * @option limit
   *   Maximum number of rows to enqueue (default: unlimited).
   *
   * @validate-module-enabled sentinel_data_import
   */
  public function enqueueFiles(string $csv_path, array $options = ['start' => 0, 'limit' => 0]): void {
    $csv_path = trim($csv_path);

    $config = $this->configFactory->get('sentinel_data_import.settings');
    $queue_name = $config->get('queue_name') ?? 'sentinel_data_import.file_managed';
    $queue = $this->queueFactory->get($queue_name);

    if ($csv_path === '' || $csv_path === 'default') {
      $default_relative = $config->get('default_csv_path') ?? 'csv/file_managed_d7.csv';
      $module_path = $this->moduleExtensionList->getPath('sentinel_data_import');
      $csv_path = DRUPAL_ROOT . '/' . trim($module_path . '/' . ltrim($default_relative, '/'), '/');
    }
    elseif ($csv_path[0] !== '/') {
      $csv_path = DRUPAL_ROOT . '/' . ltrim($csv_path, '/');
    }

    $csv_path = $this->fileSystem->realpath($csv_path) ?: $csv_path;
    if (!is_readable($csv_path)) {
      throw new \RuntimeException(sprintf('CSV file not readable: %s', $csv_path));
    }

    $start = (int) $options['start'];
    $limit = (int) $options['limit'];

    $file = new \SplFileObject($csv_path);
    $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

    // Read header.
    $headers = [];
    if (!$file->eof()) {
      $headers = $file->fgetcsv();
    }
    $headers = array_map('trim', $headers);

    $expected = ['fid', 'uid', 'filename', 'uri', 'filemime', 'filesize', 'status', 'timestamp'];
    $missing = array_diff($expected, $headers);
    if (!empty($missing)) {
      throw new \RuntimeException(sprintf('CSV missing required headers: %s', implode(', ', $missing)));
    }
    $header_flip = array_flip($headers);

    $processed = 0;
    $enqueued = 0;
    $skipped = 0;

    foreach ($file as $row_index => $row) {
      if ($row_index === 0) {
        continue;
      }
      if ($row === [NULL] || $row === FALSE) {
        continue;
      }

      if ($row_index - 1 < $start) {
        continue;
      }

      if ($limit > 0 && $enqueued >= $limit) {
        break;
      }

      $processed++;
      $data = $this->normalizeRow($row, $header_flip);

      $source_info = $this->resolveSourceAndDestination($data, $config->get('private_source_base'), $config->get('public_source_base'));
      if (!$source_info) {
        $skipped++;
        $this->logger()->warning(dt('Skipping fid @fid due to missing source file (@uri).', [
          '@fid' => $data['fid'],
          '@uri' => $data['uri'],
        ]));
        continue;
      }

      $queue->createItem($data + $source_info);
      $enqueued++;
    }

    $this->logger()->success(dt('Processed @processed rows, enqueued @enqueued items (skipped @skipped).', [
      '@processed' => $processed,
      '@enqueued' => $enqueued,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Normalize a CSV row to an associative array.
   */
  protected function normalizeRow(array $row, array $header_flip): array {
    $value = fn(string $key) => $row[$header_flip[$key]] ?? NULL;

    return [
      'fid' => (int) $value('fid'),
      'uid' => (int) $value('uid'),
      'filename' => $value('filename'),
      'uri' => $value('uri'),
      'destination_uri' => $value('uri'),
      'filemime' => $value('filemime'),
      'filesize' => isset($row[$header_flip['filesize']]) ? (int) $row[$header_flip['filesize']] : NULL,
      'status' => isset($row[$header_flip['status']]) ? (int) $row[$header_flip['status']] : NULL,
      'timestamp' => isset($row[$header_flip['timestamp']]) ? (int) $row[$header_flip['timestamp']] : NULL,
    ];
  }

  /**
   * Map the legacy URI to a source filesystem location and destination URI.
   */
  protected function resolveSourceAndDestination(array $data, ?string $private_base, ?string $public_base): ?array {
    $uri = $data['uri'] ?? '';
    if (!$uri) {
      return NULL;
    }

    $scheme = explode('://', $uri)[0] ?? '';
    $target = explode('://', $uri, 2)[1] ?? '';

    switch ($scheme) {
      case 'private':
        if ($private_base) {
          $source = rtrim($private_base, '/') . '/' . ltrim($target, '/');
          if (is_readable($source)) {
            return [
              'source_path' => $source,
              'destination_uri' => 'private://' . ltrim($target, '/'),
            ];
          }
        }
        break;

      case 'public':
        if ($public_base) {
          $source = rtrim($public_base, '/') . '/' . ltrim($target, '/');
          if (is_readable($source)) {
            return [
              'source_path' => $source,
              'destination_uri' => 'public://' . ltrim($target, '/'),
            ];
          }
        }
        break;

      default:
        // Attempt to treat as relative filesystem path.
        if (is_readable($uri)) {
          return [
            'source_path' => $uri,
            'destination_uri' => $uri,
          ];
        }
    }

    return NULL;
  }

  /**
   * Enqueue legacy test_entity records from a CSV export.
   *
   * @param string $csv_path
   *   Absolute path to the CSV (use "default" for module CSV).
   * @param array $options
   *   CLI options.
   *
   * @command sentinel-data-import:enqueue-test-entities
   *
   * @option start
   *   Zero-based row index to start from (default 0).
   * @option limit
   *   Maximum rows to enqueue (0 = no limit).
   *
   * @validate-module-enabled sentinel_data_import
   */
  public function enqueueTestEntities(string $csv_path, array $options = ['start' => 0, 'limit' => 0]): void {
    $config = $this->configFactory->get('sentinel_data_import.settings');
    $queue_name = $config->get('test_entity_queue_name') ?? 'sentinel_data_import.test_entity';
    $queue = $this->queueFactory->get($queue_name);

    if ($csv_path === '' || $csv_path === 'default') {
      $default_relative = $config->get('test_entity_default_csv_path') ?? 'csv/test_entities_d7.csv';
      $module_path = $this->moduleExtensionList->getPath('sentinel_data_import');
      $csv_path = DRUPAL_ROOT . '/' . trim($module_path . '/' . ltrim($default_relative, '/'), '/');
    }
    elseif ($csv_path[0] !== '/') {
      $csv_path = DRUPAL_ROOT . '/' . ltrim($csv_path, '/');
    }

    $csv_path = $this->fileSystem->realpath($csv_path) ?: $csv_path;
    if (!is_readable($csv_path)) {
      throw new \RuntimeException(sprintf('CSV file not readable: %s', $csv_path));
    }

    $start = (int) ($options['start'] ?? 0);
    $limit = (int) ($options['limit'] ?? 0);

    $file = new \SplFileObject($csv_path);
    $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

    $headers = $file->fgetcsv();
    if ($headers === FALSE) {
      throw new \RuntimeException('Unable to read CSV header.');
    }

    $headers = array_map('trim', $headers);
    $header_flip = array_flip($headers);

    $required_headers = [
      'id',
      'type',
      'uid',
      'created',
      'changed',
      'language',
      'appearance_result',
      'ph_result',
      'boron_result',
      'boiler_type',
      'molybdenum_result',
      'sys_cond_result',
      'mains_cond_result',
      'mains_calcium_result',
      'sys_calcium_result',
      'sys_cl_result',
      'iron_result',
      'copper_result',
      'aluminium_result',
      'appearance_pass_fail',
      'cond_pass_fail',
      'cl_pass_fail',
      'iron_pass_fail',
      'copper_pass_fail',
      'aluminium_pass_fail',
      'calcium_pass_fail',
      'sentinel_x100_pass_fail',
      'ph_pass_fail',
      'installer_name',
      'company_name',
      'company_address1',
      'date_reported',
      'project_id',
      'boiler_id',
      'system_age',
      'site_address',
      'pack_reference_number',
      'customer_id',
      'sentinel_x100_result',
      'mains_cl_result',
      'pass_fail',
      'company_address2',
      'company_town',
      'company_county',
      'company_postcode',
      'property_number',
      'street',
      'town_city',
      'county',
      'postcode',
      'system_6_months',
    ];

    $missing_headers = array_diff($required_headers, $headers);
    if (!empty($missing_headers)) {
      throw new \RuntimeException(sprintf('CSV missing required columns: %s', implode(', ', $missing_headers)));
    }

    $processed = 0;
    $enqueued = 0;

    foreach ($file as $row_index => $row) {
      if ($row === [NULL] || $row === FALSE) {
        continue;
      }
      if ($row_index - 1 < $start) {
        continue;
      }
      if ($limit > 0 && $enqueued >= $limit) {
        break;
      }

      $record = [];
      foreach ($required_headers as $column) {
        $record[$column] = $row[$header_flip[$column]] ?? '';
      }

      // Include core columns (type, language, etc.).
      foreach (['id', 'uid', 'created', 'changed', 'type', 'language'] as $core_column) {
        if (!isset($record[$core_column]) && isset($header_flip[$core_column])) {
          $record[$core_column] = $row[$header_flip[$core_column]] ?? '';
        }
      }

      $queue->createItem($record);
      $processed++;
      $enqueued++;
    }

    $this->logger()->success(dt('Enqueued @count test entities from @file (processed rows: @processed).', [
      '@count' => $enqueued,
      '@file' => $csv_path,
      '@processed' => $processed,
    ]));
  }

  /**
   * Enqueue legacy condition entity records from a CSV export.
   *
   * @param string $csv_path
   *   Absolute path to the CSV (use "default" for module CSV).
   * @param array $options
   *   CLI options.
   *
   * @command sentinel-data-import:enqueue-condition-entities
   *
   * @option start
   *   Zero-based row index to start from (default 0).
   * @option limit
   *   Maximum rows to enqueue (0 = no limit).
   *
   * @validate-module-enabled sentinel_data_import
   */
  public function enqueueConditionEntities(string $csv_path, array $options = ['start' => 0, 'limit' => 0]): void {
    $csv_path = trim($csv_path);
    $queue = $this->queueFactory->get('sentinel_data_import.condition_entity');

    if ($csv_path === '' || $csv_path === 'default') {
      $module_path = $this->moduleExtensionList->getPath('sentinel_data_import');
      $csv_path = DRUPAL_ROOT . '/' . trim($module_path . '/csv/condition_entities_d7.csv', '/');
    }
    elseif ($csv_path[0] !== '/') {
      $csv_path = DRUPAL_ROOT . '/' . ltrim($csv_path, '/');
    }

    $csv_path = $this->fileSystem->realpath($csv_path) ?: $csv_path;
    if (!is_readable($csv_path)) {
      throw new \RuntimeException(sprintf('CSV file not readable: %s', $csv_path));
    }

    $start = (int) ($options['start'] ?? 0);
    $limit = (int) ($options['limit'] ?? 0);

    $file = new \SplFileObject($csv_path, 'r');
    $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

    $headers = $file->fgetcsv();
    if ($headers === FALSE) {
      throw new \RuntimeException('Unable to read CSV header.');
    }
    $headers = array_map('trim', $headers);

    $required_headers = [
      'id',
      'type',
      'uid',
      'created',
      'changed',
      'language',
      'event_number',
      'event_element',
      'event_string',
      'event_individual_comment',
      'event_individual_recommendation',
      'number_of_white_spaces',
      'condition_event_result_tid',
    ];
    $missing_headers = array_diff($required_headers, $headers);
    if (!empty($missing_headers)) {
      throw new \RuntimeException(sprintf('CSV missing required columns: %s', implode(', ', $missing_headers)));
    }

    $processed = 0;
    $enqueued = 0;
    $skipped = 0;

    foreach ($file as $row_index => $row) {
      if ($row === [NULL] || $row === FALSE) {
        continue;
      }
      if ($row_index - 1 < $start) {
        continue;
      }
      if ($limit > 0 && $enqueued >= $limit) {
        break;
      }

      $record = [];
      foreach ($headers as $position => $column) {
        if ($column === '') {
          continue;
        }
        $raw = $row[$position] ?? '';
        $record[$column] = $this->normalizeCsvValue(is_string($raw) ? $raw : (string) $raw);
      }

      $id = isset($record['id']) ? (int) $record['id'] : 0;
      if ($id <= 0 || ($record['type'] ?? '') === '') {
        $skipped++;
        continue;
      }
      $record['id'] = $id;

      $queue->createItem($record);
      $processed++;
      $enqueued++;
    }

    $this->logger()->success(dt('Processed @processed rows, enqueued @enqueued condition entities (skipped @skipped).', [
      '@processed' => $processed,
      '@enqueued' => $enqueued,
      '@skipped' => $skipped,
    ]));
  }

  /**
   * Process the condition entity import queue.
   *
   * @command sentinel-data-import:process-condition-entities
   * @aliases sdi-pce
   */
  public function processConditionEntities(): void {
    $queue_name = 'sentinel_data_import.condition_entity';
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($queue_name);
    $queue = $this->queueFactory->get($queue_name);
    
    $processed = 0;
    $errors = 0;
    $start_time = time();
    
    $this->logger()->notice('Processing condition entity import queue...');
    
    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
        
        if ($processed % 10 === 0) {
          $elapsed = time() - $start_time;
          $rate = $elapsed > 0 ? round($processed / $elapsed, 2) : 0;
          $this->logger()->notice("Processed {$processed} items ({$rate} items/sec)");
        }
      }
      catch (\Exception $e) {
        $errors++;
        $this->logger()->error('Error processing condition entity item: ' . $e->getMessage());
        $queue->deleteItem($item);
      }
    }
    
    if ($processed > 0) {
      $this->logger()->success(dt('Processed @processed condition entities (@errors errors).', [
        '@processed' => $processed,
        '@errors' => $errors,
      ]));
    }
    else {
      $this->logger()->notice('No items in condition entity import queue.');
    }
  }

  /**
   * Normalize whitespace in CSV values.
   */
  protected function normalizeCsvValue(string $value): string {
    $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
    $value = str_replace(';', ', ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
  }

}

