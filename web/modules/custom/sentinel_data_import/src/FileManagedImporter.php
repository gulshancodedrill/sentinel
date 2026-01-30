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

    $destination_uri = (string) ($item['destination_uri'] ?? $item['uri'] ?? '');

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

    if ($destination_uri !== '') {
      // Ensure the destination directory exists when we have a target URI.
      $this->fileSystem->prepareDirectory(
        dirname($destination_uri),
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
      );
    }

    $storage = $this->entityTypeManager->getStorage('file');
    
    // Clear cache before loading to ensure we get the latest entity
    $storage->resetCache([$fid]);
    
    // Check if file entity exists by fid - if so, update it; otherwise create new
    /** @var \Drupal\file\Entity\File|null $file */
    $file = $storage->load($fid);
    $is_update = $file !== NULL;
    
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
        
        // Update file content only if source file is available and URI exists.
        if ($has_source_file && $data !== NULL && $destination_uri !== '') {
          $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
          $updated_fields[] = 'file_content';
        }
        
        // Update filename if provided (allow empty string).
        if (array_key_exists('filename', $item)) {
          $new_filename = $item['filename'] !== NULL ? (string) $item['filename'] : '';
          if ($file->getFilename() !== $new_filename) {
            $file->setFilename($new_filename);
            $updated_fields[] = 'filename';
          }
        }
        
        // Update filemime if provided (allow empty string).
        if (array_key_exists('filemime', $item)) {
          $new_filemime = $item['filemime'] !== NULL ? (string) $item['filemime'] : '';
          if ($file->getMimeType() !== $new_filemime) {
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
        // Create new file entity with preserved fid
        // Use direct database insert to preserve the original fid from D7
        $connection = \Drupal::database();
        
        // Check if fid already exists
        $existing = $connection->select('file_managed', 'f')
          ->fields('f', ['fid'])
          ->condition('fid', $fid)
          ->execute()
          ->fetchField();
        
        if (!$existing) {
          // Prepare file data
          $uid = isset($item['uid']) && !empty($item['uid']) ? (int) $item['uid'] : 1;
          $filename = isset($item['filename']) && $item['filename'] !== NULL ? (string) $item['filename'] : '';
          $filemime = isset($item['filemime']) && $item['filemime'] !== NULL ? (string) $item['filemime'] : '';
          $filesize = isset($item['filesize']) ? (int) $item['filesize'] : 0;
          $status = isset($item['status']) ? (int) $item['status'] : 1;
          $timestamp = isset($item['timestamp']) && !empty($item['timestamp']) ? (int) $item['timestamp'] : time();
          
          // Generate UUID for the entity
          $uuid_service = \Drupal::service('uuid');
          $uuid = $uuid_service->generate();
          
          // Insert directly into database to preserve fid
          $connection->insert('file_managed')
            ->fields([
              'fid' => $fid,
              'uuid' => $uuid,
              'langcode' => 'en',
              'uid' => $uid,
              'filename' => $filename,
              'uri' => $destination_uri,
              'filemime' => $filemime,
              'filesize' => $filesize,
              'status' => $status,
              'created' => $timestamp,
              'changed' => $timestamp,
            ])
            ->execute();
          
          // Clear entity cache
          $storage->resetCache([$fid]);
        }
        
        // Load the file entity
        $storage->resetCache([$fid]);
        $file = $storage->load($fid);
        
        if (!$file) {
          // Fallback: create normally if direct insert failed
          $this->logger->warning('Direct insert failed for fid @fid, creating with auto-increment ID.', ['@fid' => $fid]);
          if ($has_source_file && $data !== NULL && $destination_uri !== '') {
            $file = $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
          }
          else {
            if ($destination_uri !== '') {
              $file = File::create([
                'uri' => $destination_uri,
              ]);
            }
          }
        }
        else {
          // File was created with preserved fid, now write content if available
          if ($has_source_file && $data !== NULL && $destination_uri !== '') {
            // Write file content using file repository
            $this->fileRepository->writeData($data, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
            
            // Update filesize if needed
            $actual_size = strlen($data);
            if ($file->getSize() != $actual_size) {
              $file->set('filesize', $actual_size);
            }
          }
          
          // Update file properties from CSV data
          if (isset($item['uid']) && !empty($item['uid'])) {
            $file->setOwnerId((int) $item['uid']);
          }
          
          if (array_key_exists('filemime', $item)) {
            $file->setMimeType($item['filemime'] !== NULL ? (string) $item['filemime'] : '');
          }
          
          if (array_key_exists('filename', $item)) {
            $file->setFilename($item['filename'] !== NULL ? (string) $item['filename'] : '');
          }
          
          // Set status
          if (array_key_exists('status', $item)) {
            $file->setPermanent((int) $item['status'] === 1);
          }
          
          // Set timestamps
          if (isset($item['timestamp']) && !empty($item['timestamp'])) {
            $timestamp = (int) $item['timestamp'];
            $file->set('created', $timestamp);
            $file->set('changed', $timestamp);
          }
          
          $file->save();
        }
        
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
    if ($file) {
      $this->fileUsage->add($file, 'sentinel_data_import', 'file', $file->id());
      $this->logger->notice('File @fid (@uri) processed and usage registered.', [
        '@fid' => $file->id(),
        '@uri' => $destination_uri,
      ]);
    }
  }

}

