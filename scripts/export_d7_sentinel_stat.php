<?php

/**
 * Export Drupal 7 sentinel_stat data to CSV.
 *
 * Filters to sentinel_stat IDs greater than a given threshold.
 *
 * Usage:
 *   php scripts/export_d7_sentinel_stat.php
 */

$host = 'localhost';
$port = 3306;
$username = 'root';
$password = 'infotech';
$database = 'prod30';

$min_id = 14582926;
$output_path = '/var/www/html/sentinel11/sentinel_stat_export_after_14582926.csv';

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}
$mysqli->set_charset('utf8mb4');

$sql = <<<SQL
SELECT
  b.id AS id,
  b.type AS type,
  FROM_UNIXTIME(b.created) AS created,
  FROM_UNIXTIME(b.changed) AS changed,
  pr.field_stat_pack_reference_id_target_id AS pack_reference_id,
  en.field_stat_element_name_value AS element_name,
  ic.field_stat_individual_comment_value AS individual_comment,
  rec.field_stat_recommendation_value AS recommendation,
  res.field_stat_result_tid AS result_tid
FROM eck_sentinel_stat b
LEFT JOIN field_data_field_stat_pack_reference_id pr
  ON pr.entity_id = b.id AND pr.bundle = 'sentinel_stat' AND pr.deleted = 0
LEFT JOIN field_data_field_stat_element_name en
  ON en.entity_id = b.id AND en.bundle = 'sentinel_stat' AND en.deleted = 0
LEFT JOIN field_data_field_stat_individual_comment ic
  ON ic.entity_id = b.id AND ic.bundle = 'sentinel_stat' AND ic.deleted = 0
LEFT JOIN field_data_field_stat_recommendation rec
  ON rec.entity_id = b.id AND rec.bundle = 'sentinel_stat' AND rec.deleted = 0
LEFT JOIN field_data_field_stat_result res
  ON res.entity_id = b.id AND res.bundle = 'sentinel_stat' AND res.deleted = 0
WHERE b.id > {$min_id}
ORDER BY b.id ASC
SQL;

$result = $mysqli->query($sql, MYSQLI_USE_RESULT);
if ($result === false) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$fp = fopen($output_path, 'w');
if (!$fp) {
  fwrite(STDERR, "Unable to open {$output_path} for writing.\n");
  $result->close();
  $mysqli->close();
  exit(1);
}

fputcsv($fp, [
  'id',
  'type',
  'created',
  'changed',
  'pack_reference_id',
  'element_name',
  'individual_comment',
  'recommendation',
  'result_tid',
]);

while ($row = $result->fetch_assoc()) {
  fputcsv($fp, $row);
}

$result->close();
$mysqli->close();
fclose($fp);

print "Exported sentinel_stat data to {$output_path}\n";
