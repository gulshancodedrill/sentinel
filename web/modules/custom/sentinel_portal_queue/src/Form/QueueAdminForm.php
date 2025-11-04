<?php

namespace Drupal\sentinel_portal_queue\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Database\Query\PagerSelectExtender;

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

    $form['results'] = $this->buildQueueTable($filters, $order);

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

    // Apply ordering.
    if (!empty($order)) {
      $query->orderBy($order['field'], $order['direction']);
    }

    // Clone the query so we can calculate total count independently of pager.
    $count_query = clone $query;

    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $pager */
    $pager = $query->extend(PagerSelectExtender::class)->limit(25);
    $result = $pager->execute();

    $rows = [];
    while ($item = $result->fetchAssoc()) {
      $pid_value = $item['pid'];
      $pid_cell = !empty($pid_value)
        ? ['data' => Link::fromTextAndUrl($pid_value, Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $pid_value]))->toRenderable()]
        : ['data' => ['#markup' => $this->t('N/A')]];

      $operations = [
        'view' => [
          'title' => $this->t('View'),
          'url' => Url::fromRoute('sentinel_portal_queue.view_item', ['item_id' => $item['item_id']]),
        ],
        'release' => [
          'title' => $this->t('Release'),
          'url' => Url::fromRoute('sentinel_portal_queue.release_item', ['item_id' => $item['item_id']]),
        ],
        'delete' => [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('sentinel_portal_queue.delete_item', ['item_id' => $item['item_id']]),
          'attributes' => ['class' => ['queue-delete-link']],
        ],
      ];

      $rows[] = [
        $item['item_id'],
        $pid_cell,
        $item['action'],
        date('d/m/Y H:i', $item['expire']),
        date('d/m/Y H:i', $item['created']),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $header = [
      $this->t('Item ID'),
      $this->t('PID'),
      $this->t('Action'),
      $this->t('Expires'),
      $this->t('Created'),
      $this->t('Operations'),
    ];

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No queue items found.'),
    ];

    // Get total count
    $count = $count_query->countQuery()->execute()->fetchField();

    return [
      'table' => $table,
      'summary' => [
        '#markup' => $this->t('Total number of items in queue: @no_items', ['@no_items' => $count]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }

}

