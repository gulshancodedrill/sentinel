<?php

/**
 * Import Sentinel sample revisions from CSV into Drupal 11.
 *
 * Usage:
 *   php sentinel/scripts/import_sentinel_sample_revisions.php [csv_path]
 *
 * If csv_path is not provided, defaults to:
 *   /var/www/html/sentinel11/sentinel_sample_revisions_d7.csv
 */

$host = 'localhost';
$port = 3306;
$username = 'root';
$password = 'infotech';
$database = 'sentinel11';

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

$csv_path = $argv[1] ?? '/var/www/html/sentinel11/sentinel_sample_revisions_d7.csv';

if (!file_exists($csv_path)) {
  fwrite(STDERR, "CSV file not found: $csv_path\n");
  exit(1);
}

// Truncate the revision table first
echo "Truncating sentinel_sample_revision table...\n";
$mysqli->query("TRUNCATE TABLE sentinel_sample_revision");
echo "Table truncated.\n";

// Open CSV file
$fp = fopen($csv_path, 'r');
if (!$fp) {
  fwrite(STDERR, "Failed to open CSV file: $csv_path\n");
  exit(1);
}

// Skip BOM if present
$bom = fread($fp, 3);
if ($bom !== "\xEF\xBB\xBF") {
  rewind($fp);
}

// Read header
$header = fgetcsv($fp);
if (!$header) {
  fwrite(STDERR, "Failed to read CSV header\n");
  exit(1);
}

// Remove BOM from first column if present
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

echo "Columns: " . implode(', ', $header) . "\n";

// Prepare column list for INSERT
$column_list = implode(', ', array_map(function($col) {
  return "`$col`";
}, $header));

// Integer fields that should be NULL if empty
$integer_fields = [
  'pid', 'vid', 'client_id', 'card_complete', 'on_hold', 'pass_fail',
  'appearance_pass_fail', 'cond_pass_fail', 'cl_pass_fail', 'iron_pass_fail',
  'copper_pass_fail', 'aluminium_pass_fail', 'calcium_pass_fail', 'ph_pass_fail',
  'sentinel_x100_pass_fail', 'molybdenum_pass_fail', 'boron_pass_fail',
  'manganese_pass_fail', 'legacy', 'api_created_by',
  'sentinel_sample_hold_state_target_id', 'sentinel_company_address_target_id',
  'sentinel_sample_address_target_id'
];

$count = 0;
$batch_size = 50; // Reduced to avoid MySQL placeholder limit (50 rows * 75 cols = 3750 placeholders)
$batch = [];
$placeholders = '(' . implode(',', array_fill(0, count($header), '?')) . ')';

// Build types string based on field types
$types = '';
foreach ($header as $col) {
  if (in_array($col, $integer_fields)) {
    $types .= 'i'; // integer
  } else {
    $types .= 's'; // string
  }
}

while (($row = fgetcsv($fp)) !== FALSE) {
  if (count($row) !== count($header)) {
    echo "Warning: Row has " . count($row) . " columns, expected " . count($header) . ". Skipping.\n";
    continue;
  }
  
  // Convert empty strings to NULL for integer fields
  foreach ($row as $idx => $value) {
    $col_name = $header[$idx];
    if ($value === '' && in_array($col_name, $integer_fields)) {
      $row[$idx] = NULL;
    }
  }
  
  $batch[] = $row;
  $count++;
  
  // Insert in batches
  if (count($batch) >= $batch_size) {
    $values = [];
    $params = [];
    $types_str = '';
    
    foreach ($batch as $row_data) {
      $values[] = $placeholders;
      $params = array_merge($params, $row_data);
      $types_str .= $types;
    }
    
    $sql = "INSERT INTO `sentinel_sample_revision` ($column_list) VALUES " . implode(',', $values);
    
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
      $stmt->bind_param($types_str, ...$params);
      if ($stmt->execute()) {
        echo "Imported batch of " . count($batch) . " revisions (total: $count)...\n";
      } else {
        echo "Error executing batch insert: {$stmt->error}\n";
      }
      $stmt->close();
    } else {
      echo "Error preparing statement: {$mysqli->error}\n";
    }
    
    // Reset for next batch
    $batch = [];
  }
}

// Insert remaining rows
if (!empty($batch)) {
  $values = [];
  $params = [];
  $types_str = '';
  
  foreach ($batch as $row_data) {
    $values[] = $placeholders;
    $params = array_merge($params, $row_data);
    $types_str .= $types;
  }
  
  $sql = "INSERT INTO `sentinel_sample_revision` ($column_list) VALUES " . implode(',', $values);
  
  $stmt = $mysqli->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types_str, ...$params);
    if ($stmt->execute()) {
      echo "Imported final batch of " . count($batch) . " revisions (total: $count)...\n";
    } else {
      echo "Error executing final batch insert: {$stmt->error}\n";
    }
    $stmt->close();
  } else {
    echo "Error preparing final statement: {$mysqli->error}\n";
  }
}

fclose($fp);
$mysqli->close();

echo "Import complete. Imported $count revision records.\n";

