<?php

namespace Drupal\hold_states\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Form for on-hold samples with filters and bulk operations.
 */
class OnHoldSamplesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'on_hold_samples_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $hold_state_tid = $request->query->get('tid');
    $pack_reference = $request->query->get('pack_reference_number');
    $queue_action = $request->query->get('queue_action');

    // Filters section
    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => TRUE,
    ];

    // Hold state filter
    $hold_state_options = ['' => $this->t('- Any -')];
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'hold_state_values']);

    foreach ($terms as $term) {
      $hold_state_options[$term->id()] = $term->getName();
    }

    $form['filters']['tid'] = [
      '#type' => 'select',
      '#title' => $this->t('Sample on hold states'),
      '#options' => $hold_state_options,
      '#default_value' => $hold_state_tid ?: '',
    ];

    $form['filters']['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pack Reference Number'),
      '#default_value' => $pack_reference ?: '',
    ];

    // Queue reason filter
    $form['filters']['queue_action'] = [
      '#type' => 'select',
      '#title' => $this->t('The reason for the pack being in the queue'),
      '#options' => [
        '' => $this->t('- Any -'),
        'sendreport' => 'sendreport',
        'invalid_pack' => 'invalid_pack',
        'generate_results' => 'generate_results',
        'invoke_send_results_hook' => 'invoke_send_results_hook',
      ],
      '#default_value' => $queue_action ?: '',
    ];

    $form['filters']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#submit' => ['::applyFilters'],
    ];

    // Get samples data
    $samples = $this->getSamples($hold_state_tid, $pack_reference, $queue_action);

    // Bulk operations
    if (!empty($samples)) {
      $form['operations'] = [
        '#type' => 'details',
        '#title' => $this->t('Operations'),
        '#open' => TRUE,
      ];

      $form['operations']['action'] = [
        '#type' => 'select',
        '#title' => $this->t('Action'),
        '#title_display' => 'invisible',
        '#options' => $this->getBulkOperations(),
        '#empty_option' => $this->t('- Choose an operation -'),
      ];

      $form['operations']['execute'] = [
        '#type' => 'submit',
        '#value' => $this->t('Execute'),
        '#submit' => ['::executeBulkOperation'],
      ];
    }

    // Build table
    $form['samples_table'] = $this->buildSamplesTable($samples);

    // Add pager
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * Get bulk operations options.
   */
  protected function getBulkOperations() {
    $operations = [];
    
    // Get all hold state terms for bulk assignment
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'hold_state_values']);

    foreach ($terms as $term) {
      $operations['set_hold_state_' . $term->id()] = $this->t('Set hold state to: @name', ['@name' => $term->getName()]);
    }

    $operations['remove_hold_state'] = $this->t('Remove hold state');

    return $operations;
  }

  /**
   * Get samples based on filters.
   */
  protected function getSamples($hold_state_tid, $pack_reference, $queue_action = NULL) {
    $database = \Drupal::database();

    $query = $database->select('sentinel_sample', 'ss')
      ->fields('ss', ['pid', 'pack_reference_number']);

    // Join with queue to get reason
    $query->leftJoin('sentinel_portal_queue', 'spq', 'ss.pid = spq.pid');
    $query->addField('spq', 'action', 'queue_reason');

    // Filter by queue action if provided
    if (!empty($queue_action)) {
      $query->condition('spq.action', $queue_action);
    }

    // Check if field table exists
    if ($database->schema()->tableExists('sentinel_sample__field_sample_hold_state')) {
      $query->leftJoin('sentinel_sample__field_sample_hold_state', 'hs', 'ss.pid = hs.entity_id');
      $query->addField('hs', 'field_sample_hold_state_target_id', 'hold_state_tid');
      $query->isNotNull('hs.field_sample_hold_state_target_id');

      if (!empty($hold_state_tid)) {
        $query->condition('hs.field_sample_hold_state_target_id', $hold_state_tid);
      }
    }
    else {
      $query->condition('ss.on_hold', 1);
      $query->addExpression('NULL', 'hold_state_tid');
    }

    if (!empty($pack_reference)) {
      $query->condition('ss.pack_reference_number', '%' . $database->escapeLike($pack_reference) . '%', 'LIKE');
    }

    $pager = $query->extend(PagerSelectExtender::class)->limit(10);
    return $pager->execute()->fetchAll();
  }

  /**
   * Build samples table.
   */
  protected function buildSamplesTable($samples) {
    $header = [
      ['data' => ['#markup' => '<input type="checkbox" id="select-all-samples">']],
      $this->t('Pack reference number'),
      $this->t('Sample hold state'),
      $this->t('The reason for the pack being in the queue'),
    ];

    $rows = [];
    foreach ($samples as $row) {
      $hold_state_name = '';
      if (!empty($row->hold_state_tid)) {
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($row->hold_state_tid);
        if ($term) {
          $hold_state_name = $term->getName();
        }
      }

      $pack_link = Link::fromTextAndUrl(
        $row->pack_reference_number,
        Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $row->pid])
      );

      $queue_reason = !empty($row->queue_reason) ? $row->queue_reason : '-';

      $rows[] = [
        ['data' => ['#markup' => '<input type="checkbox" class="sample-checkbox" name="samples[' . $row->pid . ']" value="' . $row->pid . '">']],
        ['data' => $pack_link->toRenderable()],
        $hold_state_name ?: 'On Hold',
        $queue_reason,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No Samples on hold'),
      '#attributes' => ['class' => ['on-hold-samples-table']],
      '#attached' => [
        'library' => ['hold_states/bulk_operations'],
      ],
    ];
  }

  /**
   * Apply filters submit handler.
   */
  public function applyFilters(array &$form, FormStateInterface $form_state) {
    $tid = $form_state->getValue('tid');
    $pack_ref = $form_state->getValue('pack_reference_number');
    $queue_action = $form_state->getValue('queue_action');

    $query = [];
    if (!empty($tid) && $tid !== '') {
      $query['tid'] = $tid;
    }
    if (!empty($pack_ref) && trim($pack_ref) !== '') {
      $query['pack_reference_number'] = trim($pack_ref);
    }
    if (!empty($queue_action) && $queue_action !== '') {
      $query['queue_action'] = $queue_action;
    }

    $url = Url::fromRoute('hold_states.on_hold_samples', [], ['query' => $query]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Execute bulk operation submit handler.
   */
  public function executeBulkOperation(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue('action');
    $selected_samples = array_filter($form_state->getValue('samples', []));

    if (empty($action) || empty($selected_samples)) {
      $this->messenger()->addWarning($this->t('Please select an action and at least one sample.'));
      return;
    }

    // Process the action
    if ($action === 'remove_hold_state') {
      $this->removeHoldState($selected_samples);
    }
    elseif (strpos($action, 'set_hold_state_') === 0) {
      $tid = str_replace('set_hold_state_', '', $action);
      $this->setHoldState($selected_samples, $tid);
    }

    $form_state->setRedirect('hold_states.on_hold_samples');
  }

  /**
   * Set hold state for samples.
   */
  protected function setHoldState(array $pids, $tid) {
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    $samples = $storage->loadMultiple($pids);
    $count = 0;

    foreach ($samples as $sample) {
      if ($sample->hasField('field_sample_hold_state')) {
        $sample->set('field_sample_hold_state', ['target_id' => $tid]);
        $sample->save();
        $count++;
      }
    }

    $this->messenger()->addStatus($this->t('Updated @count samples.', ['@count' => $count]));
  }

  /**
   * Remove hold state from samples.
   */
  protected function removeHoldState(array $pids) {
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    $samples = $storage->loadMultiple($pids);
    $count = 0;

    foreach ($samples as $sample) {
      if ($sample->hasField('field_sample_hold_state')) {
        $sample->set('field_sample_hold_state', NULL);
        $sample->save();
        $count++;
      }
    }

    $this->messenger()->addStatus($this->t('Removed hold state from @count samples.', ['@count' => $count]));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handled by specific submit handlers
  }

}

