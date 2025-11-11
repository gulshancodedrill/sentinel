<?php

/**
 * Export Drupal 7 condition entities (ECK) to CSV.
 *
 * Usage:
 *   php sentinel/scripts/export_d7_condition_entities.php
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

// Ensure UTF-8 for text exports.
$mysqli->set_charset('utf8mb4');

$sql = <<<SQL
SELECT
  base.id,
  base.type,
  base.uid,
  base.created,
  base.changed,
  base.language,
  num.field_condition_event_number_value AS event_number,
  elem.field_condition_event_element_value AS event_element,
  str.field_condition_event_string_value AS event_string,
  comment.field_event_individual_comment_value AS event_individual_comment,
  recommend.field_individual_recommend_value AS event_individual_recommendation,
  whitespace.field_number_of_white_spaces_value AS number_of_white_spaces,
  result.field_condition_event_result_tid AS condition_event_result_tid
FROM eck_condition_entity base
LEFT JOIN field_data_field_condition_event_number AS num
  ON num.entity_type = 'condition_entity'
  AND num.bundle = 'condition_entity'
  AND num.entity_id = base.id
  AND num.language = 'und'
  AND num.delta = 0
LEFT JOIN field_data_field_condition_event_element AS elem
  ON elem.entity_type = 'condition_entity'
  AND elem.bundle = 'condition_entity'
  AND elem.entity_id = base.id
  AND elem.language = 'und'
  AND elem.delta = 0
LEFT JOIN field_data_field_condition_event_string AS str
  ON str.entity_type = 'condition_entity'
  AND str.bundle = 'condition_entity'
  AND str.entity_id = base.id
  AND str.language = 'und'
  AND str.delta = 0
LEFT JOIN field_data_field_event_individual_comment AS comment
  ON comment.entity_type = 'condition_entity'
  AND comment.bundle = 'condition_entity'
  AND comment.entity_id = base.id
  AND comment.language = 'und'
  AND comment.delta = 0
LEFT JOIN field_data_field_individual_recommend AS recommend
  ON recommend.entity_type = 'condition_entity'
  AND recommend.bundle = 'condition_entity'
  AND recommend.entity_id = base.id
  AND recommend.language = 'und'
  AND recommend.delta = 0
LEFT JOIN field_data_field_number_of_white_spaces AS whitespace
  ON whitespace.entity_type = 'condition_entity'
  AND whitespace.bundle = 'condition_entity'
  AND whitespace.entity_id = base.id
  AND whitespace.language = 'und'
  AND whitespace.delta = 0
LEFT JOIN field_data_field_condition_event_result AS result
  ON result.entity_type = 'condition_entity'
  AND result.bundle = 'condition_entity'
  AND result.entity_id = base.id
  AND result.language = 'und'
  AND result.delta = 0
WHERE base.type = 'condition_entity'
ORDER BY base.id ASC
SQL;

$result = $mysqli->query($sql, MYSQLI_USE_RESULT);
if ($result === false) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$output_path = '/var/www/html/sentinel11/condition_entities_d7.csv';
$fp = fopen($output_path, 'w');
if (!$fp) {
  fwrite(STDERR, "Unable to open {$output_path} for writing.\n");
  $result->close();
  $mysqli->close();
  exit(1);
}

$headers = [
  'id',
  'type',
  'uid',
  'created',
  'changed',
  'language',
  'event_number',
  'event_element',
  'event_string',
  'event_individual_comment',
  'event_individual_recommendation',
  'number_of_white_spaces',
  'condition_event_result_tid',
];
fputcsv($fp, $headers);

while ($row = $result->fetch_assoc()) {
  // Normalize text: convert newlines to spaces for CSV readability.
  $row['event_string'] = normalizeText($row['event_string']);
  $row['event_individual_comment'] = normalizeText($row['event_individual_comment']);
  $row['event_individual_recommendation'] = normalizeText($row['event_individual_recommendation']);

  $csv_row = [];
  foreach ($headers as $header) {
    $csv_row[] = isset($row[$header]) ? $row[$header] : '';
  }
  fputcsv($fp, $csv_row);
}

$result->close();
$mysqli->close();
fclose($fp);

print "Exported condition entities to {$output_path}\n";

/**
 * Replace newlines with spaces and trim extra whitespace.
 */
function normalizeText($value) {
  if ($value === null) {
    return '';
  }
  $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);
  // Collapse consecutive whitespace to a single space.
  return trim(preg_replace('/\s+/', ' ', $value));
}

