<?php

/**
 * Import Drupal 7 Sentinel sample revisions to Drupal 11.
 * 
 * This script:
 * 1. Removes all current revisions from D11
 * 2. Gets revision records from sentinel_d7.sentinel_sample_revision
 * 3. Inserts revisions into D11 sentinel_sample_revision
 * 4. Updates vid in sentinel_sample table
 * 5. For fields sentinel_sample_hold_state_target_id, sentinel_company_address_target_id,
 *    and sentinel_sample_address_target_id, takes values from sentinel_sample table
 * 
 * Usage:
 *   php scripts/import_d7_revisions.php
 */

// Database configuration
// D7 PROD SOURCE
$d7_host = 'localhost';
$d7_port = 3306;
$d7_username = 'root';
$d7_password = 'infotech';
$d7_database = 'prod';

// D11 LOCAL TARGET
$d11_host = 'localhost';
$d11_port = 3306;
$d11_username = 'root';
$d11_password = 'infotech';
$d11_database = 'sentinel';

// Batch processing configuration
$batch_size = 10; // Process this many pids at a time
$limit_pids = NULL; // Set to a number to limit total pids (e.g., 100), or NULL to process all

// Connect to D7 database
$d7_mysqli = new mysqli($d7_host, $d7_username, $d7_password, $d7_database, $d7_port);
if ($d7_mysqli->connect_error) {
  fwrite(STDERR, "D7 Database connection failed: {$d7_mysqli->connect_error}\n");
  exit(1);
}
$d7_mysqli->set_charset('utf8mb4');

// Connect to D11 database
$d11_mysqli = new mysqli($d11_host, $d11_username, $d11_password, $d11_database, $d11_port);
if ($d11_mysqli->connect_error) {
  fwrite(STDERR, "D11 Database connection failed: {$d11_mysqli->connect_error}\n");
  $d7_mysqli->close();
  exit(1);
}
$d11_mysqli->set_charset('utf8mb4');

print "Starting revision import process...\n";
print "Batch size: {$batch_size} pids per batch\n";
if ($limit_pids !== NULL) {
  print "Total pids limit: {$limit_pids}\n";
} else {
  print "Processing all pids from D7.\n";
}

// Step 1: Get total count of pids to process
print "Step 1: Getting total count of pids to process...\n";
$count_query = "SELECT COUNT(DISTINCT r.pid) as total
FROM sentinel_sample_revision r
INNER JOIN sentinel_sample s ON s.pid = r.pid
WHERE s.created >= DATE_SUB(NOW(), INTERVAL 3 YEAR)";
if ($limit_pids !== NULL) {
  $count_query = "SELECT COUNT(DISTINCT pid) as total
FROM (
  SELECT DISTINCT r.pid AS pid
  FROM sentinel_sample_revision r
  INNER JOIN sentinel_sample s ON s.pid = r.pid
  WHERE s.created >= DATE_SUB(NOW(), INTERVAL 3 YEAR)
  ORDER BY r.pid
  LIMIT {$limit_pids}
) as limited";
}
$count_result = $d7_mysqli->query($count_query);
if ($count_result === false) {
  fwrite(STDERR, "Error getting pid count: {$d7_mysqli->error}\n");
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}
$total_pids = (int)$count_result->fetch_assoc()['total'];
$count_result->close();

if ($total_pids == 0) {
  print "No pids found to process.\n";
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(0);
}

$total_batches = ceil($total_pids / $batch_size);
print "Found {$total_pids} total pids to process in {$total_batches} batches.\n";

// Step 2: No global truncation; delete and replace per PID batch.
print "Step 2: Using per-PID delete/replace (no full truncation).\n";

// Step 3: Get all columns from D7 revision table
print "Step 3: Getting column list from D7 revision table...\n";
$columns_result = $d7_mysqli->query("SHOW COLUMNS FROM sentinel_sample_revision");
if ($columns_result === false) {
  fwrite(STDERR, "Error getting columns: {$d7_mysqli->error}\n");
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}

$d7_columns = [];
$exclude_columns = ['vid']; // vid is auto_increment in D11, so we exclude it from insert
while ($row = $columns_result->fetch_assoc()) {
  if (!in_array($row['Field'], $exclude_columns)) {
    $d7_columns[] = $row['Field'];
  }
}
$columns_result->close();

// Add the three special fields if they don't exist in D7 but exist in D11
$special_fields = ['sentinel_sample_hold_state_target_id', 'sentinel_company_address_target_id', 'sentinel_sample_address_target_id'];
foreach ($special_fields as $field) {
  if (!in_array($field, $d7_columns)) {
    $d7_columns[] = $field;
    print "Added special field {$field} to column list (not in D7 revision table).\n";
  }
}

print "Found " . count($d7_columns) . " columns to migrate.\n";

// Step 5: Get column types from D11 (once, before batch loop)
print "Step 5: Getting column types from D11...\n";
$d11_columns_result = $d11_mysqli->query("SHOW COLUMNS FROM sentinel_sample_revision");
if ($d11_columns_result === false) {
  fwrite(STDERR, "Error getting D11 columns: {$d11_mysqli->error}\n");
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}

$column_types = [];
while ($row = $d11_columns_result->fetch_assoc()) {
  $column_types[$row['Field']] = $row['Type'];
}
$d11_columns_result->close();

// Get columns that actually exist in D7 (excluding the special fields we'll add from D11)
$d7_existing_columns = array_filter($d7_columns, function($col) use ($special_fields) {
  return !in_array($col, $special_fields);
});
$select_columns = implode(', ', array_map(function($col) {
  return "`{$col}`";
}, $d7_existing_columns));

// Initialize batch processing variables
$total_inserted = 0;
$total_updated = 0;
$d11_columns = $d7_columns; // Use same columns

// Helper function to escape and format value for SQL
function formatValueForSql($value, $column_type, $mysqli) {
  if (is_null($value)) {
    return 'NULL';
  }
  
  $column_type_lower = strtolower($column_type);
  
  // For numeric types, return as-is (but validate)
  if (strpos($column_type_lower, 'int') !== false || 
      strpos($column_type_lower, 'tinyint') !== false ||
      strpos($column_type_lower, 'float') !== false ||
      strpos($column_type_lower, 'double') !== false ||
      strpos($column_type_lower, 'decimal') !== false) {
    if (is_numeric($value)) {
      return $value;
    }
    return 'NULL';
  }
  
  // For datetime/date types
  if (strpos($column_type_lower, 'datetime') !== false || 
      strpos($column_type_lower, 'date') !== false ||
      strpos($column_type_lower, 'timestamp') !== false) {
    if (empty($value) || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
      return 'NULL';
    }
    return "'" . $mysqli->real_escape_string($value) . "'";
  }
  
  // For string types, escape and quote
  return "'" . $mysqli->real_escape_string($value) . "'";
}

// Step 4: Process pids in batches
print "\nStep 4: Starting batch processing...\n";
$offset = 0;

while ($offset < $total_pids) {
  $current_batch = floor($offset / $batch_size) + 1;
  print "\n--- Processing Batch {$current_batch}/{$total_batches} (offset: {$offset}) ---\n";
  
  // Get pids for this batch
  $batch_limit = $limit_pids !== NULL ? min($batch_size, $limit_pids - $offset) : $batch_size;
  $pids_query = "SELECT DISTINCT r.pid
FROM sentinel_sample_revision r
INNER JOIN sentinel_sample s ON s.pid = r.pid
WHERE s.created >= DATE_SUB(NOW(), INTERVAL 3 YEAR)
ORDER BY r.pid
LIMIT {$batch_limit} OFFSET {$offset}";
  $pids_result = $d7_mysqli->query($pids_query);
  if ($pids_result === false) {
    fwrite(STDERR, "Error getting pids for batch: {$d7_mysqli->error}\n");
    break;
  }
  
  $batch_pids = [];
  while ($row = $pids_result->fetch_assoc()) {
    $batch_pids[] = (int)$row['pid'];
  }
  $pids_result->close();
  
  if (empty($batch_pids)) {
    print "No more pids to process.\n";
    break;
  }
  
  print "Processing " . count($batch_pids) . " pids in this batch: " . implode(', ', array_slice($batch_pids, 0, 10)) . (count($batch_pids) > 10 ? '...' : '') . "\n";
  
  // Delete existing revisions for these pids (both revision tables) before re-inserting.
  $pids_placeholders_d11 = implode(',', $batch_pids);
  if (!$d11_mysqli->query("DELETE FROM sentinel_sample_field_revision WHERE pid IN ({$pids_placeholders_d11})")) {
    fwrite(STDERR, "Error deleting field revisions for batch: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }
  if (!$d11_mysqli->query("DELETE FROM sentinel_sample_revision WHERE pid IN ({$pids_placeholders_d11})")) {
    fwrite(STDERR, "Error deleting revisions for batch: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }

  // Get revision data from D7 for this batch
  $pids_placeholders_d7 = implode(',', $batch_pids);
  $revisions_query = "SELECT {$select_columns}, vid FROM sentinel_sample_revision WHERE pid IN ({$pids_placeholders_d7}) ORDER BY pid, vid";
  $revisions_result = $d7_mysqli->query($revisions_query);
  if ($revisions_result === false) {
    fwrite(STDERR, "Error fetching revisions for batch: {$d7_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }
  
  $revisions = [];
  while ($row = $revisions_result->fetch_assoc()) {
    $revisions[] = $row;
  }
  $revisions_result->close();
  
  print "Found " . count($revisions) . " revisions to import for this batch.\n";
  
  // Get current values from sentinel_sample table for the three special fields
  $sample_values = [];
  $sample_query = "SELECT pid, sentinel_sample_hold_state_target_id, sentinel_company_address_target_id, sentinel_sample_address_target_id 
                   FROM sentinel_sample 
                   WHERE pid IN ({$pids_placeholders_d7})";
  $sample_result = $d11_mysqli->query($sample_query);
  if ($sample_result === false) {
    fwrite(STDERR, "Error fetching sample values: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }
  
  while ($row = $sample_result->fetch_assoc()) {
    $pid_key = (int)$row['pid'];
    $sample_values[$pid_key] = [
      'sentinel_sample_hold_state_target_id' => $row['sentinel_sample_hold_state_target_id'] ?? NULL,
      'sentinel_company_address_target_id' => $row['sentinel_company_address_target_id'] ?? NULL,
      'sentinel_sample_address_target_id' => $row['sentinel_sample_address_target_id'] ?? NULL,
    ];
  }
  $sample_result->close();

  // Insert revisions for this batch
  print "Inserting revisions for this batch...\n";
  $inserted_count = 0;
  $new_vids = []; // Track new vid for each pid in this batch
  
  foreach ($revisions as $revision) {
    $pid = (int)$revision['pid'];
    $old_vid = (int)$revision['vid'];
    
    // Prepare values array
    $values = [];
    
    foreach ($d11_columns as $col) {
      // For the three special fields, use value from sentinel_sample table (D11)
      if (in_array($col, ['sentinel_sample_hold_state_target_id', 'sentinel_company_address_target_id', 'sentinel_sample_address_target_id'])) {
        if (isset($sample_values[$pid]) && isset($sample_values[$pid][$col])) {
          $raw_value = $sample_values[$pid][$col];
          // Convert empty string or null to NULL, otherwise use the value
          if ($raw_value === '' || $raw_value === null) {
            $value = NULL;
          } else {
            $value = $raw_value; // Keep as-is, formatValueForSql will handle it
          }
        } else {
          $value = NULL;
        }
      } else {
        // For regular fields, get from revision data (from D7)
        $value = $revision[$col] ?? NULL;
      }
      
      $column_type = $column_types[$col] ?? 'varchar(255)';
      $formatted_value = formatValueForSql($value, $column_type, $d11_mysqli);
      $values[] = $formatted_value;
    }
    
    // Build and execute INSERT statement
    $columns_sql = implode(', ', array_map(function($col) {
      return "`{$col}`";
    }, $d11_columns));
    
    $values_sql = implode(', ', $values);
    
    $insert_sql = "INSERT INTO sentinel_sample_revision ({$columns_sql}) VALUES ({$values_sql})";
    
    if (!$d11_mysqli->query($insert_sql)) {
      fwrite(STDERR, "Error inserting revision (pid: {$pid}, old_vid: {$old_vid}): {$d11_mysqli->error}\n");
      continue;
    }
    
    $new_vid = $d11_mysqli->insert_id;
    $inserted_count++;
    
    // Track the latest vid for each pid
    if (!isset($new_vids[$pid]) || $new_vid > $new_vids[$pid]) {
      $new_vids[$pid] = $new_vid;
    }
    
    if ($inserted_count % 500 == 0) {
      print "  Inserted {$inserted_count} revisions in this batch...\n";
    }
  }
  
  print "Inserted {$inserted_count} revisions for this batch.\n";
  $total_inserted += $inserted_count;
  
  // Update vid in sentinel_sample table for this batch
  print "Updating vid in sentinel_sample table for this batch...\n";
  $update_vid_stmt = $d11_mysqli->prepare("UPDATE sentinel_sample SET vid = ? WHERE pid = ?");
  if ($update_vid_stmt === false) {
    fwrite(STDERR, "Error preparing update vid statement: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }
  
  $updated_count = 0;
  foreach ($new_vids as $pid => $vid) {
    $update_vid_stmt->bind_param('ii', $vid, $pid);
    if ($update_vid_stmt->execute()) {
      $updated_count++;
    } else {
      fwrite(STDERR, "Error updating vid for pid {$pid}: {$update_vid_stmt->error}\n");
    }
  }
  $update_vid_stmt->close();
  print "Updated vid for {$updated_count} samples in this batch.\n";
  $total_updated += $updated_count;
  
  // Move to next batch
  $offset += $batch_size;
  
  // Progress update
  $processed_pids = min($offset, $total_pids);
  $progress = min(100, round(($processed_pids / $total_pids) * 100, 2));
  print "Batch {$current_batch} completed. Overall progress: {$progress}% ({$processed_pids}/{$total_pids} pids)\n";
}

// Summary
print "\n=== Final Summary ===\n";
print "Total batches processed: {$total_batches}\n";
print "Total pids processed: {$total_pids}\n";
print "Revisions replaced per PID batch (no full truncation).\n";
print "Total revisions imported: {$total_inserted}\n";
print "Total vids updated: {$total_updated}\n";
print "\nImport completed successfully!\n";

// Close connections
$d7_mysqli->close();
$d11_mysqli->close();
