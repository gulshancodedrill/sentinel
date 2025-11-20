<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\sentinel_portal_entities\Utility\PackTypeFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for exporting sentinel samples to CSV.
 */
class SentinelSampleExportController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a SentinelSampleExportController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
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
   * Export sentinel samples to CSV using batch processing.
   */
  public function exportCsv() {
    $request = \Drupal::request();
    $query_params = $request->query->all();
    
    // Extract filters from query params (same as list builder)
    $filters = $this->extractFilters($query_params);
    
    // Get all entity IDs matching the current filters
    $entity_ids = $this->getFilteredEntityIds($filters);
    
    if (empty($entity_ids)) {
      $this->messenger()->addWarning($this->t('No samples found to export.'));
      return $this->redirect('sentinel_portal.admin_sample');
    }

    // Create batch
    $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_portal_entities');
    $batch = [
      'title' => $this->t('Exporting samples to CSV...'),
      'operations' => [],
      'finished' => [static::class, 'batchFinished'],
      'file' => $module_path . '/src/Controller/SentinelSampleExportController.php',
    ];

    // Split entity IDs into chunks
    $chunk_size = 100;
    $chunks = array_chunk($entity_ids, $chunk_size);
    
    foreach ($chunks as $chunk) {
      $batch['operations'][] = [
        [static::class, 'batchExportChunk'],
        [$chunk],
      ];
    }

    // Set the batch and process it
    batch_set($batch);
    
    // Return the batch processing response
    return batch_process();
  }

  /**
   * Extract filters from query parameters (same logic as list builder).
   */
  protected function extractFilters(array $query_params) {
    // Use same logic as SentinelSampleListBuilder::getFilters()
    $filters = [];
    
    // Pack reference number
    if (isset($query_params['pack_reference_number'])) {
      $value = trim((string) $query_params['pack_reference_number']);
      if ($value !== '') {
        $filters['pack_reference_number'] = $value;
      }
    }
    
    // Pass/Fail - handle "All" as empty
    if (isset($query_params['pass_fail'])) {
      $value = trim((string) $query_params['pass_fail']);
      if ($value !== '' && $value !== 'All') {
        $filters['pass_fail'] = $value;
      }
    }
    
    // Pack Type (pack_reference_number_1) - handle "All" as empty
    if (isset($query_params['pack_reference_number_1'])) {
      $value = trim((string) $query_params['pack_reference_number_1']);
      if ($value !== '' && $value !== 'All') {
        $filters['pack_reference_number_1'] = $value;
      }
    }
    
    // Email
    if (isset($query_params['email'])) {
      $value = trim((string) $query_params['email']);
      if ($value !== '') {
        $filters['email'] = $value;
      }
    }
    
    // Date Reported (simple select) - handle "All" as empty
    if (isset($query_params['date_reported'])) {
      $value = trim((string) $query_params['date_reported']);
      if ($value !== '' && $value !== 'All') {
        $filters['date_reported'] = $value;
      }
    }
    
    // Date Booked (simple select) - handle "All" as empty
    if (isset($query_params['date_booked'])) {
      $value = trim((string) $query_params['date_booked']);
      if ($value !== '' && $value !== 'All') {
        $filters['date_booked'] = $value;
      }
    }
    
    // Postcode
    if (isset($query_params['postcode'])) {
      $value = trim((string) $query_params['postcode']);
      if ($value !== '') {
        $filters['postcode'] = $value;
      }
    }
    
    // Combine (System address)
    if (isset($query_params['combine'])) {
      $value = trim((string) $query_params['combine']);
      if ($value !== '') {
        $filters['combine'] = $value;
      }
    }
    
    // Date Reported Range (date_reported_1)
    if (isset($query_params['date_reported_1']) && is_array($query_params['date_reported_1'])) {
      $date_reported_1 = [];
      if (isset($query_params['date_reported_1']['min']['date'])) {
        $value = trim((string) $query_params['date_reported_1']['min']['date']);
        if ($value !== '') {
          $date_reported_1['min']['date'] = $value;
        }
      }
      if (isset($query_params['date_reported_1']['max']['date'])) {
        $value = trim((string) $query_params['date_reported_1']['max']['date']);
        if ($value !== '') {
          $date_reported_1['max']['date'] = $value;
        }
      }
      if (!empty($date_reported_1)) {
        $filters['date_reported_1'] = $date_reported_1;
      }
    }
    
    // Date Booked Range (date_booked_1)
    if (isset($query_params['date_booked_1']) && is_array($query_params['date_booked_1'])) {
      $date_booked_1 = [];
      if (isset($query_params['date_booked_1']['min']['date'])) {
        $value = trim((string) $query_params['date_booked_1']['min']['date']);
        if ($value !== '') {
          $date_booked_1['min']['date'] = $value;
        }
      }
      if (isset($query_params['date_booked_1']['max']['date'])) {
        $value = trim((string) $query_params['date_booked_1']['max']['date']);
        if ($value !== '') {
          $date_booked_1['max']['date'] = $value;
        }
      }
      if (!empty($date_booked_1)) {
        $filters['date_booked_1'] = $date_booked_1;
      }
    }
    
    return $filters;
  }

  /**
   * Get filtered entity IDs based on filters (same logic as list builder).
   */
  protected function getFilteredEntityIds(array $filters) {
    $query = $this->database->select('sentinel_sample', 'ss')
      ->fields('ss', ['pid'])
      ->orderBy('ss.changed', 'DESC');

    // Apply same filters as list builder
    // Search Pack ID - filter on pack_reference_number
    if (!empty($filters['pack_reference_number'])) {
      $query->condition('ss.pack_reference_number', '%' . $this->database->escapeLike($filters['pack_reference_number']) . '%', 'LIKE');
    }
    
    // The Sample Result - handle 'p' for pending (NULL only)
    if (isset($filters['pass_fail']) && $filters['pass_fail'] !== '' && $filters['pass_fail'] !== 'All') {
      $pass_fail_value = $filters['pass_fail'];
      if ($pass_fail_value === 'p') {
        // Pending means NULL pass_fail (boolean field: 0=fail, 1=pass, NULL=pending)
        // Use where() with raw SQL to ensure NULL check works correctly
        $query->where('ss.pass_fail IS NULL');
      }
      else {
        $query->condition('ss.pass_fail', $pass_fail_value, '=');
      }
    }
    
    // Pack Type (pack_reference_number_1) - apply combined pack type / prefix filters.
    if (!empty($filters['pack_reference_number_1'])) {
      PackTypeFilter::applyFilterConditions($query, $this->database, $filters['pack_reference_number_1']);
    }
    
    // Date Reported (simple select) - check if date_reported is not null
    if (isset($filters['date_reported']) && $filters['date_reported'] !== '') {
      if ($filters['date_reported'] === 'reported') {
        $query->isNotNull('ss.date_reported');
      }
      elseif ($filters['date_reported'] === 'not_reported') {
        $query->isNull('ss.date_reported');
      }
    }
    
    // Date Booked (simple select) - check if date_booked is not null
    if (isset($filters['date_booked']) && $filters['date_booked'] !== '') {
      if ($filters['date_booked'] === '5_plus_days_booked') {
        $threshold = (new \DateTime('-5 days'))->format('Y-m-d H:i:s');
        $query->isNotNull('ss.date_booked');
        $query->condition('ss.date_booked', $threshold, '<=');
      }
    }
    
    // System postcode
    if (!empty($filters['postcode'])) {
      $query->condition('ss.postcode', '%' . $this->database->escapeLike($filters['postcode']) . '%', 'LIKE');
    }
    
    // System address (combine) - combine street, county, town_city, system_location
    if (!empty($filters['combine'])) {
      $or_group = $query->orConditionGroup()
        ->condition('ss.street', '%' . $this->database->escapeLike($filters['combine']) . '%', 'LIKE')
        ->condition('ss.county', '%' . $this->database->escapeLike($filters['combine']) . '%', 'LIKE')
        ->condition('ss.town_city', '%' . $this->database->escapeLike($filters['combine']) . '%', 'LIKE')
        ->condition('ss.system_location', '%' . $this->database->escapeLike($filters['combine']) . '%', 'LIKE');
      $query->condition($or_group);
    }
    
    // Date Reported range (date_reported_1)
    if (!empty($filters['date_reported_1'])) {
      if (!empty($filters['date_reported_1']['min']['date'])) {
        if ($from = $this->normalizeFilterDate($filters['date_reported_1']['min']['date'])) {
          $query->condition('ss.date_reported', $from, '>=');
        }
      }
      if (!empty($filters['date_reported_1']['max']['date'])) {
        if ($to = $this->normalizeFilterDate($filters['date_reported_1']['max']['date'], TRUE)) {
          $query->condition('ss.date_reported', $to, '<=');
        }
      }
    }
    
    // Date Booked range (date_booked_1)
    if (!empty($filters['date_booked_1'])) {
      if (!empty($filters['date_booked_1']['min']['date'])) {
        if ($from = $this->normalizeFilterDate($filters['date_booked_1']['min']['date'])) {
          $query->condition('ss.date_booked', $from, '>=');
        }
      }
      if (!empty($filters['date_booked_1']['max']['date'])) {
        if ($to = $this->normalizeFilterDate($filters['date_booked_1']['max']['date'], TRUE)) {
          $query->condition('ss.date_booked', $to, '<=');
        }
      }
    }

    // Email filter uses installer_email field
    if (!empty($filters['email'])) {
      $query->join('sentinel_client', 'sc', 'ss.ucr = sc.ucr');
      $query->condition('sc.email', '%' . $this->database->escapeLike($filters['email']) . '%', 'LIKE');
    }

    return $query->execute()->fetchCol();
  }

  /**
   * Normalize filter date value.
   */
  protected function normalizeFilterDate($value, $end_of_day = FALSE) {
    if ($value === NULL) {
      return NULL;
    }

    $value = trim((string) $value);
    if ($value === '') {
      return NULL;
    }

    // Support multiple date formats including MM/DD/YYYY from datepicker
    $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $value);
      if ($date instanceof \DateTime) {
        $date->setTime($end_of_day ? 23 : 0, $end_of_day ? 59 : 0, $end_of_day ? 59 : 0);
        return $date->format('Y-m-d H:i:s');
      }
    }

    try {
      $date = new \DateTime($value);
      $date->setTime($end_of_day ? 23 : 0, $end_of_day ? 59 : 0, $end_of_day ? 59 : 0);
      return $date->format('Y-m-d H:i:s');
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Batch operation: Export a chunk of entities.
   */
  public static function batchExportChunk(array $entity_ids, array &$context) {
    if (!isset($context['results']['file'])) {
      // Initialize CSV file
      $filename = 'sentinel_samples_export_' . date('Y-m-d_His') . '.csv';
      $filepath = \Drupal::service('file_system')->getTempDirectory() . '/' . $filename;
      $context['results']['file'] = $filepath;
      $context['results']['count'] = 0;
      
      // Open file and write header
      $fp = fopen($filepath, 'w');
      fprintf($fp, "\xEF\xBB\xBF"); // BOM for Excel
      
      // Get all columns from sentinel_sample table
      $columns = static::getExportColumns();
      fputcsv($fp, $columns);
      fclose($fp);
    }

    // Load entities and write to CSV
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    $entities = $storage->loadMultiple($entity_ids);
    
    $fp = fopen($context['results']['file'], 'a');
    
    foreach ($entities as $entity) {
      $row = static::entityToCsvRow($entity);
      fputcsv($fp, $row);
      $context['results']['count']++;
    }
    
    fclose($fp);
    
    $context['message'] = t('Exported @count samples...', ['@count' => $context['results']['count']]);
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success && isset($results['file']) && file_exists($results['file'])) {
      // Store file path in session for download
      $session = \Drupal::service('session');
      $session->set('sentinel_sample_export_file', $results['file']);
      $session->set('sentinel_sample_export_count', $results['count'] ?? 0);
      
      // Redirect to success page with download link
      $url = \Drupal\Core\Url::fromRoute('sentinel_portal.sample_export_csv_success');
      return new \Symfony\Component\HttpFoundation\RedirectResponse($url->toString());
    }
    else {
      \Drupal::messenger()->addError(t('Export failed.'));
      $url = \Drupal\Core\Url::fromRoute('sentinel_portal.admin_sample');
      return new \Symfony\Component\HttpFoundation\RedirectResponse($url->toString());
    }
  }

  /**
   * Show export success page with automatic download.
   */
  public function exportSuccess() {
    $session = \Drupal::service('session');
    $filepath = $session->get('sentinel_sample_export_file');
    $count = $session->get('sentinel_sample_export_count', 0);
    
    if (!$filepath || !file_exists($filepath)) {
      \Drupal::messenger()->addError(t('Export file not found. Please try exporting again.'));
      return $this->redirect('sentinel_portal.admin_sample');
    }
    
    $download_url = \Drupal\Core\Url::fromRoute('sentinel_portal.sample_export_csv_download')->toString();
    
    $build = [
      '#markup' => '<div class="export-success-message">
        <h2>' . $this->t('Data export successful') . '</h2>
        <p>' . $this->t('Your export has been created. @count samples exported.', ['@count' => $count]) . '</p>
        <p>' . $this->t('View/download the file <a href="@url" id="export-download-link">here</a> (will automatically download in <span id="countdown">3</span> seconds.)', [
          '@url' => $download_url,
        ]) . '</p>
      </div>',
      '#attached' => [
        'library' => ['sentinel_portal_entities/export_success'],
        'drupalSettings' => [
          'sentinelSampleExport' => [
            'downloadUrl' => $download_url,
            'countdown' => 3,
          ],
        ],
      ],
    ];
    
    return $build;
  }

  /**
   * Download the exported CSV file.
   */
  public function downloadCsv() {
    $session = \Drupal::service('session');
    $filepath = $session->get('sentinel_sample_export_file');
    $count = $session->get('sentinel_sample_export_count', 0);
    
    if (!$filepath || !file_exists($filepath)) {
      \Drupal::messenger()->addError(t('Export file not found. Please try exporting again.'));
      return $this->redirect('sentinel_portal.admin_sample');
    }
    
    $filename = 'sentinel_samples_export_' . date('Y-m-d_His') . '.csv';
    
    // Stream the file for download
    $response = new StreamedResponse(function() use ($filepath) {
      $fp = fopen($filepath, 'r');
      if ($fp) {
        while (!feof($fp)) {
          echo fread($fp, 8192);
          flush();
        }
        fclose($fp);
        unlink($filepath);
      }
    });
    
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    
    // Clear session
    $session->remove('sentinel_sample_export_file');
    $session->remove('sentinel_sample_export_count');
    
    return $response;
  }

  /**
   * Get all columns to export.
   */
  protected static function getExportColumns() {
    return [
      'pid',
      'vid',
      'pack_reference_number',
      'project_id',
      'installer_name',
      'installer_email',
      'company_name',
      'company_email',
      'company_address1',
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
      'nitrate_result',
      'mob_ratio',
      'created',
      'updated',
      'ucr',
      'installer_company',
      'old_pack_reference_number',
      'duplicate_of',
      'legacy',
      'api_created_by',
      'sentinel_sample_hold_state_target_id',
      'sentinel_company_address_target_id',
      'sentinel_sample_address_target_id',
    ];
  }

  /**
   * Convert entity to CSV row.
   */
  protected static function entityToCsvRow($entity) {
    $columns = static::getExportColumns();
    $row = [];
    
    foreach ($columns as $column) {
      $value = '';
      
      // Try field access first
      if ($entity->hasField($column)) {
        $field_item = $entity->get($column);
        if (!$field_item->isEmpty()) {
          $field_definition = $field_item->getFieldDefinition();
          $field_type = $field_definition ? $field_definition->getType() : '';
          
          // Handle different field types
          if ($field_type === 'datetime') {
            $value = $field_item->value ?? '';
          }
          elseif ($field_type === 'boolean') {
            $value = $field_item->value ? '1' : '0';
          }
          else {
            $value = $field_item->value ?? '';
          }
        }
      }
      else {
        // Try direct property access (for base table columns)
        if (property_exists($entity, $column)) {
          $value = $entity->$column ?? '';
        }
        // Try getter method
        elseif (method_exists($entity, 'get' . ucfirst($column))) {
          $method = 'get' . ucfirst($column);
          $value = $entity->$method();
        }
      }
      
      // Normalize text (replace newlines, semicolons)
      if (is_string($value)) {
        $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
        $value = str_replace(';', ', ', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));
      }
      elseif (is_null($value)) {
        $value = '';
      }
      else {
        $value = (string) $value;
      }
      
      $row[] = $value;
    }
    
    return $row;
  }

}

