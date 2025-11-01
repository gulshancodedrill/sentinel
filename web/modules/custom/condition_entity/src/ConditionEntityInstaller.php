<?php

namespace Drupal\condition_entity;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Installs missing ECK configuration for condition entities.
 */
class ConditionEntityInstaller {

  /**
   * Ensures the ECK entity type and bundle exist.
   */
  public static function ensureConfiguration(EntityTypeManagerInterface $entity_type_manager): void {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $type_storage */
    $type_storage = $entity_type_manager->getStorage('eck_entity_type');
    $bundle_storage = $entity_type_manager->getStorage('eck_entity_bundle');

    // Create the entity type if missing.
    $type = $type_storage->load('condition_entity');
    if (!$type) {
      $type_storage->create([
        'id' => 'condition_entity',
        'name' => 'condition_entity',
        'label' => 'Condition Entity',
        'uid' => TRUE,
        'created' => TRUE,
        'changed' => TRUE,
        'language' => TRUE,
        'title' => TRUE,
        'status' => TRUE,
        'standalone_url' => FALSE,
      ])->save();
      $entity_type_manager->clearCachedDefinitions();
    }

    // Create the bundle if missing.
    $bundle = $bundle_storage->load('condition_entity');
    if (!$bundle) {
      $bundle = $bundle_storage->load('condition_entity.condition_entity');
    }
    if (!$bundle) {
      $bundle_storage->create([
        'id' => 'condition_entity',
        'type' => 'condition_entity',
        'name' => 'condition_entity',
        'description' => 'Condition Entity',
      ])->save();
    }

    static::ensureVocabulary();
    static::ensureFields();

    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Ensures the taxonomy vocabulary exists for condition events.
   */
  protected static function ensureVocabulary(): void {
    if (!Vocabulary::load('condition_event_results')) {
      Vocabulary::create([
        'vid' => 'condition_event_results',
        'name' => 'Condition event results',
        'description' => 'Sentinel Certificate pass/fail values',
      ])->save();
    }
  }

  /**
   * Ensures required fields exist on condition entities.
   */
  protected static function ensureFields(): void {
    $definitions = [
      'field_condition_event_number' => [
        'storage' => ['type' => 'integer', 'settings' => []],
        'field' => ['label' => 'Event Number', 'required' => TRUE, 'settings' => ['min' => NULL, 'max' => NULL]],
      ],
      'field_condition_event_element' => [
        'storage' => ['type' => 'string', 'settings' => ['max_length' => 255]],
        'field' => ['label' => 'Event element', 'required' => TRUE, 'settings' => ['max_length' => 255]],
      ],
      'field_condition_event_string' => [
        'storage' => ['type' => 'text_long', 'settings' => []],
        'field' => ['label' => 'Event String', 'required' => FALSE, 'settings' => ['display_summary' => FALSE]],
      ],
      'field_condition_event_result' => [
        'storage' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term']],
        'field' => [
          'label' => 'Condition Event Result',
          'required' => FALSE,
          'settings' => [
            'handler' => 'default:taxonomy_term',
            'handler_settings' => [
              'target_bundles' => ['condition_event_results' => 'condition_event_results'],
              'auto_create' => FALSE,
            ],
          ],
        ],
      ],
      'field_event_individual_comment' => [
        'storage' => ['type' => 'text_long', 'settings' => []],
        'field' => ['label' => 'Event individual comment', 'required' => FALSE, 'settings' => ['display_summary' => FALSE]],
      ],
      'field_individual_recommend' => [
        'storage' => ['type' => 'text_long', 'settings' => []],
        'field' => ['label' => 'Event individual recommendation', 'required' => FALSE, 'settings' => ['display_summary' => FALSE]],
      ],
      'field_number_of_white_spaces' => [
        'storage' => ['type' => 'string', 'settings' => ['max_length' => 255]],
        'field' => ['label' => 'Number of white spaces', 'required' => FALSE, 'settings' => ['max_length' => 255]],
      ],
    ];

    foreach ($definitions as $field_name => $definition) {
      $storage = FieldStorageConfig::loadByName('condition_entity', $field_name);
      if (!$storage) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'condition_entity',
          'type' => $definition['storage']['type'],
          'settings' => $definition['storage']['settings'],
          'cardinality' => 1,
          'translatable' => FALSE,
        ])->save();
      }

      $field = FieldConfig::loadByName('condition_entity', 'condition_entity', $field_name);
      if (!$field) {
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'condition_entity',
          'bundle' => 'condition_entity',
          'label' => $definition['field']['label'],
          'required' => $definition['field']['required'],
          'settings' => $definition['field']['settings'],
        ])->save();
      }
    }
  }

}
