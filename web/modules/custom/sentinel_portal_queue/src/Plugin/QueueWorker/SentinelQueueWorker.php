<?php

namespace Drupal\sentinel_portal_queue\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\QueueInterface;

/**
 * Queue worker for processing sentinel queue items.
 *
 * @QueueWorker(
 *   id = "sentinel_queue",
 *   title = @Translation("Sentinel Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
class SentinelQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // This is handled by the hook_cron_queue_info callback
    // The actual processing is done in sentinel_portal_queue_run_action()
    return TRUE;
  }

}

