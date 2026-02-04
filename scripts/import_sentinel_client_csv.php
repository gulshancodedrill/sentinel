<?php

/**
 * Import sentinel_client entities from CSV file.
 *
 * This script reads a CSV file exported from prod30 and imports/updates
 * sentinel_client entities in Drupal 11 using the entity API.
 *
 * Usage:
 *   drush scr scripts/import_sentinel_client_csv.php [csv_file_path] [truncate]
 *
 *   If csv_file_path is not provided, defaults to scripts/sentinel_client_export.csv
 *   Use "truncate" (without --) to clear all existing sentinel_client records before import
 *
 * Example:
 *   drush scr scripts/import_sentinel_client_csv.php
 *   drush scr scripts/import_sentinel_client_csv.php scripts/sentinel_client_export.csv
 *   drush scr scripts/import_sentinel_client_csv.php truncate
 *   drush scr scripts/import_sentinel_client_csv.php /path/to/file.csv truncate
 */

use Drupal\sentinel_portal_entities\Entity\SentinelClient;

// Parse command line arguments
$csv_path = __DIR__ . '/sentinel_client_export.csv';
$truncate = false;

// Get script arguments (check both $argv and $GLOBALS['argv'])
$script_args = [];
if (isset($argv) && is_array($argv)) {
  $script_args = $argv;
}
elseif (isset($GLOBALS['argv']) && is_array($GLOBALS['argv'])) {
  $script_args = $GLOBALS['argv'];
}

// Parse arguments
foreach ($script_args as $arg) {
  // Skip script name itself
  if ($arg === __FILE__ || strpos($arg, 'import_sentinel_client_csv.php') !== false) {
    continue;
  }
  
  // Check for truncate flag (without -- to avoid Drush interception)
  if (strtolower($arg) === 'truncate' || $arg === '--truncate') {
    $truncate = true;
  }
  // Check if argument is a file path
  elseif (!str_starts_with($arg, '--')) {
    // Try the argument as a file path
    $test_path = $arg;
    
    // If relative path, try relative to script directory first
    if (!file_exists($test_path) && !str_starts_with($test_path, '/')) {
      $test_path = __DIR__ . '/' . $test_path;
    }
    
    // If still doesn't exist, try relative to current working directory
    if (!file_exists($test_path) && getcwd()) {
      $test_path = getcwd() . '/' . $arg;
    }
    
    if (file_exists($test_path)) {
      $csv_path = $test_path;
    }
    elseif (!str_starts_with($arg, '/') && $arg !== 'truncate') {
      // Accept as path even if doesn't exist yet (will be checked later)
      $csv_path = $arg;
    }
  }
}

// Resolve absolute path - try multiple locations
if (!file_exists($csv_path)) {
  // Try relative to script directory
  if (!str_starts_with($csv_path, '/')) {
    $test_path = __DIR__ . '/' . $csv_path;
    if (file_exists($test_path)) {
      $csv_path = $test_path;
    }
  }
  
  // Try relative to current working directory
  if (!file_exists($csv_path) && getcwd()) {
    $test_path = getcwd() . '/' . basename($csv_path);
    if (file_exists($test_path)) {
      $csv_path = $test_path;
    }
  }
}

if (!file_exists($csv_path)) {
  fwrite(STDERR, "CSV file not found: {$csv_path}\n");
  exit(1);
}

$csv_path = realpath($csv_path);

print "Reading CSV file: {$csv_path}\n";

// Open CSV file
$fp = fopen($csv_path, 'r');
if (!$fp) {
  fwrite(STDERR, "Unable to open CSV file: {$csv_path}\n");
  exit(1);
}

// Read header row
$headers = fgetcsv($fp);
if ($headers === false) {
  fwrite(STDERR, "Unable to read CSV header.\n");
  fclose($fp);
  exit(1);
}

// Trim headers
$headers = array_map('trim', $headers);

// Required columns (must exist)
$required_columns = ['cid', 'name', 'email'];
$missing_required = array_diff($required_columns, $headers);
if (!empty($missing_required)) {
  fwrite(STDERR, "CSV missing required columns: " . implode(', ', $missing_required) . "\n");
  fclose($fp);
  exit(1);
}

// Optional columns (will be handled if missing)
$optional_columns = ['uuid', 'uid', 'api_key', 'global_access', 'send_pending', 'ucr', 'company', 'created', 'updated'];
print "Found columns: " . implode(', ', $headers) . "\n";

// Create header index map
$header_index = array_flip($headers);

// Get entity storage
$storage = \Drupal::entityTypeManager()->getStorage('sentinel_client');
$uuid_service = \Drupal::service('uuid');
$connection = \Drupal::database();
$logger = \Drupal::logger('sentinel_client_import');

// Truncate table if requested
if ($truncate) {
  print "Truncating sentinel_client table...\n";
  $connection->truncate('sentinel_client')->execute();
  $storage->resetCache();
  print "Table truncated.\n\n";
}

$inserted = 0;
$updated = 0;
$errors = 0;
$row_count = 0;

print "Starting import...\n\n";

/**
 * Convert date value from CSV to Unix timestamp.
 *
 * Handles various formats:
 * - Year only (e.g., "2016") -> January 1st of that year
 * - Unix timestamp (numeric)
 * - Date strings (various formats)
 *
 * @param string|int|null $value
 *   The date value from CSV.
 *
 * @return int|null
 *   Unix timestamp or NULL if invalid.
 */
function convertToTimestamp($value) {
  if (empty($value) && $value !== '0') {
    return NULL;
  }
  
  // If already a valid Unix timestamp (numeric and reasonable range)
  if (is_numeric($value)) {
    $timestamp = (int) $value;
    // Check if it's a valid Unix timestamp (between 1970 and 2100)
    if ($timestamp > 0 && $timestamp < 4102444800) {
      // If it's just a year (4 digits), convert to timestamp
      if ($timestamp >= 1970 && $timestamp <= 2100 && strlen((string)$value) <= 4) {
        // It's a year, convert to January 1st of that year
        return mktime(0, 0, 0, 1, 1, $timestamp);
      }
      return $timestamp;
    }
  }
  
  // Try to parse as date string
  $value = trim((string) $value);
  if (empty($value)) {
    return NULL;
  }
  
  // If it's just a 4-digit year
  if (preg_match('/^\d{4}$/', $value)) {
    $year = (int) $value;
    if ($year >= 1970 && $year <= 2100) {
      return mktime(0, 0, 0, 1, 1, $year);
    }
  }
  
  // Try various date formats - try most specific first
  $formats = [
    'Y-m-d H:i:s',      // 2015-11-20 16:55:00
    'Y-m-d H:i:s.u',    // With microseconds
    'Y-m-d\TH:i:s',     // ISO format with T
    'Y-m-d\TH:i:s\Z',   // ISO format with Z
    'Y-m-d\TH:i:sP',    // ISO format with timezone
    'Y-m-d',            // Date only
    'd/m/Y H:i:s',      // European format with time
    'd/m/Y',            // European date only
    'm/d/Y H:i:s',      // US format with time
    'm/d/Y',            // US date only
  ];
  
  foreach ($formats as $format) {
    $date = \DateTime::createFromFormat($format, $value);
    if ($date !== false) {
      // Check if all parts were parsed (not just partial match)
      $errors = \DateTime::getLastErrors();
      if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
        return $date->getTimestamp();
      }
    }
  }
  
  // Try strtotime as last resort
  $timestamp = strtotime($value);
  if ($timestamp !== false) {
    return $timestamp;
  }
  
  return NULL;
}

// Process each row
while (($row = fgetcsv($fp)) !== false) {
  $row_count++;
  
  // Skip empty rows
  if (empty(array_filter($row))) {
    continue;
  }
  
  // Map row data to headers
  $data = [];
  foreach ($headers as $index => $header) {
    $data[$header] = isset($row[$index]) ? trim($row[$index]) : '';
  }
  
  // Get cid (primary key)
  $cid = isset($data['cid']) && $data['cid'] !== '' ? (int) $data['cid'] : 0;
  if ($cid <= 0) {
    $logger->warning('Skipping row @row: invalid cid', ['@row' => $row_count]);
    $errors++;
    continue;
  }
  
  try {
    // Clear cache before checking
    $storage->resetCache([$cid]);
    
    // Check if entity exists
    $entity = $storage->load($cid);
    $is_new = $entity === NULL;
    
    if ($is_new) {
      // Create new entity - need to insert directly to preserve cid
      // Generate UUID if not provided in CSV (prod30 may not have uuid column)
      $uuid = (!empty($data['uuid']) && isset($header_index['uuid'])) ? $data['uuid'] : $uuid_service->generate();
      $email = !empty($data['email']) ? $data['email'] : '';
      
      // Validate required fields - email is mandatory
      if (empty($email)) {
        $logger->warning('Skipping row @row (cid: @cid): missing required field (email)', [
          '@row' => $row_count,
          '@cid' => $cid,
        ]);
        $errors++;
        continue;
      }
      
      // Use name from CSV, or fallback to email or "Client {cid}" if empty
      $name = !empty($data['name']) ? $data['name'] : (!empty($email) ? $email : 'Client ' . $cid);
      
      // Insert directly into database to preserve cid
      // Convert created/updated to timestamps
      $created = convertToTimestamp($data['created'] ?? '');
      if ($created === NULL) {
        $created = \Drupal::time()->getRequestTime();
      }
      
      $updated = convertToTimestamp($data['updated'] ?? '');
      if ($updated === NULL) {
        $updated = $created;
      }
      
      $connection->insert('sentinel_client')
        ->fields([
          'cid' => $cid,
          'uuid' => $uuid,
          'name' => $name,
          'email' => $email,
          'uid' => !empty($data['uid']) ? (int) $data['uid'] : NULL,
          'api_key' => !empty($data['api_key']) ? $data['api_key'] : NULL,
          'global_access' => isset($data['global_access']) && $data['global_access'] !== '' ? (int) $data['global_access'] : 0,
          'send_pending' => isset($data['send_pending']) && $data['send_pending'] !== '' ? (int) $data['send_pending'] : 0,
          'ucr' => !empty($data['ucr']) ? (int) $data['ucr'] : NULL,
          'company' => !empty($data['company']) ? $data['company'] : NULL,
          'created' => $created,
          'updated' => $updated,
        ])
        ->execute();
      
      // Clear cache and load entity
      $storage->resetCache([$cid]);
      $entity = $storage->load($cid);
      
      if (!$entity) {
        throw new \Exception("Failed to create entity with cid {$cid}");
      }
      
      $inserted++;
      $logger->info('Created sentinel_client entity cid @cid', ['@cid' => $cid]);
    }
    else {
      // Update existing entity - only update fields that have values in CSV
      // For required fields (name, email), only update if CSV has a value
      if (!empty($data['name'])) {
        $entity->set('name', $data['name']);
      }
      // If name is empty in CSV but required, skip update or use email as fallback
      elseif (empty($entity->get('name')->value)) {
        // Use email as fallback for name if name is empty
        $fallback_name = !empty($data['email']) ? $data['email'] : 'Client ' . $cid;
        $entity->set('name', $fallback_name);
        $logger->warning('Using fallback name for cid @cid: @name', [
          '@cid' => $cid,
          '@name' => $fallback_name,
        ]);
      }
      
      if (!empty($data['email'])) {
        $entity->set('email', $data['email']);
      }
      
      if (isset($data['uid']) && $data['uid'] !== '') {
        $entity->set('uid', (int) $data['uid']);
      }
      
      if (isset($data['api_key']) && $data['api_key'] !== '') {
        $entity->set('api_key', $data['api_key']);
      }
      
      if (isset($data['global_access']) && $data['global_access'] !== '') {
        $entity->set('global_access', (bool) ((int) $data['global_access']));
      }
      
      if (isset($data['send_pending']) && $data['send_pending'] !== '') {
        $entity->set('send_pending', (bool) ((int) $data['send_pending']));
      }
      
      // UCR is read-only, but we can set it if it's empty
      if (!empty($data['ucr']) && empty($entity->get('ucr')->value)) {
        $entity->set('ucr', (int) $data['ucr']);
      }
      
      if (isset($data['company']) && $data['company'] !== '') {
        $entity->set('company', $data['company']);
      }
      
      // Update timestamps if provided - convert to proper timestamps
      if (isset($data['created']) && $data['created'] !== '') {
        $created_timestamp = convertToTimestamp($data['created']);
        if ($created_timestamp !== NULL) {
          $entity->set('created', $created_timestamp);
        }
      }
      if (isset($data['updated']) && $data['updated'] !== '') {
        $updated_timestamp = convertToTimestamp($data['updated']);
        if ($updated_timestamp !== NULL) {
          $entity->set('updated', $updated_timestamp);
        }
      }
      
      $entity->save();
      $updated++;
      $logger->info('Updated sentinel_client entity cid @cid', ['@cid' => $cid]);
    }
    
    // Progress indicator
    if (($inserted + $updated + $errors) % 100 === 0) {
      print "Processed: " . ($inserted + $updated + $errors) . " rows (Inserted: {$inserted}, Updated: {$updated}, Errors: {$errors})\n";
    }
  }
  catch (\Exception $e) {
    $errors++;
    $logger->error('Failed to import sentinel_client cid @cid: @message', [
      '@cid' => $cid,
      '@message' => $e->getMessage(),
    ]);
    
    if ($errors <= 10) {
      print "Error processing cid {$cid}: {$e->getMessage()}\n";
    }
  }
}

fclose($fp);

// Clear entity cache after bulk operations
$storage->resetCache();

print "\n=== Import Complete ===\n";
print "Total rows processed: {$row_count}\n";
print "Inserted: {$inserted}\n";
print "Updated: {$updated}\n";
print "Errors: {$errors}\n";
