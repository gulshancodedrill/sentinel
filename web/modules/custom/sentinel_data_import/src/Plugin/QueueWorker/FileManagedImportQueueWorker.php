<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\sentinel_data_import\FileManagedImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued file_managed imports from Drupal 7.
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.file_managed",
 *   title = @Translation("Sentinel file_managed importer"),
 *   cron = {"time" = 60}
 * )
 */
class FileManagedImportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the QueueWorker.
   */
  public function __construct(
    protected FileManagedImporter $importer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $container->get('sentinel_data_import.file_managed_importer')
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

