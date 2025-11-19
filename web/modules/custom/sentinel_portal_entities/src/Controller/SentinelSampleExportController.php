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
    $filter_keys = [
      'pack_id',
      'pass_fail',
      'pack_type',
      'client_email',
      'reported',
      'booked',
      'system_postcode',
      'system_address',
      'date_reported_from',
      'date_reported_to',
      'date_booked_from',
      'date_booked_to',
    ];
    
    $filters = [];
    foreach ($filter_keys as $key) {
      if (isset($query_params[$key])) {
        $value = trim((string) $query_params[$key]);
        if ($value !== '') {
          $filters[$key] = $value;
        }
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
      ->orderBy('ss.pid', 'ASC');

    // Apply same filters as list builder
    if (!empty($filters['pack_id'])) {
      $query->condition('ss.pack_reference_number', '%' . $this->database->escapeLike($filters['pack_id']) . '%', 'LIKE');
    }
    
    if (isset($filters['pass_fail']) && $filters['pass_fail'] !== '') {
      $query->condition('ss.pass_fail', $filters['pass_fail'], '=');
    }
    
    if (!empty($filters['pack_type'])) {
      PackTypeFilter::applyFilterConditions($query, $this->database, $filters['pack_type']);
    }
    if (!empty($filters['pack_type'])) {
      PackTypeFilter::applyFilterConditions($query, $this->database, $filters['pack_type']);
    }
    
    if (isset($filters['reported']) && $filters['reported'] !== '') {
      if ($filters['reported'] === 'reported') {
        $query->isNotNull('ss.date_reported');
      }
      elseif ($filters['reported'] === 'not_reported') {
        $query->isNull('ss.date_reported');
      }
    }
    
    if (isset($filters['booked']) && $filters['booked'] !== '') {
      if ($filters['booked'] === '5_plus_days_booked') {
        $threshold = (new \DateTime('-5 days'))->format('Y-m-d H:i:s');
        $query->isNotNull('ss.date_booked');
        $query->condition('ss.date_booked', $threshold, '<=');
      }
    }
    
    if (!empty($filters['system_postcode'])) {
      $query->condition('ss.postcode', '%' . $this->database->escapeLike($filters['system_postcode']) . '%', 'LIKE');
    }
    
    if (!empty($filters['system_address'])) {
      $or_group = $query->orConditionGroup()
        ->condition('ss.street', '%' . $this->database->escapeLike($filters['system_address']) . '%', 'LIKE')
        ->condition('ss.county', '%' . $this->database->escapeLike($filters['system_address']) . '%', 'LIKE')
        ->condition('ss.town_city', '%' . $this->database->escapeLike($filters['system_address']) . '%', 'LIKE')
        ->condition('ss.system_location', '%' . $this->database->escapeLike($filters['system_address']) . '%', 'LIKE');
      $query->condition($or_group);
    }
    
    // Date filters
    if (!empty($filters['date_reported_from'])) {
      if ($from = $this->normalizeFilterDate($filters['date_reported_from'])) {
        $query->condition('ss.date_reported', $from, '>=');
      }
    }
    if (!empty($filters['date_reported_to'])) {
      if ($to = $this->normalizeFilterDate($filters['date_reported_to'], TRUE)) {
        $query->condition('ss.date_reported', $to, '<=');
      }
    }
    if (!empty($filters['date_booked_from'])) {
      if ($from = $this->normalizeFilterDate($filters['date_booked_from'])) {
        $query->condition('ss.date_booked', $from, '>=');
      }
    }
    if (!empty($filters['date_booked_to'])) {
      if ($to = $this->normalizeFilterDate($filters['date_booked_to'], TRUE)) {
        $query->condition('ss.date_booked', $to, '<=');
      }
    }
    
    // Client email filter - join with sentinel_client table
    if (!empty($filters['client_email'])) {
      $query->join('sentinel_client', 'sc', 'ss.ucr = sc.ucr');
      $query->condition('sc.email', '%' . $this->database->escapeLike($filters['client_email']) . '%', 'LIKE');
    }

    return $query->execute()->fetchCol();
  }

  /**
   * Normalize filter date value.
   */
  protected function normalizeFilterDate($value, $end_of_day = FALSE) {
    if (empty($value)) {
      return NULL;
    }
    
    try {
      $date = new \DateTime($value);
      if ($end_of_day) {
        $date->setTime(23, 59, 59);
      } else {
        $date->setTime(0, 0, 0);
      }
      return $date->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
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

