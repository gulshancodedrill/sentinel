<?php

namespace Drupal\hold_states\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\File\FileSystemInterface;

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

    // Support encoded filter payload when returning from batch redirects.
    $encoded_filters = $request->query->get('filters');
    if ($encoded_filters) {
      $decoded = json_decode(base64_decode($encoded_filters, TRUE) ?: 'null', TRUE);
      if (is_array($decoded)) {
        if (isset($decoded['tid'])) {
          $hold_state_tid = $decoded['tid'];
        }
        if (isset($decoded['pack_reference_number'])) {
          $pack_reference = $decoded['pack_reference_number'];
        }
      }
    }

    // Filters section
    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filters'),
      '#attributes' => ['class' => ['form-item']],
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

    $form['filters']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#submit' => ['::applyFilters'],
    ];

    // Get samples data
    $samples = $this->getSamples($hold_state_tid, $pack_reference);

    // Bulk operations
    if (!empty($samples)) {
      $form['operations'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Operations'),
        '#attributes' => ['class' => ['form-item']],
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

    // CSV export button
    $form['export_csv'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download CSV'),
      '#submit' => ['::exportCsv'],
      '#limit_validation_errors' => [],
      '#button_type' => 'secondary',
    ];

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
   * Builds a reusable base query with common filters applied.
   */
  protected static function buildBaseQuery(array $filters, $include_hold_state = FALSE) {
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 'ss');

    $hold_state_alias = NULL;
    if ($include_hold_state) {
      $hold_state_alias = $query->leftJoin('taxonomy_term_field_data', 'tt', 'tt.tid = ss.sentinel_sample_hold_state_target_id');
    }

    $query->isNotNull('ss.sentinel_sample_hold_state_target_id');

    if (!empty($filters['tid'])) {
      $query->condition('ss.sentinel_sample_hold_state_target_id', (int) $filters['tid']);
    }

    if (!empty($filters['pack_reference_number'])) {
      $query->condition('ss.pack_reference_number', '%' . $database->escapeLike($filters['pack_reference_number']) . '%', 'LIKE');
    }

    return [$query, $hold_state_alias];
  }

  /**
   * Get samples based on filters.
   */
  protected function getSamples($hold_state_tid, $pack_reference) {
    $filters = [
      'tid' => $hold_state_tid,
      'pack_reference_number' => $pack_reference,
    ];

    [$query, $hold_state_alias] = static::buildBaseQuery($filters, TRUE);
    $query->fields('ss', ['pid', 'pack_reference_number']);
    $query->addField('ss', 'sentinel_sample_hold_state_target_id', 'hold_state_tid');

    if ($hold_state_alias) {
      $query->addField($hold_state_alias, 'name', 'hold_state_name');
    }

    $pager = $query->extend(PagerSelectExtender::class)->limit(10);
    return $pager->execute()->fetchAll();
  }

  /**
   * Build samples table.
   */
  protected function buildSamplesTable($samples) {
    $header = [
      'pack_reference_number' => $this->t('Pack reference number'),
      'hold_state' => $this->t('Sample hold state'),
    ];

    $options = [];
    foreach ($samples as $row) {
      $hold_state_name = $row->hold_state_name ?? '';

      $pack_link = Link::fromTextAndUrl(
        $row->pack_reference_number,
        Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $row->pid])
      );

      $options[$row->pid] = [
        'pack_reference_number' => $pack_link->toString(),
        'hold_state' => $hold_state_name ?: 'On Hold',
      ];
    }

    return [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#js_select' => TRUE,
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

    $query = [];
    if (!empty($tid) && $tid !== '') {
      $query['tid'] = $tid;
    }
    if (!empty($pack_ref) && trim($pack_ref) !== '') {
      $query['pack_reference_number'] = trim($pack_ref);
    }

    $url = Url::fromRoute('hold_states.on_hold_samples', [], ['query' => $query]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Execute bulk operation submit handler.
   */
  public function executeBulkOperation(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue('action');
    $selected_values = array_filter($form_state->getValue('samples_table', []));
    $selected_samples = array_keys($selected_values);

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
    $count = $this->updateHoldStateLegacyField($pids, (int) $tid);
    $this->messenger()->addStatus($this->t('Updated @count samples.', ['@count' => $count]));
  }

  /**
   * Remove hold state from samples.
   */
  protected function removeHoldState(array $pids) {
    $count = $this->updateHoldStateLegacyField($pids, NULL);
    $this->messenger()->addStatus($this->t('Removed hold state from @count samples.', ['@count' => $count]));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handled by specific submit handlers
  }

  /**
   * Update the legacy hold state ID column directly for selected samples.
   */
  protected function updateHoldStateLegacyField(array $pids, $tid = NULL) {
    if (empty($pids)) {
      return 0;
    }

    $connection = \Drupal::database();
    $value = $tid === NULL ? NULL : (int) $tid;

    $query = $connection->update('sentinel_sample')
      ->fields(['sentinel_sample_hold_state_target_id' => $value])
      ->condition('pid', $pids, 'IN');

    return (int) $query->execute();
  }

  /**
   * Submit handler for CSV export.
   */
  public function exportCsv(array &$form, FormStateInterface $form_state) {
    $filters = [
      'tid' => $form_state->getValue('tid') ?: NULL,
      'pack_reference_number' => $form_state->getValue('pack_reference_number') ?: NULL,
    ];

    [$count_query, ] = static::buildBaseQuery($filters, FALSE);
    $total = (int) $count_query->countQuery()->execute()->fetchField();

    if ($total === 0) {
      $this->messenger()->addWarning($this->t('No samples found for the selected filters.'));
      return;
    }

    $limit = 500;
    $operations = [];
    for ($offset = 0; $offset < $total; $offset += $limit) {
      $operations[] = [
        [static::class, 'csvBatchProcess'],
        [$filters, $offset, $limit],
      ];
    }

    // Redirect after batch to landing route including filter state so that the
    // download controller has context once the batch finishes.
    $batch = [
      'title' => $this->t('Exporting on hold samples'),
      'operations' => $operations,
      'finished' => [static::class, 'csvBatchFinished'],
    ];

    batch_set($batch);
    $form_state->setRedirect('hold_states.export_ready');
  }

  /**
   * Batch operation: append CSV rows for a data chunk.
   */
  public static function csvBatchProcess(array $filters, $offset, $limit, array &$context) {
    static::initializeCsvContext($context);
    $context['results']['filters'] = $filters;

    [$query, $hold_state_alias] = static::buildBaseQuery($filters, TRUE);
    $query->fields('ss', static::getCsvFieldList());

    if ($hold_state_alias) {
      $query->addField($hold_state_alias, 'name', 'hold_state_name');
    }

    $query->range($offset, $limit);
    $result = $query->execute();

    if (!$result) {
      return;
    }

    if ($handle = fopen($context['results']['file'], 'a')) {
      if (empty($context['results']['header_written'])) {
        fputcsv($handle, static::getCsvHeaders());
        $context['results']['header_written'] = TRUE;
      }

      foreach ($result as $record) {
        fputcsv($handle, static::formatCsvRow($record));
      }

      fclose($handle);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function csvBatchFinished($success, array $results, array $operations) {
    if ($success && !empty($results['file'])) {
      $file_system = \Drupal::service('file_system');
      $destination_dir = 'public://hold_state_exports';
      $download_path = $results['file'];
      $download_name = $results['filename'] ?? basename($results['file']);

      if ($file_system->prepareDirectory($destination_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $destination = $destination_dir . '/' . $download_name;
        try {
          $file_system->copy($results['file'], $destination, FileSystemInterface::EXISTS_REPLACE);
          $download_path = $destination;
        }
        catch (\Exception $e) {
          // Fall back to original temporary file.
        }
      }

      $encoded_path = base64_encode($download_path);
      $encoded_filters = base64_encode(json_encode($results['filters'] ?? []));

      $store = \Drupal::service('tempstore.private')->get('hold_states_export');
      $store->set(\Drupal::currentUser()->id(), [
        'file' => $encoded_path,
        'name' => $download_name,
        'filters' => $encoded_filters,
      ]);

      $batch =& batch_get();
      if ($batch !== NULL) {
        $batch['redirect'] = Url::fromRoute('hold_states.export_ready', [], [
          'absolute' => TRUE,
        ])->toString();
      }
    }
    else {
      \Drupal::messenger()->addError(t('CSV export did not complete.'));
    }
  }

  /**
   * Prepare the batch context with a writable file.
   */
  protected static function initializeCsvContext(array &$context) {
    if (!empty($context['results']['file'])) {
      return;
    }

    $file_system = \Drupal::service('file_system');
    $directory = 'temporary://hold_state_exports';
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $filename = 'on-hold-samples-' . date('Ymd-His') . '.csv';
    $filepath = $directory . '/' . $filename;

    if ($handle = fopen($filepath, 'w')) {
      fclose($handle);
    }

    $context['results']['file'] = $filepath;
    $context['results']['header_written'] = FALSE;
    $context['results']['filename'] = $filename;
    $context['results']['filters'] = $filters;
  }

  /**
   * Ordered list of CSV field machine names.
   */
  protected static function getCsvFieldList() {
    return [
      'pid',
      'uuid',
      'pack_reference_number',
      'installer_name',
      'installer_email',
      'company_name',
      'company_email',
      'company_address1',
      'project_id',
      'company_address2',
      'company_town',
      'company_county',
      'company_postcode',
      'company_tel',
      'system_location',
      'system_age',
      'system_6_months',
      'uprn',
      'property_number',
      'street',
      'town_city',
      'county',
      'postcode',
      'landlord',
      'boiler_manufacturer',
      'boiler_id',
      'boiler_type',
      'engineers_code',
      'service_call_id',
      'date_installed',
      'date_sent',
      'date_booked',
      'date_processed',
      'date_reported',
      'fileid',
      'filename',
      'client_id',
      'client_name',
      'customer_id',
      'lab_ref',
      'pack_type',
      'card_complete',
      'on_hold',
      'pass_fail',
      'appearance_result',
      'appearance_pass_fail',
      'mains_cond_result',
      'sys_cond_result',
      'cond_pass_fail',
      'mains_cl_result',
      'sys_cl_result',
      'cl_pass_fail',
      'iron_result',
      'iron_pass_fail',
      'copper_result',
      'copper_pass_fail',
      'aluminium_result',
      'aluminium_pass_fail',
      'mains_calcium_result',
      'sys_calcium_result',
      'calcium_pass_fail',
      'ph_result',
      'ph_pass_fail',
      'sentinel_x100_result',
      'sentinel_x100_pass_fail',
      'molybdenum_result',
      'molybdenum_pass_fail',
      'boron_result',
      'boron_pass_fail',
      'manganese_result',
      'manganese_pass_fail',
      'mob_ratio',
      'updated',
      'ucr',
      'installer_company',
      'old_pack_reference_number',
      'duplicate_of',
      'legacy',
      'api_created_by',
      'created',
      'changed',
      'sentinel_sample_hold_state_target_id',
      'sentinel_company_address_target_id',
      'sentinel_sample_address_target_id',
    ];
  }

  /**
   * Returns the CSV header labels.
   */
  protected static function getCsvHeaders() {
    $headers = static::getCsvFieldList();
    $headers[] = 'hold_state_name';
    return $headers;
  }

  /**
   * Formats a database record into a CSV data row.
   */
  protected static function formatCsvRow($record) {
    $row = [];
    foreach (static::getCsvFieldList() as $field) {
      $row[] = isset($record->$field) ? $record->$field : '';
    }
    $row[] = $record->hold_state_name ?? '';
    return $row;
  }

}

