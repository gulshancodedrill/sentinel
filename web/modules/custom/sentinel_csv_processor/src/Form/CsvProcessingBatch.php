<?php

namespace Drupal\sentinel_csv_processor\Form;

use Drupal\sentinel_csv_processor\Entity\LabData;

/**
 * Batch processing for CSV files.
 */
class CsvProcessingBatch {

  /**
   * Creates batch operations for processing CSV files.
   *
   * @param array $file_ids
   *   Array of lab_data entity IDs to process.
   *
   * @return array
   *   Batch operations array.
   */
  public static function createBatch(array $file_ids) {
    $operations = [];
    
    foreach ($file_ids as $file_id) {
      $operations[] = [
        [self::class, 'processFile'],
        [$file_id],
      ];
    }

    $batch = [
      'title' => t('Processing CSV Files'),
      'operations' => $operations,
      'finished' => [self::class, 'batchFinished'],
      'progress_message' => t('Processed @current out of @total files.'),
    ];

    return $batch;
  }

  /**
   * Processes a single CSV file.
   *
   * @param int $file_id
   *   The lab_data entity ID.
   * @param array $context
   *   Batch context.
   */
  public static function processFile($file_id, array &$context) {
    $storage = \Drupal::entityTypeManager()->getStorage('lab_data');
    $lab_data = $storage->load($file_id);

    if (!$lab_data) {
      $context['results']['errors'][] = t('Lab data entity @id not found.', ['@id' => $file_id]);
      return;
    }

    // Set status to processing when batch starts.
    $lab_data->set('status', 'processing');
    $lab_data->save();
    
    \Drupal::logger('sentinel_csv_processor')->info('Batch processing started for file @filename. Status set to processing.', [
      '@filename' => $lab_data->get('filename')->value,
    ]);

    $filename = $lab_data->get('filename')->value;
    $file_uri = $lab_data->get('path')->value;

    // Find file by URI.
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $files = $file_storage->loadByProperties(['uri' => $file_uri]);
    $file = !empty($files) ? reset($files) : NULL;

    if (!$file) {
      $context['results']['errors'][] = t('File not found for @filename.', ['@filename' => $filename]);
      return;
    }

    $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    if (!$file_path || !file_exists($file_path)) {
      $context['results']['errors'][] = t('File path not accessible for @filename.', ['@filename' => $filename]);
      return;
    }

    // Read and process CSV.
    $csv_data = self::readCsvFile($file_path);
    if (empty($csv_data['rows'])) {
      $context['results']['errors'][] = t('No data found in @filename.', ['@filename' => $filename]);
      return;
    }

    // Log CSV structure.
    \Drupal::logger('sentinel_csv_processor')->info('Processing file @filename: @rows rows found', [
      '@filename' => $filename,
      '@rows' => count($csv_data['rows']),
    ]);

    // Group rows by Site (pack reference number).
    $sites_data = self::groupRowsBySite($csv_data['headers'], $csv_data['rows']);

    \Drupal::logger('sentinel_csv_processor')->info('Grouped into @sites unique sites', [
      '@sites' => count($sites_data),
    ]);

    // Process each site (one API call per site).
    $processed_count = 0;
    $error_count = 0;

    foreach ($sites_data as $site => $site_rows) {
      \Drupal::logger('sentinel_csv_processor')->info('Processing site @site with @rows test results', [
        '@site' => $site,
        '@rows' => count($site_rows),
      ]);
      try {
        $result = self::processSiteData($site, $site_rows);
        
        if ($result['success']) {
          $processed_count++;
        }
        else {
          $error_count++;
          $context['results']['errors'][] = t('Site @site in @filename: @error', [
            '@site' => $site,
            '@filename' => $filename,
            '@error' => $result['error'],
          ]);
        }
      }
      catch (\Exception $e) {
        $error_count++;
        $context['results']['errors'][] = t('Site @site in @filename: @error', [
          '@site' => $site,
          '@filename' => $filename,
          '@error' => $e->getMessage(),
        ]);
      }
    }

    // Update processed timestamp and status.
    $lab_data->set('processed', \Drupal::time()->getRequestTime());
    
    if ($error_count === 0 && $processed_count > 0) {
      // Normal success case - set status to success regardless of pending UCR.
      $lab_data->set('status', 'success');
      \Drupal::logger('sentinel_csv_processor')->info('Batch completed successfully. Status set to success for file @filename.', [
        '@filename' => $filename,
      ]);
    }
    else {
      $lab_data->set('status', 'failed');
      \Drupal::logger('sentinel_csv_processor')->error('Batch failed. Status set to failed for file @filename. Errors: @errors', [
        '@filename' => $filename,
        '@errors' => $error_count,
      ]);
    }
    $lab_data->save();

    $context['results']['processed'][] = [
      'filename' => $filename,
      'rows' => $processed_count,
      'errors' => $error_count,
    ];

    $context['message'] = t('Processed @filename: @processed rows, @errors errors', [
      '@filename' => $filename,
      '@processed' => $processed_count,
      '@errors' => $error_count,
    ]);
  }

  /**
   * Reads CSV file and returns headers and rows.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return array
   *   Array with 'headers' and 'rows' keys.
   */
  protected static function readCsvFile($file_path) {
    $handle = fopen($file_path, 'r');
    if ($handle === FALSE) {
      return ['headers' => [], 'rows' => []];
    }

    // Read header row.
    $headers = fgetcsv($handle);
    if ($headers === FALSE) {
      fclose($handle);
      return ['headers' => [], 'rows' => []];
    }

    // Clean headers.
    $headers = array_map('trim', $headers);

    // Read all data rows and filter out empty rows.
    $rows = [];
    while (($line = fgetcsv($handle)) !== FALSE) {
      $trimmed_line = array_map('trim', $line);
      // Filter out completely empty rows.
      $has_data = FALSE;
      foreach ($trimmed_line as $cell) {
        if (!empty($cell)) {
          $has_data = TRUE;
          break;
        }
      }
      if ($has_data) {
        $rows[] = $trimmed_line;
      }
    }

    fclose($handle);

    return [
      'headers' => $headers,
      'rows' => $rows,
    ];
  }

  /**
   * Groups CSV rows by Site (pack reference number).
   *
   * @param array $headers
   *   CSV headers.
   * @param array $rows
   *   CSV rows.
   *
   * @return array
   *   Array keyed by Site, containing arrays of rows for each site.
   */
  protected static function groupRowsBySite(array $headers, array $rows) {
    // Create header to index mapping.
    $header_map = [];
    foreach ($headers as $index => $header) {
      $normalized_header = strtolower(trim($header));
      $header_map[$normalized_header] = $index;
    }

    $site_index = $header_map['site'] ?? NULL;
    if ($site_index === NULL) {
      return [];
    }

    $sites_data = [];
    foreach ($rows as $row) {
      if (isset($row[$site_index]) && !empty(trim($row[$site_index]))) {
        $site = trim($row[$site_index]);
        if (!isset($sites_data[$site])) {
          $sites_data[$site] = [];
        }
        $sites_data[$site][] = $row;
      }
    }

    return $sites_data;
  }

  /**
   * Processes all rows for a single site and sends to API.
   *
   * @param string $site
   *   The Site (pack reference number).
   * @param array $rows
   *   Array of CSV rows for this site.
   *
   * @return array
   *   Result array with 'success' and optional 'error' keys.
   */
  protected static function processSiteData($site, array $rows) {
    // Get UCR from sentinel_sample entity.
    $ucr = self::getUcrFromPackReference($site);
    $is_pending = FALSE;
    
    if (!$ucr) {
      // Pack reference not found - set ucr to "pending" and installer_email to system email.
      $ucr = 'pending';
      $is_pending = TRUE;
      
      \Drupal::logger('sentinel_csv_processor')->warning('Pack reference @site not found in sentinel_sample. Setting ucr=pending and installer_email to system email.', [
        '@site' => $site,
      ]);
    }

    // Map CSV rows to API fields.
    $api_data = self::mapRowsToApiFields($rows, $site);
    
    // If UCR is pending, set installer_email to system email.
    if ($is_pending) {
      $system_email = \Drupal::config('system.site')->get('mail');
      if ($system_email) {
        $api_data['installer_email'] = $system_email;
        \Drupal::logger('sentinel_csv_processor')->info('Set installer_email to system email: @email', [
          '@email' => $system_email,
        ]);
      }
    }

    // Log mapped data before sending.
    \Drupal::logger('sentinel_csv_processor')->info('Mapped API Data for Site @site: @data', [
      '@site' => $site,
      '@data' => print_r($api_data, TRUE),
    ]);

    // Make POST request to API.
    $result = self::sendToApi($api_data, $ucr);
    
    // Add pending flag to result for status handling.
    $result['is_pending'] = $is_pending;

    return $result;
  }

  /**
   * Gets UCR from sentinel_sample entity by pack_reference_number.
   *
   * @param string $pack_reference_number
   *   The pack reference number (Site value from CSV).
   *
   * @return string|null
   *   The UCR value or NULL if not found.
   */
  protected static function getUcrFromPackReference($pack_reference_number) {
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('pack_reference_number', $pack_reference_number)
      ->range(0, 1);
    
    $entity_ids = $query->execute();
    
    if (empty($entity_ids)) {
      return NULL;
    }

    $sample = $storage->load(reset($entity_ids));
    if (!$sample) {
      return NULL;
    }

    // Get UCR field value.
    if ($sample->hasField('ucr') && !$sample->get('ucr')->isEmpty()) {
      return $sample->get('ucr')->value;
    }

    return NULL;
  }

  /**
   * Maps CSV rows to API fields based on Variable and Sample Point.
   *
   * @param array $rows
   *   Array of CSV rows for a single site.
   * @param string $site
   *   Site value (Pack Reference Number).
   *
   * @return array
   *   Mapped data for API.
   */
  protected static function mapRowsToApiFields(array $rows, $site) {
    $api_data = [];

    // Set pack_reference_number from Site (required by API).
    $api_data['pack_reference_number'] = $site;

    // Extract date fields from CSV rows.
    // Headers: Data Source, Lab Reference, Sample Reference, Site, Sample Point, Date, Test Method, Variable, CAS Number, Value, Unit, Detection Limit, Accreditation, Date Received, Analysis Date
    // Get date_booked from FIRST row (Date Received at index 13)
    $date_received = NULL;
    if (!empty($rows)) {
      $first_row = reset($rows);
      if (isset($first_row[13]) && !empty(trim($first_row[13]))) {
        $date_received = trim($first_row[13]);
      }
    }
    
    // Get date_processed from LAST row (Analysis Date at index 14)
    $analysis_date = NULL;
    if (!empty($rows)) {
      $last_row = end($rows);
      if (isset($last_row[14]) && !empty(trim($last_row[14]))) {
        $analysis_date = trim($last_row[14]);
      }
    }

    // Parse and format date_received as date_booked.
    $formatted_date_booked = self::parseAndFormatDate($date_received);
    if ($formatted_date_booked) {
      $api_data['date_booked'] = $formatted_date_booked;
      \Drupal::logger('sentinel_csv_processor')->info('Extracted date_booked from CSV Date Received: @original -> @formatted', [
        '@original' => $date_received,
        '@formatted' => $formatted_date_booked,
      ]);
    }
    else {
      // Fallback to current date if not found or invalid.
      $current_datetime = \Drupal::time()->getRequestTime();
      $api_data['date_booked'] = (string) date('Y-m-d\TH:i:00', $current_datetime);
      \Drupal::logger('sentinel_csv_processor')->warning('Date Received not found or invalid in CSV for site @site, using current date.', [
        '@site' => $site,
      ]);
    }

    // Parse and format analysis_date as date_processed.
    $formatted_date_processed = self::parseAndFormatDate($analysis_date);
    if ($formatted_date_processed) {
      $api_data['date_processed'] = $formatted_date_processed;
      \Drupal::logger('sentinel_csv_processor')->info('Extracted date_processed from CSV Analysis Date: @original -> @formatted', [
        '@original' => $analysis_date,
        '@formatted' => $formatted_date_processed,
      ]);
    }
    else {
      // Fallback to current date if not found or invalid.
      $current_datetime = \Drupal::time()->getRequestTime();
      $api_data['date_processed'] = (string) date('Y-m-d\TH:i:00', $current_datetime);
      \Drupal::logger('sentinel_csv_processor')->warning('Analysis Date not found or invalid in CSV for site @site, using current date.', [
        '@site' => $site,
      ]);
    }

    // Process each row to extract Variable/Value pairs.
    foreach ($rows as $row) {
      // Get column indices (assuming standard CSV structure).
      // Headers: Data Source, Lab Reference, Sample Reference, Site, Sample Point, Date, Test Method, Variable, CAS Number, Value, Unit, Detection Limit, Accreditation, Date Received, Analysis Date
      $variable = isset($row[7]) ? trim($row[7]) : ''; // Variable column
      $value = isset($row[9]) ? trim($row[9]) : ''; // Value column
      $sample_point = isset($row[4]) ? trim($row[4]) : ''; // Sample Point column (Main or System)

      if (empty($variable) || empty($value)) {
        continue;
      }

      // Remove < symbol and handle detection limits.
      $value = str_replace('<', '', $value);
      $value = trim($value);

      if (empty($value)) {
        continue;
      }

      // Map Variable names to entity fields based on Sample Point.
      $normalized_variable = strtolower($variable);
      $normalized_sample_point = strtolower($sample_point);
      
      // Normalize value for validation (check for null, empty, or "pending").
      $normalized_value = strtolower(trim($value));
      $is_valid_value = ($normalized_value !== '' && $normalized_value !== 'null' && $normalized_value !== 'pending' && $value !== NULL);

      // pH Level (Lab) - can be from Main or System, but typically System.
      if (strpos($normalized_variable, 'ph') !== FALSE && strpos($normalized_variable, 'lab') !== FALSE) {
        if ($is_valid_value) {
          $api_data['ph_result'] = $value;
        }
      }
      // Boron as B
      elseif (strpos($normalized_variable, 'boron') !== FALSE) {
        if ($is_valid_value) {
          $api_data['boron_result'] = $value;
        }
      }
      // Molybdenum as Mo
      elseif (strpos($normalized_variable, 'molybdenum') !== FALSE) {
        if ($is_valid_value) {
          $api_data['molybdenum_result'] = $value;
        }
      }
      // Conductivity - depends on Sample Point (Main vs System)
      elseif (strpos($normalized_variable, 'conductivity') !== FALSE) {
        if ($is_valid_value) {
          if ($normalized_sample_point === 'main') {
            $api_data['mains_cond_result'] = $value;
          }
          elseif ($normalized_sample_point === 'system') {
            $api_data['sys_cond_result'] = $value;
          }
        }
      }
      // Calcium as Ca - depends on Sample Point
      elseif (strpos($normalized_variable, 'calcium') !== FALSE) {
        if ($is_valid_value) {
          if ($normalized_sample_point === 'main') {
            $api_data['mains_calcium_result'] = $value;
          }
          elseif ($normalized_sample_point === 'system') {
            $api_data['sys_calcium_result'] = $value;
          }
        }
      }
      // Iron as Fe
      elseif (strpos($normalized_variable, 'iron') !== FALSE) {
        if ($is_valid_value) {
          $api_data['iron_result'] = $value;
        }
      }
      // Copper as Cu
      elseif (strpos($normalized_variable, 'copper') !== FALSE) {
        if ($is_valid_value) {
          $api_data['copper_result'] = $value;
        }
      }
      // Aluminium as Al
      elseif (strpos($normalized_variable, 'aluminium') !== FALSE || strpos($normalized_variable, 'aluminum') !== FALSE) {
        if ($is_valid_value) {
          $api_data['aluminium_result'] = $value;
        }
      }
      // Appearance
      elseif (strpos($normalized_variable, 'appearance') !== FALSE) {
        if ($is_valid_value) {
          $api_data['appearance_result'] = $value;
        }
      }
      // Nitrate Result
      elseif (strpos($normalized_variable, 'nitrate') !== FALSE) {
        if ($is_valid_value) {
          $api_data['nitrate_result'] = $value;
        }
      }
      // Manganese Result
      elseif (strpos($normalized_variable, 'manganese') !== FALSE) {
        if ($is_valid_value) {
          $api_data['manganese_result'] = $value;
        }
      }
    }

    // Set default values for required fields if not present in CSV.
    // These are required for isReported() to return TRUE.
    if (!isset($api_data['manganese_result'])) {
      // Set a default value if not found in CSV (required field).
      $api_data['manganese_result'] = '0';
    }
    if (!isset($api_data['nitrate_result'])) {
      // Set a default value if not found in CSV (required field).
      $api_data['nitrate_result'] = '0';
    }

    // Set date_reported to current date and time (not in CSV).
    // Format: 'Y-m-d\TH:i:00' (ISO 8601 format accepted by validateDate)
    // This matches the format returned by SentinelSampleValidation::validateDate()
    $current_datetime = \Drupal::time()->getRequestTime();
    $api_data['date_reported'] = (string) date('Y-m-d\TH:i:00', $current_datetime);
    
    \Drupal::logger('sentinel_csv_processor')->info('Date fields set - date_booked: @booked, date_processed: @processed, date_reported: @reported', [
      '@booked' => $api_data['date_booked'] ?? 'NOT SET',
      '@processed' => $api_data['date_processed'] ?? 'NOT SET',
      '@reported' => $api_data['date_reported'],
    ]);

    return $api_data;
  }

  /**
   * Parses a date string from CSV and formats it to API format.
   *
   * Handles 2-digit years (e.g., "3/12/25" -> "03/12/2025").
   *
   * @param string|null $date_string
   *   The date string from CSV (can be in various formats).
   *
   * @return string|null
   *   Formatted date string in 'Y-m-d\TH:i:00' format, or NULL if invalid.
   */
  protected static function parseAndFormatDate($date_string) {
    if (empty($date_string)) {
      return NULL;
    }

    $date_string = trim($date_string);
    $original_string = $date_string;
    
    // Handle 2-digit years in formats like "3/12/25" or "03/12/25"
    // Convert to 4-digit year: if year < 100, assume 2000-2099 range
    // Pattern: day/month/year or day-month-year where year is exactly 2 digits
    // Note: CSV dates are in UK format (day/month/year), not US format
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})(?:\s|$)/', $date_string, $matches)) {
      $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
      $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
      $year_2digit = (int) $matches[3];
      // Convert 2-digit year to 4-digit (00-99 -> 2000-2099)
      $year_4digit = 2000 + $year_2digit;
      // Reconstruct as d/m/Y format for proper parsing
      $date_string = $day . '/' . $month . '/' . $year_4digit;
      \Drupal::logger('sentinel_csv_processor')->info('Converted 2-digit year date: @original -> @converted', [
        '@original' => $original_string,
        '@converted' => $date_string,
      ]);
    }
    // Handle formats like "3-12-25" or "03-12-25"
    elseif (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2})(?:\s|$)/', $date_string, $matches)) {
      $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
      $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
      $year_2digit = (int) $matches[3];
      $year_4digit = 2000 + $year_2digit;
      $date_string = $day . '-' . $month . '-' . $year_4digit;
      \Drupal::logger('sentinel_csv_processor')->info('Converted 2-digit year date: @original -> @converted', [
        '@original' => $original_string,
        '@converted' => $date_string,
      ]);
    }
    
    // Try common date formats that might appear in CSV.
    $formats = [
      'Y-m-d\TH:i:s',      // ISO 8601 with seconds: 2024-01-15T14:30:00
      'Y-m-d\TH:i',        // ISO 8601 without seconds: 2024-01-15T14:30
      'Y-m-d H:i:s',       // Standard datetime: 2024-01-15 14:30:00
      'Y-m-d H:i',         // Standard datetime without seconds: 2024-01-15 14:30
      'Y-m-d',             // Date only: 2024-01-15
      'd/m/Y H:i:s',       // UK format with time: 15/01/2024 14:30:00
      'd/m/Y H:i',         // UK format without seconds: 15/01/2024 14:30
      'd/m/Y',             // UK format date only: 15/01/2024
      'd-m-Y H:i:s',       // UK format with dashes: 15-01-2024 14:30:00
      'd-m-Y H:i',         // UK format with dashes: 15-01-2024 14:30
      'd-m-Y',             // UK format with dashes date only: 15-01-2024
      'Ymd\TH:i:s',        // Compact ISO: 20240115T14:30:00
      'Ymd\TH:i',          // Compact ISO without seconds: 20240115T14:30
      'Ymd',               // Compact date: 20240115
    ];

    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $date_string);
      if ($date !== FALSE) {
        // Format to API expected format: 'Y-m-d\TH:i:00'
        return $date->format('Y-m-d\TH:i:00');
      }
    }

    // If no format matched, try strtotime as fallback (handles many formats including 2-digit years).
    $timestamp = strtotime($date_string);
    if ($timestamp !== FALSE) {
      // Check if strtotime interpreted a 2-digit year incorrectly (before year 2000)
      $parsed_date = getdate($timestamp);
      if ($parsed_date['year'] < 2000 && preg_match('/\b(\d{2})\b/', $date_string, $year_match)) {
        $year_2digit = (int) $year_match[1];
        // If the original had a 2-digit year, assume it's 2000-2099
        if ($year_2digit < 100) {
          $year_4digit = 2000 + $year_2digit;
          // Reconstruct with correct year
          $date = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s', $timestamp));
          if ($date !== FALSE) {
            $date->setDate($year_4digit, $date->format('m'), $date->format('d'));
            return $date->format('Y-m-d\TH:i:00');
          }
        }
      }
      return date('Y-m-d\TH:i:00', $timestamp);
    }

    return NULL;
  }

  /**
   * Sends data to the API endpoint.
   *
   * @param array $data
   *   The data to send.
   * @param string $ucr
   *   The UCR value.
   *
   * @return array
   *   Result array with 'success' and optional 'error' keys.
   */
  protected static function sendToApi(array $data, $ucr) {
    $api_key = '99754106633f94d350db34d548d6091a';
    $api_url = '/sentinel/sampleservice?key=' . $api_key;

    // Build full URL.
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    $full_url = $base_url . $api_url;

    // Add UCR to data (API expects it in the body).
    // If ucr is "pending", send as string, otherwise convert to integer.
    if ($ucr === 'pending') {
      $data['ucr'] = 'pending';
    }
    else {
      $data['ucr'] = (int) $ucr;
    }

    // Log the request body before sending.
    \Drupal::logger('sentinel_csv_processor')->info('API Request Body: @body', [
      '@body' => print_r([
        'url' => $full_url,
        'data' => $data,
        'ucr' => $ucr,
        'date_reported' => $data['date_reported'] ?? 'NOT SET',
        'date_booked' => $data['date_booked'] ?? 'NOT SET',
        'date_processed' => $data['date_processed'] ?? 'NOT SET',
        'nitrate_result' => $data['nitrate_result'] ?? 'NOT SET',
        'manganese_result' => $data['manganese_result'] ?? 'NOT SET',
      ], TRUE),
    ]);

    try {
      $client = \Drupal::httpClient();
      $response = $client->post($full_url, [
        'json' => $data,
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
      ]);

      $status_code = $response->getStatusCode();
      if ($status_code >= 200 && $status_code < 300) {
        return ['success' => TRUE];
      }
      else {
        return [
          'success' => FALSE,
          'error' => t('API returned status code: @code', ['@code' => $status_code]),
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => t('API request failed: @message', ['@message' => $e->getMessage()]),
      ];
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   Batch operations.
   */
  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      $processed_count = count($results['processed'] ?? []);
      $error_count = count($results['errors'] ?? []);

      if ($processed_count > 0) {
        \Drupal::messenger()->addStatus(t('Successfully processed @count file(s).', [
          '@count' => $processed_count,
        ]));
      }

      if ($error_count > 0) {
        \Drupal::messenger()->addWarning(t('Encountered @count error(s) during processing.', [
          '@count' => $error_count,
        ]));
      }
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during batch processing.'));
    }
  }

}

