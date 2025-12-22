<?php

namespace Drupal\sentinel_csv_processor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\sentinel_csv_processor\Entity\LabData;

/**
 * Form for listing and selecting CSV files from lab_data entity.
 */
class CsvFileListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_csv_processor_file_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get filter values from query parameters.
    $request = \Drupal::request();
    $refname_filter = trim($request->query->get('refname', ''));
    $filename_filter = trim($request->query->get('filename', ''));
    $status_filter = trim($request->query->get('status', ''));
    $sort_by = $request->query->get('sort', 'uploaded');
    $sort_order = strtoupper($request->query->get('order', 'DESC'));

    // Compact search form - two inputs in a single row.
    $form['search'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['csv-filter-form']],
    ];

    $form['search']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-row', 'clearfix']],
    ];

    $form['search']['wrapper']['refname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reference Number'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Reference Number'),
      '#default_value' => $refname_filter,
      '#size' => 20,
      '#attributes' => ['class' => ['filter-input']],
      '#parents' => ['refname'],
    ];

    $form['search']['wrapper']['filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filename'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Filename'),
      '#default_value' => $filename_filter,
      '#size' => 20,
      '#attributes' => ['class' => ['filter-input']],
      '#parents' => ['filename'],
    ];

    $form['search']['wrapper']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#title_display' => 'invisible',
      '#options' => [
        '' => $this->t('All'),
        'pending' => $this->t('Pending'),
        'success' => $this->t('Success'),
        'failed' => $this->t('Failed'),
        'processing' => $this->t('Processing'),
      ],
      '#default_value' => $status_filter,
      '#attributes' => ['class' => ['filter-input']],
      '#parents' => ['status'],
    ];

    $form['search']['wrapper']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['filter-actions']],
    ];

    $form['search']['wrapper']['actions']['search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#submit' => ['::searchSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $form['search']['wrapper']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetSubmit'],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button']],
    ];

    // Add inline CSS for compact layout.
    $form['#attached']['library'][] = 'core/drupal.form';
    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .csv-filter-form .form-row { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; }
          .csv-filter-form .filter-input { flex: 0 0 auto; }
          .csv-filter-form .filter-actions { display: flex; gap: 5px; }
          /* Remove mb-3 class from all divs on this page */
          div.mb-3 { margin-bottom: 0 !important; }
          div[class*="mb-3"] { margin-bottom: 0 !important; }
        ',
      ],
      'csv_filter_styles',
    ];

    // Load lab_data entities with filters applied.
    $storage = \Drupal::entityTypeManager()->getStorage('lab_data');
    $query = $storage->getQuery()
      ->accessCheck(FALSE);
    
    // Apply sorting - validate sort_by and sort_order.
    $valid_sort_fields = ['uploaded', 'processed'];
    if (!in_array($sort_by, $valid_sort_fields)) {
      $sort_by = 'uploaded';
    }
    if (!in_array($sort_order, ['ASC', 'DESC'])) {
      $sort_order = 'DESC';
    }
    $query->sort($sort_by, $sort_order);
    
    // Apply filters - use CONTAINS for partial matching in entity queries.
    if (!empty($refname_filter)) {
      $query->condition('refname', $refname_filter, 'CONTAINS');
    }
    
    if (!empty($filename_filter)) {
      $query->condition('filename', $filename_filter, 'CONTAINS');
    }
    
    if (!empty($status_filter)) {
      $query->condition('status', $status_filter, '=');
    }
    
    // Get total count for pagination.
    $count_query = clone $query;
    $total = $count_query->count()->execute();
    
    // Add pagination using Drupal's pager system (like /portal/samples).
    $items_per_page = 10; // Set to 3 for testing pagination.
    $pager_element = 0;
    $current_page = \Drupal::service('pager.parameters')->findPage($pager_element);
    \Drupal::service('pager.manager')->createPager($total, $items_per_page, $pager_element);
    
    $offset = $current_page * $items_per_page;
    $query->range($offset, $items_per_page);
    
    $entity_ids = $query->execute();
    
    if (empty($entity_ids)) {
      $form['no_files'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No CSV files found matching your criteria.') . '</p>',
      ];
      return $form;
    }

    $entities = $storage->loadMultiple($entity_ids);

    // Add upload button at the top.
    $form['upload_button'] = [
      '#type' => 'link',
      '#title' => $this->t('Upload CSV Files'),
      '#url' => Url::fromRoute('sentinel_csv_processor.upload_form'),
      '#attributes' => [
        'class' => ['button btn btn-warning'],
      ],
      '#weight' => -10,
    ];

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Select one or more CSV files to process.') . '</p>',
    ];

    // Build table rows with columns: filename, pack reference, status, uploaded date, processed date.
    $rows = [];
    $date_formatter = \Drupal::service('date.formatter');
    
    foreach ($entities as $entity) {
      $id = $entity->id();
      $filename = $entity->get('filename')->value ?? '';
      $refname = $entity->get('refname')->value ?? '';
      $status = $entity->get('status')->value ?? 'pending';
      $uploaded = $entity->get('uploaded')->value;
      $processed = $entity->get('processed')->value;
      
      // Format dates.
      $uploaded_date = $uploaded ? $date_formatter->format((int) $uploaded, 'short') : '-';
      $processed_date = $processed ? $date_formatter->format((int) $processed, 'short') : '-';
      
      // Format status with existing lozenge classes (matching /portal/samples pattern).
      $status_class = '';
      $status_text = '';
      
      if ($status === 'success') {
        $status_class = 'lozenge--passed';
        $status_text = 'SUCCESS';
      }
      elseif ($status === 'failed') {
        $status_class = 'lozenge--failed';
        $status_text = 'FAILED';
      }
      elseif ($status === 'processing') {
        $status_class = 'lozenge--processing';
        $status_text = 'PROCESSING';
      }
      elseif ($status === 'pending') {
        $status_class = 'lozenge--pending';
        $status_text = 'PENDING';
      }
      else {
        $status_text = strtoupper($status);
      }
      
      if ($status_class) {
        // Use Markup::create() with existing lozenge CSS classes.
        $status_display = Markup::create('<span class="' . $status_class . '">' . htmlspecialchars($status_text) . '</span>');
      }
      else {
        $status_display = $status_text;
      }
      
      // Build row data for tableselect.
      $rows[$id] = [
        'filename' => $filename,
        'refname' => $refname ?: '-',
        'status' => $status_display,
        'uploaded' => $uploaded_date,
        'processed' => $processed_date,
      ];
    }

    // Build sortable headers for date columns.
    $uploaded_header = $this->buildSortableHeader('Uploaded Date', 'uploaded', $sort_by, $sort_order);
    $processed_header = $this->buildSortableHeader('Processed Date', 'processed', $sort_by, $sort_order);

    $form['files'] = [
      '#type' => 'tableselect',
      '#header' => [
        'filename' => $this->t('Filename'),
        'refname' => $this->t('Pack Reference'),
        'status' => $this->t('Status'),
        'uploaded' => $uploaded_header,
        'processed' => $processed_header,
      ],
      '#options' => $rows,
      '#empty' => $this->t('No CSV files found.'),
      '#attributes' => ['class' => ['csv-file-list-table']],
    ];

    // Add pager with same style as /portal/samples.
    $form['pager'] = [
      '#type' => 'pager',
      '#quantity' => 9,
      '#element' => $pager_element,
      '#tags' => [
        'first' => $this->t('« first'),
        'previous' => $this->t('‹ previous'),
        'next' => $this->t('next ›'),
        'last' => $this->t('last »'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process Selected Files'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_ids = $form_state->getValue('files');
    
    // Filter out unchecked items (value is 0).
    $selected_ids = array_filter($selected_ids, function($value) {
      return $value !== 0;
    });

    if (empty($selected_ids)) {
      $this->messenger()->addWarning($this->t('No files were selected.'));
      return;
    }

    // Redirect to preview page with selected file IDs (entity IDs from lab_data table).
    $file_ids_string = implode(',', $selected_ids);
    $form_state->setRedirect('sentinel_csv_processor.data_preview', [], [
      'query' => ['files' => $file_ids_string],
    ]);
  }

  /**
   * Build a sortable header link for table columns.
   *
   * @param string $title
   *   The header title.
   * @param string $field
   *   The field name to sort by.
   * @param string $current_sort
   *   The currently active sort field.
   * @param string $current_order
   *   The current sort order (ASC or DESC).
   *
   * @return \Drupal\Core\Render\Markup
   *   The sortable header markup.
   */
  protected function buildSortableHeader($title, $field, $current_sort, $current_order) {
    $request = \Drupal::request();
    $query_params = $request->query->all();
    
    // Determine new sort order - toggle if clicking same field, otherwise default to DESC.
    if ($current_sort === $field) {
      $new_order = ($current_order === 'ASC') ? 'DESC' : 'ASC';
    } else {
      $new_order = 'DESC';
    }
    
    $query_params['sort'] = $field;
    $query_params['order'] = $new_order;
    $query_params['page'] = 0; // Reset to first page when sorting.
    
    $url = Url::fromRoute('sentinel_csv_processor.file_list_form', [], ['query' => $query_params]);
    $link = Link::fromTextAndUrl($title, $url)->toString();
    
    // Add sort indicator.
    $indicator = '';
    if ($current_sort === $field) {
      $indicator = $current_order === 'ASC' ? ' ↑' : ' ↓';
    }
    
    return Markup::create($link . $indicator);
  }

  /**
   * Submit handler for search button.
   */
  public function searchSubmit(array &$form, FormStateInterface $form_state) {
    // Get values from input (raw form submission data).
    $input = $form_state->getUserInput();
    $refname = trim($input['refname'] ?? '');
    $filename = trim($input['filename'] ?? '');
    $status = trim($input['status'] ?? '');

    // Redirect with query parameters.
    $query = [];
    if (!empty($refname)) {
      $query['refname'] = $refname;
    }
    if (!empty($filename)) {
      $query['filename'] = $filename;
    }
    if (!empty($status)) {
      $query['status'] = $status;
    }

    // Preserve current sort parameters.
    $request = \Drupal::request();
    $current_sort = $request->query->get('sort', 'uploaded');
    $current_order = strtoupper($request->query->get('order', 'DESC'));
    if ($current_sort) {
      $query['sort'] = $current_sort;
    }
    if ($current_order) {
      $query['order'] = $current_order;
    }

    // Reset pager when filtering.
    $query['page'] = 0;

    $form_state->setRedirect('sentinel_csv_processor.file_list_form', [], ['query' => $query]);
  }

  /**
   * Submit handler for reset button.
   */
  public function resetSubmit(array &$form, FormStateInterface $form_state) {
    // Redirect without query parameters.
    $form_state->setRedirect('sentinel_csv_processor.file_list_form');
  }

}

