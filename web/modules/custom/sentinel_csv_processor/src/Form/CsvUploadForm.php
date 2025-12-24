<?php

namespace Drupal\sentinel_csv_processor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\Component\Utility\Environment;
use Drupal\sentinel_csv_processor\Entity\LabData;

/**
 * Form for uploading multiple CSV files.
 */
class CsvUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_csv_processor_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['enctype'] = 'multipart/form-data';

    // Add "Uploaded Files" button at the top, matching the style of "Process Selected Files" button.
    $form['uploaded_files_button'] = [
      '#type' => 'link',
      '#title' => $this->t('Uploaded Files'),
      '#url' => Url::fromRoute('sentinel_csv_processor.file_list_form'),
      '#attributes' => [
        'class' => ['button btn btn-warning'],
      ],
      '#weight' => -10,
    ];

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Upload multiple CSV files for processing. Files will be stored in the private lab_files directory.') . '</p>',
    ];

    // Get max file size and format it.
    $max_size = Environment::getUploadMaxSize();
    $max_size_mb = round($max_size / 1024 / 1024, 2);
    
    $form['csv_files'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV Files'),
      '#description' => $this->t('Select one or more CSV files to upload. Maximum file size: @size MB', [
        '@size' => $max_size_mb,
      ]),
      '#multiple' => TRUE,
      '#attributes' => [
        'accept' => '.csv',
        'multiple' => 'multiple',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload Files'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $files = $request->files->get('files', []);

    if (empty($files['csv_files'])) {
      $form_state->setErrorByName('csv_files', $this->t('Please select at least one CSV file to upload.'));
      return;
    }

    $uploaded_files = $files['csv_files'];
    
    // Handle single file upload (not an array).
    if (!is_array($uploaded_files)) {
      $uploaded_files = [$uploaded_files];
    }

    $file_repository = \Drupal::service('file.repository');
    $file_system = \Drupal::service('file_system');
    
    // Prepare destination directory.
    $destination = 'private://lab_files';
    $file_system->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    
    $saved_file_ids = [];
    
    // Process and save files immediately - convert UploadedFile to File entities.
    foreach ($uploaded_files as $file) {
      if ($file && $file->isValid()) {
        $filename = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate file extension.
        if ($extension !== 'csv') {
          $form_state->setErrorByName('csv_files', $this->t('File @filename is not a CSV file.', [
            '@filename' => $filename,
          ]));
          continue;
        }

        // Validate file size.
        if ($file->getSize() > Environment::getUploadMaxSize()) {
          $form_state->setErrorByName('csv_files', $this->t('File @filename exceeds the maximum file size.', [
            '@filename' => $filename,
          ]));
          continue;
        }

        // Read file content and save immediately.
        $file_path = $file->getRealPath();
        $file_content = file_get_contents($file_path);
        
        if ($file_content !== FALSE) {
          // Save to temporary first.
          $temp_uri = 'temporary://' . $filename;
          $temp_file = $file_repository->writeData($file_content, $temp_uri, FileSystemInterface::EXISTS_RENAME);
          
          if ($temp_file) {
            // Generate unique filename.
            $destination_uri = $destination . '/' . $filename;
            $counter = 1;
            $real_destination = $file_system->realpath($destination_uri);
            while ($real_destination && file_exists($real_destination)) {
              $path_info = pathinfo($filename);
              $destination_uri = $destination . '/' . $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
              $real_destination = $file_system->realpath($destination_uri);
              $counter++;
            }

            // Move to private://lab_files.
            $permanent_file = $file_repository->move($temp_file, $destination_uri, FileSystemInterface::EXISTS_REPLACE);
            
            if ($permanent_file) {
              $permanent_file->setPermanent();
              $permanent_file->save();
              
              // Extract refname from CSV immediately (while we have file access).
              $refname = self::extractSiteFromCsv($permanent_file);
              
              // Store ALL entity data (primitives only) in form_state.
              $saved_file_ids[] = [
                'file_id' => (int) $permanent_file->id(),
                'path' => (string) $permanent_file->getFileUri(),
                'filename' => (string) $filename,
                'refname' => $refname ? (string) $refname : NULL,
              ];
            }
          }
        }
      }
    }

    if (empty($saved_file_ids)) {
      $form_state->setErrorByName('csv_files', $this->t('No valid CSV files were selected.'));
      return;
    }

    // Store entity data in form_state (only primitives).
    $form_state->set('entity_data', $saved_file_ids);
  }

  /**
   * Extract Site value from CSV file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity.
   *
   * @return string|null
   *   The Site value or NULL if not found.
   */
  protected static function extractSiteFromCsv(File $file) {
    $file_system = \Drupal::service('file_system');
    $file_path = $file_system->realpath($file->getFileUri());

    if (!$file_path || !file_exists($file_path)) {
      return NULL;
    }

    $handle = fopen($file_path, 'r');
    if ($handle === FALSE) {
      return NULL;
    }

    try {
      // Read header row.
      $headers = fgetcsv($handle);
      if ($headers === FALSE) {
        return NULL;
      }

      // Find Site column index.
      $site_index = NULL;
      foreach ($headers as $index => $header) {
        if (trim(strtolower($header)) === 'site') {
          $site_index = $index;
          break;
        }
      }

      if ($site_index === NULL) {
        return NULL;
      }

      // Read first data row.
      $first_row = fgetcsv($handle);
      if ($first_row === FALSE || !isset($first_row[$site_index])) {
        return NULL;
      }

      return trim($first_row[$site_index]) ?: NULL;
    }
    finally {
      fclose($handle);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get entity data from form_state (already processed in validateForm).
    $entity_data = $form_state->get('entity_data', []);

    if (empty($entity_data)) {
      $this->messenger()->addError($this->t('No files were uploaded.'));
      return;
    }

    $success_count = 0;
    $error_count = 0;
    $skipped_count = 0;
    $skipped_filenames = [];
    $logger = \Drupal::logger('sentinel_csv_processor');
    $storage = \Drupal::entityTypeManager()->getStorage('lab_data');

    // Create entities directly - no batch processing.
    foreach ($entity_data as $data) {
      $filename = (string) $data['filename'];
      
      // Check if filename already exists.
      $existing = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('filename', $filename)
        ->execute();
      
      if (!empty($existing)) {
        // Filename already exists, skip it.
        $skipped_count++;
        $skipped_filenames[] = $filename;
        $logger->warning('Skipped upload: filename already exists - @filename', [
          '@filename' => $filename,
        ]);
        continue;
      }
      
      try {
        // Create lab_data entity.
        $lab_data = LabData::create([
          'path' => (string) $data['path'],
          'filename' => $filename,
          'status' => 'pending',
          'refname' => isset($data['refname']) && $data['refname'] ? (string) $data['refname'] : NULL,
          'process_type' => 'manual',
        ]);

        $lab_data->save();
        $success_count++;

        $logger->info('Successfully created entity for file: @filename (refname: @refname)', [
          '@filename' => $filename,
          '@refname' => $data['refname'] ?: 'N/A',
        ]);
      }
      catch (\Exception $e) {
        $error_count++;
        $logger->error('Error creating entity for file @filename: @message', [
          '@filename' => $filename,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // Show success/error/skipped messages.
    if ($success_count > 0) {
      $this->messenger()->addStatus(\Drupal::translation()->formatPlural(
        $success_count,
        'Successfully uploaded 1 CSV file.',
        'Successfully uploaded @count CSV files.'
      ));
    }

    if ($skipped_count > 0) {
      $this->messenger()->addWarning(\Drupal::translation()->formatPlural(
        $skipped_count,
        '1 file was skipped because the filename already exists: @filenames',
        '@count files were skipped because the filenames already exist: @filenames',
        [
          '@filenames' => implode(', ', $skipped_filenames),
        ]
      ));
    }

    if ($error_count > 0) {
      $this->messenger()->addWarning(\Drupal::translation()->formatPlural(
        $error_count,
        '1 file failed to process.',
        '@count files failed to process.'
      ));
    }

    // Redirect back to file list page after upload.
    $form_state->setRedirect('sentinel_csv_processor.file_list_form');
  }

}

