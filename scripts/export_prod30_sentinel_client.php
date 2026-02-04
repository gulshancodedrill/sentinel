<?php

/**
 * Export sentinel_client table from prod30 database to CSV.
 *
 * Usage:
 *   php scripts/export_prod30_sentinel_client.php
 */

$host = 'localhost';
$port = 3306;
$username = 'root';
$password = 'infotech';
$database = 'prod30';

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

// Get actual columns from the table
$result = $mysqli->query("DESCRIBE `sentinel_client`");
if ($result === false) {
  fwrite(STDERR, "Failed to describe table: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$available_columns = [];
while ($row = $result->fetch_assoc()) {
  $available_columns[] = $row['Field'];
}
$result->close();

// Define desired columns (in order) - only include those that exist
$desired_columns = [
  'cid',
  'uuid',
  'name',
  'email',
  'uid',
  'api_key',
  'global_access',
  'send_pending',
  'ucr',
  'company',
  'created',
  'updated',
];

// Filter to only include columns that exist in the table
$columns = array_intersect($desired_columns, $available_columns);

if (empty($columns)) {
  fwrite(STDERR, "No matching columns found in sentinel_client table.\n");
  $mysqli->close();
  exit(1);
}

print "Exporting columns: " . implode(', ', $columns) . "\n";

// Query all rows from sentinel_client table
$sql = "SELECT " . implode(', ', array_map(function($col) {
  return "`{$col}`";
}, $columns)) . " FROM `sentinel_client` ORDER BY `cid` ASC";

$result = $mysqli->query($sql, MYSQLI_USE_RESULT);
if ($result === false) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$output_path = __DIR__ . '/sentinel_client_export.csv';
$fp = fopen($output_path, 'w');
if (!$fp) {
  fwrite(STDERR, "Unable to open {$output_path} for writing.\n");
  $result->close();
  $mysqli->close();
  exit(1);
}

// Write CSV header
fputcsv($fp, $columns);

$row_count = 0;
while ($row = $result->fetch_assoc()) {
  $csv_row = [];
  foreach ($columns as $column) {
    $value = $row[$column] ?? '';
    
    // Handle NULL values
    if ($value === NULL) {
      $value = '';
    }
    // Convert boolean fields (0/1) to string
    elseif (in_array($column, ['global_access', 'send_pending'])) {
      $value = (string) ((int) $value);
    }
    // Handle timestamps
    elseif (in_array($column, ['created', 'updated'])) {
      $value = $value ? (string) $value : '';
    }
    // Normalize text fields
    elseif (is_string($value)) {
      $value = normalizeText($value);
    }
    else {
      $value = (string) $value;
    }
    
    $csv_row[] = $value;
  }
  fputcsv($fp, $csv_row);
  $row_count++;
  
  // Progress indicator
  if ($row_count % 100 === 0) {
    print "Exported {$row_count} rows...\n";
  }
}

$result->close();
$mysqli->close();
fclose($fp);

print "\n=== Export Complete ===\n";
print "Total rows exported: {$row_count}\n";
print "Output file: {$output_path}\n";

/**
 * Normalize text values for CSV output.
 */
function normalizeText($value) {
  $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
  $value = str_replace(';', ', ', $value);
  return trim(preg_replace('/\s+/', ' ', $value));
}
