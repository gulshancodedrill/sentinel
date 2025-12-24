<?php

namespace Drupal\sentinel_csv_processor\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for processing automated CSV files.
 *
 * @QueueWorker(
 *   id = "automated_csv_processor",
 *   title = @Translation("Automated CSV Processor Queue"),
 *   cron = {"time" = 120}
 * )
 */
class AutomatedCsvQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // $data should contain: file_path (real path), file_uri (optional), filename, file_mtime
    if (!isset($data['file_path']) || !isset($data['filename']) || !isset($data['file_mtime'])) {
      \Drupal::logger('sentinel_csv_processor')->error('Invalid queue item data: missing required fields.');
      return;
    }

    $file_path = $data['file_path'];
    $file_uri = $data['file_uri'] ?? NULL;
    $filename = $data['filename'];
    $file_mtime = $data['file_mtime'];
    $automate_dir_uri = $data['automate_dir_uri'] ?? 'private://lab_files/automate-csvs';
    
    // Log for debugging.
    \Drupal::logger('sentinel_csv_processor')->debug('Queue worker processing file @file, automate_dir_uri: @uri', [
      '@file' => $filename,
      '@uri' => $automate_dir_uri,
    ]);

    // Define directory URIs - processing, archive, and failed should be inside automate-csvs.
    $processing_dir_uri = $automate_dir_uri . '/processing';
    $archive_dir_uri = $automate_dir_uri . '/archive';
    $failed_dir_uri = $automate_dir_uri . '/failed';

    // Ensure parent directory (automate-csvs) exists first.
    $parent_path = $this->fileSystem->realpath($automate_dir_uri);
    if (!$parent_path || !is_dir($parent_path)) {
      // Try to create it.
      $parent_prepared = $this->fileSystem->prepareDirectory($automate_dir_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$parent_prepared) {
        // Try manual creation as fallback.
        if ($parent_path && !is_dir($parent_path)) {
          @mkdir($parent_path, 0775, TRUE);
        }
        $parent_path = $this->fileSystem->realpath($automate_dir_uri);
        if (!$parent_path || !is_dir($parent_path)) {
          \Drupal::logger('sentinel_csv_processor')->error('Failed to create parent automate-csvs directory: @dir (path: @path)', [
            '@dir' => $automate_dir_uri,
            '@path' => $parent_path ?: 'unknown',
          ]);
          return;
        }
      }
    }
    
    // Verify parent directory is writable.
    if (!is_writable($parent_path)) {
      \Drupal::logger('sentinel_csv_processor')->error('Parent automate-csvs directory is not writable: @path', [
        '@path' => $parent_path,
      ]);
      return;
    }
    
    // Ensure processing directory exists with proper permissions.
    $processing_path = $this->fileSystem->realpath($processing_dir_uri);
    if (!$processing_path || !is_dir($processing_path)) {
      // Try to create it.
      $processing_prepared = $this->fileSystem->prepareDirectory($processing_dir_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      if (!$processing_prepared) {
        // Try manual creation as fallback.
        if ($processing_path && !is_dir($processing_path)) {
          @mkdir($processing_path, 0775, TRUE);
        }
        $processing_path = $this->fileSystem->realpath($processing_dir_uri);
        if (!$processing_path || !is_dir($processing_path)) {
          \Drupal::logger('sentinel_csv_processor')->error('Failed to create processing directory: @dir (path: @path)', [
            '@dir' => $processing_dir_uri,
            '@path' => $processing_path ?: 'unknown',
          ]);
          return;
        }
      }
    }
    
    // Verify processing directory is writable.
    if (!is_writable($processing_path)) {
      \Drupal::logger('sentinel_csv_processor')->error('Processing directory is not writable: @path', [
        '@path' => $processing_path,
      ]);
      return;
    }
    // Ensure archive and failed directories exist.
    $archive_prepared = $this->fileSystem->prepareDirectory($archive_dir_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$archive_prepared) {
      $archive_path = $this->fileSystem->realpath($archive_dir_uri);
      if ($archive_path && !is_dir($archive_path)) {
        @mkdir($archive_path, 0775, TRUE);
      }
      if (!$archive_path || !is_dir($archive_path)) {
        \Drupal::logger('sentinel_csv_processor')->warning('Archive directory may not be ready: @dir', [
          '@dir' => $archive_dir_uri,
        ]);
      }
    }
    
    $failed_prepared = $this->fileSystem->prepareDirectory($failed_dir_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if (!$failed_prepared) {
      $failed_path = $this->fileSystem->realpath($failed_dir_uri);
      if ($failed_path && !is_dir($failed_path)) {
        @mkdir($failed_path, 0775, TRUE);
      }
      if (!$failed_path || !is_dir($failed_path)) {
        \Drupal::logger('sentinel_csv_processor')->warning('Failed directory may not be ready: @dir', [
          '@dir' => $failed_dir_uri,
        ]);
      }
    }

    // Move file to processing directory using URI.
    $processing_uri = $processing_dir_uri . '/' . $filename;
    
    // Ensure processing directory is ready right before move (double-check).
    if (!$this->fileSystem->prepareDirectory($processing_dir_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      \Drupal::logger('sentinel_csv_processor')->error('Processing directory not ready before move: @dir', [
        '@dir' => $processing_dir_uri,
      ]);
      return;
    }
    
    // Use file_uri if provided, otherwise use file_path (real path).
    $source = $file_uri ?: $file_path;
    
    // Verify source file exists.
    $source_path = $this->fileSystem->realpath($source) ?: $source;
    if (!file_exists($source_path)) {
      \Drupal::logger('sentinel_csv_processor')->error('Source file does not exist: @file', [
        '@file' => $source_path,
      ]);
      return;
    }
    
    // Get real paths for both source and destination.
    $dest_dir_path = $this->fileSystem->realpath($processing_dir_uri);
    $dest_file_path = $dest_dir_path . '/' . $filename;
    
    if (!$dest_dir_path) {
      \Drupal::logger('sentinel_csv_processor')->error('Could not resolve destination directory path: @dir', [
        '@dir' => $processing_dir_uri,
      ]);
      return;
    }
    
    // Verify destination directory is writable.
    if (!is_writable($dest_dir_path)) {
      \Drupal::logger('sentinel_csv_processor')->error('Destination directory is not writable: @dir', [
        '@dir' => $dest_dir_path,
      ]);
      return;
    }
    
    // Use direct file system move (rename) instead of Drupal's move to avoid prepareDestination issues.
    try {
      // If destination file exists, remove it first.
      if (file_exists($dest_file_path)) {
        @unlink($dest_file_path);
      }
      
      // Use PHP's rename function for direct file system move.
      if (!@rename($source_path, $dest_file_path)) {
        // Fallback: try copy + unlink if rename fails.
        if (!@copy($source_path, $dest_file_path)) {
          \Drupal::logger('sentinel_csv_processor')->error('Failed to move file @file to processing directory. Source: @source, Destination: @dest', [
            '@file' => $filename,
            '@source' => $source_path,
            '@dest' => $dest_file_path,
          ]);
          return;
        }
        @unlink($source_path);
      }
      
      // Update the URI to point to the new location.
      $processing_uri = $processing_dir_uri . '/' . $filename;
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_csv_processor')->error('Exception moving file @file: @message', [
        '@file' => $filename,
        '@message' => $e->getMessage(),
      ]);
      return;
    }

    \Drupal::logger('sentinel_csv_processor')->info('Moved file @file to processing directory.', [
      '@file' => $filename,
    ]);

    try {
      // Set a maximum execution time for this queue item (2 minutes).
      $max_execution_time = 120;
      $start_time = time();
      
      // Extract refname from CSV before creating entity.
      $refname = $this->extractSiteFromCsv($processing_uri);
      
      // Create or load lab_data entity with all required fields.
      $lab_data = $this->createOrLoadLabData($filename, $processing_uri, $file_mtime, $refname);

      // Process the file using existing batch logic.
      $file_id = $lab_data->id();
      
      // Check execution time before processing.
      if ((time() - $start_time) > $max_execution_time) {
        throw new \Exception('Queue item timeout before processing started.');
      }
      
      $result = $this->processCsvFile($file_id, $processing_uri, $max_execution_time - (time() - $start_time));

      if ($result['success']) {
        // Move to archive on success.
        $archive_uri = $archive_dir_uri . '/' . $filename;
        if ($this->fileSystem->move($processing_uri, $archive_uri, FileSystemInterface::EXISTS_REPLACE)) {
          \Drupal::logger('sentinel_csv_processor')->info('Successfully processed and archived file @file.', [
            '@file' => $filename,
          ]);
        }
        else {
          \Drupal::logger('sentinel_csv_processor')->warning('File @file processed successfully but failed to move to archive.', [
            '@file' => $filename,
          ]);
        }
      }
      else {
        // Move to failed on error.
        $failed_uri = $failed_dir_uri . '/' . $filename;
        if ($this->fileSystem->move($processing_uri, $failed_uri, FileSystemInterface::EXISTS_REPLACE)) {
          \Drupal::logger('sentinel_csv_processor')->error('File @file processing failed. Moved to failed directory. Error: @error', [
            '@file' => $filename,
            '@error' => $result['error'] ?? 'Unknown error',
          ]);
        }
        else {
          \Drupal::logger('sentinel_csv_processor')->error('File @file processing failed and could not be moved to failed directory.', [
            '@file' => $filename,
          ]);
        }
      }
    }
    catch (\Exception $e) {
      // Move to failed on exception.
      $failed_uri = $failed_dir_uri . '/' . $filename;
      if ($this->fileSystem->realpath($processing_uri) && file_exists($this->fileSystem->realpath($processing_uri))) {
        if ($this->fileSystem->move($processing_uri, $failed_uri, FileSystemInterface::EXISTS_REPLACE)) {
          \Drupal::logger('sentinel_csv_processor')->error('Exception processing file @file. Moved to failed directory. Exception: @exception', [
            '@file' => $filename,
            '@exception' => $e->getMessage(),
          ]);
        }
      }
      throw $e;
    }
  }

  /**
   * Creates or loads a lab_data entity for the file.
   *
   * @param string $filename
   *   The filename.
   * @param string $file_uri
   *   The file URI (e.g., private://lab_files/processing/filename.csv).
   * @param int $file_mtime
   *   The file modification timestamp.
   * @param string|null $refname
   *   The refname extracted from CSV (Site value).
   *
   * @return \Drupal\sentinel_csv_processor\Entity\LabData
   *   The lab_data entity.
   */
  protected function createOrLoadLabData($filename, $file_uri, $file_mtime, $refname = NULL) {
    $storage = $this->entityTypeManager->getStorage('lab_data');
    $current_time = \Drupal::time()->getRequestTime();

    // Check if entity already exists with this filename.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('filename', $filename)
      ->range(0, 1);
    $ids = $query->execute();

    if (!empty($ids)) {
      $lab_data = $storage->load(reset($ids));
      // Update the file URI, modification time, refname, and status.
      $lab_data->set('path', $file_uri);
      $lab_data->set('ftp_file_updated', $file_mtime);
      $lab_data->set('process_type', 'automate');
      $lab_data->set('status', 'processing');
      // Update refname if provided and different.
      if ($refname !== NULL) {
        $lab_data->set('refname', $refname);
      }
      // Set uploaded timestamp if not already set (for existing entities).
      if ($lab_data->get('uploaded')->isEmpty()) {
        $lab_data->set('uploaded', $current_time);
      }
      $lab_data->save();
      
      \Drupal::logger('sentinel_csv_processor')->info('Updated existing lab_data entity for @file (refname: @refname, ftp_file_updated: @mtime)', [
        '@file' => $filename,
        '@refname' => $refname ?: 'N/A',
        '@mtime' => $file_mtime,
      ]);
      
      return $lab_data;
    }

    // Create new entity with all required fields.
    $lab_data = $storage->create([
      'filename' => $filename,
      'path' => $file_uri,
      'ftp_file_updated' => $file_mtime,
      'process_type' => 'automate',
      'status' => 'processing',
      'refname' => $refname,
      'uploaded' => $current_time, // Set uploaded timestamp when creating new entity.
    ]);
    $lab_data->save();

    \Drupal::logger('sentinel_csv_processor')->info('Created new lab_data entity for @file (refname: @refname, uploaded: @uploaded, ftp_file_updated: @mtime)', [
      '@file' => $filename,
      '@refname' => $refname ?: 'N/A',
      '@uploaded' => $current_time,
      '@mtime' => $file_mtime,
    ]);

    return $lab_data;
  }

  /**
   * Extract Site value from CSV file (same logic as CsvUploadForm).
   *
   * @param string $file_uri
   *   The file URI.
   *
   * @return string|null
   *   The Site value or NULL if not found.
   */
  protected function extractSiteFromCsv($file_uri) {
    $file_path = $this->fileSystem->realpath($file_uri);

    if (!$file_path || !file_exists($file_path)) {
      \Drupal::logger('sentinel_csv_processor')->warning('Cannot extract refname: file not found at @uri', [
        '@uri' => $file_uri,
      ]);
      return NULL;
    }

    $handle = fopen($file_path, 'r');
    if ($handle === FALSE) {
      \Drupal::logger('sentinel_csv_processor')->warning('Cannot extract refname: failed to open file @uri', [
        '@uri' => $file_uri,
      ]);
      return NULL;
    }

    try {
      // Read header row.
      $headers = fgetcsv($handle);
      if ($headers === FALSE) {
        return NULL;
      }

      // Find Site column index.
      $site_index = NULL;
      foreach ($headers as $index => $header) {
        if (trim(strtolower($header)) === 'site') {
          $site_index = $index;
          break;
        }
      }

      if ($site_index === NULL) {
        \Drupal::logger('sentinel_csv_processor')->warning('Cannot extract refname: Site column not found in CSV @uri', [
          '@uri' => $file_uri,
        ]);
        return NULL;
      }

      // Read first data row.
      $first_row = fgetcsv($handle);
      if ($first_row === FALSE || !isset($first_row[$site_index])) {
        \Drupal::logger('sentinel_csv_processor')->warning('Cannot extract refname: no data rows found in CSV @uri', [
          '@uri' => $file_uri,
        ]);
        return NULL;
      }

      $refname = trim($first_row[$site_index]) ?: NULL;
      
      if ($refname) {
        \Drupal::logger('sentinel_csv_processor')->info('Extracted refname from CSV: @refname', [
          '@refname' => $refname,
        ]);
      }
      
      return $refname;
    }
    finally {
      fclose($handle);
    }
  }

  /**
   * Processes a CSV file (cloned logic from CsvProcessingBatch).
   *
   * @param int $file_id
   *   The lab_data entity ID.
   * @param string $file_uri
   *   The file URI (e.g., private://lab_files/processing/filename.csv).
   * @param int $timeout
   *   Maximum time in seconds to spend processing (optional).
   *
   * @return array
   *   Result array with 'success' and optional 'error' keys.
   */
  protected function processCsvFile($file_id, $file_uri, $timeout = NULL) {
    $start_time = time();
    
    // Load lab_data entity.
    $storage = $this->entityTypeManager->getStorage('lab_data');
    $lab_data = $storage->load($file_id);
    if (!$lab_data) {
      return [
        'success' => FALSE,
        'error' => 'Lab data entity not found.',
      ];
    }

    // Set status to processing.
    $lab_data->set('status', 'processing');
    $lab_data->save();
    
    \Drupal::logger('sentinel_csv_processor')->info('Queue processing started for file @filename. Status set to processing.', [
      '@filename' => $lab_data->get('filename')->value,
    ]);

    $filename = $lab_data->get('filename')->value;

    // Get real path to verify file exists.
    $file_path = $this->fileSystem->realpath($file_uri);
    if (!$file_path || !file_exists($file_path)) {
      $lab_data->set('status', 'failed');
      $lab_data->save();
      return [
        'success' => FALSE,
        'error' => 'File path not accessible for ' . $filename,
      ];
    }

    // Check timeout before starting.
    if ($timeout !== NULL && (time() - $start_time) >= $timeout) {
      return [
        'success' => FALSE,
        'error' => 'Processing timeout before starting.',
      ];
    }

    // Read and process CSV.
    $csv_data = $this->readCsvFile($file_path);
    if (empty($csv_data['rows'])) {
      $lab_data->set('status', 'failed');
      $lab_data->set('processed', \Drupal::time()->getRequestTime());
      $lab_data->save();
      return [
        'success' => FALSE,
        'error' => 'No data found in ' . $filename,
      ];
    }

    // Log CSV structure.
    \Drupal::logger('sentinel_csv_processor')->info('Processing file @filename: @rows rows found', [
      '@filename' => $filename,
      '@rows' => count($csv_data['rows']),
    ]);

    // Group rows by Site (pack reference number).
    $sites_data = $this->groupRowsBySite($csv_data['headers'], $csv_data['rows']);

    \Drupal::logger('sentinel_csv_processor')->info('Grouped into @sites unique sites', [
      '@sites' => count($sites_data),
    ]);

    // Process each site (one API call per site).
    $processed_count = 0;
    $error_count = 0;
    $errors = [];

    foreach ($sites_data as $site => $site_rows) {
      // Check timeout during processing.
      if ($timeout !== NULL && (time() - $start_time) >= $timeout) {
        \Drupal::logger('sentinel_csv_processor')->warning('Processing timeout during site processing.');
        break;
      }

      \Drupal::logger('sentinel_csv_processor')->info('Processing site @site with @rows test results', [
        '@site' => $site,
        '@rows' => count($site_rows),
      ]);
      
      try {
        $result = $this->processSiteData($site, $site_rows);
        
        if ($result['success']) {
          $processed_count++;
        }
        else {
          $error_count++;
          $error_msg = 'Site ' . $site . ' in ' . $filename . ': ' . $result['error'];
          $errors[] = $error_msg;
          \Drupal::logger('sentinel_csv_processor')->error($error_msg);
        }
      }
      catch (\Exception $e) {
        $error_count++;
        $error_msg = 'Site ' . $site . ' in ' . $filename . ': ' . $e->getMessage();
        $errors[] = $error_msg;
        \Drupal::logger('sentinel_csv_processor')->error($error_msg);
      }
    }

    // Update processed timestamp and status.
    $lab_data->set('processed', \Drupal::time()->getRequestTime());
    
    if ($error_count === 0 && $processed_count > 0) {
      $lab_data->set('status', 'success');
      \Drupal::logger('sentinel_csv_processor')->info('Queue processing completed successfully. Status set to success for file @filename.', [
        '@filename' => $filename,
      ]);
    }
    else {
      $lab_data->set('status', 'failed');
      \Drupal::logger('sentinel_csv_processor')->error('Queue processing failed. Status set to failed for file @filename. Errors: @errors', [
        '@filename' => $filename,
        '@errors' => $error_count,
      ]);
    }
    $lab_data->save();

    if ($error_count > 0) {
      return [
        'success' => FALSE,
        'error' => implode('; ', $errors),
      ];
    }

    return ['success' => TRUE];
  }

  /**
   * Reads CSV file and returns headers and rows.
   */
  protected function readCsvFile($file_path) {
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
   */
  protected function groupRowsBySite(array $headers, array $rows) {
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
   */
  protected function processSiteData($site, array $rows) {
    // Get UCR from sentinel_sample entity.
    $ucr = $this->getUcrFromPackReference($site);
    $is_pending = FALSE;
    
    if (!$ucr) {
      $ucr = 'pending';
      $is_pending = TRUE;
      \Drupal::logger('sentinel_csv_processor')->warning('Pack reference @site not found in sentinel_sample. Setting ucr=pending and installer_email to system email.', [
        '@site' => $site,
      ]);
    }

    // Map CSV rows to API fields.
    $api_data = $this->mapRowsToApiFields($rows, $site);
    
    // Validate that we have actual test result data.
    if (!$this->validateApiData($api_data)) {
      $error_message = 'No valid test result data found in CSV for site ' . $site . '. All result fields are NULL or empty.';
      \Drupal::logger('sentinel_csv_processor')->error('Validation failed for site @site: @message', [
        '@site' => $site,
        '@message' => $error_message,
      ]);
      return [
        'success' => FALSE,
        'error' => $error_message,
      ];
    }
    
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
    return $this->sendToApi($api_data, $ucr);
  }

  /**
   * Gets UCR from sentinel_sample entity by pack_reference_number.
   */
  protected function getUcrFromPackReference($pack_reference_number) {
    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
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
   */
  protected function mapRowsToApiFields(array $rows, $site) {
    $api_data = [];

    // Set pack_reference_number from Site.
    $api_data['pack_reference_number'] = $site;

    // Extract date fields from CSV rows.
    $date_received = NULL;
    if (!empty($rows)) {
      $first_row = reset($rows);
      if (isset($first_row[13]) && !empty(trim($first_row[13]))) {
        $date_received = trim($first_row[13]);
      }
    }
    
    $analysis_date = NULL;
    if (!empty($rows)) {
      $last_row = end($rows);
      if (isset($last_row[14]) && !empty(trim($last_row[14]))) {
        $analysis_date = trim($last_row[14]);
      }
    }

    // Parse and format date_received as date_booked.
    $formatted_date_booked = $this->parseAndFormatDate($date_received);
    if ($formatted_date_booked) {
      $api_data['date_booked'] = $formatted_date_booked;
      \Drupal::logger('sentinel_csv_processor')->info('Extracted date_booked from CSV Date Received: @original -> @formatted', [
        '@original' => $date_received,
        '@formatted' => $formatted_date_booked,
      ]);
    }
    else {
      $current_datetime = \Drupal::time()->getRequestTime();
      $api_data['date_booked'] = (string) date('Y-m-d\TH:i:00', $current_datetime);
      \Drupal::logger('sentinel_csv_processor')->warning('Date Received not found or invalid in CSV for site @site, using current date.', [
        '@site' => $site,
      ]);
    }

    // Parse and format analysis_date as date_processed.
    $formatted_date_processed = $this->parseAndFormatDate($analysis_date);
    if ($formatted_date_processed) {
      $api_data['date_processed'] = $formatted_date_processed;
      \Drupal::logger('sentinel_csv_processor')->info('Extracted date_processed from CSV Analysis Date: @original -> @formatted', [
        '@original' => $analysis_date,
        '@formatted' => $formatted_date_processed,
      ]);
    }
    else {
      $current_datetime = \Drupal::time()->getRequestTime();
      $api_data['date_processed'] = (string) date('Y-m-d\TH:i:00', $current_datetime);
      \Drupal::logger('sentinel_csv_processor')->warning('Analysis Date not found or invalid in CSV for site @site, using current date.', [
        '@site' => $site,
      ]);
    }

    // Process each row to extract Variable/Value pairs.
    foreach ($rows as $row) {
      $variable = isset($row[7]) ? trim($row[7]) : '';
      $value = isset($row[9]) ? trim($row[9]) : '';
      $sample_point = isset($row[4]) ? trim($row[4]) : '';

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
      $normalized_value = strtolower(trim($value));
      $is_valid_value = ($normalized_value !== '' && $normalized_value !== 'null' && $normalized_value !== 'pending' && $value !== NULL);

      // Debug logging for mapping.
      \Drupal::logger('sentinel_csv_processor')->debug('Mapping: Variable=[@var], Value=[@val], SamplePoint=[@sp], Valid=[@valid]', [
        '@var' => $variable,
        '@val' => $value,
        '@sp' => $sample_point,
        '@valid' => $is_valid_value ? 'YES' : 'NO',
      ]);

      if (strpos($normalized_variable, 'ph') !== FALSE && strpos($normalized_variable, 'lab') !== FALSE) {
        if ($is_valid_value) {
          $api_data['ph_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped ph_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'boron') !== FALSE) {
        if ($is_valid_value) {
          $api_data['boron_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped boron_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'molybdenum') !== FALSE) {
        if ($is_valid_value) {
          $api_data['molybdenum_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped molybdenum_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'conductivity') !== FALSE) {
        if ($is_valid_value) {
          if ($normalized_sample_point === 'main') {
            $api_data['mains_cond_result'] = $value;
            \Drupal::logger('sentinel_csv_processor')->info('Mapped mains_cond_result = @value', ['@value' => $value]);
          }
          elseif ($normalized_sample_point === 'system') {
            $api_data['sys_cond_result'] = $value;
            \Drupal::logger('sentinel_csv_processor')->info('Mapped sys_cond_result = @value', ['@value' => $value]);
          }
        }
      }
      elseif (strpos($normalized_variable, 'calcium') !== FALSE) {
        if ($is_valid_value) {
          if ($normalized_sample_point === 'main') {
            $api_data['mains_calcium_result'] = $value;
            \Drupal::logger('sentinel_csv_processor')->info('Mapped mains_calcium_result = @value', ['@value' => $value]);
          }
          elseif ($normalized_sample_point === 'system') {
            $api_data['sys_calcium_result'] = $value;
            \Drupal::logger('sentinel_csv_processor')->info('Mapped sys_calcium_result = @value', ['@value' => $value]);
          }
        }
      }
      elseif (strpos($normalized_variable, 'iron') !== FALSE) {
        if ($is_valid_value) {
          $api_data['iron_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped iron_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'copper') !== FALSE) {
        if ($is_valid_value) {
          $api_data['copper_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped copper_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'aluminium') !== FALSE || strpos($normalized_variable, 'aluminum') !== FALSE) {
        if ($is_valid_value) {
          $api_data['aluminium_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped aluminium_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'appearance') !== FALSE) {
        if ($is_valid_value) {
          $api_data['appearance_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped appearance_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'nitrate') !== FALSE) {
        if ($is_valid_value) {
          $api_data['nitrate_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped nitrate_result = @value', ['@value' => $value]);
        }
      }
      elseif (strpos($normalized_variable, 'manganese') !== FALSE) {
        if ($is_valid_value) {
          $api_data['manganese_result'] = $value;
          \Drupal::logger('sentinel_csv_processor')->info('Mapped manganese_result = @value', ['@value' => $value]);
        }
      }
    }

    // Set default values for required fields if not present.
    if (!isset($api_data['manganese_result'])) {
      $api_data['manganese_result'] = '0';
    }
    if (!isset($api_data['nitrate_result'])) {
      $api_data['nitrate_result'] = '0';
    }

    // Set date_reported to current date and time.
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
   */
  protected function parseAndFormatDate($date_string) {
    if (empty($date_string)) {
      return NULL;
    }

    $date_string = trim($date_string);
    $original_string = $date_string;
    
    // Handle 2-digit years.
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})(?:\s|$)/', $date_string, $matches)) {
      $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
      $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
      $year_2digit = (int) $matches[3];
      $year_4digit = 2000 + $year_2digit;
      $date_string = $day . '/' . $month . '/' . $year_4digit;
      \Drupal::logger('sentinel_csv_processor')->info('Converted 2-digit year date: @original -> @converted', [
        '@original' => $original_string,
        '@converted' => $date_string,
      ]);
    }
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
    
    // Try common date formats.
    $formats = [
      'Y-m-d\TH:i:s',
      'Y-m-d\TH:i',
      'Y-m-d H:i:s',
      'Y-m-d H:i',
      'Y-m-d',
      'd/m/Y H:i:s',
      'd/m/Y H:i',
      'd/m/Y',
      'd-m-Y H:i:s',
      'd-m-Y H:i',
      'd-m-Y',
      'Ymd\TH:i:s',
      'Ymd\TH:i',
      'Ymd',
    ];

    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $date_string);
      if ($date !== FALSE) {
        return $date->format('Y-m-d\TH:i:00');
      }
    }

    // Fallback to strtotime.
    $timestamp = strtotime($date_string);
    if ($timestamp !== FALSE) {
      $parsed_date = getdate($timestamp);
      if ($parsed_date['year'] < 2000 && preg_match('/\b(\d{2})\b/', $date_string, $year_match)) {
        $year_2digit = (int) $year_match[1];
        if ($year_2digit < 100) {
          $year_4digit = 2000 + $year_2digit;
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
   * Validates that API data contains at least some test result values.
   */
  protected function validateApiData(array $api_data) {
    $result_fields = [
      'ph_result',
      'boron_result',
      'molybdenum_result',
      'sys_cond_result',
      'mains_cond_result',
      'mains_calcium_result',
      'sys_calcium_result',
      'iron_result',
      'copper_result',
      'aluminium_result',
      'manganese_result',
      'nitrate_result',
      'appearance_result',
    ];

    foreach ($result_fields as $field) {
      if (isset($api_data[$field])) {
        $value = trim((string) $api_data[$field]);
        if ($value !== '' && $value !== '0' && $value !== 'null' && strtolower($value) !== 'pending') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Sends data to the API endpoint (with fixed URL construction).
   */
  protected function sendToApi(array $data, $ucr) {
    $api_key = '99754106633f94d350db34d548d6091a';
    $api_url = '/sentinel/sampleservice?key=' . $api_key;

    // Build full URL - fix for queue context.
    // In queue context, \Drupal::request() and global $base_url may return 'default' host.
    // We need to detect the actual base URL.
    $api_base_url = NULL;
    
    // First, try to get from request stack (but may not work in queue context).
    $request_stack = \Drupal::requestStack();
    $request = $request_stack->getCurrentRequest();
    
    if ($request) {
      $request_url = $request->getSchemeAndHttpHost();
      // Only use if it's not 'default'.
      if ($request_url && $request_url !== 'http://default' && $request_url !== 'https://default') {
        $api_base_url = $request_url;
      }
    }
    
    // If still not set or is 'default', try global $base_url from settings.php.
    if (empty($api_base_url)) {
      global $base_url;
      // Only use if it's not 'default'.
      if (!empty($base_url) && $base_url !== 'http://default' && $base_url !== 'https://default') {
        $api_base_url = $base_url;
      }
    }
    
    // If still not set, try to get from site config.
    if (empty($api_base_url)) {
      $site_config = \Drupal::config('system.site');
      $config_base_url = $site_config->get('base_url');
      if (!empty($config_base_url)) {
        $api_base_url = $config_base_url;
      }
    }
    
    // Last resort: determine from environment.
    // Check $_SERVER['HTTP_HOST'] or use sentinel.local for local development.
    if (empty($api_base_url)) {
      // Check HTTP_HOST from server variables.
      if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'default') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $api_base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
      }
      // If HTTP_HOST is 'default' or not set, use sentinel.local for local dev.
      else {
        // For local development, use sentinel.local
        // For production, this should be set in settings.php or site config.
        $api_base_url = 'http://sentinel.local';
      }
    }
    
    // Remove trailing slash if present.
    $api_base_url = rtrim($api_base_url, '/');
    $full_url = $api_base_url . $api_url;
    
    // Log the URL being used for debugging.
    \Drupal::logger('sentinel_csv_processor')->info('API URL constructed: @url', [
      '@url' => $full_url,
    ]);

    // Add UCR to data.
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
          'error' => 'API returned status code: ' . $status_code,
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'error' => 'API request failed: ' . $e->getMessage(),
      ];
    }
  }


}

