<?php

/**
 * @file
 * Devel PHP script to delete all lab_data entity records.
 * 
 * Usage: Copy and paste this code into Devel > Execute PHP > PHP code
 * Or run via drush: drush php-eval "$(cat web/modules/custom/sentinel_csv_processor/delete_lab_data_entities.php)"
 */

use Drupal\sentinel_csv_processor\Entity\LabData;

// Load the storage handler for lab_data entities.
$storage = \Drupal::entityTypeManager()->getStorage('lab_data');

// Get all lab_data entity IDs.
$query = $storage->getQuery()
  ->accessCheck(FALSE);
$entity_ids = $query->execute();

if (empty($entity_ids)) {
  print "No lab_data entities found to delete.\n";
  exit;
}

// Count total entities.
$total = count($entity_ids);
print "Found $total lab_data entity(ies) to delete.\n";

// Load all entities.
$entities = $storage->loadMultiple($entity_ids);

// Delete all entities.
$deleted = 0;
$errors = 0;

foreach ($entities as $entity) {
  try {
    $filename = $entity->get('filename')->value;
    $entity->delete();
    $deleted++;
    print "Deleted: $filename (ID: {$entity->id()})\n";
  }
  catch (\Exception $e) {
    $errors++;
    print "Error deleting entity ID {$entity->id()}: " . $e->getMessage() . "\n";
  }
}

print "\n";
print "Summary:\n";
print "- Total entities found: $total\n";
print "- Successfully deleted: $deleted\n";
if ($errors > 0) {
  print "- Errors: $errors\n";
}
print "\nDone!\n";

