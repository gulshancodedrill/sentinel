<?php

namespace Drupal\sentinel_data_import\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\sentinel_data_import\FileManagedImporter;
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
    );
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

}

