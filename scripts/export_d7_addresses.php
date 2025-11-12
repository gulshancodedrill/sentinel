<?php

/**
 * Export Drupal 7 address entities and related lookup tables to CSV files.
 *
 * Usage:
 *   php sentinel/scripts/export_d7_addresses.php
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

// Ensure UTF-8 and allow large group concatenations.
$mysqli->set_charset('utf8mb4');
$mysqli->query('SET SESSION group_concat_max_len = 1048576');

$output_path = '/var/www/html/sentinel11/address_entities_d7.csv';

exportAllAddressEntities($mysqli, $output_path);

$mysqli->close();

/**
 * Export all address entities (site and company) into a single CSV.
 *
 * @param \mysqli $mysqli
 *   The database connection.
 * @param string $outputPath
 *   Destination CSV path.
 */
function exportAllAddressEntities(mysqli $mysqli, $outputPath) {
  $sql = <<<SQL
SELECT *
FROM (
  SELECT
    a.id,
    a.type,
    addr.field_address_country AS country,
    addr.field_address_administrative_area AS administrative_area,
    addr.field_address_sub_administrative_area AS sub_administrative_area,
    addr.field_address_locality AS locality,
    addr.field_address_dependent_locality AS dependent_locality,
    addr.field_address_postal_code AS postal_code,
    addr.field_address_thoroughfare AS thoroughfare,
    addr.field_address_premise AS premise,
    addr.field_address_sub_premise AS sub_premise,
    addr.field_address_organisation_name AS organisation_name,
    addr.field_address_name_line AS name_line,
    addr.field_address_first_name AS first_name,
    addr.field_address_last_name AS last_name,
    addr.field_address_data AS address_data,
    COUNT(DISTINCT map.entity_id) AS sample_count,
    GROUP_CONCAT(DISTINCT map.entity_id ORDER BY map.entity_id ASC) AS sample_ids,
    GROUP_CONCAT(DISTINCT ss.pack_reference_number ORDER BY ss.pack_reference_number ASC) AS pack_reference_numbers,
    GROUP_CONCAT(DISTINCT ss.lab_ref ORDER BY ss.lab_ref ASC) AS lab_refs
  FROM eck_address a
  LEFT JOIN field_data_field_address addr
    ON addr.entity_type = 'address'
    AND addr.bundle = a.type
    AND addr.entity_id = a.id
    AND addr.language = 'und'
    AND addr.deleted = 0
    AND addr.delta = 0
  LEFT JOIN field_data_field_sentinel_sample_address map
    ON map.field_sentinel_sample_address_target_id = a.id
    AND map.entity_type = 'sentinel_sample'
    AND map.bundle = 'sentinel_sample'
    AND map.language = 'und'
    AND map.deleted = 0
    AND map.delta = 0
  LEFT JOIN sentinel_sample ss
    ON ss.pid = map.entity_id
  WHERE a.type = 'address'
  GROUP BY a.id

  UNION ALL

  SELECT
    a.id,
    a.type,
    addr.field_address_country AS country,
    addr.field_address_administrative_area AS administrative_area,
    addr.field_address_sub_administrative_area AS sub_administrative_area,
    addr.field_address_locality AS locality,
    addr.field_address_dependent_locality AS dependent_locality,
    addr.field_address_postal_code AS postal_code,
    addr.field_address_thoroughfare AS thoroughfare,
    addr.field_address_premise AS premise,
    addr.field_address_sub_premise AS sub_premise,
    addr.field_address_organisation_name AS organisation_name,
    addr.field_address_name_line AS name_line,
    addr.field_address_first_name AS first_name,
    addr.field_address_last_name AS last_name,
    addr.field_address_data AS address_data,
    COUNT(DISTINCT map.entity_id) AS sample_count,
    GROUP_CONCAT(DISTINCT map.entity_id ORDER BY map.entity_id ASC) AS sample_ids,
    GROUP_CONCAT(DISTINCT ss.pack_reference_number ORDER BY ss.pack_reference_number ASC) AS pack_reference_numbers,
    GROUP_CONCAT(DISTINCT ss.lab_ref ORDER BY ss.lab_ref ASC) AS lab_refs
  FROM eck_address a
  LEFT JOIN field_data_field_address addr
    ON addr.entity_type = 'address'
    AND addr.bundle = a.type
    AND addr.entity_id = a.id
    AND addr.language = 'und'
    AND addr.deleted = 0
    AND addr.delta = 0
  LEFT JOIN field_data_field_company_address map
    ON map.field_company_address_target_id = a.id
    AND map.entity_type = 'sentinel_sample'
    AND map.bundle = 'sentinel_sample'
    AND map.language = 'und'
    AND map.deleted = 0
    AND map.delta = 0
  LEFT JOIN sentinel_sample ss
    ON ss.pid = map.entity_id
  WHERE a.type = 'company_address'
  GROUP BY a.id
) combined
ORDER BY type, id
SQL;

  $headers = [
    'id',
    'type',
    'country',
    'administrative_area',
    'sub_administrative_area',
    'locality',
    'dependent_locality',
    'postal_code',
    'thoroughfare',
    'premise',
    'sub_premise',
    'organisation_name',
    'name_line',
    'first_name',
    'last_name',
    'address_data',
    'sample_count',
    'sample_ids',
    'pack_reference_numbers',
    'lab_refs',
  ];

  $normalize = [
    'administrative_area',
    'sub_administrative_area',
    'locality',
    'dependent_locality',
    'postal_code',
    'thoroughfare',
    'premise',
    'sub_premise',
    'organisation_name',
    'name_line',
    'first_name',
    'last_name',
    'address_data',
    'pack_reference_numbers',
    'lab_refs',
  ];

  exportQueryResult(
    $mysqli,
    $sql,
    $headers,
    $outputPath,
    'address entities',
    $normalize
  );
}

/**
 * Execute a SQL query and export the result to CSV.
 *
 * @param \mysqli $mysqli
 *   The database connection.
 * @param string $sql
 *   The SQL query to execute.
 * @param array $headers
 *   Ordered list of column headers.
 * @param string $outputPath
 *   Destination CSV path.
 * @param string $label
 *   Label used for console messages.
 * @param array $normalizeColumns
 *   Columns that should have whitespace normalised.
 */
function exportQueryResult(mysqli $mysqli, $sql, array $headers, $outputPath, $label, array $normalizeColumns = []) {
  $result = $mysqli->query($sql, MYSQLI_USE_RESULT);
  if ($result === false) {
    fwrite(STDERR, "Query for {$label} failed: {$mysqli->error}\n");
    return;
  }

  $fp = fopen($outputPath, 'w');
  if (!$fp) {
    fwrite(STDERR, "Unable to open {$outputPath} for writing.\n");
    $result->close();
    return;
  }

  fputcsv($fp, $headers);

  $row_count = 0;
  while ($row = $result->fetch_assoc()) {
    $csv_row = [];
    foreach ($headers as $column) {
      $value = isset($row[$column]) ? $row[$column] : '';
      if ($value === null) {
        $value = '';
      }

      if (in_array($column, $normalizeColumns, TRUE)) {
        $value = normalizeText($value);
      }

      $csv_row[] = $value;
    }
    fputcsv($fp, $csv_row);
    $row_count++;
  }

  $result->close();
  fclose($fp);

  print "Exported {$row_count} rows for {$label} to {$outputPath}\n";
}

/**
 * Replace newlines with spaces and collapse excessive whitespace.
 *
 * @param string $value
 *   The original value.
 *
 * @return string
 *   The normalised value.
 */
function normalizeText($value) {
  if ($value === '') {
    return '';
  }

  $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
  return trim(preg_replace('/\s+/', ' ', $value));
}


