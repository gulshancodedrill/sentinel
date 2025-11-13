<?php

/**
 * Export Drupal 7 Sentinel sample entities to CSV.
 *
 * Usage:
 *   php sentinel/scripts/export_d7_sentinel_samples.php
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
  'pid',
  'vid',
  'pack_reference_number',
  'project_id',
  'installer_name',
  'installer_email',
  'company_name',
  'company_email',
  'company_address1',
  'company_address2',
  'company_town',
  'company_county',
  'company_postcode',
  'company_tel',
  'system_location',
  'system_age',
  'system_6_months',
  'uprn',
  'property_number',
  'street',
  'town_city',
  'county',
  'postcode',
  'landlord',
  'boiler_manufacturer',
  'boiler_id',
  'boiler_type',
  'engineers_code',
  'service_call_id',
  'date_installed',
  'date_sent',
  'date_booked',
  'date_processed',
  'date_reported',
  'fileid',
  'filename',
  'client_id',
  'client_name',
  'customer_id',
  'lab_ref',
  'pack_type',
  'card_complete',
  'on_hold',
  'pass_fail',
  'appearance_result',
  'appearance_pass_fail',
  'mains_cond_result',
  'sys_cond_result',
  'cond_pass_fail',
  'mains_cl_result',
  'sys_cl_result',
  'cl_pass_fail',
  'iron_result',
  'iron_pass_fail',
  'copper_result',
  'copper_pass_fail',
  'aluminium_result',
  'aluminium_pass_fail',
  'mains_calcium_result',
  'sys_calcium_result',
  'calcium_pass_fail',
  'ph_result',
  'ph_pass_fail',
  'sentinel_x100_result',
  'sentinel_x100_pass_fail',
  'molybdenum_result',
  'molybdenum_pass_fail',
  'boron_result',
  'boron_pass_fail',
  'manganese_result',
  'manganese_pass_fail',
  'nitrate_result',
  'mob_ratio',
  'created',
  'updated',
  'ucr',
  'installer_company',
  'old_pack_reference_number',
  'duplicate_of',
  'legacy',
  'api_created_by',
  'sample_address_id',
  'company_address_id',
];

$sql = <<<SQL
SELECT
  s.pid,
  s.vid,
  s.pack_reference_number,
  s.project_id,
  s.installer_name,
  s.installer_email,
  s.company_name,
  s.company_email,
  s.company_address1,
  s.company_address2,
  s.company_town,
  s.company_county,
  s.company_postcode,
  s.company_tel,
  s.system_location,
  s.system_age,
  s.system_6_months,
  s.uprn,
  s.property_number,
  s.street,
  s.town_city,
  s.county,
  s.postcode,
  s.landlord,
  s.boiler_manufacturer,
  s.boiler_id,
  s.boiler_type,
  s.engineers_code,
  s.service_call_id,
  s.date_installed,
  s.date_sent,
  s.date_booked,
  s.date_processed,
  s.date_reported,
  s.fileid,
  s.filename,
  s.client_id,
  s.client_name,
  s.customer_id,
  s.lab_ref,
  s.pack_type,
  s.card_complete,
  s.on_hold,
  s.pass_fail,
  s.appearance_result,
  s.appearance_pass_fail,
  s.mains_cond_result,
  s.sys_cond_result,
  s.cond_pass_fail,
  s.mains_cl_result,
  s.sys_cl_result,
  s.cl_pass_fail,
  s.iron_result,
  s.iron_pass_fail,
  s.copper_result,
  s.copper_pass_fail,
  s.aluminium_result,
  s.aluminium_pass_fail,
  s.mains_calcium_result,
  s.sys_calcium_result,
  s.calcium_pass_fail,
  s.ph_result,
  s.ph_pass_fail,
  s.sentinel_x100_result,
  s.sentinel_x100_pass_fail,
  s.molybdenum_result,
  s.molybdenum_pass_fail,
  s.boron_result,
  s.boron_pass_fail,
  s.manganese_result,
  s.manganese_pass_fail,
  s.nitrate_result,
  s.mob_ratio,
  s.created,
  s.updated,
  s.ucr,
  s.installer_company,
  s.old_pack_reference_number,
  s.duplicate_of,
  s.legacy,
  s.api_created_by,
  sample_addr.field_sentinel_sample_address_target_id AS sample_address_id,
  company_addr.field_company_address_target_id AS company_address_id
FROM sentinel_sample s
LEFT JOIN field_data_field_sentinel_sample_address sample_addr
  ON sample_addr.entity_type = 'sentinel_sample'
  AND sample_addr.bundle = 'sentinel_sample'
  AND sample_addr.entity_id = s.pid
  AND sample_addr.language = 'und'
  AND sample_addr.deleted = 0
  AND sample_addr.delta = 0
LEFT JOIN field_data_field_company_address company_addr
  ON company_addr.entity_type = 'sentinel_sample'
  AND company_addr.bundle = 'sentinel_sample'
  AND company_addr.entity_id = s.pid
  AND company_addr.language = 'und'
  AND company_addr.deleted = 0
  AND company_addr.delta = 0
ORDER BY s.pid ASC
SQL;

$result = $mysqli->query($sql, MYSQLI_USE_RESULT);
if ($result === false) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$output_path = '/var/www/html/sentinel11/sentinel_samples_d7.csv';
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

print "Exported sentinel sample entities to {$output_path}\n";

/**
 * Normalize text values for CSV output.
 */
function normalizeText($value) {
  $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
  $value = str_replace(';', ', ', $value);
  return trim(preg_replace('/\s+/', ' ', $value));
}


