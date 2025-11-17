<?php

/**
 * Update sentinel_sample vid values in Drupal 11 from CSV file.
 *
 * This script reads pid and vid from a CSV file and updates the vid column
 * in the sentinel_sample table in Drupal 11.
 *
 * CSV Format:
 *   pid,vid
 *   1,1
 *   2,2
 *
 * Usage:
 *   php update_sentinel_sample_vids.php [csv_file_path]
 *
 * If csv_file_path is not provided, defaults to:
 *   sentinel_sample_pid_vid.csv (in the same directory as the script)
 */

// Database configuration for Drupal 11
$d11_host = 'localhost';
$d11_port = 3306;
$d11_username = 'root';
$d11_password = 'infotech';
$d11_database = 'sentinel11';

// Get CSV file path from command line argument or use default
$script_dir = dirname(__FILE__);
$csv_file = $argv[1] ?? $script_dir . '/sentinel_sample_pid_vid.csv';

if (!file_exists($csv_file)) {
  fwrite(STDERR, "Error: CSV file not found: $csv_file\n");
  fwrite(STDERR, "Please provide a CSV file with 'pid' and 'vid' columns.\n");
  fwrite(STDERR, "Usage: php update_sentinel_sample_vids.php [csv_file_path]\n");
  exit(1);
}

// Connect to Drupal 11 database
$mysqli = new mysqli($d11_host, $d11_username, $d11_password, $d11_database, $d11_port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

echo "Reading CSV file: $csv_file\n";

// Open CSV file
$fp = fopen($csv_file, 'r');
if (!$fp) {
  fwrite(STDERR, "Failed to open CSV file: $csv_file\n");
  exit(1);
}

// Skip BOM if present
$bom = fread($fp, 3);
if ($bom !== "\xEF\xBB\xBF") {
  rewind($fp);
}

// Detect delimiter (tab or comma)
$first_line = fgets($fp);
rewind($fp);
$delimiter = (strpos($first_line, "\t") !== FALSE) ? "\t" : ",";

// Read header
$header = fgetcsv($fp, 0, $delimiter);
if (!$header) {
  fwrite(STDERR, "Failed to read CSV header\n");
  exit(1);
}

// Remove BOM from first column if present
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

// Normalize header (trim whitespace, lowercase)
$header = array_map('trim', array_map('strtolower', $header));

// Find pid and vid column indices
$pid_idx = array_search('pid', $header);
$vid_idx = array_search('vid', $header);

if ($pid_idx === FALSE) {
  fwrite(STDERR, "Error: 'pid' column not found in CSV header. Found columns: " . implode(', ', $header) . "\n");
  exit(1);
}

if ($vid_idx === FALSE) {
  fwrite(STDERR, "Error: 'vid' column not found in CSV header. Found columns: " . implode(', ', $header) . "\n");
  exit(1);
}

echo "Found columns: pid (index $pid_idx), vid (index $vid_idx)\n\n";

// Prepare update statement
$update_stmt = $mysqli->prepare("UPDATE sentinel_sample SET vid = ? WHERE pid = ?");
if (!$update_stmt) {
  fwrite(STDERR, "Failed to prepare update statement: {$mysqli->error}\n");
  exit(1);
}

$update_stmt->bind_param('ii', $vid, $pid);

$count = 0;
$updated = 0;
$errors = 0;
$batch_size = 1000;

// Process CSV rows
while (($row = fgetcsv($fp, 0, $delimiter)) !== FALSE) {
  $count++;
  
  if (count($row) <= max($pid_idx, $vid_idx)) {
    echo "Warning: Row $count has insufficient columns. Skipping.\n";
    $errors++;
    continue;
  }
  
  $pid = trim($row[$pid_idx]);
  $vid = trim($row[$vid_idx]);
  
  // Skip empty values
  if ($pid === '' || $vid === '') {
    continue;
  }
  
  // Convert to integers
  $pid = (int) $pid;
  $vid = (int) $vid;
  
  // Execute update
  if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
      $updated++;
    }
  } else {
    echo "Error updating pid=$pid, vid=$vid: {$update_stmt->error}\n";
    $errors++;
  }
  
  // Progress indicator
  if ($count % $batch_size === 0) {
    echo "Processed $count rows, updated $updated records...\n";
  }
}

$update_stmt->close();
fclose($fp);
$mysqli->close();

echo "\n";
echo "========================================\n";
echo "Update Complete!\n";
echo "========================================\n";
echo "Total rows processed: $count\n";
echo "Records updated: $updated\n";
echo "Errors: $errors\n";
echo "\n";
