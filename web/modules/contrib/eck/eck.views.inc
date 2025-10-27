<?php

/**
 * @file
 * Contains Views related hook implementations.
 */

use Drupal\eck\Entity\EckEntityType;

/**
 * Implements hook_views_data_alter().
 */
function eck_views_data_alter(&$data) {
  foreach (EckEntityType::loadMultiple() as $entity_type) {
    $data[$entity_type->id()]['label'] = [
      'field' => [
        'title' => t('Label'),
        'help' => t('The label of the %entity_label content.', ['%entity_label' => $entity_type->label()]),
        'id' => 'eck_entity_label',
      ],
    ];
  }
}
