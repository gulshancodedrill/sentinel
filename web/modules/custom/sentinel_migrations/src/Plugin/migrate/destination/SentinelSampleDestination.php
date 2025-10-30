<?php

namespace Drupal\sentinel_migrations\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Destination plugin for Sentinel Sample entities.
 *
 * @MigrateDestination(
 *   id = "sentinel_sample",
 *   destination_module = "sentinel_migrations"
 * )
 */
class SentinelSampleDestination extends EntityContentBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, \Drupal\migrate\Plugin\MigrationInterface $migration = NULL) {
    $entity_type = static::getEntityTypeId($plugin_id);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')->getStorage($entity_type),
      array_keys($container->get('entity_type.bundle.info')->getBundleInfo($entity_type)),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId($plugin_id) {
    return 'sentinel_sample';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityId(Row $row) {
    return $row->getDestinationProperty('id');
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    // Process test data before import.
    $this->processTestData($row);
    
    // Process dates_analysed.
    if ($dates_analysed = $row->getSourceProperty('dates_analysed')) {
      $dates = explode(',', $dates_analysed);
      if (!empty($dates)) {
        $latest_date = array_shift($dates);
        $row->setDestinationProperty('date_processed', $latest_date);
      }
    }

    return parent::import($row, $old_destination_id_values);
  }

  /**
   * Process test data from the source row.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row.
   */
  protected function processTestData(Row $row) {
    $test = $row->getSourceProperty('test');
    $test_result = $row->getSourceProperty('test_result');

    if (empty($test) || empty($test_result)) {
      return;
    }

    $tests = explode(',', $test);
    $results = explode(',', $test_result);

    try {
      $result_set = array_combine($tests, $results);
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_migrations')->warning('Array combine failed: @data', [
        '@data' => json_encode(['test' => $test, 'test_result' => $test_result]),
      ]);
      return;
    }

    $main_and_sys_elements = [
      'calcium' => 'calcium',
      'conductivity' => 'cond',
      'chloride' => 'cl',
    ];

    if (!empty($result_set)) {
      foreach ($result_set as $test_element => $test_result_value) {
        $test_result_formatted = substr($test_result_value, strpos($test_result_value, "|") + 1);
        $test_element_formatted = substr($test_element, strpos($test_element, "|") + 1);

        if (isset($main_and_sys_elements[$test_element_formatted])) {
          $prefix = '';
          if (substr($test_element, 0, 1) == 's') {
            $prefix = 'sys_';
          }
          elseif (substr($test_element, 0, 1) == 'm') {
            $prefix = 'mains_';
          }
          
          $field_name = $prefix . $main_and_sys_elements[$test_element_formatted] . '_result';
          $row->setDestinationProperty($field_name, $test_result_formatted);
        }
        else {
          $row->setDestinationProperty($test_element_formatted . '_result', $test_result_formatted);
        }
      }
    }
  }

}


