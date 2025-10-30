<?php

namespace Drupal\sentinel_migrations\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes system location extraction queue.
 *
 * @QueueWorker(
 *   id = "sentinel_migration_extract_syslocation_queue",
 *   title = @Translation("System Location Extraction Queue"),
 *   cron = {"time" = 30}
 * )
 */
class SystemLocationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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

    if (!$sample) {
      return;
    }

    // Check if sample has system_location field/property.
    $system_location = NULL;
    if ($sample->hasField('system_location') && !$sample->get('system_location')->isEmpty()) {
      $system_location = $sample->get('system_location')->value;
    }
    elseif (property_exists($sample, 'system_location')) {
      $system_location = $sample->system_location;
    }

    if (!$system_location) {
      return;
    }

    // Process the address.
    if (strpos($system_location, ',') !== FALSE) {
      // Split by comma.
      $address = explode(',', $system_location);
      $address = array_filter($address);

      if (count($address) > 2) {
        // More than 2 items.
        $array_item = array_pop($address);

        if (sentinel_migrations_postcode_check($array_item)) {
          // It's a postcode.
          if ($sample->hasField('postcode')) {
            $sample->set('postcode', $array_item);
          }
          elseif (property_exists($sample, 'postcode')) {
            $sample->postcode = $array_item;
          }

          if (count($address) > 2) {
            $sample->set('town_city', array_pop($address));
          }
        }
        else {
          // Not a postcode, use as town_city.
          if ($sample->hasField('town_city')) {
            $sample->set('town_city', $array_item);
          }
          elseif (property_exists($sample, 'town_city')) {
            $sample->town_city = $array_item;
          }
        }

        // Implode the rest into street.
        $street = implode(', ', $address);
        if ($sample->hasField('street')) {
          $sample->set('street', $street);
        }
        elseif (property_exists($sample, 'street')) {
          $sample->street = $street;
        }
      }
      else {
        // 1-2 items.
        if (isset($address[0])) {
          if ($sample->hasField('street')) {
            $sample->set('street', $address[0]);
          }
          elseif (property_exists($sample, 'street')) {
            $sample->street = $address[0];
          }
        }
        if (isset($address[1]) && !empty($address[1])) {
          if (sentinel_migrations_postcode_check($address[1])) {
            if ($sample->hasField('postcode')) {
              $sample->set('postcode', $address[1]);
            }
            elseif (property_exists($sample, 'postcode')) {
              $sample->postcode = $address[1];
            }
          }
          else {
            if ($sample->hasField('town_city')) {
              $sample->set('town_city', $address[1]);
            }
            elseif (property_exists($sample, 'town_city')) {
              $sample->town_city = $address[1];
            }
          }
        }
      }
    }
    else {
      // No comma, use entire string as street.
      if ($sample->hasField('street')) {
        $sample->set('street', $system_location);
      }
      elseif (property_exists($sample, 'street')) {
        $sample->street = $system_location;
      }
    }

    // Save the sample.
    $sample->save();
  }

}

/**
 * Processes blank addresses queue.
 *
 * @QueueWorker(
 *   id = "sentinel_migration_save_blank_addresses_queue",
 *   title = @Translation("Save Blank Addresses Queue"),
 *   cron = {"time" = 30}
 * )
 */
class SaveBlankAddressesQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    $sample_storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $sample = $sample_storage->load($data);

    if ($sample) {
      // Save the sample entity (which will trigger address entity creation).
      $sample->save();
    }
  }

}
