<?php

use Symfony\Component\HttpFoundation\Request;

$request = \Drupal::request();
$original_query = $request->query->all();

$request->query->set('date_reported_from', '18/03/2016');
$request->query->set('date_reported_to', '18/03/2016');

$builder = \Drupal::entityTypeManager()->getListBuilder('sentinel_sample');
$build = $builder->render();

$request->query->replace($original_query);

$ids = [];
if (!empty($build['table']) && is_array($build['table'])) {
  foreach ($build['table'] as $key => $row) {
    if (is_string($key) && str_starts_with($key, '#')) {
      continue;
    }
    if (is_array($row) && isset($row['pack_reference_number'])) {
      $ids[] = $key;
    }
  }
}

print 'Filtered IDs: ' . implode(', ', $ids) . "\n";
