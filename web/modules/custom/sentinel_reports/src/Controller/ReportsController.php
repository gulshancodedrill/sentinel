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
    $pids = explode('+', $data['pids']);
    $pids_chunks = array_chunk($pids, 500);
    
    // Get headers from database.
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 'ss');
    $headers = _sentinel_reports_get_csv_export_headers();
    foreach ($headers as $header) {
      $query->addField('ss', $header);
    }
    $query->range(0, 1);
    $result = $query->execute()->fetchObject();
    $header_keys = array_keys((array) $result);

    // Create temporary directory and file.
    $temp_dir = \Drupal::service('file_system')->getTempDirectory() . '/' . $key_name;
    \Drupal::service('file_system')->prepareDirectory($temp_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
    $file_path = $temp_dir . '/' . $key_name . '.csv';

    // Build batch.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Exporting data...'))
      ->setInitMessage($this->t('CSV file builder is starting...'))
      ->setProgressMessage($this->t('Processed @current out of @total.'))
      ->setErrorMessage($this->t('An error occurred during export.'))
      ->setFinishCallback([$this, 'batchFinish']);

    foreach ($pids_chunks as $chunk) {
      $batch_builder->addOperation([$this, 'buildCsvBatch'], [$chunk, $file_path, $header_keys]);
    }

    batch_set($batch_builder->toArray());
    return batch_process(Url::fromRoute('sentinel_reports.download_stats', ['key_name' => $key_name]));
  }

  /**
   * Batch operation to build CSV file.
   */
  public function buildCsvBatch($pids, $file_path, $headers, &$context) {
    $output = fopen($file_path, 'a');
    
    if ($output === FALSE) {
      \Drupal::logger('sentinel_reports')->error('Unable to open file whilst generating report @filePath.', ['@filePath' => $file_path]);
      return FALSE;
    }

    if (empty($context['sandbox'])) {
      // Write headers on first run.
      $output = fopen($file_path, 'w');
      if ($output === FALSE) {
        \Drupal::logger('sentinel_reports')->error('Unable to open file whilst generating report @filePath.', ['@filePath' => $file_path]);
        return FALSE;
      }
      fputcsv($output, $headers, ',', '"');
      fclose($output);
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($pids);
    }

    // Query samples.
    $database = \Drupal::database();
    $query = $database->select('sentinel_sample', 'ss');
    $headers = _sentinel_reports_get_csv_export_headers();
    foreach ($headers as $header) {
      $query->addField('ss', $header);
    }
    $query->condition('ss.pid', $pids, 'IN');
    $results = $query->execute();

    $output = fopen($file_path, 'a');
    foreach ($results as $row) {
      $row_array = (array) $row;
      fputcsv($output, $row_array, ',', '"');
    }
    fclose($output);

    $context['sandbox']['progress']++;
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Batch finish callback.
   */
  public function batchFinish($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage($this->t('CSV export completed successfully.'));
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
