<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Sentinel Client entities.
 */
class SentinelClientListBuilder extends EntityListBuilder implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_client_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['table-filter']],
    ];

    $form['filters']['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The email of the client'),
      '#default_value' => $form_state->getUserInput()['email'] ?? '',
    ];

    $form['filters']['ucr'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ucr'),
      '#default_value' => $form_state->getUserInput()['ucr'] ?? '',
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    // Build the entity list.
    $form['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('There are no @label.', ['@label' => $this->entityType->getPluralLabel()]),
    ];

    $storage = $this->getStorage();
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('cid', 'DESC');

    // Apply filters.
    $input = $form_state->getUserInput();
    if (!empty($input['email'])) {
      $query->condition('email', $input['email'], 'CONTAINS');
    }
    if (!empty($input['ucr'])) {
      $query->condition('ucr', $input['ucr'], '=');
    }

    $header = $this->buildHeader();
    $query->tableSort($header);
    $query->pager(10);

    $ids = $query->execute();
    $entities = $storage->loadMultiple($ids);

    foreach ($entities as $entity) {
      $form['table'][$entity->id()] = $this->buildRow($entity);
    }

    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No validation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['ucr'] = [
      'data' => $this->t('Client ucr'),
      'field' => 'ucr',
      'specifier' => 'ucr',
    ];
    $header['name'] = [
      'data' => $this->t('The Name of the client'),
      'field' => 'name',
      'specifier' => 'name',
    ];
    $header['email'] = [
      'data' => $this->t('The email of the client'),
      'field' => 'email',
      'specifier' => 'email',
    ];
    $header['operations'] = $this->t('Operations');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\sentinel_portal_entities\Entity\SentinelClient $entity */
    $row['ucr']['data'] = [
      '#markup' => $entity->get('ucr')->value ?: '-',
    ];
    $row['name']['data'] = [
      '#markup' => $entity->label(),
    ];
    $row['email']['data'] = [
      '#markup' => $entity->get('email')->value,
    ];
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return \Drupal::formBuilder()->getForm($this);
  }

}