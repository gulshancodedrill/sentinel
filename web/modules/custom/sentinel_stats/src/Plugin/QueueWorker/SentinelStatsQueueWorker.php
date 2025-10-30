<?php

namespace Drupal\sentinel_stats\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes sentinel stats generation queue.
 *
 * @QueueWorker(
 *   id = "sentinel_stats",
 *   title = @Translation("Sentinel Stats Generation Queue"),
 *   cron = {"time" = 120}
 * )
 */
class SentinelStatsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // $data is the PID (sample ID).
    if (!is_numeric($data)) {
      return;
    }

    $sample_storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $sample = $sample_storage->load($data);

    if ($sample && function_exists('sentinel_systemcheck_certificate_populate_results')) {
      // Call the function that populates results (creates stat entities).
      sentinel_systemcheck_certificate_populate_results(new \stdClass(), $sample, new \stdClass());
    }
  }

}


