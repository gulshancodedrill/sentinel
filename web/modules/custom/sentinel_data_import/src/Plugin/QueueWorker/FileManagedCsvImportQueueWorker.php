<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\file\Entity\File;

/**
 * Processes queued file_managed CSV imports - creates entities from CSV data only.
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.file_managed_csv",
 *   title = @Translation("Sentinel file_managed CSV importer"),
 *   cron = {"time" = 60}
 * )
 */
class FileManagedCsvImportQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Queue item must be an array.');
    }

    $fid = isset($data['fid']) ? (int) $data['fid'] : 0;
    $uid = isset($data['uid']) ? (int) ($data['uid'] ?? 0) : 0;
    $filename = isset($data['filename']) ? trim($data['filename']) : '';
    $uri = isset($data['uri']) ? trim($data['uri']) : '';
    $filemime = isset($data['filemime']) ? trim($data['filemime']) : '';
    $filesize = isset($data['filesize']) ? (int) ($data['filesize'] ?? 0) : 0;
    $status = isset($data['status']) ? (int) $data['status'] : 0;
    $timestamp = isset($data['timestamp']) ? (int) ($data['timestamp'] ?? 0) : 0;

    if (empty($fid)) {
      \Drupal::logger('file_import')->warning('Skipping invalid queue item: missing fid', [
        'fid' => $fid,
      ]);
      return;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    $file_storage = $entity_type_manager->getStorage('file');
    $logger = \Drupal::logger('file_import');

    try {
      // Check if file entity already exists.
      $file = $file_storage->load($fid);
      $is_update = (bool) $file;

      if (!$file) {
        // For new files, we need to insert directly to preserve the original fid.
        // Generate UUID for new entity.
        $uuid_service = \Drupal::service('uuid');
        $uuid = $uuid_service->generate();

        $connection = \Drupal::database();
        $now = $timestamp ?: \Drupal::time()->getRequestTime();

        // Insert directly into file_managed table to preserve fid.
        $connection->insert('file_managed')
          ->fields([
            'fid' => $fid,
            'uuid' => $uuid,
            'langcode' => 'en',
            'uid' => $uid ?: 1,
            'filename' => $filename,
            'uri' => $uri,
            'filemime' => $filemime,
            'filesize' => $filesize,
            'status' => $status,
            'created' => $now,
            'changed' => $now,
          ])
          ->execute();

        // Clear entity cache and reload to get the full entity object.
        $file_storage->resetCache([$fid]);
        $file = $file_storage->load($fid);

        if (!$file) {
          throw new \Exception("Failed to create file entity with fid $fid");
        }

        $logger->info('Created file entity fid @fid', ['@fid' => $fid]);
      }
      else {
        // Update existing file entity.
        $file->set('uid', $uid ?: $file->getOwnerId());
        $file->set('filename', $filename);
        $file->set('uri', $uri);
        $file->set('filemime', $filemime);
        $file->set('filesize', $filesize);
        $file->set('status', $status);
        if ($timestamp) {
          $file->set('created', $timestamp);
          $file->set('changed', $timestamp);
        }

        // Save the file entity.
        $file->save();

        $logger->info('Updated file entity fid @fid', ['@fid' => $fid]);
      }
    }
    catch (\Exception $e) {
      $logger->error('Failed to import file entity fid @fid: @message', [
        '@fid' => $fid,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}

