<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\sentinel_data_import\AddressImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued address imports.
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.address",
 *   title = @Translation("Sentinel address importer"),
 *   cron = {"time" = 60}
 * )
 */
class AddressImportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the queue worker.
   */
  public function __construct(
    protected AddressImporter $importer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $container->get('sentinel_data_import.address_importer'),
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


