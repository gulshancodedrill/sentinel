<?php

namespace Drupal\sentinel_data_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\sentinel_data_import\TestEntityImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued test entity imports.
 *
 * @QueueWorker(
 *   id = "sentinel_data_import.test_entity",
 *   title = @Translation("Sentinel test_entity importer"),
 *   cron = {"time" = 60}
 * )
 */
class TestEntityImportQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the queue worker.
   */
  public function __construct(
    protected TestEntityImporter $importer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $container->get('sentinel_data_import.test_entity_importer'),
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

