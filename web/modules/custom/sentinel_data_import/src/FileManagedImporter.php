<?php

namespace Drupal\sentinel_data_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Handles creation and updating of Drupal file entities for legacy imports.
 */
class FileManagedImporter {

  /**
   * Constructs the importer.
   */
  public function __construct(
    protected FileRepositoryInterface $fileRepository,
    protected FileSystemInterface $fileSystem,
    LoggerChannelFactoryInterface $loggerFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUsageInterface $fileUsage,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->logger = $loggerFactory->get('sentinel_data_import');
  }

  /**
   * Logger instance.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Processes a single queue item for file import.
   *
   * @param array $item
   *   Queued data describing a file to import.
   *
   * @throws \RuntimeException
   *   When the source file cannot be read.
   */
  public function processItem(array $item): void {
    $config = $this->configFactory->get('sentinel_data_import.settings');
    $queue_name = $config->get('queue_name') ?? 'sentinel_data_import.file_managed';

    if (empty($item['source_path']) || !is_readable($item['source_path'])) {
      $this->logger->error('Queue item @fid missing or unreadable source file (@source).', [
        '@fid' => $item['fid'] ?? 'unknown',
        '@source' => $item['source_path'] ?? 'n/a',
        'channel' => $queue_name,
      ]);
      throw new \RuntimeException('Source file missing: ' . ($item['source_path'] ?? 'n/a'));
    }

    $destination_uri = $item['destination_uri'] ?? $item['uri'] ?? NULL;
    if (!$destination_uri) {
      $this->logger->error('Queue item @fid does not include a destination URI.', [
        '@fid' => $item['fid'] ?? 'unknown',
      ]);
      throw new \RuntimeException('Destination URI missing.');
    }

    // Ensure the destination directory exists.
    $this->fileSystem->prepareDirectory(
      dirname($destination_uri),
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    // Check if a file already exists for this URI.
    $existing = NULL;
    $storage = $this->entityTypeManager->getStorage('file');
    $existing_candidates = $storage->loadByProperties(['uri' => $destination_uri]);
    if (!empty($existing_candidates)) {
      $existing = reset($existing_candidates);
    }

    $data = file_get_contents($item['source_path']);
    if ($data === FALSE) {
      $this->logger->error('Unable to read source file @source for fid @fid.', [
        '@fid' => $item['fid'] ?? 'unknown',
        '@source' => $item['source_path'],
      ]);
      throw new \RuntimeException('Unable to read source file.');
    }

    if ($existing) {
      $file = $existing;
      $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
    }
    else {
      $file = $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
      // File repository already saves the file; ensure ownership.
      $file->setOwnerId(1);
      $file->setMimeType($item['filemime'] ?? $file->getMimeType());
      $file->setFilename($item['filename'] ?? basename($destination_uri));
    }

    // Update status and timestamps if provided.
    if (isset($item['status'])) {
      $file->setPermanent((int) $item['status'] === 1);
    }
    if (isset($item['timestamp'])) {
      $timestamp = (int) $item['timestamp'];
      $file->set('created', $timestamp);
      $file->set('changed', $timestamp);
    }

    $file->save();

    // Register usage to prevent garbage collection.
    $this->fileUsage->add($file, 'sentinel_data_import', 'file', $file->id());

    $this->logger->notice('Imported legacy file @fid to @uri (fid: @new_fid).', [
      '@fid' => $item['fid'] ?? 'unknown',
      '@uri' => $destination_uri,
      '@new_fid' => $file->id(),
    ]);
  }

}

