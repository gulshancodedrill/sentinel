<?php

namespace Drupal\condition_entity_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trigger form to queue condition entity imports.
 */
class ImportTriggerForm extends FormBase {

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->queueFactory = $container->get('queue');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'condition_entity_import_trigger_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JSON file path'),
      '#default_value' => '/var/www/html/condition-entity-export.json',
      '#required' => TRUE,
      '#description' => $this->t('Absolute path to the condition entity JSON export file.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file_path = $form_state->getValue('file_path');

    if (!file_exists($file_path)) {
      $this->messenger()->addError($this->t('File not found: @path', ['@path' => $file_path]));
      return;
    }

    $json = file_get_contents($file_path);
    $records = json_decode($json, TRUE);

    if (!is_array($records)) {
      $this->messenger()->addError($this->t('Invalid JSON format.'));
      return;
    }

    $queue = $this->queueFactory->get('condition_entity_import');
    $queued = 0;

    foreach ($records as $record) {
      $queue->createItem($record);
      $queued++;
    }

    $this->messenger()->addStatus($this->t('Queued @count condition entity records for import.', ['@count' => $queued]));
  }

}

