<?php

/**
 * Update sentinel_sample_revision table with address and hold state IDs from sentinel_sample.
 *
 * Updates the following columns in sentinel_sample_revision:
 * - sentinel_sample_hold_state_target_id
 * - sentinel_company_address_target_id
 * - sentinel_sample_address_target_id
 *
 * Gets data from sentinel_sample where pid matches.
 *
 * Usage:
 *   php sentinel/scripts/update_sentinel_sample_revision_addresses.php
 */

$host = 'localhost';
$port = 3306;
$username = 'root';
$password = 'infotech';
$database = 'sentinel11';

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}

$mysqli->set_charset('utf8mb4');

echo "Updating sentinel_sample_revision with address and hold state IDs from sentinel_sample...\n";

// Update query to copy the three columns from sentinel_sample to sentinel_sample_revision
$update_sql = <<<SQL
UPDATE sentinel_sample_revision AS rev
INNER JOIN sentinel_sample AS sample ON rev.pid = sample.pid
SET 
  rev.sentinel_sample_hold_state_target_id = sample.sentinel_sample_hold_state_target_id,
  rev.sentinel_company_address_target_id = sample.sentinel_company_address_target_id,
  rev.sentinel_sample_address_target_id = sample.sentinel_sample_address_target_id
WHERE 
  rev.pid = sample.pid
SQL;

echo "Executing update query...\n";
$result = $mysqli->query($update_sql);

if (!$result) {
  fwrite(STDERR, "Update failed: {$mysqli->error}\n");
  exit(1);
}

$affected_rows = $mysqli->affected_rows;
echo "Update complete. Updated $affected_rows revision records.\n";

// Verify the update
echo "\nVerifying update...\n";
$verify_sql = <<<SQL
SELECT 
  COUNT(*) as total_revisions,
  COUNT(rev.sentinel_sample_hold_state_target_id) as hold_state_count,
  COUNT(rev.sentinel_company_address_target_id) as company_addr_count,
  COUNT(rev.sentinel_sample_address_target_id) as sample_addr_count
FROM sentinel_sample_revision AS rev
INNER JOIN sentinel_sample AS sample ON rev.pid = sample.pid
SQL;

$verify_result = $mysqli->query($verify_sql);
if ($verify_result) {
  $row = $verify_result->fetch_assoc();
  echo "Total revisions with matching pids: {$row['total_revisions']}\n";
  echo "Revisions with hold_state_target_id: {$row['hold_state_count']}\n";
  echo "Revisions with company_address_target_id: {$row['company_addr_count']}\n";
  echo "Revisions with sample_address_target_id: {$row['sample_addr_count']}\n";
}

$mysqli->close();

echo "\nDone.\n";

