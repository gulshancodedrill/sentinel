<?php

namespace Drupal\sentinel_stats\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for importing sentinel_stat data.
 */
class SentinelStatsCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a new SentinelStatsCommands object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
  }

  /**
   * Import sentinel_stat entities from CSV.
   *
   * @param string $csv_file
   *   Path to CSV file.
   *
   * @command sentinel-stats:import
   * @aliases ssi
   * @usage sentinel-stats:import /path/to/file.csv
   */
  public function import($csv_file) {
    if (!file_exists($csv_file)) {
      $this->logger()->error("CSV file not found: {$csv_file}");
      return;
    }

    $handle = fopen($csv_file, 'r');
    if (!$handle) {
      $this->logger()->error("Failed to open CSV file.");
      return;
    }

    // Read header
    $headers = fgetcsv($handle);
    
    // Skip duplicate header row if present
    $second_line = fgetcsv($handle);
    if ($second_line && $second_line[0] === 'id') {
      // It's a duplicate header, skip it
    } else {
      // It's data, rewind and skip only first header
      fseek($handle, 0);
      fgetcsv($handle); // Skip first header
    }

    $queue = $this->queueFactory->get('sentinel_stat_import');
    $queued = 0;

    $this->logger()->notice('Reading CSV and queueing items...');

    while (($row = fgetcsv($handle)) !== FALSE) {
      if (count($row) < 9) {
        continue;
      }

      $data = [
        'id' => $row[0],
        'type' => $row[1],
        'created' => $row[2],
        'changed' => $row[3],
        'pack_reference_id' => $row[4],
        'element_name' => $row[5],
        'individual_comment' => $row[6],
        'recommendation' => $row[7],
        'result_tid' => $row[8],
      ];

      $queue->createItem($data);
      $queued++;

      if ($queued % 10000 === 0) {
        $this->logger()->notice("Queued {$queued} items...");
      }
    }

    fclose($handle);

    $this->logger()->success("Queued {$queued} items for import. Run 'drush queue:run sentinel_stat_import' to process.");
  }

  /**
   * Process the sentinel_stat import queue.
   *
   * @command sentinel-stats:process
   * @aliases ssp
   */
  public function process() {
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('sentinel_stat_import');
    $queue = $this->queueFactory->get('sentinel_stat_import');
    
    $processed = 0;
    $start_time = time();
    
    $this->logger()->notice('Processing sentinel_stat import queue...');
    
    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
        
        if ($processed % 1000 === 0) {
          $elapsed = time() - $start_time;
          $rate = $elapsed > 0 ? round($processed / $elapsed, 2) : 0;
          $this->logger()->notice("Processed {$processed} items ({$rate} items/sec)");
        }
      }
      catch (\Exception $e) {
        $this->logger()->error('Error processing item: ' . $e->getMessage());
        $queue->deleteItem($item);
      }
    }
    
    $this->logger()->success("Processed {$processed} sentinel_stat entities.");
  }

}






