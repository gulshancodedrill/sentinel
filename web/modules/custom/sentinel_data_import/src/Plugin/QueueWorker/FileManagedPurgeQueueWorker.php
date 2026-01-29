<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes queued file entity deletions (last-18-month purge).
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.file_managed_purge",
 *   title = @Translation("Sentinel file purge"),
 *   cron = {"time" = 60}
 * )
 */
class FileManagedPurgeQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Queue item must be an array.');
    }

    $fid = isset($data['fid']) ? (int) $data['fid'] : 0;
    if ($fid <= 0) {
      \Drupal::logger('sentinel_data_import')->warning('Skipping invalid file purge item: missing fid.');
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $storage->resetCache([$fid]);
    $file = $storage->load($fid);

    if (!$file) {
      \Drupal::logger('sentinel_data_import')->warning('File purge skipped: file @fid not found.', ['@fid' => $fid]);
      return;
    }

    try {
      $file->delete();
      \Drupal::logger('sentinel_data_import')->info('Purged file entity @fid and removed file.', ['@fid' => $fid]);
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_data_import')->error('Failed to purge file @fid: @message', [
        '@fid' => $fid,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
