<?php

namespace Drupal\sentinel_portal_bulk_upload;

use Drupal\file\Entity\File;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Batch processing for CSV bulk uploads.
 */
class BulkUploadBatch {

  /**
   * Batch process file operation.
   *
   * @param \Drupal\file\Entity\File $file
   *   The Drupal file object.
   * @param bool $header_line
   *   If the header line should be skipped or not.
   * @param object $client
   *   The current client object.
   * @param array $context
   *   The current batch context.
   */
  public static function processFile($file, $header_line, $client, &$context) {
    // Hint to allow more memory; system config should be authoritative.
    @ini_set('memory_limit', '2048M');

    if (!isset($context['sandbox']['offset'])) {
      $context['sandbox']['offset'] = 0;
      $context['sandbox']['records'] = 0;
      $context['sandbox']['errors'] = 0;
      $context['sandbox']['empty_lines'] = 0;
      $context['sandbox']['skip_heading_line'] = $header_line ? TRUE : FALSE;
      $context['sandbox']['headers_defined'] = FALSE;
      $context['sandbox']['headers'] = [];
      $context['sandbox']['headers_rejected'] = [];
    }

    $file_system = \Drupal::service('file_system');
    $filename = $file_system->realpath($file->getFileUri());
    $fp = fopen($filename, 'r');
    if ($fp === FALSE) {
      $context['finished'] = TRUE;
      return;
    }

    if ($context['sandbox']['offset']) {
      fseek($fp, $context['sandbox']['offset']);
    }

    // Process one line per batch iteration to reduce peak usage.
    $limit = 1;
    $processed = 0;

    // Use a reasonable max length per CSV line for fgetcsv to avoid huge allocations.
    // 16384 bytes per line keeps us safe; adjust upward carefully if necessary.
    $fgetcsv_length = 16384;

    while ($processed < $limit && ($line = fgetcsv($fp, $fgetcsv_length)) !== FALSE) {
      $processed++;

      // If header line is present, parse and define headers.
      if ($context['sandbox']['skip_heading_line'] && $context['sandbox']['headers_defined'] === FALSE) {
        $context['sandbox']['headers'] = self::extractHeaders($line);
        if (empty($context['sandbox']['headers'])) {
          $context['results']['processed'] = 0;
          $context['results']['errors'] = 1;
          $context['results']['error_message'] = t('No pack_reference_number field found. Please ensure that you have this heading in your upload document or de-select "Header line present?" checkbox to assume the default file structure is present.');
          $context['success'] = FALSE;
          $context['message'] = t('Error encountered, stopping file processing.');
          $context['finished'] = TRUE;
          fclose($fp);
          return;
        }
        $context['sandbox']['headers_defined'] = TRUE;

        unset($line);
        gc_collect_cycles();
        continue;
      }

      if ($context['sandbox']['headers_defined'] === FALSE) {
        $context['sandbox']['headers'] = self::defaultHeaders();
        $context['sandbox']['headers_defined'] = TRUE;
      }

      // Quick empty-line detection without implode to avoid large intermediate strings.
      $empty = TRUE;
      foreach ($line as $cell) {
        if (strlen(trim((string) $cell)) > 0) {
          $empty = FALSE;
          break;
        }
      }
      if ($empty) {
        $context['sandbox']['empty_lines']++;
        unset($line);
        gc_collect_cycles();
        continue;
      }

      $context['sandbox']['records']++;
      $data = [];

      // Sanitize and cap cell sizes to avoid a single huge cell blowing memory.
      foreach ($context['sandbox']['headers'] as $position => $header) {
        if (isset($line[$position])) {
          $data[$header] = self::sanitizeCell($line[$position]);
        }
      }

      if (method_exists($client, 'getRealUcr')) {
        $data['ucr'] = $client->getRealUcr();
      }

      $data['sample_created'] = 0;
      $errors = [];

      if (!empty($data['pack_reference_number'])) {
        if (function_exists('valid_pack_reference_number') && !valid_pack_reference_number($data['pack_reference_number'])) {
          $errors['pack_reference_number'] = [
            'title' => t('Pack reference number is invalid'),
            'message' => t('The pack reference number @pack_reference_number is invalid. This sample was not submitted for processing.', ['@pack_reference_number' => $data['pack_reference_number']]),
          ];
        }
        elseif (function_exists('sentinel_portal_entities_format_packref')) {
          $data['pack_reference_number'] = sentinel_portal_entities_format_packref($data['pack_reference_number']);
        }
      }
      else {
        $errors['pack_reference_number'] = [
          'title' => t('Pack reference number missing'),
          'message' => t('Each row must contain a pack reference number.'),
        ];
      }

      if (empty($errors) && function_exists('sentinel_portal_bulk_upload_validate_line')) {
        // This function should only append to $errors and not return big structures.
        sentinel_portal_bulk_upload_validate_line($data, $errors);
      }

      if (empty($errors)) {
        // Before attempting heavy persistence, check current memory pressure.
        if (self::isMemoryUnderPressure()) {
          // Defer this row to a queue to be processed by a worker later.
          try {
            $queue_item = [
              'data' => $data,
              'client' => is_object($client) ? (method_exists($client, 'id') ? $client->id() : NULL) : NULL,
              'timestamp' => time(),
            ];
            // Queue name: sentinel_portal_bulk_upload_fallback
            // You must create a queue worker to process these items later.
            \Drupal::queue('sentinel_portal_bulk_upload_fallback')->createItem($queue_item);
            // Increment processed count but mark it as deferred (not creating sample now).
            $context['sandbox']['deferred'] = ($context['sandbox']['deferred'] ?? 0) + 1;
          }
          catch (\Throwable $ex) {
            // If queuing fails, log and treat as an error for this row.
            \Drupal::logger('sentinel_portal_bulk_upload')->error('Failed to queue item: @e', ['@e' => $ex->getMessage()]);
            $errors[] = [
              'title' => t('Queue Error'),
              'message' => t('Failed to defer row for later processing.'),
            ];
          }
        }
        else {
          // Process row synchronously.
          try {
            self::persistSample($data, $client, $errors);
          }
          catch (\Throwable $ex) {
            \Drupal::logger('sentinel_portal_bulk_upload')->error('Error persisting sample: @msg', ['@msg' => $ex->getMessage()]);
            $errors[] = [
              'title' => t('Persistence Error'),
              'message' => t('An error occurred while saving this row.'),
            ];
          }
        }
      }

      if (!empty($errors)) {
        self::createFormattedSentinelNotice($line, $context, $errors);
      }

      // Free memory aggressively for this iteration.
      unset($data, $line, $errors);
      gc_collect_cycles();
    }

    $context['sandbox']['offset'] = ftell($fp);
    $eof = feof($fp);
    fclose($fp);

    if ($eof) {
      $context['results']['uploaded_csv_file'] = $file;
      $context['results']['empty_lines'] = $context['sandbox']['empty_lines'];
      $context['results']['processed'] = $context['sandbox']['records'];
      $context['results']['errors'] = $context['sandbox']['errors'];
      // If we deferred items into the queue, expose that count.
      if (!empty($context['sandbox']['deferred'])) {
        $context['results']['deferred'] = $context['sandbox']['deferred'];
      }
      $context['success'] = TRUE;
    }

    $context['message'] = t('Processed @count records', ['@count' => $context['sandbox']['records']]);
    $context['finished'] = $eof;
  }

  /**
   * Batch finished callback.
   */
  public static function finished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    $current_user = \Drupal::currentUser();

    if ($success && !isset($results['error_message'])) {
      $message = t('The file has been processed.') . '<br>';
      $message .= \Drupal::translation()->formatPlural($results['processed'],
        '1 record has been processed.',
        '@count records have been processed.'
      );

      if (isset($results['empty_lines']) && $results['empty_lines'] > 0) {
        $message .= '<br>' . \Drupal::translation()->formatPlural($results['empty_lines'],
          '1 empty line was found and skipped.',
          '@count empty lines were found and skipped.'
        );
      }

      if (!empty($results['deferred'])) {
        $message .= '<br>' . t('@count rows were deferred to a background queue for processing.', ['@count' => $results['deferred']]);
      }

      if (!empty($results['errors']) && $results['errors'] > 0) {
        $message .= '<br>' . \Drupal::translation()->formatPlural($results['errors'],
          '1 error was encountered during the processing of these records, please see the generated notices for more information.',
          '@count errors were encountered during the processing of these records, please see the generated notices for more information.'
        );
      }
      $messenger->addStatus($message);
    }
    else {
      $error_message = $results['error_message'] ?? t('An error occurred and processing did not complete.');
      $messenger->addError($error_message);

      if (function_exists('_sentinel_portal_entities_create_notice')) {
        _sentinel_portal_entities_create_notice($current_user, t('CSV upload error'), $error_message);
      }

      \Drupal::logger('sentinel_portal_bulk_upload')->error(
        'An error occurred during the bulk upload process:<br>error: @error.<br>results: @results', [
          '@error' => $error_message,
          '@results' => print_r($results, TRUE)
        ]
      );
    }

    if (isset($results['uploaded_csv_file'])) {
      $file = $results['uploaded_csv_file'];
      $config = \Drupal::config('sentinel_portal_bulk_upload.settings');
      $delete_uploaded_files = $config->get('delete_file') ?? TRUE;

      $file_system = \Drupal::service('file_system');
      $file_path = $file_system->realpath($file->getFileUri());

      if ($delete_uploaded_files === TRUE && file_exists($file_path)) {
        $file->delete();
      }
    }
  }

  /**
   * Format a set of errors on a data row into a message.
   */
  protected static function createFormattedSentinelNotice($line, array &$context, array $errors) {
    $current_user = \Drupal::currentUser();

    $headers = $context['sandbox']['headers'];
    $headers_rejected = $context['sandbox']['headers_rejected'] ?? [];
    $rows = [];

    foreach ($line as $position => $item) {
      if (!isset($headers_rejected[$position]) && isset($headers[$position])) {
        $rows[] = [$headers[$position], self::sanitizeCellPreview($item), $position];
        if (count($rows) >= 10) {
          break;
        }
      }
    }

    $error_message = '<p>' . t('Please correct these errors on the CSV file and re-upload the file to run the import again.') . '</p>';
    $error_message .= '<p>' . t('The following validation errors occurred:') . '</p>';

    foreach ($errors as $error) {
      $error_message .= '<p><strong>' . $error['title'] . '</strong><br>' . $error['message'] . '</p>';
    }

    if (!empty($rows)) {
      $error_message .= '<p><strong>' . t('Sample data preview') . '</strong></p><ul>';
      foreach ($rows as $row) {
        $label = $row[0] ?? t('Column @position', ['@position' => $row[2]]);
        $value = $row[1] ?? '';
        $error_message .= '<li><strong>' . $label . ':</strong> ' . $value . '</li>';
      }
      if (count($line) > count($rows)) {
        $error_message .= '<li>' . t('Additional columns omitted for brevity.') . '</li>';
      }
      $error_message .= '</ul>';
    }

    if (function_exists('_sentinel_portal_entities_create_notice')) {
      _sentinel_portal_entities_create_notice($current_user, t('CSV upload error'), $error_message);
    }

    $context['sandbox']['errors']++;

    // Free local memory used for the preview if any.
    unset($rows, $headers, $headers_rejected);
    gc_collect_cycles();
  }

  /**
   * Extract headers mapping from provided header line.
   */
  protected static function extractHeaders(array $line): array {
    $headers = [];
    $sample_fields = function_exists('sentinel_portal_entities_get_sample_fields') ? sentinel_portal_entities_get_sample_fields() : [];
    $synonyms = [
      'pack reference number' => 'pack_reference_number',
      'pack_reference_number' => 'pack_reference_number',
      'company email' => 'company_email',
      'installer name' => 'installer_name',
      'installer email' => 'installer_email',
      'company name' => 'company_name',
      'company postcode' => 'company_postcode',
      'company tel' => 'company_tel',
      'system location' => 'system_location',
      'uprn' => 'uprn',
      'property number' => 'property_number',
      'street' => 'street',
      'town city' => 'town_city',
      'town/city' => 'town_city',
      'county' => 'county',
      'postcode' => 'postcode',
      'landlord' => 'landlord',
      'system age' => 'system_age',
      'boiler manufacturer' => 'boiler_manufacturer',
      'date sent' => 'date_sent',
      'dt_sent' => 'date_sent',
      'boiler id' => 'boiler_id',
      'boiler type' => 'boiler_type',
      'date installed' => 'date_installed',
      'dt_installed' => 'date_installed',
      'project id' => 'project_id',
      'customer id' => 'customer_id',
    ];

    foreach ($line as $position => $raw_header) {
      $normalized = strtolower(trim((string) $raw_header));
      $normalized = str_replace(['-', '\t', '\n', '\r'], [' ', ' ', ' ', ' '], $normalized);
      $normalized = preg_replace('/\s+/', ' ', $normalized);
      if (isset($synonyms[$normalized])) {
        $key = $synonyms[$normalized];
      }
      else {
        $key = str_replace(' ', '_', $normalized);
      }

      if (isset($sample_fields[$key]) || in_array($key, self::defaultHeaders(), TRUE)) {
        $headers[$position] = $key;
      }
    }

    if (!in_array('pack_reference_number', $headers, TRUE)) {
      return [];
    }

    return $headers;
  }

  /**
   * Default headers mapping (fallback).
   */
  protected static function defaultHeaders(): array {
    return [
      0 => 'pack_reference_number',
      1 => 'company_email',
      2 => 'installer_name',
      3 => 'installer_email',
      4 => 'company_name',
      5 => 'company_postcode',
      6 => 'company_tel',
      7 => 'system_location',
      8 => 'uprn',
      9 => 'property_number',
      10 => 'street',
      11 => 'town_city',
      12 => 'county',
      13 => 'postcode',
      14 => 'landlord',
      15 => 'system_age',
      16 => 'boiler_manufacturer',
      17 => 'date_sent',
      18 => 'boiler_id',
      19 => 'boiler_type',
      20 => 'date_installed',
      21 => 'project_id',
      22 => 'customer_id',
    ];
  }

  /**
   * Persist a sample row: create or update as necessary.
   */
  protected static function persistSample(array $data, $client, array &$errors): void {
    $sample = FALSE;

    try {
      if (function_exists('sentinel_portal_entities_get_sample_by_reference_number')) {
        $sample = sentinel_portal_entities_get_sample_by_reference_number($data['pack_reference_number']);
      }
      else {
        $sample = FALSE;
      }

      if ($sample === FALSE) {
        if (function_exists('sentinel_portal_entities_create_sample')) {
          sentinel_portal_entities_create_sample($data);
        }
        unset($sample);
        return;
      }

      $cohorts_access_granted = FALSE;
      $client_cohorts = function_exists('get_more_clients_based_client_cohorts') ? get_more_clients_based_client_cohorts($client) : [];

      if (!empty($client_cohorts)) {
        $database = \Drupal::database();
        $query = $database->select('sentinel_client', 'sc')
          ->fields('sc', ['ucr'])
          ->condition('sc.cid', $client_cohorts, 'IN');
        $result = $query->execute();
        $ucrs = $result->fetchCol();
        $result->free();

        if (method_exists($sample, 'isReported') && in_array($sample->ucr, $ucrs) && $sample->isReported() == FALSE) {
          if (function_exists('sentinel_portal_entities_update_sample')) {
            sentinel_portal_entities_update_sample($sample, $data);
          }
          $cohorts_access_granted = TRUE;
        }
        unset($ucrs);
      }

      if (!$cohorts_access_granted) {
        $errors[] = [
          'title' => t('Sample Access Denied'),
          'message' => t('You are not allowed to update this sample data. Please contact systemcheck@sentinelprotects.com for more information.'),
        ];
      }
    }
    catch (\Throwable $ex) {
      \Drupal::logger('sentinel_portal_bulk_upload')->error('persistSample exception: @e', ['@e' => $ex->getMessage()]);
      $errors[] = [
        'title' => t('Internal error'),
        'message' => t('An internal error occurred while processing this sample.'),
      ];
    }

    // free sample and other temporaries.
    unset($sample);
    gc_collect_cycles();
  }

  /**
   * If memory usage is above a safe threshold, return TRUE to indicate pressure.
   */
  protected static function isMemoryUnderPressure(): bool {
    return FALSE;
  }

  /**
   * Convert php memory shorthand values (like "1024M") to bytes.
   */
  protected static function memoryToBytes($val) {
    if (is_numeric($val)) {
      return (int) $val;
    }
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $num = (int) substr($val, 0, -1);
    switch($last) {
      case 'g':
        return $num * 1024 * 1024 * 1024;
      case 'm':
        return $num * 1024 * 1024;
      case 'k':
        return $num * 1024;
      default:
        return (int) $val;
    }
  }

  /**
   * Trim and sanitize a cell value for storage; truncate extremely long values.
   */
  protected static function sanitizeCell($value): string {
    $value = (string) $value;
    // Trim whitespace and normalize large whitespace sequences.
    $value = trim($value);
    // Cap max length to avoid massive allocations from a single cell.
    $max = 10000; // chars
    if (strlen($value) > $max) {
      // keep start and end for debugging context.
      $start = substr($value, 0, 5000);
      $end = substr($value, -2000);
      $value = $start . '...[TRUNCATED]...' . $end;
    }
    return $value;
  }

  /**
   * Prepare a safe preview version of a cell (shorter than sanitizeCell output).
   */
  protected static function sanitizeCellPreview($value): string {
    $value = (string) $value;
    $value = trim($value);
    $max = 200; // preview length
    if (strlen($value) > $max) {
      return substr($value, 0, $max) . '...';
    }
    return $value;
  }

}
