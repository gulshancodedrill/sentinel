<?php

namespace Drupal\sentinel_portal_queue\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for queue administration.
 */
class QueueAdminForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_queue_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Create form.
    $filter_list = [
      '' => '--',
      'action_invalid_pack' => 'invalid_pack',
      'action_sendreport' => 'sendreport',
      'action_generate_results' => 'generate_results',
      'action_invoke_send_results_hook' => 'invoke_send_results_hook',
    ];

    $form['queue_filter_quick'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter'),
      '#options' => $filter_list,
    ];

    if ($form_state->getValue('queue_filter_quick')) {
      $form['queue_filter_quick']['#default_value'] = $form_state->getValue('queue_filter_quick');
    }

    $form['queue_filter_pid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter by PID'),
    ];

    if ($form_state->getValue('queue_filter_pid')) {
      $form['queue_filter_pid']['#default_value'] = $form_state->getValue('queue_filter_pid');
    }

    $filter_list = [
      '' => '--',
      'order_expires_desc' => 'Expires DESC',
      'order_expires_asc' => 'Expires ASC',
    ];

    $form['queue_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Order'),
      '#options' => $filter_list,
    ];

    if ($form_state->getValue('queue_order')) {
      $form['queue_order']['#default_value'] = $form_state->getValue('queue_order');
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    // Sort out filters.
    $filters = [];

    $quick_filter = $form_state->getValue('queue_filter_quick');

    switch ($quick_filter) {
      case 'action_invalid_pack':
        $filters[] = [
          'field' => 'action',
          'value' => 'invalid_pack',
        ];
        break;
      case 'action_sendreport':
        $filters[] = [
          'field' => 'action',
          'value' => 'sendreport',
        ];
        break;
      case 'action_generate_results':
        $filters[] = [
          'field' => 'action',
          'value' => 'generate_results',
        ];
        break;
      case 'action_invoke_send_results_hook':
        $filters[] = [
          'field' => 'action',
          'value' => 'invoke_send_results_hook',
        ];
        break;
    }

    $pid_filter = $form_state->getValue('queue_filter_pid');

    if (!is_null($pid_filter) && $pid_filter != '') {
      $filters[] = [
        'field' => 'pid',
        'value' => $pid_filter,
      ];
    }

    $order = [];
    $queue_order = $form_state->getValue('queue_order');

    switch ($queue_order) {
      case 'order_expires_desc':
        $order = [
          'field' => 'expire',
          'direction' => 'DESC',
        ];
        break;
      case 'order_expires_asc':
        $order = [
          'field' => 'expire',
          'direction' => 'ASC',
        ];
        break;
    }

    $table = $this->buildQueueTable($filters, $order);

    $form['results'] = [
      '#markup' => $table,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Build the queue table.
   *
   * @param array $filters
   *   The filters to apply.
   * @param array $order
   *   The ordering to apply.
   *
   * @return string
   *   The HTML table.
   */
  protected function buildQueueTable(array $filters, array $order) {
    $database = \Drupal::database();
    
    $query = $database->select('sentinel_portal_queue', 'q')
      ->fields('q');

    // Apply filters
    foreach ($filters as $filter) {
      $query->condition($filter['field'], $filter['value']);
    }

    // Apply ordering
    if (!empty($order)) {
      $query->orderBy($order['field'], $order['direction']);
    }

    $query->range(0, 25);
    $result = $query->execute();

    $rows = [];
    while ($item = $result->fetchAssoc()) {
      $rows[] = [
        $item['item_id'],
        $item['name'],
        $item['pid'],
        $item['action'],
        date('Y-m-d H:i:s', $item['expire']),
        date('Y-m-d H:i:s', $item['created']),
        $item['failed'],
        \Drupal::l($this->t('View'), \Drupal\Core\Url::fromRoute('sentinel_portal_queue.view_item', ['item_id' => $item['item_id']])),
      ];
    }

    $header = [
      $this->t('ID'),
      $this->t('Name'),
      $this->t('PID'),
      $this->t('Action'),
      $this->t('Expires'),
      $this->t('Created'),
      $this->t('Failed'),
      $this->t('Operations'),
    ];

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No queue items found.'),
    ];

    // Get total count
    $count_query = $database->select('sentinel_portal_queue', 'q');
    foreach ($filters as $filter) {
      $count_query->condition($filter['field'], $filter['value']);
    }
    $count = $count_query->countQuery()->execute()->fetchField();

    $output = \Drupal::service('renderer')->render($table);
    $output .= '<p>' . $this->t('Total number of items in queue: @no_items', ['@no_items' => $count]) . '</p>';

    return $output;
  }

}

