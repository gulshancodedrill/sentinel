<?php

$request = \Drupal::request();
$original_query = $request->query->all();

$request->query->set('pack_id', '886');

$builder = \Drupal::entityTypeManager()->getListBuilder('sentinel_sample');
$build = $builder->render();

// Restore original query parameters.
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

print "Filtered IDs: " . implode(', ', $ids) . "\n";
