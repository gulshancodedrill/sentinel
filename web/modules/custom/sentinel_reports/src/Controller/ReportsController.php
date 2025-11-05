<?php

namespace Drupal\sentinel_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for reports export and download.
 */
class ReportsController extends ControllerBase {

  /**
   * Export stats to CSV using batch processing.
   *
   * @param string $key_name
   *   The cache key name.
   *
   * @return array
   *   A render array or redirect response.
   */
  public function exportStats($key_name) {
    $cache = \Drupal::cache()->get('sentinel_reports_' . $key_name);
    
    if (!$cache) {
      throw new NotFoundHttpException();
    }

    $data = $cache->data;
    
    // Get headers.
    $headers = _sentinel_reports_get_csv_export_headers();

    // Create temporary directory and file.
    $temp_dir = \Drupal::service('file_system')->getTempDirectory() . '/' . $key_name;
    \Drupal::service('file_system')->prepareDirectory($temp_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
    $file_path = $temp_dir . '/' . $key_name . '.csv';

    // Build batch with single operation.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Exporting data...'))
      ->setInitMessage($this->t('Preparing CSV export...'))
      ->setProgressMessage($this->t('Exporting samples...'))
      ->setErrorMessage($this->t('An error occurred during export.'))
      ->setFinishCallback(['\Drupal\sentinel_reports\Controller\ReportsController', 'batchFinish'])
      ->addOperation(['\Drupal\sentinel_reports\Controller\ReportsController', 'buildCsvBatch'], 
        [$data, $file_path, $headers]);

    batch_set($batch_builder->toArray());
    return batch_process(Url::fromRoute('sentinel_reports.download_stats', ['key_name' => $key_name]));
  }

  /**
   * Batch operation to build CSV file.
   */
  public static function buildCsvBatch($data, $file_path, $headers, &$context) {
    $database = \Drupal::database();
    
    // Initialize on first run.
    if (!isset($context['sandbox']['progress'])) {
      // Write headers.
      $output = fopen($file_path, 'w');
      if ($output === FALSE) {
        \Drupal::logger('sentinel_reports')->error('Unable to open file for writing: @filePath', ['@filePath' => $file_path]);
        $context['finished'] = 1;
        return;
      }
      fputcsv($output, $headers, ',', '"');
      fclose($output);
      
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_offset'] = 0;
      $context['sandbox']['batch_size'] = 500;
    }

    // Build query with filters from cache data.
    $query = $database->select('sentinel_sample', 'ss');
    foreach ($headers as $header) {
      $query->addField('ss', $header);
    }
    
    // Apply filters.
    $current_user = \Drupal::currentUser();
    if (!$current_user->hasPermission('sentinel view all sentinel_sample') && !empty($data['cids'])) {
      $query->leftJoin('sentinel_client', 'sc', 'sc.ucr = ss.ucr');
      $query->condition('sc.cid', $data['cids'], 'IN');
    }
    
    if (!empty($data['date_from']) && !empty($data['date_to'])) {
      $date_from = $data['date_from'] . ' 00:00:00';
      $date_to = $data['date_to'] . ' 23:59:59';
      $query->condition('ss.date_reported', [$date_from, $date_to], 'BETWEEN');
    }
    
    if (!empty($data['location'])) {
      $query->condition('ss.town_city', '%' . $database->escapeLike($data['location']) . '%', 'LIKE');
    }
    
    if (!empty($data['installer_name'])) {
      $query->condition('ss.installer_name', '%' . $database->escapeLike($data['installer_name']) . '%', 'LIKE');
    }
    
    // Apply pass/fail filter if specified.
    if (isset($data['pass_fail_filter']) && $data['pass_fail_filter'] !== NULL) {
      $query->condition('ss.pass_fail', $data['pass_fail_filter']);
    }
    
    $query->range($context['sandbox']['current_offset'], $context['sandbox']['batch_size']);
    $query->orderBy('ss.pid', 'ASC');
    
    $results = $query->execute();

    // Append to CSV file.
    $output = fopen($file_path, 'a');
    if ($output === FALSE) {
      \Drupal::logger('sentinel_reports')->error('Unable to open file for appending: @filePath', ['@filePath' => $file_path]);
      $context['finished'] = 1;
      return;
    }
    
    $row_count = 0;
    foreach ($results as $row) {
      $row_array = (array) $row;
      fputcsv($output, $row_array, ',', '"');
      $row_count++;
    }
    fclose($output);

    $context['sandbox']['progress'] += $row_count;
    $context['sandbox']['current_offset'] += $context['sandbox']['batch_size'];
    
    // Check if we're done.
    if ($row_count < $context['sandbox']['batch_size']) {
      $context['finished'] = 1;
      $context['message'] = t('Exported @count records', ['@count' => $context['sandbox']['progress']]);
    }
    else {
      $context['finished'] = 0;
      $context['message'] = t('Exported @count records...', ['@count' => $context['sandbox']['progress']]);
    }
  }

  /**
   * Batch finish callback.
   */
  public static function batchFinish($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('CSV export completed successfully. Your download will start shortly.'));
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during CSV export.'));
    }
  }

  /**
   * Download CSV file.
   *
   * @param string $key_name
   *   The cache key name.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   File download response.
   */
  public function downloadCsv($key_name) {
    $cache = \Drupal::cache()->get('sentinel_reports_' . $key_name);
    
    if (!$cache) {
      throw new NotFoundHttpException();
    }

    $data = $cache->data;
    $filename = $data['category'] . '-' . $data['date_from'] . '-' . $data['date_to'] . '.csv';
    $temp_dir = \Drupal::service('file_system')->getTempDirectory() . '/' . $key_name;
    $file_path = $temp_dir . '/' . $key_name . '.csv';

    if (!file_exists($file_path)) {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    
    return $response;
  }

}
