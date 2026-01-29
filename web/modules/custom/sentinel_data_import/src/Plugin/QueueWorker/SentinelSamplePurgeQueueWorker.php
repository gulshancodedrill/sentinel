<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes queued sentinel sample deletions (last-year purge).
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.sentinel_sample_purge",
 *   title = @Translation("Sentinel sample purge"),
 *   cron = {"time" = 60}
 * )
 */
class SentinelSamplePurgeQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Queue item must be an array.');
    }

    $pid = isset($data['pid']) ? (int) $data['pid'] : 0;
    if ($pid <= 0) {
      \Drupal::logger('sentinel_data_import')->warning('Skipping invalid purge item: missing pid.');
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    $storage->resetCache([$pid]);
    $entity = $storage->load($pid);

    if (!$entity) {
      \Drupal::logger('sentinel_data_import')->warning('Purge skipped: sentinel_sample @pid not found.', ['@pid' => $pid]);
      return;
    }

    try {
      if (method_exists($entity, 'deleteExistingPdf')) {
        $entity->deleteExistingPdf();
      }
      $entity->delete();
      \Drupal::logger('sentinel_data_import')->info('Purged sentinel_sample @pid (entity, revisions, files).', ['@pid' => $pid]);
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_data_import')->error('Failed to purge sentinel_sample @pid: @message', [
        '@pid' => $pid,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
