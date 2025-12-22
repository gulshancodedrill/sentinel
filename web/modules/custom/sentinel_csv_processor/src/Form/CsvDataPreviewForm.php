<?php

namespace Drupal\sentinel_csv_processor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\sentinel_csv_processor\Entity\LabData;
use Drupal\sentinel_csv_processor\Form\CsvProcessingBatch;

/**
 * Form for previewing CSV data from selected files.
 */
class CsvDataPreviewForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_csv_processor_data_preview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get entity IDs from query parameters (entity IDs from lab_data table).
    $request = \Drupal::request();
    $file_ids = $request->query->get('files', '');
    
    if (empty($file_ids)) {
      $form['no_files'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No files selected.') . '</p>',
      ];
      return $form;
    }

    // Parse entity IDs (comma-separated integers from lab_data table).
    $file_ids = explode(',', $file_ids);
    $file_ids = array_filter(array_map('intval', $file_ids));

    if (empty($file_ids)) {
      $form['no_files'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No valid files selected.') . '</p>',
      ];
      return $form;
    }

    // Load lab_data entities by their entity IDs.
    $storage = \Drupal::entityTypeManager()->getStorage('lab_data');
    $entities = $storage->loadMultiple($file_ids);

    if (empty($entities)) {
      $form['no_files'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Selected files not found.') . '</p>',
      ];
      return $form;
    }

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Preview of CSV data from selected files. Please review before processing.') . '</p>',
    ];

    // Use Drupal's pager system for file navigation (like /portal/samples).
    $total_files = count($entities);
    $items_per_page = 1; // One file per page.
    
    // Initialize pager for file navigation.
    $pager_element = 0;
    $current_page = \Drupal::service('pager.parameters')->findPage($pager_element);
    \Drupal::service('pager.manager')->createPager($total_files, $items_per_page, $pager_element);
    
    // Calculate current file index from pager.
    $current_file_index = $current_page;
    
    // Ensure file index is valid.
    if ($current_file_index < 0 || $current_file_index >= $total_files) {
      $current_file_index = 0;
    }

    // Get the current entity to display.
    // Convert entities array to indexed array to get by position.
    $entities_array = array_values($entities);
    $current_entity = $entities_array[$current_file_index];
    $current_entity_id = $current_entity->id();

    $filename = $current_entity->get('filename')->value;
    $file_uri = $current_entity->get('path')->value;
    
    // Find file by URI.
    $file_storage = \Drupal::entityTypeManager()->getStorage('file');
    $files = $file_storage->loadByProperties(['uri' => $file_uri]);
    $file = !empty($files) ? reset($files) : NULL;

    // Single fieldset wrapper.
    $form['file_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('File @current of @total: @filename', [
        '@current' => $current_file_index + 1,
        '@total' => $total_files,
        '@filename' => $filename,
      ]),
      '#collapsible' => FALSE,
      '#attributes' => ['class' => ['csv-file-wrapper']],
    ];

    if (!$file) {
      $form['file_wrapper']['error'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('File not found.') . '</p>',
      ];
    }
    else {
      $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
      if (!$file_path || !file_exists($file_path)) {
        $form['file_wrapper']['error'] = [
          '#type' => 'markup',
          '#markup' => '<p>' . $this->t('File path not accessible.') . '</p>',
        ];
      }
      else {
        // Read ALL CSV data (no pagination within file).
        $table_data = $this->readAllCsvData($file_path);
        
        if (!empty($table_data['headers'])) {
          $form['file_wrapper']['table'] = [
            '#type' => 'table',
            '#header' => $table_data['headers'],
            '#rows' => $table_data['rows'],
            '#empty' => $this->t('No data found.'),
            '#attributes' => ['class' => ['csv-preview-table']],
          ];

          // Show total row count.
          $form['file_wrapper']['row_count'] = [
            '#type' => 'markup',
            '#markup' => '<p><strong>' . $this->t('Total: @total rows', ['@total' => $table_data['total']]) . '</strong></p>',
          ];
        }
        else {
          $form['file_wrapper']['no_data'] = [
            '#type' => 'markup',
            '#markup' => '<p>' . $this->t('Could not read CSV data from this file.') . '</p>',
          ];
        }
      }
    }

    // File navigation using Drupal's standard pager (like /portal/samples).
    // The pager automatically preserves query parameters (like 'files').
    if ($total_files > 1) {
      $form['pager'] = [
        '#type' => 'pager',
        '#quantity' => 9,
        '#element' => $pager_element,
        '#tags' => [
          'first' => $this->t('« first'),
          'previous' => $this->t('‹ previous'),
          'next' => $this->t('next ›'),
          'last' => $this->t('last »'),
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm and Process'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('sentinel_csv_processor.file_list_form'),
      '#attributes' => ['class' => ['button btn btn-danger']],
    ];
    return $form;
  }

  /**
   * Read all CSV data (no pagination).
   *
   * @param string $file_path
   *   The file path.
   *
   * @return array
   *   Array with 'headers', 'rows', and 'total' keys.
   */
  protected function readAllCsvData($file_path) {
    $handle = fopen($file_path, 'r');
    if ($handle === FALSE) {
      return ['headers' => [], 'rows' => [], 'total' => 0];
    }

    // Read header row.
    $headers = fgetcsv($handle);
    if ($headers === FALSE) {
      fclose($handle);
      return ['headers' => [], 'rows' => [], 'total' => 0];
    }

    // Clean headers.
    $headers = array_map('trim', $headers);

    // Read all data rows and filter out empty rows.
    $rows = [];
    while (($line = fgetcsv($handle)) !== FALSE) {
      $trimmed_line = array_map('trim', $line);
      // Filter out completely empty rows (all cells are empty).
      $has_data = FALSE;
      foreach ($trimmed_line as $cell) {
        if (!empty($cell)) {
          $has_data = TRUE;
          break;
        }
      }
      if ($has_data) {
        $rows[] = $trimmed_line;
      }
    }

    fclose($handle);

    $total = count($rows);

    return [
      'headers' => $headers,
      'rows' => $rows,
      'total' => $total,
    ];
  }



  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get file IDs from query parameters.
    $request = \Drupal::request();
    $file_ids = $request->query->get('files', '');
    
    if (empty($file_ids)) {
      $this->messenger()->addError($this->t('No files selected.'));
      return;
    }

    // Parse entity IDs (comma-separated integers from lab_data table).
    $file_ids = explode(',', $file_ids);
    $file_ids = array_filter(array_map('intval', $file_ids));

    if (empty($file_ids)) {
      $this->messenger()->addError($this->t('No valid files selected.'));
      return;
    }

    // Create batch operations.
    $batch = CsvProcessingBatch::createBatch($file_ids);
    batch_set($batch);
  }

}

