<?php

namespace Drupal\sentinel_csv_processor\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for sentinel_csv_processor.
 */
class SentinelCsvProcessorCommands extends DrushCommands {

  /**
   * Constructs the commands object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Purge all lab_data entities.
   *
   * @command sentinel-csv-processor:purge-lab-data
   * @aliases scp-pld
   *
   * @option batch-size
   *   Number of entities to delete per batch (default: 500).
   * @option limit
   *   Maximum number of entities to delete (default: all).
   */
  public function purgeLabData(array $options = ['batch-size' => 500, 'limit' => 0]): void {
    $batch_size = max(1, (int) ($options['batch-size'] ?? 500));
    $limit = max(0, (int) ($options['limit'] ?? 0));

    $storage = $this->entityTypeManager->getStorage('lab_data');
    $query = $storage->getQuery()->accessCheck(FALSE);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $ids = $query->execute();
    $total = count($ids);

    if ($total === 0) {
      $this->logger()->notice('No lab_data entities found.');
      return;
    }

    $deleted = 0;
    $errors = 0;
    $chunks = array_chunk($ids, $batch_size);

    $this->logger()->notice(dt('Deleting @total lab_data entities in batches of @batch.', [
      '@total' => $total,
      '@batch' => $batch_size,
    ]));

    foreach ($chunks as $chunk) {
      try {
        $entities = $storage->loadMultiple($chunk);
        if (!empty($entities)) {
          $storage->delete($entities);
          $deleted += count($entities);
        }
      }
      catch (\Exception $e) {
        $errors += count($chunk);
        $this->logger()->error('Error deleting batch: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $this->logger()->success(dt('Lab data purge complete. Deleted @deleted of @total (errors: @errors).', [
      '@deleted' => $deleted,
      '@total' => $total,
      '@errors' => $errors,
    ]));
  }

}
