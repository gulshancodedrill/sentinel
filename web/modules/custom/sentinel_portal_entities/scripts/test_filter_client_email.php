<?php

use Symfony\Component\HttpFoundation\Request;

$request = \Drupal::request();
$original_query = $request->query->all();

$request->query->set('client_email', 'Bob.ford@kirklees.gov.uk');

$builder = \Drupal::entityTypeManager()->getListBuilder('sentinel_sample');
$build = $builder->render();

$request->query->replace($original_query);

$rows = [];
if (!empty($build['table']) && is_array($build['table'])) {
  foreach ($build['table'] as $key => $row) {
    if (is_string($key) && str_starts_with($key, '#')) {
      continue;
    }
    if (is_array($row) && isset($row['pack_reference_number'])) {
      $rows[] = $key;
    }
  }
}

print \Drupal::logger('test')->notice('Rows: @rows', ['@rows' => implode(',', $rows)]);
print "Filtered IDs: " . implode(', ', $rows) . "\n";
