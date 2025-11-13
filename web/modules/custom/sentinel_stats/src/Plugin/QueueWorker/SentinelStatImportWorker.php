<?php

namespace Drupal\sentinel_stats\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes sentinel_stat import queue items.
 *
 * @QueueWorker(
 *   id = "sentinel_stat_import",
 *   title = @Translation("Sentinel Stat Import Worker"),
 *   cron = {"time" = 60}
 * )
 */
class SentinelStatImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SentinelStatImportWorker object.
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
    $storage = $this->entityTypeManager->getStorage('sentinel_stat');
    
    // Check if entity already exists
    $existing = $storage->load($data['id']);
    
    $values = [
      'id' => $data['id'],
      'type' => $data['type'],
      'created' => $data['created'],
      'changed' => $data['changed'],
    ];
    
    // Map CSV fields to Drupal 11 fields
    if (!empty($data['pack_reference_id'])) {
      $values['field_stat_pack_reference_id'] = ['target_id' => $data['pack_reference_id']];
    }
    
    if (!empty($data['element_name'])) {
      $values['field_stat_element_name'] = $data['element_name'];
    }
    
    if (!empty($data['individual_comment'])) {
      $values['field_stat_individual_comment'] = $data['individual_comment'];
    }
    
    if (!empty($data['recommendation'])) {
      $values['field_stat_recommendation'] = $data['recommendation'];
    }
    
    if (!empty($data['result_tid'])) {
      $values['field_stat_result'] = ['target_id' => $data['result_tid']];
    }
    
    if ($existing) {
      // Update existing entity
      foreach ($values as $field => $value) {
        if ($existing->hasField($field)) {
          $existing->set($field, $value);
        }
      }
      $existing->save();
    }
    else {
      // Create new entity
      $entity = $storage->create($values);
      $entity->save();
    }
  }

}








