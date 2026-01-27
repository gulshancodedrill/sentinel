<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for custom samples test list page.
 */
class SentinelSampleTestListController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a SentinelSampleTestListController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Builds the samples test list page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array.
   */
  public function buildPage(Request $request) {
    $build = [];

    // Get filters from query parameters
    $filters = $this->extractFilters($request);

    // Build filter form
    $build['filters'] = $this->buildFilterForm($filters, $request);

    // Build table and pager
    $table_build = $this->buildTable($filters, $request);
    $build['table'] = $table_build['table'];
    $build['pager'] = $table_build['pager'];

    // Add CSS
    $build['#attached']['library'][] = 'sentinel_portal_entities/sample-list-styling';

    return $build;
  }

  /**
   * Extract filters from request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Array of filter values.
   */
  protected function extractFilters(Request $request) {
    $query = $request->query->all();
    return [
      'pack_reference_number' => $query['pack_reference_number'] ?? '',
      'postcode' => $query['postcode'] ?? '',
      'system_location' => $query['system_location'] ?? '',
      'project_id' => $query['project_id'] ?? '',
      'pass_fail' => $query['pass_fail'] ?? '',
      'order' => $query['order'] ?? '',
      'sort' => $query['sort'] ?? 'desc',
    ];
  }

  /**
   * Build filter form.
   *
   * @param array $filters
   *   Current filter values.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Form render array.
   */
  protected function buildFilterForm(array $filters, Request $request) {
    // Get current query parameters directly from request
    $query = $request->query->all();
    
    // Read and decode values from query string, ensuring they're properly set
    // Use raw query parameters directly, they're already decoded by Symfony
    $pack_ref = isset($query['pack_reference_number']) ? (string) $query['pack_reference_number'] : '';
    $postcode = isset($query['postcode']) ? (string) $query['postcode'] : '';
    $system_location = isset($query['system_location']) ? (string) $query['system_location'] : '';
    $project_id = isset($query['project_id']) ? (string) $query['project_id'] : '';
    $pass_fail = isset($query['pass_fail']) ? (string) $query['pass_fail'] : '';
    
    // Override filters with actual query parameters (prioritize query string)
    $filters['pack_reference_number'] = $pack_ref ?: ($filters['pack_reference_number'] ?? '');
    $filters['postcode'] = $postcode ?: ($filters['postcode'] ?? '');
    $filters['system_location'] = $system_location ?: ($filters['system_location'] ?? '');
    $filters['project_id'] = $project_id ?: ($filters['project_id'] ?? '');
    $filters['pass_fail'] = $pass_fail ?: ($filters['pass_fail'] ?? '');
    $form = [
      '#type' => 'form',
      '#form_id' => 'sentinel_sample_test_filter_form',
      '#attributes' => [
        'class' => ['views-exposed-form', 'submitted-filters'],
        'method' => 'get',
        'action' => Url::fromRoute('sentinel_portal.samples_test')->toString(),
      ],
    ];

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-exposed-widgets'],
        'style' => 'display: flex; flex-wrap: nowrap; gap: 10px 15px; align-items: flex-end;',
      ],
    ];
    
    // Add CSS to ensure form items align properly
    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '.views-exposed-widgets { display: flex !important; flex-wrap: nowrap !important; gap: 10px 15px !important; align-items: flex-end !important; } .views-exposed-widgets > .form-item { display: flex !important; flex-direction: column !important; margin-bottom: 0 !important; flex: 1 !important; min-width: 150px !important; } .views-exposed-widgets > .form-item label { margin-bottom: 5px !important; } .views-exposed-widgets > .form-item input, .views-exposed-widgets > .form-item select { margin-top: 0 !important; width: 100% !important; } .views-exposed-widgets > .form-actions, .views-exposed-widgets > div.form-actions { flex: 0 0 auto !important; display: flex !important; align-items: flex-end !important; margin: 0 !important; padding: 0 !important; } .views-exposed-widgets > .form-actions input[type="submit"], .views-exposed-widgets > div.form-actions input[type="submit"] { white-space: nowrap !important; height: 34px !important; margin: 0 !important; }',
      ],
      'filter_form_alignment',
    ];

    // Search by reference no.
    $pack_ref_value = trim((string) ($filters['pack_reference_number'] ?? ''));
    $form['filters']['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search by reference no.'),
      '#description' => $this->t('The unique pack reference, beginning 102: 001: 005: 006; etc.'),
      '#description_display' => 'after',
      '#default_value' => $pack_ref_value,
      '#value' => $pack_ref_value,
      '#attributes' => [
        'class' => ['form-control'],
        'name' => 'pack_reference_number',
      ],
    ];

    // Search by postcode
    $postcode_value = trim((string) ($filters['postcode'] ?? ''));
    $form['filters']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search by postcode'),
      '#description' => $this->t('May or may not contain spaces.'),
      '#description_display' => 'after',
      '#default_value' => $postcode_value,
      '#value' => $postcode_value,
      '#attributes' => [
        'class' => ['form-control'],
        'name' => 'postcode',
      ],
    ];

    // Search by system address
    $system_location_value = trim((string) ($filters['system_location'] ?? ''));
    $form['filters']['system_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search by system address'),
      '#description' => $this->t('Search by street or town. May or may not contain commas.'),
      '#description_display' => 'after',
      '#default_value' => $system_location_value,
      '#value' => $system_location_value,
      '#attributes' => [
        'class' => ['form-control'],
        'name' => 'system_location',
      ],
    ];

    // Search by Project ID
    $project_id_value = trim((string) ($filters['project_id'] ?? ''));
    $form['filters']['project_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search by Project ID'),
      '#description' => $this->t('Provided by Vaillant for 001 packs.'),
      '#description_display' => 'after',
      '#default_value' => $project_id_value,
      '#value' => $project_id_value,
      '#attributes' => [
        'class' => ['form-control'],
        'name' => 'project_id',
      ],
    ];

    // Filter by result
    $pass_fail_value = trim((string) ($filters['pass_fail'] ?? ''));
    $form['filters']['pass_fail'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by result'),
      '#description' => '',
      '#description_display' => 'after',
      '#options' => [
        '' => $this->t('- Any -'),
        '1' => $this->t('Pass'),
        '0' => $this->t('Fail'),
        'pending' => $this->t('Pending'),
      ],
      '#default_value' => $pass_fail_value,
      '#value' => $pass_fail_value,
      '#attributes' => [
        'class' => ['form-control'],
        'name' => 'pass_fail',
      ],
    ];

    // Apply and Reset buttons - positioned after all filters
    $form['filters']['submit_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'flex: 0 0 auto; display: flex; align-items: flex-end; gap: 10px;',
      ],
    ];
    $form['filters']['submit_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
        'style' => 'white-space: nowrap; height: 34px;',
      ],
    ];
    
    // Reset button - clears all filters
    $form['filters']['submit_wrapper']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('sentinel_portal.samples_test'),
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
        'style' => 'white-space: nowrap; height: 34px; display: inline-block; padding: 6px 12px; text-decoration: none;',
      ],
    ];

    return $form;
  }

  /**
   * Build summary statistics.
   *
   * @param array $filters
   *   Current filter values.
   *
   * @return array
   *   Render array for summary stats.
   */
  protected function buildSummaryStats(array $filters) {
    $query = $this->buildQuery($filters);

    // Count passed
    $passed_query = clone $query;
    $passed_query->condition('ss.pass_fail', 1);
    $passed_count = $passed_query->countQuery()->execute()->fetchField();

    // Count failed
    $failed_query = clone $query;
    $failed_query->condition('ss.pass_fail', 0);
    $failed_count = $failed_query->countQuery()->execute()->fetchField();

    // Count pending (pass_fail is NULL)
    $pending_query = clone $query;
    $pending_query->isNull('ss.pass_fail');
    $pending_count = $pending_query->countQuery()->execute()->fetchField();

    // Total
    $total_count = $query->countQuery()->execute()->fetchField();

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sample-summary-stats'],
        'style' => 'display: flex; gap: 15px; margin-bottom: 20px;',
      ],
    ];

    $build['passed'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['stat-box', 'stat-passed'],
        'style' => 'background: #28a745; color: white; padding: 15px; border-radius: 5px; flex: 1;',
      ],
      '#markup' => '<strong>' . $this->t('Passed Tests') . '</strong><br><span style="font-size: 24px;">' . $passed_count . '</span>',
    ];

    $build['failed'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['stat-box', 'stat-failed'],
        'style' => 'background: #dc3545; color: white; padding: 15px; border-radius: 5px; flex: 1;',
      ],
      '#markup' => '<strong>' . $this->t('Failed Tests') . '</strong><br><span style="font-size: 24px;">' . $failed_count . '</span>',
    ];

    $build['pending'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['stat-box', 'stat-pending'],
        'style' => 'background: #007bff; color: white; padding: 15px; border-radius: 5px; flex: 1;',
      ],
      '#markup' => '<strong>' . $this->t('Pending Tests') . '</strong><br><span style="font-size: 24px;">' . $pending_count . '</span>',
    ];

    $build['total'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['stat-box', 'stat-total'],
        'style' => 'background: #6c757d; color: white; padding: 15px; border-radius: 5px; flex: 1;',
      ],
      '#markup' => '<strong>' . $this->t('Total Tests') . '</strong><br><span style="font-size: 24px;">' . $total_count . '</span>',
    ];

    return $build;
  }

  /**
   * Build the base query with filters.
   *
   * @param array $filters
   *   Filter values.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The query object.
   */
  protected function buildQuery(array $filters) {
    $query = $this->database->select('sentinel_sample', 'ss')
      ->fields('ss', ['pid']);

    // Access control: Non-admin users can only see their own packs
    $current_user = \Drupal::currentUser();
    $is_admin = $current_user->hasPermission('administer sentinel_sample') || 
                $current_user->hasPermission('sentinel portal administration');
    
    if (!$is_admin && $current_user->isAuthenticated()) {
      // Get the current user's sentinel_client to get their UCR
      $client = sentinel_portal_entities_get_client_by_user($current_user);
      if ($client && $client->hasField('ucr') && !$client->get('ucr')->isEmpty()) {
        $user_ucr = $client->get('ucr')->value;
        // Filter by UCR: sentinel_sample.ucr == sentinel_client.ucr
        $query->condition('ss.ucr', $user_ucr, '=');
      } else {
        // User has no client/UCR, show no results
        $query->condition('ss.ucr', -1, '='); // Impossible condition
      }
    }
    // If admin, no UCR filter is applied - they see all packs

    // Apply filters
    if (!empty($filters['pack_reference_number'])) {
      $query->condition('ss.pack_reference_number', '%' . $this->database->escapeLike($filters['pack_reference_number']) . '%', 'LIKE');
    }

    if (!empty($filters['postcode'])) {
      $query->condition('ss.postcode', '%' . $this->database->escapeLike($filters['postcode']) . '%', 'LIKE');
    }

    if (!empty($filters['system_location'])) {
      $query->condition('ss.system_location', '%' . $this->database->escapeLike($filters['system_location']) . '%', 'LIKE');
    }

    if (!empty($filters['project_id'])) {
      $query->condition('ss.project_id', $filters['project_id']);
    }

    if ($filters['pass_fail'] !== '') {
      if ($filters['pass_fail'] === 'pending') {
        $query->isNull('ss.pass_fail');
      } else {
        $query->condition('ss.pass_fail', (int) $filters['pass_fail']);
      }
    }

    return $query;
  }

  /**
   * Build the table.
   *
   * @param array $filters
   *   Filter values.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Table render array.
   */
  protected function buildTable(array $filters, Request $request) {
    $query = $this->buildQuery($filters);

    // Select all needed fields (including created for sorting)
    $query->fields('ss', [
      'pid',
      'pack_reference_number',
      'system_location',
      'date_booked',
      'date_reported',
      'pass_fail',
      'fileid',
      'postcode',
      'created',
      'changed',
    ]);

    // Add sorting based on query parameters
    $order = $filters['order'] ?? '';
    $sort_direction = strtoupper($filters['sort'] ?? 'desc');
    if ($sort_direction !== 'ASC' && $sort_direction !== 'DESC') {
      $sort_direction = 'DESC';
    }
    
    if ($order === 'date_reported_1' || $order === 'date_reported') {
      // Sort by date_reported
      $query->orderBy('ss.date_reported', $sort_direction);
      // Secondary sort by created and pid
      $query->orderBy('ss.date_reported', 'DESC');
      $query->orderBy('ss.pid', 'DESC');
    } elseif ($order === 'date_received' || $order === 'date_booked') {
      // Sort by date_received (date_booked in database)
      $query->orderBy('ss.date_booked', $sort_direction);
      // Secondary sort by created and pid
      $query->orderBy('ss.date_reported', 'DESC');
      $query->orderBy('ss.pid', 'DESC');
    } else {
      // Default sorting - latest first
      $query->orderBy('ss.changed', 'DESC');
      $query->orderBy('ss.pid', 'DESC');
    }

    // Get pager with full pagination
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
    
    // Preserve query parameters for pagination
    $query_params = $request->query->all();
    if (!empty($query_params)) {
      $pager->addTag('sentinel_sample_test_filter');
    }
    
    $results = $pager->execute();

    // Build Date Received header with sorting link
    $current_order = $filters['order'] ?? '';
    $current_sort = strtolower($filters['sort'] ?? 'desc');
    
    // Date Received sorting
    $date_received_sort = ($current_order === 'date_received' || $current_order === 'date_booked') ? $current_sort : 'desc';
    $next_sort_received = ($date_received_sort === 'desc') ? 'asc' : 'desc';
    
    $sort_query_received = $request->query->all();
    $sort_query_received['order'] = 'date_received';
    $sort_query_received['sort'] = $next_sort_received;
    
    $date_received_header = Link::createFromRoute(
      $this->t('Date received'),
      'sentinel_portal.samples_test',
      [],
      [
        'query' => $sort_query_received,
        'attributes' => [
          'class' => ['sort-link'],
        ],
      ]
    )->toString();
    
    // Add sort indicator icon for Date Received
    if ($current_order === 'date_received' || $current_order === 'date_booked') {
      if ($current_sort === 'asc') {
        $sort_icon_received = '<i class="fa fa-sort-up" aria-hidden="true"></i>';
      } else {
        $sort_icon_received = '<i class="fa fa-sort-down" aria-hidden="true"></i>';
      }
      $date_received_header = str_replace('</a>', ' ' . $sort_icon_received . '</a>', $date_received_header);
    } else {
      // Show unsorted icon when not sorting by this column
      $sort_icon_received = '<i class="fa fa-sort" aria-hidden="true" style="opacity: 0.3;"></i>';
      $date_received_header = str_replace('</a>', ' ' . $sort_icon_received . '</a>', $date_received_header);
    }
    
    // Build Date Reported header with sorting link
    $date_reported_sort = ($current_order === 'date_reported_1' || $current_order === 'date_reported') ? $current_sort : 'desc';
    $next_sort_reported = ($date_reported_sort === 'desc') ? 'asc' : 'desc';
    
    // Build query parameters for sorting link
    $sort_query_reported = $request->query->all();
    $sort_query_reported['order'] = 'date_reported_1';
    $sort_query_reported['sort'] = $next_sort_reported;
    
    $date_reported_header = Link::createFromRoute(
      $this->t('Date Reported'),
      'sentinel_portal.samples_test',
      [],
      [
        'query' => $sort_query_reported,
        'attributes' => [
          'class' => ['sort-link'],
        ],
      ]
    )->toString();
    
    // Add sort indicator icon if currently sorting by this column
    if ($current_order === 'date_reported_1' || $current_order === 'date_reported') {
      if ($current_sort === 'asc') {
        $sort_icon_reported = '<i class="fa fa-sort-up" aria-hidden="true"></i>';
      } else {
        $sort_icon_reported = '<i class="fa fa-sort-down" aria-hidden="true"></i>';
      }
      $date_reported_header = str_replace('</a>', ' ' . $sort_icon_reported . '</a>', $date_reported_header);
    } else {
      // Show unsorted icon when not sorting by this column
      $sort_icon_reported = '<i class="fa fa-sort" aria-hidden="true" style="opacity: 0.3;"></i>';
      $date_reported_header = str_replace('</a>', ' ' . $sort_icon_reported . '</a>', $date_reported_header);
    }
    
    $header = [
      $this->t('Pack reference number'),
      $this->t('System address'),
      ['data' => ['#markup' => $date_received_header]],
      ['data' => ['#markup' => $date_reported_header]],
      $this->t('Status'),
      $this->t('Certificate'),
      $this->t('Postcode'),
    ];

    $rows = [];

    foreach ($results as $row) {
      // Pack ID
      $pack_id = $row->pid;

      // Pack reference number
      $pack_ref = $row->pack_reference_number ?? '';
      $pack_ref_link = Link::createFromRoute(
        $pack_ref,
        'entity.sentinel_sample.canonical',
        ['sentinel_sample' => $pack_id]
      )->toString();

      // System address
      $system_location = $row->system_location ?? '';

      // Date received (date_booked)
      $date_booked = $row->date_booked ?? '';
      $date_received = $date_booked ? date('d/m/Y', strtotime($date_booked)) : 'Not yet received';

      // Date reported
      $date_reported = $row->date_reported ?? '';
      $date_reported_formatted = $date_reported ? date('d/m/Y', strtotime($date_reported)) : '-';

      // Status
      $pass_fail = $row->pass_fail;
      if ($pass_fail === NULL) {
        $status = '<span class="lozenge--pending">PENDING</span>';
      } elseif ($pass_fail == 1) {
        $status = '<span class="lozenge--passed">PASSED</span>';
      } else {
        $status = '<span class="lozenge--failed">FAILED</span>';
      }

      // Certificate
      $fileid = $row->fileid ?? NULL;
      if ($fileid) {
        $file = File::load($fileid);
        if ($file instanceof File) {
          $pdf_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          $certificate = Link::fromTextAndUrl(
            $this->t('Download PDF'),
            Url::fromUri($pdf_url, [
              'attributes' => [
                'class' => ['link-download'],
                'download' => '',
              ],
            ])
          )->toString();
        } else {
          $certificate = $this->t('No report');
        }
      } else {
        $certificate = $this->t('No report');
      }

      // Postcode
      $postcode = $row->postcode ?? '';

      $rows[] = [
        ['data' => ['#markup' => $pack_ref_link]],
        $system_location,
        $date_received,
        $date_reported_formatted,
        ['data' => ['#markup' => $status]],
        ['data' => ['#markup' => $certificate]],
        $postcode,
      ];
    }

    $build = [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No samples found.'),
        '#attributes' => ['class' => ['samples-table']],
      ],
      'pager' => [
        '#type' => 'pager',
        '#quantity' => 9,
        '#tags' => [
          'first' => $this->t('« first'),
          'previous' => $this->t('‹ previous'),
          'next' => $this->t('next ›'),
          'last' => $this->t('last »'),
        ],
      ],
    ];

    return $build;
  }

}

