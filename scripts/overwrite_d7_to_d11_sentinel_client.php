<?php

/**
 * Overwrite data from Drupal 7 (sentineld7) to Drupal 11 (sentinel11).
 * 
 * This script copies/overwrites data from D7 to D11 for specified tables.
 * 
 * Usage: php sentinel/scripts/overwrite_d7_to_d11.php [table_name]
 * 
 * If no table name is provided, it will process sentinel_client by default.
 */

// Database configuration
$d7_config = [
  'host' => 'localhost',
  'port' => 3306,
  'username' => 'liveportal_d7',
  'password' => '$lp=O[n.$2F8MaqQ',
  'database' => 'liveportal_d7',
];

$d11_config = [
  'host' => 'localhost',
  'port' => 3306,
  'username' => 'liveportal_livedrupalsentinel',
  'password' => 'NM8MppeTl_iq?@)M',
  'database' => 'liveportal_drupalsentinel',
];

// Get table name from command line argument
$table_name = $argv[1] ?? 'sentinel_client';

// Connect to D7 database
$d7_conn = new mysqli(
  $d7_config['host'],
  $d7_config['username'],
  $d7_config['password'],
  $d7_config['database'],
  $d7_config['port']
);

if ($d7_conn->connect_error) {
  fwrite(STDERR, "D7 Connection failed: {$d7_conn->connect_error}\n");
  exit(1);
}
$d7_conn->set_charset('utf8mb4');

// Connect to D11 database
$d11_conn = new mysqli(
  $d11_config['host'],
  $d11_config['username'],
  $d11_config['password'],
  $d11_config['database'],
  $d11_config['port']
);

if ($d11_conn->connect_error) {
  fwrite(STDERR, "D11 Connection failed: {$d11_conn->connect_error}\n");
  $d7_conn->close();
  exit(1);
}
$d11_conn->set_charset('utf8mb4');

// Check if table exists in D7
$result = $d7_conn->query("SHOW TABLES LIKE '{$table_name}'");
if ($result->num_rows === 0) {
  fwrite(STDERR, "Table '{$table_name}' does not exist in D7 database.\n");
  $d7_conn->close();
  $d11_conn->close();
  exit(1);
}

// Check if table exists in D11
$result = $d11_conn->query("SHOW TABLES LIKE '{$table_name}'");
if ($result->num_rows === 0) {
  fwrite(STDERR, "Table '{$table_name}' does not exist in D11 database.\n");
  $d7_conn->close();
  $d11_conn->close();
  exit(1);
}

print "Starting overwrite process for table: {$table_name}\n";

// Get table structure from D7
$result = $d7_conn->query("DESCRIBE `{$table_name}`");
$d7_columns = [];
$primary_key = null;
while ($row = $result->fetch_assoc()) {
  $d7_columns[] = $row['Field'];
  if ($row['Key'] === 'PRI') {
    $primary_key = $row['Field'];
  }
}

if (!$primary_key) {
  fwrite(STDERR, "No primary key found in table '{$table_name}'.\n");
  $d7_conn->close();
  $d11_conn->close();
  exit(1);
}

print "Primary key: {$primary_key}\n";
print "Columns: " . implode(', ', $d7_columns) . "\n";

// Get all data from D7
$d7_data = $d7_conn->query("SELECT * FROM `{$table_name}`");
if ($d7_data === false) {
  fwrite(STDERR, "Error reading from D7: {$d7_conn->error}\n");
  $d7_conn->close();
  $d11_conn->close();
  exit(1);
}

$total_rows = $d7_data->num_rows;
print "Found {$total_rows} rows in D7.\n";

if ($total_rows === 0) {
  print "No data to copy.\n";
  $d7_conn->close();
  $d11_conn->close();
  exit(0);
}

// Start transaction in D11
$d11_conn->begin_transaction();

$inserted = 0;
$updated = 0;
$errors = 0;

try {
  while ($row = $d7_data->fetch_assoc()) {
    $primary_value = $row[$primary_key];
    
    // Check if record exists in D11
    $check_stmt = $d11_conn->prepare("SELECT {$primary_key} FROM `{$table_name}` WHERE `{$primary_key}` = ?");
    $check_stmt->bind_param('s', $primary_value);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();
    
    // Prepare column names and values
    $columns = [];
    $values = [];
    $placeholders = [];
    $types = '';
    
    // Get column types from D11
    $d11_result = $d11_conn->query("DESCRIBE `{$table_name}`");
    $d11_column_types = [];
    while ($d11_row = $d11_result->fetch_assoc()) {
      $d11_column_types[$d11_row['Field']] = $d11_row['Type'];
    }
    
    foreach ($d7_columns as $col) {
      $columns[] = "`{$col}`";
      $placeholders[] = '?';
      $value = $row[$col];
      
      // Determine type based on D11 column type
      $col_type = strtolower($d11_column_types[$col] ?? '');
      
      if (is_null($value)) {
        $types .= 's'; // NULL as string
        $values[] = null;
      } elseif (strpos($col_type, 'int') !== false || strpos($col_type, 'tinyint') !== false) {
        $types .= 'i';
        $values[] = (int) $value;
      } elseif (strpos($col_type, 'float') !== false || strpos($col_type, 'double') !== false || strpos($col_type, 'decimal') !== false) {
        $types .= 'd';
        $values[] = (float) $value;
      } elseif (strpos($col_type, 'datetime') !== false || strpos($col_type, 'timestamp') !== false || strpos($col_type, 'date') !== false) {
        // Handle datetime - convert to proper format or NULL
        $types .= 's';
        if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
          $values[] = null;
        } else {
          $values[] = (string) $value;
        }
      } else {
        $types .= 's';
        $values[] = (string) $value;
      }
    }
    
    if ($exists) {
      // Update existing record
      $set_clauses = [];
      $update_values = [];
      $update_types = '';
      
      foreach ($d7_columns as $col) {
        if ($col === $primary_key) {
          continue; // Skip primary key in UPDATE
        }
        $set_clauses[] = "`{$col}` = ?";
        $value = $row[$col];
        
        $col_type = strtolower($d11_column_types[$col] ?? '');
        
        if (is_null($value)) {
          $update_types .= 's';
          $update_values[] = null;
        } elseif (strpos($col_type, 'int') !== false || strpos($col_type, 'tinyint') !== false) {
          $update_types .= 'i';
          $update_values[] = (int) $value;
        } elseif (strpos($col_type, 'float') !== false || strpos($col_type, 'double') !== false || strpos($col_type, 'decimal') !== false) {
          $update_types .= 'd';
          $update_values[] = (float) $value;
        } elseif (strpos($col_type, 'datetime') !== false || strpos($col_type, 'timestamp') !== false || strpos($col_type, 'date') !== false) {
          // Handle datetime - convert to proper format or NULL
          $update_types .= 's';
          if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
            $update_values[] = null;
          } else {
            $update_values[] = (string) $value;
          }
        } else {
          $update_types .= 's';
          $update_values[] = (string) $value;
        }
      }
      
      $update_types .= 's'; // For WHERE clause
      $update_values[] = $primary_value;
      
      $sql = "UPDATE `{$table_name}` SET " . implode(', ', $set_clauses) . " WHERE `{$primary_key}` = ?";
      $stmt = $d11_conn->prepare($sql);
      
      if ($stmt === false) {
        throw new Exception("Prepare failed: {$d11_conn->error}");
      }
      
      $stmt->bind_param($update_types, ...$update_values);
      
      if ($stmt->execute()) {
        $updated++;
      } else {
        $errors++;
        print "Error updating {$primary_key}={$primary_value}: {$stmt->error}\n";
      }
      $stmt->close();
    } else {
      // Insert new record
      $sql = "INSERT INTO `{$table_name}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
      $stmt = $d11_conn->prepare($sql);
      
      if ($stmt === false) {
        throw new Exception("Prepare failed: {$d11_conn->error}");
      }
      
      $stmt->bind_param($types, ...$values);
      
      if ($stmt->execute()) {
        $inserted++;
      } else {
        $errors++;
        print "Error inserting {$primary_key}={$primary_value}: {$stmt->error}\n";
      }
      $stmt->close();
    }
    
    // Progress indicator
    if (($inserted + $updated + $errors) % 100 === 0) {
      print "Processed: " . ($inserted + $updated + $errors) . " / {$total_rows}\n";
    }
  }
  
  // Commit transaction
  $d11_conn->commit();
  
  print "\n=== Overwrite Complete ===\n";
  print "Total rows processed: {$total_rows}\n";
  print "Inserted: {$inserted}\n";
  print "Updated: {$updated}\n";
  print "Errors: {$errors}\n";
  
} catch (Exception $e) {
  $d11_conn->rollback();
  fwrite(STDERR, "Transaction failed: {$e->getMessage()}\n");
  exit(1);
}

$d7_conn->close();
$d11_conn->close();

