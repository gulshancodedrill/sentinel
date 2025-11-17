<?php

/**
 * Export Drupal 7 Sentinel sample revision entities to CSV.
 *
 * Usage:
 *   php sentinel/scripts/export_d7_sentinel_sample_revisions.php
 */

$host = 'localhost';
$port = 3306;
$username = 'root';
$password = 'infotech';
$database = 'sentineld7';

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

// Get all columns from the revision table
$result = $mysqli->query("SHOW COLUMNS FROM sentinel_sample_revision");
$columns = [];
while ($row = $result->fetch_assoc()) {
  $columns[] = $row['Field'];
}

// Normalize text for CSV output
function normalizeText($value) {
  if ($value === null) {
    return '';
  }
  $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
  $value = str_replace(';', ', ', $value);
  return trim(preg_replace('/\s+/', ' ', $value));
}

// Build SQL query
$column_list = implode(', ', array_map(function($col) {
  return "`$col`";
}, $columns));

$sql = "SELECT $column_list FROM sentinel_sample_revision ORDER BY pid, vid";

$result = $mysqli->query($sql);
if (!$result) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  exit(1);
}

// Output CSV
$output_file = '/var/www/html/sentinel11/sentinel_sample_revisions_d7.csv';
$fp = fopen($output_file, 'w');

// Write BOM for Excel compatibility
fprintf($fp, "\xEF\xBB\xBF");

// Write header
fputcsv($fp, $columns);

// Write data rows
$count = 0;
while ($row = $result->fetch_assoc()) {
  $normalized_row = [];
  foreach ($columns as $col) {
    $value = $row[$col] ?? '';
    // Normalize text fields
    if (is_string($value)) {
      $value = normalizeText($value);
    }
    $normalized_row[] = $value;
  }
  fputcsv($fp, $normalized_row);
  $count++;
  
  if ($count % 1000 === 0) {
    echo "Exported $count revisions...\n";
  }
}

fclose($fp);
$mysqli->close();

echo "Export complete. Exported $count revision records to $output_file\n";

