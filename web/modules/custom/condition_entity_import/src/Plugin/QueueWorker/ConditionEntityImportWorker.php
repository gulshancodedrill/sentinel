<?php

namespace Drupal\condition_entity_import\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Queue worker to import condition entities from JSON.
 *
 * @QueueWorker(
 *   id = "condition_entity_import",
 *   title = @Translation("Condition Entity Import Worker"),
 *   cron = {"time" = 60}
 * )
 */
class ConditionEntityImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new ConditionEntityImportWorker.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('condition_entity_import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Check if entity already exists by ID.
    $existing = $this->entityTypeManager
      ->getStorage('condition_entity')
      ->load($data['id']);

    if ($existing) {
      $this->logger->info('Condition entity @id already exists, skipping.', ['@id' => $data['id']]);
      return;
    }

    // Prepare base entity values.
    $values = [
      'id' => $data['id'],
      'type' => $data['type'] ?? 'condition_entity',
      'uid' => $data['uid'] ?? 0,
      'created' => $data['created'] ?? \Drupal::time()->getRequestTime(),
      'changed' => $data['changed'] ?? \Drupal::time()->getRequestTime(),
      'langcode' => $this->mapLanguage($data['language'] ?? 'und'),
    ];

    // Map simple text/number fields from Drupal 7 structure to Drupal 11.
    $field_map = [
      'field_condition_event_number' => 'value',
      'field_condition_event_element' => 'value',
      'field_condition_event_string' => 'value',
      'field_event_individual_comment' => 'value',
      'field_number_of_white_spaces' => 'value',
    ];

    foreach ($field_map as $field_name => $value_key) {
      if (!empty($data[$field_name]['und'])) {
        $values[$field_name] = [];
        foreach ($data[$field_name]['und'] as $item) {
          if (isset($item[$value_key])) {
            $values[$field_name][] = [$value_key => $item[$value_key]];
          }
        }
      }
    }

    // Handle Event Individual Recommendation (different machine name in D11).
    if (!empty($data['field_individual_recommend']['und'])) {
      $values['field_event_individual_recommend'] = [];
      foreach ($data['field_individual_recommend']['und'] as $item) {
        if (isset($item['value'])) {
          $values['field_event_individual_recommend'][] = ['value' => $item['value']];
        }
      }
    }

    // Handle taxonomy reference field (Condition Event Result).
    // Map Drupal 7 term IDs to Drupal 11 term IDs.
    if (!empty($data['field_condition_event_result']['und'])) {
      $values['field_condition_event_result'] = [];
      foreach ($data['field_condition_event_result']['und'] as $item) {
        if (!empty($item['tid'])) {
          $d7_tid = (int) $item['tid'];
          $d11_tid = $this->mapTermId($d7_tid);
          if ($d11_tid) {
            $values['field_condition_event_result'][] = $d11_tid;
          }
        }
      }
    }

    try {
      $entity = $this->entityTypeManager
        ->getStorage('condition_entity')
        ->create($values);
      $entity->save();

      $this->logger->info('Created condition entity @id', ['@id' => $data['id']]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create condition entity @id: @message', [
        '@id' => $data['id'],
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Maps Drupal 7 language codes to Drupal 11.
   */
  protected function mapLanguage($langcode) {
    return ($langcode === 'und' || empty($langcode)) ? 'en' : $langcode;
  }

  /**
   * Maps Drupal 7 condition event result term IDs to Drupal 11 term IDs.
   *
   * @param int $d7_tid
   *   The Drupal 7 term ID.
   *
   * @return int|null
   *   The Drupal 11 term ID or NULL if no mapping exists.
   */
  protected function mapTermId(int $d7_tid): ?int {
    $map = [
      1 => 7363, // Pass
      2 => 7364, // Fail
      3 => 7365, // Warning
    ];

    return $map[$d7_tid] ?? NULL;
  }

}


