<?php
$path = '/var/www/html/sentinel11/sentinel_portal_queue.sql';
$content = file_get_contents($path);
if ($content === FALSE) {
  throw new Exception('Unable to read SQL dump at ' . $path);
}
if (!preg_match_all('/\((\d+), \'([^\']*)\', (NULL|\d+), \'([^\']*)\', (\d+), (\d+), (\d+)\)/', $content, $matches, PREG_SET_ORDER)) {
  throw new Exception('No queue rows found in dump.');
}
$connection = \Drupal::database();
// Remove existing sentinel_queue entries.
$connection->delete('queue')->condition('name', 'sentinel_queue')->execute();
$connection->truncate('sentinel_portal_queue')->execute();
foreach ($matches as $match) {
  $item_id = (int) $match[1];
  $name = $match[2];
  $pid = ($match[3] === 'NULL') ? NULL : (int) $match[3];
  $action = $match[4];
  $expire = (int) $match[5];
  $created = (int) $match[6];
  $failed = (int) $match[7];

  $payload = (object) [
    'pid' => $pid,
    'action' => $action,
    'created' => $created,
    'expire' => $expire,
    'failed' => $failed,
  ];
  $serialized = serialize($payload);

  $connection->insert('queue')
    ->fields([
      'item_id' => $item_id,
      'name' => $name,
      'data' => $serialized,
      'expire' => $expire,
      'created' => $created,
    ])
    ->execute();

  $connection->insert('sentinel_portal_queue')
    ->fields([
      'item_id' => $item_id,
      'name' => $name,
      'data' => $serialized,
      'pid' => $pid,
      'action' => $action,
      'failed' => $failed,
      'expire' => $expire,
      'created' => $created,
    ])
    ->execute();
}
print("Imported " . count($matches) . " queue records.\n");





