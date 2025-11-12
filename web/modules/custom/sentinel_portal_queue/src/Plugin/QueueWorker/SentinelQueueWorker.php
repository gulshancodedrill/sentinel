<?php

namespace Drupal\sentinel_portal_queue\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

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
    if (function_exists('sentinel_portal_queue_run_action')) {
      sentinel_portal_queue_run_action($data);
    }
    else {
      \Drupal::logger('sentinel_portal_queue')->error('sentinel_portal_queue_run_action() not found while processing queue item.');
    }
  }

}

