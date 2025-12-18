<?php

/**
 * Script to import file entities from Drupal 7 file_managed table CSV export.
 *
 * This script reads file_managed_d7.csv and enqueues file entities for processing.
 * Use the queue worker to process the items.
 *
 * Usage:
 *   drush scr scripts/import_file_managed_d7.php [fid] [--process]
 *   
 *   If fid is provided, only that specific file ID will be enqueued.
 *   Otherwise, all rows from the CSV will be enqueued.
 *   
 *   Use --process to process the queue after enqueuing (or use separate command).
 *
 * Examples:
 *   drush scr scripts/import_file_managed_d7.php                    # Enqueue all files
 *   drush scr scripts/import_file_managed_d7.php 481                # Enqueue only fid 481
 *   drush scr scripts/import_file_managed_d7.php --process          # Process queue
 *   drush scr scripts/import_file_managed_d7.php 481 --process      # Enqueue and process fid 481
 */

use Drupal\file\Entity\File;

// Get optional fid parameter and --process flag from command line.
$target_fid = NULL;
$process_queue = FALSE;
$script_args = [];

// Check for $argv (available in CLI context).
if (isset($argv) && is_array($argv)) {
  $script_args = $argv;
}
// Fallback to $GLOBALS['argv'].
elseif (isset($GLOBALS['argv']) && is_array($GLOBALS['argv'])) {
  $script_args = $GLOBALS['argv'];
}

// Parse arguments.
foreach ($script_args as $arg) {
  if ($arg === '--process') {
    $process_queue = TRUE;
  }
  elseif (is_numeric($arg) && (int) $arg > 0) {
    $target_fid = (int) $arg;
  }
}

// Queue name for file imports.
$queue_name = 'sentinel_data_import.file_managed_csv';
$queue_factory = \Drupal::service('queue');
$queue = $queue_factory->get($queue_name);

// Path to the CSV file.
$csv_path = __DIR__ . '/file_managed_d7_excluding_last_2_years_part3.csv';

if (!file_exists($csv_path)) {
  print "Error: CSV file not found at $csv_path\n";
  return;
}

$logger = \Drupal::logger('file_import');

// Open CSV file.
$handle = fopen($csv_path, 'r');
if ($handle === FALSE) {
  print "Error: Could not open CSV file.\n";
  return;
}

// Read header row.
$header = fgetcsv($handle);
if ($header === FALSE) {
  print "Error: Could not read CSV header.\n";
  fclose($handle);
  return;
}

// Validate header columns.
$expected_columns = ['fid', 'uid', 'filename', 'uri', 'filemime', 'filesize', 'status', 'timestamp'];
$header = array_map('trim', $header);
if ($header !== $expected_columns) {
  print "Warning: CSV header does not match expected columns.\n";
  print "Expected: " . implode(', ', $expected_columns) . "\n";
  print "Found: " . implode(', ', $header) . "\n";
}

$enqueued = 0;
$skipped = 0;
$errors = 0;
$row_count = 0;

if ($target_fid !== NULL) {
  print "Starting file entity enqueue from CSV (targeting fid: $target_fid)...\n";
}
else {
  print "Starting file entity enqueue from CSV (enqueuing all files)...\n";
}

// Process each row.
while (($row = fgetcsv($handle)) !== FALSE) {
  $row_count++;
  
  // Skip empty rows.
  if (empty(array_filter($row))) {
    continue;
  }
  
  // Map CSV columns to variables.
  $data = array_combine($header, $row);
  
  // Extract and validate required fields.
  $fid = isset($data['fid']) ? (int) trim($data['fid']) : 0;
  
  // If target_fid is specified, skip rows that don't match.
  if ($target_fid !== NULL && $fid !== $target_fid) {
    continue;
  }
  
  $uid = isset($data['uid']) ? (int) trim($data['uid']) : 0;
  $filename = isset($data['filename']) ? trim($data['filename']) : '';
  $uri = isset($data['uri']) ? trim($data['uri']) : '';
  $filemime = isset($data['filemime']) ? trim($data['filemime']) : '';
  $filesize = isset($data['filesize']) ? (int) trim($data['filesize']) : 0;
  $status = isset($data['status']) ? (int) trim($data['status']) : 0;
  $timestamp = isset($data['timestamp']) ? (int) trim($data['timestamp']) : 0;
  
  // Skip rows with missing critical data.
  if (empty($fid) || empty($uri)) {
    $skipped++;
    if ($row_count % 1000 == 0) {
      print "Processed $row_count rows (enqueued: $enqueued, skipped: $skipped)\n";
    }
    continue;
  }
  
  try {
    // Enqueue the item for processing.
    $queue->createItem([
      'fid' => $fid,
      'uid' => $uid,
      'filename' => $filename,
      'uri' => $uri,
      'filemime' => $filemime,
      'filesize' => $filesize,
      'status' => $status,
      'timestamp' => $timestamp,
    ]);
    
    $enqueued++;
    
    // Progress indicator.
    if ($row_count % 1000 == 0) {
      print sprintf(
        "Processed %d rows (enqueued: %d, skipped: %d, errors: %d)\n",
        $row_count,
        $enqueued,
        $skipped,
        $errors
      );
    }
  }
  catch (\Exception $e) {
    $errors++;
    $logger->error('Failed to enqueue file entity fid @fid: @message', [
      '@fid' => $fid,
      '@message' => $e->getMessage(),
    ]);
    
    if ($errors <= 10) {
      print sprintf("Error enqueuing fid %d: %s\n", $fid, $e->getMessage());
    }
  }
}

fclose($handle);

// If targeting a specific fid and nothing was enqueued, warn the user.
if ($target_fid !== NULL && $enqueued === 0) {
  print sprintf("\nWarning: File ID %d was not found in the CSV.\n", $target_fid);
}

print sprintf(
  "\nEnqueue complete!\n" .
  "Total rows processed: %d\n" .
  "Enqueued: %d\n" .
  "Skipped: %d\n" .
  "Errors: %d\n",
  $row_count,
  $enqueued,
  $skipped,
  $errors
);

// Process queue if --process flag was set.
if ($process_queue) {
  print "\nProcessing queue...\n";
  
  $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
  $queue_worker = $queue_worker_manager->createInstance($queue_name);
  
  $processed = 0;
  $queue_errors = 0;
  $start_time = time();
  
  while ($item = $queue->claimItem(300)) {
    try {
      $queue_worker->processItem($item->data);
      $queue->deleteItem($item);
      $processed++;
      
      if ($processed % 100 == 0) {
        $elapsed = time() - $start_time;
        $rate = $elapsed > 0 ? round($processed / $elapsed, 2) : 0;
        print sprintf("Processed %d items (%d items/sec)\n", $processed, $rate);
      }
    }
    catch (\Exception $e) {
      $queue_errors++;
      $logger->error('Error processing queue item: @message', [
        '@message' => $e->getMessage(),
      ]);
      $queue->deleteItem($item);
      
      if ($queue_errors <= 10) {
        print sprintf("Error processing item: %s\n", $e->getMessage());
      }
    }
  }
  
  print sprintf(
    "\nQueue processing complete!\n" .
    "Processed: %d\n" .
    "Errors: %d\n",
    $processed,
    $queue_errors
  );
}
else {
  $number_of_items = $queue->numberOfItems();
  print sprintf("\nQueue now contains %d items.\n", $number_of_items);
  print "Run with --process flag to process the queue, or use:\n";
  print "  drush queue:run $queue_name\n";
  print "  drush queue:run $queue_name --items=1000\n";
}

