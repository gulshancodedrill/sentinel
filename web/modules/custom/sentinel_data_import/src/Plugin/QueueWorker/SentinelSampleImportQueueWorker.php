<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\sentinel_data_import\SentinelSampleImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued sentinel sample imports.
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.sentinel_sample",
 *   title = @Translation("Sentinel sample importer"),
 *   cron = {"time" = 60}
 * )
 */
class SentinelSampleImportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the queue worker.
   */
  public function __construct(
    protected SentinelSampleImporter $importer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $container->get('sentinel_data_import.sentinel_sample_importer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Queue item must be an array.');
    }
    $this->importer->processItem($data);
  }

}


