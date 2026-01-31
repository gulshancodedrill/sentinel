<?php

/**
 * Export Drupal 7 file_managed data to CSV.
 *
 * Usage:
 *   php sentinel/scripts/export_d7_file_managed.php
 */

$host = 'localhost';
$port = 3306;
$username = 'root';
$password = 'infotech';
$database = 'prod30';

$mysqli = new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
  fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}\n");
  exit(1);
}

$query = <<<SQL
SELECT
  fid,
  uid,
  filename,
  uri,
  filemime,
  filesize,
  status,
  timestamp
FROM file_managed
WHERE fid > 878226
ORDER BY fid
SQL;

$result = $mysqli->query($query, MYSQLI_USE_RESULT);
if ($result === false) {
  fwrite(STDERR, "Query failed: {$mysqli->error}\n");
  $mysqli->close();
  exit(1);
}

$output_path = '/var/www/html/sentinel11/file_managed_d7.csv';
$fp = fopen($output_path, 'w');
if (!$fp) {
  fwrite(STDERR, "Unable to open {$output_path} for writing.\n");
  $result->close();
  $mysqli->close();
  exit(1);
}

fputcsv($fp, ['fid', 'uid', 'filename', 'uri', 'filemime', 'filesize', 'status', 'timestamp']);

while ($row = $result->fetch_assoc()) {
  fputcsv($fp, $row);
}

$result->close();
$mysqli->close();
fclose($fp);

print "Exported Drupal 7 file_managed data to {$output_path}\n";

