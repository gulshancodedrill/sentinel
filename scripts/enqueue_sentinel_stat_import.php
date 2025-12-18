<?php

/**
 * Enqueue sentinel_stat entities from CSV to the sentinel_data_import queue.
 * 
 * Usage: php scripts/enqueue_sentinel_stat_import.php [csv_file]
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once __DIR__ . '/../vendor/autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();

// Get CSV file path from command line or use default
$csv_path = $argv[1] ?? '/home/liveportal/public_html/web/modules/custom/sentinel_data_import/csv/sentinel_stat_export_after_14361631.csv';

if (!file_exists($csv_path)) {
  fwrite(STDERR, "CSV file not found: {$csv_path}\n");
  exit(1);
}

$queue = \Drupal::service('queue')->get('sentinel_data_import.sentinel_stat');
$file = fopen($csv_path, 'r');
if (!$file) {
  fwrite(STDERR, "Unable to open CSV file.\n");
  exit(1);
}

// Read header
$headers = fgetcsv($file);
if ($headers === FALSE) {
  fwrite(STDERR, "Unable to read CSV header.\n");
  exit(1);
}
$headers = array_map('trim', $headers);

$required_headers = [
  'id',
  'type',
  'created',
  'changed',
  'pack_reference_id',
  'element_name',
  'individual_comment',
  'recommendation',
  'result_tid',
];

$header_flip = array_flip($headers);
foreach ($required_headers as $column) {
  if (!isset($header_flip[$column])) {
    fwrite(STDERR, "Required column '{$column}' missing from CSV.\n");
    exit(1);
  }
}

function normalizeCsvValue($value) {
  if ($value === null || $value === '') {
    return '';
  }
  $value = str_replace(["\r\n", "\n", "\r"], ' ', (string) $value);
  $value = str_replace(';', ', ', $value);
  return trim(preg_replace('/\s+/', ' ', $value));
}

$queued = 0;
$skipped = 0;

while (($row = fgetcsv($file)) !== FALSE) {
  if (count($row) < count($headers)) {
    continue;
  }
  
  $record = [];
  foreach ($headers as $position => $column) {
    if ($column === '') {
      continue;
    }
    $raw = $row[$position] ?? '';
    $record[$column] = normalizeCsvValue($raw);
  }
  
  $id = isset($record['id']) ? (int) $record['id'] : 0;
  if ($id <= 0 || empty($record['type'])) {
    $skipped++;
    continue;
  }
  
  $record['id'] = $id;
  $queue->createItem($record);
  $queued++;
  
  if ($queued % 10000 === 0) {
    print "Queued {$queued} items...\n";
  }
}

fclose($file);

print "Enqueued {$queued} sentinel_stat entities (skipped {$skipped}).\n";
print "Run 'drush queue:run sentinel_data_import.sentinel_stat' to process.\n";

