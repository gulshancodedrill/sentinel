<?php

namespace Drupal\sentinel_data_import;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
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

    $fid = isset($item['fid']) ? (int) $item['fid'] : 0;
    if ($fid <= 0) {
      $this->logger->warning('Skipping file import with invalid fid (@fid).', [
        '@fid' => $item['fid'] ?? 'missing',
      ]);
      return;
    }

    $destination_uri = $item['destination_uri'] ?? $item['uri'] ?? NULL;
    if (!$destination_uri) {
      $this->logger->error('Queue item @fid does not include a destination URI.', [
        '@fid' => $fid,
      ]);
      throw new \RuntimeException('Destination URI missing.');
    }

    // Directly construct source_path from URI
    $config = $this->configFactory->get('sentinel_data_import.settings');
    $private_base = $config->get('private_source_base');
    $public_base = $config->get('public_source_base');
    
    $scheme = explode('://', $destination_uri)[0] ?? '';
    $target = explode('://', $destination_uri, 2)[1] ?? '';
    
    // Build source path from URI
    $source_path = NULL;
    switch ($scheme) {
      case 'private':
        if ($private_base) {
          $source_path = rtrim($private_base, '/') . '/' . ltrim($target, '/');
        }
        break;
      case 'public':
        if ($public_base) {
          $source_path = rtrim($public_base, '/') . '/' . ltrim($target, '/');
        }
        break;
      default:
        // Try as direct filesystem path
        $source_path = $destination_uri;
    }
    
    // Use provided source_path if it exists and is readable, otherwise use constructed one
    if (!empty($item['source_path']) && is_readable($item['source_path'])) {
      $source_path = $item['source_path'];
    }

    // Ensure the destination directory exists.
    $this->fileSystem->prepareDirectory(
      dirname($destination_uri),
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    $storage = $this->entityTypeManager->getStorage('file');
    
    // Clear cache before loading to ensure we get the latest entity
    $storage->resetCache([$fid]);
    
    // Check if file entity exists by fid - if so, update it; otherwise create new
    /** @var \Drupal\file\Entity\File|null $file */
    $file = $storage->load($fid);
    $is_update = $file !== NULL;
    
    // If not found by fid, also check by URI as fallback
    if (!$is_update) {
      $existing_candidates = $storage->loadByProperties(['uri' => $destination_uri]);
      if (!empty($existing_candidates)) {
        $file = reset($existing_candidates);
        $is_update = TRUE;
        $this->logger->info('Found existing file by URI @uri (fid: @existing_fid), will update instead of creating @requested_fid.', [
          '@uri' => $destination_uri,
          '@existing_fid' => $file->id(),
          '@requested_fid' => $fid,
        ]);
      }
    }
    
    if ($is_update) {
      $this->logger->info('Found existing file @fid, will update.', ['@fid' => $file->id()]);
    }
    else {
      $this->logger->info('No existing file @fid found, will create new.', ['@fid' => $fid]);
    }

    // Check if source file exists - if it does, copy it; if not, just create/update entity with URI
    $has_source_file = !empty($source_path) && is_readable($source_path);
    $data = NULL;
    
    if ($has_source_file) {
      $data = file_get_contents($source_path);
      if ($data === FALSE) {
        $this->logger->warning('Unable to read source file @source for fid @fid. Will create entity without file content.', [
          '@fid' => $fid,
          '@source' => $source_path,
        ]);
        $has_source_file = FALSE;
      }
    }
    else {
      $this->logger->info('Source file not yet available for fid @fid (@uri). Creating entity with URI - file will be available when placed in directory.', [
        '@fid' => $fid,
        '@uri' => $destination_uri,
      ]);
    }

    try {
      if ($is_update) {
        // Update existing file entity
        $updated_fields = [];
        
        // Update file content only if source file is available
        if ($has_source_file && $data !== NULL) {
          $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
          $updated_fields[] = 'file_content';
        }
        
        // Update filename if provided (for updates, allow empty to clear)
        if (array_key_exists('filename', $item)) {
          $new_filename = $item['filename'] !== '' && $item['filename'] !== NULL ? $item['filename'] : basename($destination_uri);
          if ($file->getFilename() !== $new_filename) {
            $file->setFilename($new_filename);
            $updated_fields[] = 'filename';
          }
        }
        
        // Update filemime if provided (for updates, allow empty)
        if (array_key_exists('filemime', $item)) {
          $new_filemime = $item['filemime'] !== '' && $item['filemime'] !== NULL ? $item['filemime'] : NULL;
          if ($new_filemime && $file->getMimeType() !== $new_filemime) {
            $file->setMimeType($new_filemime);
            $updated_fields[] = 'filemime';
          }
        }
        
        // Update owner if provided
        if (array_key_exists('uid', $item)) {
          $new_uid = !empty($item['uid']) ? (int) $item['uid'] : 1;
          if ($file->getOwnerId() != $new_uid) {
            $file->setOwnerId($new_uid);
            $updated_fields[] = 'uid';
          }
        }
        
        // Update status if provided (for updates, include even if empty)
        if (array_key_exists('status', $item)) {
          $new_status = isset($item['status']) ? ((int) $item['status'] === 1) : TRUE;
          if ($file->isPermanent() !== $new_status) {
            $file->setPermanent($new_status);
            $updated_fields[] = 'status';
          }
        }
        
        // Update timestamps if provided
        if (array_key_exists('timestamp', $item)) {
          $timestamp = !empty($item['timestamp']) ? (int) $item['timestamp'] : time();
          $file->set('created', $timestamp);
          $file->set('changed', $timestamp);
          $updated_fields[] = 'timestamp';
        }
        else {
          // Always update changed timestamp on update
          $file->set('changed', time());
        }
        
        $file->save();
        
        $this->logger->notice('Updated file @fid. Fields updated: @fields', [
          '@fid' => $fid,
          '@fields' => implode(', ', $updated_fields) ?: 'none (no changes)',
        ]);
      }
      else {
        // Create new file entity
        // If source file exists, write it; otherwise create entity with URI only
        if ($has_source_file && $data !== NULL) {
          $file = $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
        }
        else {
          // Create file entity with URI but without file content
          // The file will be available when the actual file is placed in the directory
          $file = File::create([
            'uri' => $destination_uri,
          ]);
          
          // Note: Drupal file entities use auto-incrementing IDs, so the new file
          // will have its own generated ID, not necessarily the fid from CSV.
          // This is expected behavior.
        }
        
        // Set file properties (for both new and existing files created without content)
        $file->setOwnerId(isset($item['uid']) && !empty($item['uid']) ? (int) $item['uid'] : 1);
        
        if (isset($item['filemime']) && $item['filemime'] !== '' && $item['filemime'] !== NULL) {
          $file->setMimeType($item['filemime']);
        }
        
        if (isset($item['filename']) && $item['filename'] !== '' && $item['filename'] !== NULL) {
          $file->setFilename($item['filename']);
        }
        else {
          $file->setFilename(basename($destination_uri));
        }
        
        // Set status
        if (array_key_exists('status', $item)) {
          $file->setPermanent((int) $item['status'] === 1);
        }
        else {
          $file->setPermanent(TRUE);
        }
        
        // Set timestamps
        if (isset($item['timestamp']) && !empty($item['timestamp'])) {
          $timestamp = (int) $item['timestamp'];
          $file->set('created', $timestamp);
          $file->set('changed', $timestamp);
        }
        else {
          $now = time();
          $file->set('created', $now);
          $file->set('changed', $now);
        }
        
        $file->save();
        
        $this->logger->notice('Created file @fid (@uri).', [
          '@fid' => $file->id(),
          '@uri' => $destination_uri,
        ]);
      }
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to save file @fid: @message', [
        '@fid' => $fid,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }

    // Register usage to prevent garbage collection.
    $this->fileUsage->add($file, 'sentinel_data_import', 'file', $file->id());

    $this->logger->notice('File @fid (@uri) processed and usage registered.', [
      '@fid' => $file->id(),
      '@uri' => $destination_uri,
    ]);
  }

}

