<?php

/**
 * Enqueue a specific fid from file_managed CSV.
 * 
 * Usage: php scripts/enqueue_specific_fid.php [csv_path] [fid]
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once __DIR__ . '/../vendor/autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();

$csv_path = $argv[1] ?? '';
$target_fid = isset($argv[2]) ? (int) $argv[2] : 0;

if (empty($csv_path) || $target_fid <= 0) {
  fwrite(STDERR, "Usage: php scripts/enqueue_specific_fid.php [csv_path] [fid]\n");
  fwrite(STDERR, "Example: php scripts/enqueue_specific_fid.php /path/to/file_managed_d7.csv 611161\n");
  exit(1);
}

if (!file_exists($csv_path)) {
  fwrite(STDERR, "CSV file not found: {$csv_path}\n");
  exit(1);
}

$queue = \Drupal::service('queue')->get('sentinel_data_import.file_managed');
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

$fid_index = array_search('fid', $headers);
if ($fid_index === FALSE) {
  fwrite(STDERR, "CSV missing 'fid' column.\n");
  exit(1);
}

$uri_index = array_search('uri', $headers);
if ($uri_index === FALSE) {
  fwrite(STDERR, "CSV missing 'uri' column.\n");
  exit(1);
}

$found = false;
$row_count = 0;

while (($row = fgetcsv($file)) !== FALSE) {
  $row_count++;
  if (count($row) < count($headers)) {
    continue;
  }
  
  $fid = isset($row[$fid_index]) ? (int) trim($row[$fid_index]) : 0;
  
  if ($fid === $target_fid) {
    $found = true;
    
    // Build record
    $record = [];
    foreach ($headers as $position => $column) {
      if ($column === '') {
        continue;
      }
      $raw = $row[$position] ?? '';
      $record[$column] = trim((string) $raw);
    }
    
    // Ensure fid is integer
    $record['fid'] = (int) $record['fid'];
    
    // Get config for source resolution
    $config = \Drupal::config('sentinel_data_import.settings');
    $private_base = $config->get('private_source_base');
    $public_base = $config->get('public_source_base');
    
    // Resolve source path
    $uri = $record['uri'] ?? '';
    $source_info = null;
    
    if ($uri) {
      $scheme = explode('://', $uri)[0] ?? '';
      $target = explode('://', $uri, 2)[1] ?? '';
      
      switch ($scheme) {
        case 'private':
          if ($private_base) {
            $source = rtrim($private_base, '/') . '/' . ltrim($target, '/');
            if (is_readable($source)) {
              $source_info = [
                'source_path' => $source,
                'destination_uri' => 'private://' . ltrim($target, '/'),
              ];
            }
          }
          break;
          
        case 'public':
          if ($public_base) {
            $source = rtrim($public_base, '/') . '/' . ltrim($target, '/');
            if (is_readable($source)) {
              $source_info = [
                'source_path' => $source,
                'destination_uri' => 'public://' . ltrim($target, '/'),
              ];
            }
          }
          break;
      }
    }
    
    // If no source found, still enqueue with just URI
    if (!$source_info) {
      $source_info = ['destination_uri' => $uri];
      print "Source file not found for fid {$target_fid} ({$uri}). Will create entity with URI only.\n";
    }
    
    $queue->createItem($record + $source_info);
    print "Enqueued fid {$target_fid} (row {$row_count})\n";
    break;
  }
}

fclose($file);

if (!$found) {
  fwrite(STDERR, "Fid {$target_fid} not found in CSV after {$row_count} rows.\n");
  exit(1);
}

print "Successfully enqueued fid {$target_fid}.\n";
print "Run 'drush queue:run sentinel_data_import.file_managed' to process.\n";

