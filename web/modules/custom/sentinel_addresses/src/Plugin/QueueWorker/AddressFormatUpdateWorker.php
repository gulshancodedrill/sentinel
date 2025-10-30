<?php

namespace Drupal\sentinel_addresses\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Processes address format updates.
 *
 * @QueueWorker(
 *   id = "sentinel_address_format_updates",
 *   title = @Translation("Address Format Updates"),
 *   cron = {"time" = 60}
 * )
 */
class AddressFormatUpdateWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $sample_storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $sample = $sample_storage->load($data);

    if ($sample) {
      // Save the entity (which creates/updates address field data).
      $sample->save();

      // Record mapping for processed addresses.
      $database = \Drupal::database();
      
      foreach (['field_sentinel_sample_address', 'field_company_address'] as $field_name) {
        if ($sample->hasField($field_name) && !$sample->get($field_name)->isEmpty()) {
          $address_id = $sample->get($field_name)->target_id;
          
          $database->merge('other_sentinel_addresses_mapping')
            ->keys(['sample_pid' => $sample->id(), 'address_id' => $address_id])
            ->fields([
              'sample_pid' => $sample->id(),
              'address_id' => $address_id,
            ])
            ->execute();
        }
      }
    }
  }

}


