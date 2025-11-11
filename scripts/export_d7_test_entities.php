<?php

/**
 * Export Drupal 7 test_entity data to CSV.
 *
 * Usage:
 *   php sentinel/scripts/export_d7_test_entities.php
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

$columns = [
  'id',
  'type',
  'uid',
  'created',
  'changed',
  'language',
  'appearance_result',
  'ph_result',
  'boron_result',
  'boiler_type',
  'molybdenum_result',
  'sys_cond_result',
  'mains_cond_result',
  'mains_calcium_result',
  'sys_calcium_result',
  'sys_cl_result',
  'iron_result',
  'copper_result',
  'aluminium_result',
  'appearance_pass_fail',
  'cond_pass_fail',
  'cl_pass_fail',
  'iron_pass_fail',
  'copper_pass_fail',
  'aluminium_pass_fail',
  'calcium_pass_fail',
  'sentinel_x100_pass_fail',
  'ph_pass_fail',
  'installer_name',
  'company_name',
  'company_address1',
  'date_reported',
  'project_id',
  'boiler_id',
  'system_age',
  'site_address',
  'pack_reference_number',
  'customer_id',
  'sentinel_x100_result',
  'mains_cl_result',
  'pass_fail',
  'company_address2',
  'company_town',
  'company_county',
  'company_postcode',
  'property_number',
  'street',
  'town_city',
  'county',
  'postcode',
  'system_6_months',
];

$sql = 'SELECT ' . implode(',', $columns) . ' FROM eck_test_entity ORDER BY id ASC';
$result = $mysqli->query($sql, MYSQLI_USE_RESULT);

if ($result === false) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$output_path = '/var/www/html/sentinel11/test_entities_d7.csv';
$fp = fopen($output_path, 'w');
if (!$fp) {
  fwrite(STDERR, "Unable to open {$output_path} for writing.\n");
  $result->close();
  $mysqli->close();
  exit(1);
}

fputcsv($fp, $columns);

while ($row = $result->fetch_assoc()) {
  $csv_row = [];
  foreach ($columns as $column) {
    $value = $row[$column] ?? '';
    if (is_string($value)) {
      $value = normalizeText($value);
    }
    $csv_row[] = $value;
  }
  fputcsv($fp, $csv_row);
}

$result->close();
$mysqli->close();
fclose($fp);

print "Exported test entities to {$output_path}\n";

/**
 * Normalize text values for CSV output.
 */
function normalizeText($value) {
  $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
  return trim(preg_replace('/\s+/', ' ', $value));
}

