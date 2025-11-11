<?php

/**
 * Script to attach legacy (or regenerated) Sentinel certificate PDFs.
 *
 * Usage:
 *   drush scr scripts/import_d7_pdfs.php
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\file\Entity\File;

// Configuration.
// Set to 0 to process all pending samples without limiting the batch size.
$limit = 0;
$source_base = '/var/www/html/sentinel11/files-private111';
$source_directories = [
  'new-pdf-certificates',
  'legacy-pdf-certificates',
];

$entity_type_manager = \Drupal::entityTypeManager();
$connection = \Drupal::database();
$file_system = \Drupal::service('file_system');
$file_repository = \Drupal::service('file.repository');
$file_usage = \Drupal::service('file.usage');
$logger = \Drupal::logger('sentinel_portal_queue');

$query = $connection->select('sentinel_sample', 's')
  ->fields('s', ['pid', 'filename', 'created', 'updated'])
  ->isNotNull('filename')
  ->condition('filename', '', '<>');

$or = $query->orConditionGroup()
  ->condition('fileid', 0)
  ->isNull('fileid');

$query
  ->condition($or)
  ->orderBy('updated', 'DESC');

if (!empty($limit)) {
  $query->range(0, $limit);
}

$rows = $query->execute()->fetchAll();

if (empty($rows)) {
  print "No sentinel_sample rows require PDF import.\n";
  return;
}

print sprintf("Found %d sentinel_sample rows without file metadata.\n", count($rows));

$imported = 0;
$failures = 0;

foreach ($rows as $row) {
  $pid = (int) $row->pid;
  $preferred_filename = $row->filename;
  $created = $row->created;

  $sample = $entity_type_manager->getStorage('sentinel_sample')->load($pid);
  if (!$sample) {
    print sprintf("[PID %d] Sample entity could not be loaded. Skipping.\n", $pid);
    ++$failures;
    continue;
  }

  $payload = sentinel_import_resolve_pdf($preferred_filename, $source_base, $source_directories, $sample, $logger);
  if (!$payload) {
    print sprintf("[PID %d] Unable to obtain PDF data.\n", $pid);
    ++$failures;
    continue;
  }

  $destination_uri = sentinel_import_build_destination_uri($payload['filename'], $created);

  try {
    $directory = preg_replace('#[^/]+$#', '', $destination_uri);
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    /** @var \Drupal\file\FileInterface $file */
    $file = $file_repository->writeData($payload['data'], $destination_uri, FileSystemInterface::EXISTS_REPLACE);
    $file->setOwnerId(1);
    $file->setPermanent();
    $file->save();

    // Register usage and update sentinel_sample via direct query (avoids legacy date issues).
    $file_usage->add($file, 'sentinel_portal_entities', 'sentinel_sample', $pid);

    $connection->update('sentinel_sample')
      ->fields([
        'fileid' => $file->id(),
        'filename' => $file->getFilename(),
      ])
      ->condition('pid', $pid)
      ->execute();

    ++$imported;
    print sprintf("[PID %d] Stored %s (fid: %d).\n", $pid, $file->getFilename(), $file->id());
  }
  catch (\Exception $e) {
    $logger->error('Failed importing PDF for sample @pid: @message', [
      '@pid' => $pid,
      '@message' => $e->getMessage(),
    ]);
    print sprintf("[PID %d] Error writing PDF: %s\n", $pid, $e->getMessage());
    ++$failures;
  }
}

print sprintf("Import complete. Successful: %d, Failed: %d.\n", $imported, $failures);

/**
 * Resolve PDF data either from legacy storage or by regenerating.
 */
function sentinel_import_resolve_pdf(string $preferred_filename, string $base, array $directories, $sample, $logger): ?array {
  $legacy_path = sentinel_import_locate_legacy_pdf($preferred_filename, $base, $directories);
  if ($legacy_path) {
    $data = file_get_contents($legacy_path);
    if ($data !== FALSE) {
      return [
        'data' => $data,
        'filename' => $preferred_filename,
        'source' => $legacy_path,
      ];
    }
  }

  // Fall back to regeneration using Drupal 11 rendering pipeline.
  $logger->log(RfcLogLevel::WARNING, 'Legacy PDF {file} missing, regenerating via sample data.', ['file' => $preferred_filename]);

  $html = sentinel_import_build_pdf_html($sample);
  if (empty($html) || !function_exists('sentinel_systemcheck_certificate_get_dompdf_object')) {
    return NULL;
  }

  try {
    $dompdf = sentinel_systemcheck_certificate_get_dompdf_object($html);
    $data = $dompdf->output();
  }
  catch (\Exception $e) {
    $logger->error('PDF regeneration failed for sample @pid: @message', [
      '@pid' => $sample->id(),
      '@message' => $e->getMessage(),
    ]);
    return NULL;
  }

  $filename = $preferred_filename ?: sentinel_import_generate_filename($sample);
  return [
    'data' => $data,
    'filename' => $filename,
    'source' => 'regenerated',
  ];
}

/**
 * Locate a legacy file in the provided source directories.
 */
function sentinel_import_locate_legacy_pdf(string $filename, string $base, array $directories): ?string {
  if (empty($filename)) {
    return NULL;
  }

  foreach ($directories as $directory) {
    $pattern = sprintf('%s/%s/*/%s', rtrim($base, '/'), $directory, $filename);
    $matches = glob($pattern);
    if (!empty($matches)) {
      return reset($matches);
    }

    $pattern = sprintf('%s/%s/%s', rtrim($base, '/'), $directory, $filename);
    $matches = glob($pattern);
    if (!empty($matches)) {
      return reset($matches);
    }
  }

  return NULL;
}

/**
 * Build the HTML for a sample certificate PDF.
 */
function sentinel_import_build_pdf_html($sample): ?string {
  if (!function_exists('_get_result_content')) {
    return NULL;
  }

  $theme_vars = _get_result_content($sample->id(), 'sentinel_sample');
  $theme_vars['pdf'] = TRUE;

  $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate');
  $template_path = $module_path . '/templates/sentinel_certificate.html.twig';
  $css_path = \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('sentinel_portal') . '/css/pdf-only.css';
  $css = file_exists($css_path) ? '<style>' . file_get_contents($css_path) . '</style>' : '';

  $html = '<!DOCTYPE html><html><head>'
    . '<meta charset="utf-8">'
    . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'
    . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
    . '<meta name="Generator" content="Drupal 11" />'
    . $css
    . '</head><body>';
  $html .= \Drupal::service('twig')->render($template_path, $theme_vars);
  $html .= '</body></html>';

  return $html;
}

/**
 * Generate a safe filename when the original is empty.
 */
function sentinel_import_generate_filename($sample): string {
  $pack_ref = $sample->get('pack_reference_number')->value ?? ('sample-' . $sample->id());
  $pack_ref = str_replace(':', '-', $pack_ref);
  $pack_ref = preg_replace('/[^A-Za-z0-9\-]+/', '-', $pack_ref);
  $pack_ref = trim($pack_ref, '-');

  return strtolower($pack_ref . '-' . $sample->id() . '.pdf');
}

/**
 * Determine the destination private:// URI for storing the file.
 */
function sentinel_import_build_destination_uri(string $filename, ?string $created): string {
  $subdir = 'other';

  if (!empty($created)) {
    try {
      $date = new \DateTime($created);
      $subdir = $date->format('m-Y');
    }
    catch (\Exception $e) {
      // Use fallback.
    }
  }

  return sprintf('private://new-pdf-certificates/%s/%s', $subdir, $filename);
}


