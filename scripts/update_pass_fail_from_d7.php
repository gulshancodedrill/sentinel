<?php

/**
 * Update 'pass_fail' column in sentinel11.sentinel_sample from sentineld7.sentinel_sample
 *
 * This script updates the pass_fail column in Drupal 11 from Drupal 7 database.
 * It matches records by pid (primary key).
 *
 * Usage:
 *   php sentinel/scripts/update_pass_fail_from_d7.php [--dry-run] [--pid=754186]
 *   
 * Options:
 *   --dry-run    Test mode, no changes will be made
 *   --pid=XXX    Update only specific pid (for testing)
 */

// Drupal 7 database connection
$d7_host = 'localhost';
$d7_port = 3306;
$d7_username = 'liveportal_d7';
$d7_password = '$lp=O[n.$2F8MaqQ';
$d7_database = 'liveportal_d7';

// Drupal 11 database connection
$d11_host = 'localhost';
$d11_port = 3306;
$d11_username = 'liveportal_livedrupalsentinel';
$d11_password = 'NM8MppeTl_iq?@)M';
$d11_database = 'liveportal_drupalsentinel';

// Parse command line arguments
$dry_run = false;
$test_pid = null;

foreach ($argv as $arg) {
  if ($arg === '--dry-run') {
    $dry_run = true;
  } elseif (strpos($arg, '--pid=') === 0) {
    $test_pid = (int) substr($arg, 6);
  }
}

// Connect to Drupal 7 database
$d7_mysqli = new mysqli($d7_host, $d7_username, $d7_password, $d7_database, $d7_port);
if ($d7_mysqli->connect_error) {
  fwrite(STDERR, "D7 Database connection failed: {$d7_mysqli->connect_error}\n");
  exit(1);
}

// Connect to Drupal 11 database
$d11_mysqli = new mysqli($d11_host, $d11_username, $d11_password, $d11_database, $d11_port);
if ($d11_mysqli->connect_error) {
  fwrite(STDERR, "D11 Database connection failed: {$d11_mysqli->connect_error}\n");
  $d7_mysqli->close();
  exit(1);
}

print "=== Updating pass_fail column from D7 to D11 ===\n";
if ($dry_run) {
  print "DRY RUN MODE - No changes will be made\n";
}
if ($test_pid) {
  print "TEST MODE - Updating only pid: {$test_pid}\n";
}
print "\n";

// Check if pass_fail column exists in D7
$d7_check = $d7_mysqli->query("SHOW COLUMNS FROM sentinel_sample LIKE 'pass_fail'");
if ($d7_check->num_rows === 0) {
  fwrite(STDERR, "ERROR: 'pass_fail' column does not exist in sentineld7.sentinel_sample\n");
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}

// Check if pass_fail column exists in D11
$d11_check = $d11_mysqli->query("SHOW COLUMNS FROM sentinel_sample LIKE 'pass_fail'");
if ($d11_check->num_rows === 0) {
  fwrite(STDERR, "ERROR: 'pass_fail' column does not exist in sentinel11.sentinel_sample\n");
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}

// Get pass_fail values from D7 (including NULL values)
if ($test_pid) {
  // Test mode: get only specific pid (including NULL)
  $d7_query = "SELECT pid, pass_fail FROM sentinel_sample WHERE pid = ?";
  $d7_stmt = $d7_mysqli->prepare($d7_query);
  $d7_stmt->bind_param('i', $test_pid);
  $d7_stmt->execute();
  $d7_result = $d7_stmt->get_result();
} else {
  // Normal mode: get all records (including NULL values)
  $d7_query = "SELECT pid, pass_fail FROM sentinel_sample";
  $d7_result = $d7_mysqli->query($d7_query);
}

if ($d7_result === false) {
  fwrite(STDERR, "D7 Query failed: {$d7_mysqli->error}\n");
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}

$total_records = $d7_result->num_rows;
print "Found {$total_records} records in D7 (including NULL values)\n\n";

if ($total_records === 0) {
  print "No records to update.\n";
  $d7_result->close();
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(0);
}

// Prepare update statement for D11 (handles NULL values)
// Use COALESCE or handle NULL separately
$update_stmt = $d11_mysqli->prepare("UPDATE sentinel_sample SET pass_fail = ? WHERE pid = ?");
if ($update_stmt === false) {
  fwrite(STDERR, "D11 Prepare failed: {$d11_mysqli->error}\n");
  $d7_result->close();
  if (isset($d7_stmt)) {
    $d7_stmt->close();
  }
  $d7_mysqli->close();
  $d11_mysqli->close();
  exit(1);
}

$updated = 0;
$not_found = 0;
$unchanged = 0;
$errors = 0;

// Process each record
while ($row = $d7_result->fetch_assoc()) {
  $pid = (int) $row['pid'];
  $pass_fail = $row['pass_fail']; // Can be NULL, 0, 1, or other value
  
  // Check if record exists in D11
  $check_stmt = $d11_mysqli->prepare("SELECT pid, pass_fail FROM sentinel_sample WHERE pid = ?");
  $check_stmt->bind_param('i', $pid);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  
  if ($check_result->num_rows === 0) {
    $not_found++;
    if ($not_found <= 10) {
      print "  [SKIP] pid {$pid}: Not found in D11\n";
    }
    $check_stmt->close();
    continue;
  }
  
  $d11_row = $check_result->fetch_assoc();
  $check_stmt->close();
  
  // Get D11 pass_fail value (can be NULL, 0, 1, etc.)
  $d11_pass_fail = $d11_row['pass_fail'];
  
  // Normalize NULL comparison: both NULL means same
  // Also check if both are the same non-NULL value
  $d7_is_null = ($pass_fail === null || $pass_fail === '');
  $d11_is_null = ($d11_pass_fail === null || $d11_pass_fail === '');
  
  if ($d7_is_null && $d11_is_null) {
    // Both are NULL - already same
    $unchanged++;
    if ($test_pid) {
      print "  [SKIP] pid {$pid}: Both are NULL - already same\n";
    }
    continue;
  } elseif (!$d7_is_null && !$d11_is_null && $pass_fail == $d11_pass_fail) {
    // Both have same non-NULL value
    $unchanged++;
    if ($test_pid) {
      print "  [SKIP] pid {$pid}: Values are already the same (D11: '{$d11_pass_fail}', D7: '{$pass_fail}')\n";
    }
    continue;
  }
  
  // Values are different - need to update
  if ($test_pid) {
    $d7_display = $d7_is_null ? 'NULL' : $pass_fail;
    $d11_display = $d11_is_null ? 'NULL' : $d11_pass_fail;
    print "  [UPDATE] pid {$pid}: D11='{$d11_display}' -> D7='{$d7_display}'\n";
  }
  
  // Update the record
  if (!$dry_run) {
    // Handle NULL values properly - use separate UPDATE for NULL
    if ($d7_is_null) {
      // Update to NULL
      $null_update = $d11_mysqli->prepare("UPDATE sentinel_sample SET pass_fail = NULL WHERE pid = ?");
      $null_update->bind_param('i', $pid);
      if ($null_update->execute()) {
        $updated++;
        if ($updated % 1000 === 0) {
          print "  Updated {$updated} records...\n";
        }
      } else {
        $errors++;
        if ($errors <= 10) {
          print "  [ERROR] pid {$pid}: {$d11_mysqli->error}\n";
        }
      }
      $null_update->close();
    } else {
      // Update to non-NULL value
      $update_stmt->bind_param('si', $pass_fail, $pid);
      if ($update_stmt->execute()) {
        $updated++;
        if ($updated % 1000 === 0) {
          print "  Updated {$updated} records...\n";
        }
      } else {
        $errors++;
        if ($errors <= 10) {
          print "  [ERROR] pid {$pid}: {$d11_mysqli->error}\n";
        }
      }
    }
  } else {
    // Dry run - just count
    $updated++;
    if ($updated % 1000 === 0) {
      print "  Would update {$updated} records...\n";
    }
  }
}

$update_stmt->close();
$d7_result->close();
if ($test_pid && isset($d7_stmt)) {
  $d7_stmt->close();
}

print "\n=== Update Summary ===\n";
print "Total records in D7: {$total_records}\n";
print "Records updated: {$updated}\n";
print "Records unchanged: {$unchanged}\n";
print "Records not found in D11: {$not_found}\n";
if ($errors > 0) {
  print "Errors: {$errors}\n";
}
if ($dry_run) {
  print "\nDRY RUN - No actual changes were made.\n";
  print "Run without --dry-run to apply changes.\n";
} else {
  print "\nUpdate completed successfully!\n";
}

$d7_mysqli->close();
$d11_mysqli->close();

