<?php

namespace Drupal\sentinel_migrations\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for initiating migration queue processing.
 */
class QueueInitiateForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_migrations_queue_initiate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to add all the samples to the queue?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('sentinel_migrations.queue_initiate');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Initiate Queue Processing');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Extract all samples that have system_location fields but no address entities.
    $query = $this->database->select('sentinel_sample', 'ss');
    $query->addField('ss', 'id', 'pid');
    $query->leftJoin('sentinel_sample__field_sentinel_sample_address', 'address_link', 'address_link.entity_id = ss.id');
    $query->isNull('address_link.entity_id');
    
    // Check if system_location field exists.
    if ($this->database->schema()->fieldExists('sentinel_sample', 'system_location')) {
      $query->isNotNull('ss.system_location');
      $query->isNull('ss.street');
      $query->isNull('ss.town_city');
      $query->isNull('ss.county');
    }

    $results = $query->execute();

    $queue = \Drupal::queue('sentinel_migration_extract_syslocation_queue', TRUE);
    $count = 0;

    foreach ($results as $record) {
      $queue->createItem($record->pid);
      $count++;
    }

    $this->messenger()->addStatus($this->t('System location queue complete. @count queue items created.', ['@count' => $count]));

    // Extract all addresses that have address fields but don't have address entities.
    $query2 = $this->database->select('sentinel_sample', 'ss');
    $query2->addField('ss', 'id', 'pid');
    $query2->leftJoin('sentinel_sample__field_sentinel_sample_address', 'address_link', 'address_link.entity_id = ss.id');
    $query2->isNull('address_link.entity_id');
    
    if ($this->database->schema()->fieldExists('sentinel_sample', 'street')) {
      $query2->isNotNull('ss.street');
      $query2->isNotNull('ss.town_city');
      $query2->isNotNull('ss.county');
    }

    $results2 = $query2->execute();
    $queue2 = \Drupal::queue('sentinel_migration_save_blank_addresses_queue', TRUE);
    $count2 = 0;

    foreach ($results2 as $record) {
      $queue2->createItem($record->pid);
      $count2++;
    }

    $this->messenger()->addStatus($this->t('Address entity update/create queue complete. @count queue items created.', ['@count' => $count2]));
  }

}


