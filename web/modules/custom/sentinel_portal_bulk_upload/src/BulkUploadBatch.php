<?php

namespace Drupal\sentinel_portal_bulk_upload;

use Drupal\file\Entity\File;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Batch processing for CSV bulk uploads.
 */
class BulkUploadBatch {

  /**
   * Batch process file operation.
   *
   * @param \Drupal\file\Entity\File $file
   *   The Drupal file object.
   * @param bool $header_line
   *   If the header line should be skipped or not.
   * @param object $client
   *   The current client object.
   * @param array $context
   *   The current batch context.
   */
  public static function processFile($file, $header_line, $client, &$context) {
    if (!isset($context['sandbox']['offset'])) {
      // Define a couple of parameters for use when accessing the file.
      $context['sandbox']['offset'] = 0;
      $context['sandbox']['records'] = 0;
      $context['sandbox']['errors'] = 0;
      $context['sandbox']['empty_lines'] = 0;

      // Value that allows us to skip the heading line.
      if ($header_line === TRUE) {
        $context['sandbox']['skip_heading_line'] = TRUE;
      }
      else {
        $context['sandbox']['skip_heading_line'] = FALSE;
      }

      // Some variables that allow us to construct and store the headings.
      if (isset($context['sandbox']['headers_defined'])) {
        if ($context['sandbox']['headers_defined'] != TRUE) {
          $context['sandbox']['headers_defined'] = FALSE;
        }
      }
      else {
        $context['sandbox']['headers_defined'] = FALSE;
      }

      if (!isset($context['sandbox']['headers_rejected'])) {
        $context['sandbox']['headers_rejected'] = [];
      }
    }

    $headers_rejected = $context['sandbox']['headers_rejected'];

    ini_set('auto_detect_line_endings', TRUE);

    $filename = \Drupal::service('file_system')->realpath($file->getFileUri());
    $fp = fopen($filename, 'r');

    if ($fp === FALSE) {
      // Failed to open file
      $context['finished'] = TRUE;
      return;
    }

    $ret = fseek($fp, $context['sandbox']['offset']);

    if ($ret != 0) {
      // Failed to seek
      $context['finished'] = TRUE;
      return;
    }

    // Set the maximum number of rows to process at a time
    $limit = 5;
    $done = FALSE;

    for ($i = 0; $i < $limit; $i++) {
      $line = fgetcsv($fp, 100000, ",");

      // See if we have the headers included or excluded.
      if ($context['sandbox']['skip_heading_line'] == TRUE && $context['sandbox']['headers_defined'] === FALSE) {
        // Store the header line, as a lower case string!
        $headers = [];

        // Check against our known set of fields and the appropriate user levels.
        if (function_exists('sentinel_portal_entities_get_sample_fields')) {
          $sample_fields = sentinel_portal_entities_get_sample_fields();
        }
        else {
          $sample_fields = [];
        }

        foreach ($line as $position => $item) {
          // Header lines might be upper or lower, so we force them to be lower when comparing.
          $converted_item = strtolower($item);
          if ($converted_item == 'dt_sent') {
            $converted_item = 'date_sent';
          }
          if ($converted_item == 'dt_installed') {
            $converted_item = 'date_installed';
          }

          if (!isset($sample_fields[$converted_item])) {
            // If the header doesn't exist then skip it.
            continue;
          }

          if (isset($sample_fields[$converted_item]['portal_config']['access']['data'])
          && $sample_fields[$converted_item]['portal_config']['access']['data'] == 'user') {
            // We ensure that the user can add this field before adding it to the list.
            $headers[$position] = $converted_item;
          }
          else {
            $headers_rejected[$position] = $item;
          }
        }

        // Make damn sure that the pack_reference_number field is present.
        if (!in_array('pack_reference_number', $headers)) {
          // pack_reference_number field not present! exit batch immediately!
          $context['results']['processed'] = 0;
          $context['results']['errors'] = 1;
          $context['results']['error_message'] = t('No pack_reference_number field found. Please ensure that you have this heading in your upload document or de-select "Header line present?" checkbox to assume the default file structure is present.');
          $context['success'] = FALSE;

          $context['message'] = t('Error encountered, stopping file processing.');

          $context['finished'] = TRUE;
          return FALSE;
        }

        $context['sandbox']['headers'] = $headers;
        $context['sandbox']['headers_defined'] = TRUE;
        continue;
      }
      else {
        if ($context['sandbox']['headers_defined'] === FALSE) {
          // No headers are defined, so we just set them as a bunch of defaults.
          $headers = [
            'pack_reference_number',
            'company_email',
            'installer_name',
            'installer_email',
            'company_name',
            'company_postcode',
            'company_tel',
            'system_location',
            'uprn',
            'property_number',
            'street',
            'town_city',
            'county',
            'postcode',
            'landlord',
            'system_age',
            'boiler_manufacturer',
            'dt_sent',
            'boiler_id',
            'boiler_type',
            'dt_installed',
            'project_id',
            'customer_id'
          ];
          $context['sandbox']['headers'] = $headers;

          // Do not continue here, the first line contains data!
          $context['sandbox']['headers_defined'] = TRUE;
        }
      }

      if ($line === FALSE) {
        $done = TRUE;
        // No more records to process.
        break;
      }
      else {
        // Do a quick check to see what content the line has before we start
        // processing it.
        if (strlen(trim(implode('', $line))) < 1) {
          $context['sandbox']['empty_lines']++;
          $context['sandbox']['offset'] = ftell($fp);
          continue;
        }

        // We have an apparently valid record, so continue.
        $context['sandbox']['records']++;
        $context['sandbox']['offset'] = ftell($fp);

        $data = [];
        $headers = $context['sandbox']['headers'];

        // Transfer the line into an associative array of data.
        foreach ($line as $position => $item) {
          // Reject any data in headers that we don't recognize.
          if (!isset($headers_rejected[$position]) && isset($headers[$position])) {
            $data[$headers[$position]] = $item;
          }
        }

        // Assign the UCR.
        if (method_exists($client, 'getRealUcr')) {
          $data['ucr'] = $client->getRealUcr();
        }

        // Set the sample created to be '0' as it will be at this point.
        $data['sample_created'] = 0;

        // All packs MUST have a pack reference.
        if (function_exists('valid_pack_reference_number') && valid_pack_reference_number($data['pack_reference_number']) === FALSE) {
          $errors = [
            'pack_reference_number' => [
              'title' => t('Pack reference number is invalid'),
              'message' => t('The pack reference number @pack_reference_number is invalid. This sample was not submitted for processing.',
                ['@pack_reference_number' => $data['pack_reference_number']]
              )
            ]
          ];
          self::createFormattedSentinelNotice($line, $context, $errors);

          // We don't want to insert a broken pack reference number.
          continue;
        }
        else {
          if (function_exists('sentinel_portal_entities_format_packref')) {
            $data['pack_reference_number'] = sentinel_portal_entities_format_packref($data['pack_reference_number']);
          }
        }

        $errors = [];

        if (function_exists('sentinel_portal_bulk_upload_validate_line') && sentinel_portal_bulk_upload_validate_line($data, $errors) === TRUE) {
          $cohorts_access_granted = false;

          // We have a valid sample so we save it.

          // Check for existing sample.
          if (function_exists('sentinel_portal_entities_get_sample_by_reference_number')) {
            $sample = sentinel_portal_entities_get_sample_by_reference_number($data['pack_reference_number']);
          }
          else {
            $sample = FALSE;
          }

          // Insert or update the sample.
          if ($sample === FALSE) {
            if (function_exists('sentinel_portal_entities_create_sample')) {
              sentinel_portal_entities_create_sample($data);
            }
          }
          else {
            if (function_exists('get_more_clients_based_client_cohorts')) {
              $client_cohorts = get_more_clients_based_client_cohorts($client);
            }
            else {
              $client_cohorts = [];
            }

            if (!empty($client_cohorts)) {
              $database = \Drupal::database();
              $ucrs = $database->select('sentinel_client', 'sc')
                ->fields('sc', ['ucr'])
                ->condition('sc.cid', $client_cohorts, 'IN')
                ->execute()
                ->fetchCol();

              // Ensure that the user has access to this sample through the
              // cohorts and the fact that the pack hasn't been reported.
              if (method_exists($sample, 'isReported') && in_array($sample->ucr, $ucrs) && $sample->isReported() == FALSE) {
                if (function_exists('sentinel_portal_entities_update_sample')) {
                  sentinel_portal_entities_update_sample($sample, $data);
                }
                $cohorts_access_granted = true;
              }
            }
          }
          if (!$cohorts_access_granted && $sample !== FALSE) {
            $errors[] = [
              'title' => t('Sample Access Denied'),
              'message' => t('You are not allowed to update this sample data. Please contact systemcheck@sentinelprotects.com for more information.')
            ];
          }
        }

        if (count($errors) > 0) {
          /**
           * If the sample was not valid then the $errors array will be full of
           * errors, we save this into a notice.
           */
          self::createFormattedSentinelNotice($line, $context, $errors);
        }
      }
    }

    $eof = feof($fp);

    if ($eof === TRUE) {
      $context['results']['uploaded_csv_file'] = $file;
      $context['results']['empty_lines'] = $context['sandbox']['empty_lines'];
      $context['results']['processed'] = $context['sandbox']['records'];
      $context['results']['errors'] = $context['sandbox']['errors'];
      $context['success'] = TRUE;
    }

    $context['message'] = "Processed " . $context['sandbox']['records'] . " records";
    $context['finished'] = ($eof || $done) ? TRUE : FALSE;

    fclose($fp);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   If the batch run was a success or not.
   * @param array $results
   *   An array of results.
   * @param array $operations
   *   The operations.
   */
  public static function finished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    $current_user = \Drupal::currentUser();

    if ($success && !isset($results['error_message'])) {
      // If we have no fatal error messages then assume success and print out results.
      $message = t('The file has been processed.');
      $message .= '<br>';
      $message .= \Drupal::translation()->formatPlural($results['processed'],
        '1 record has been processed.',
        '@count records have been processed.'
      );

      if (isset($results['empty_lines']) && $results['empty_lines'] > 0) {
        // Print out the number of empty lines found.
        $message .= '<br>';
        $message .= \Drupal::translation()->formatPlural($results['empty_lines'],
          '1 empty line was found and skipped.',
          '@count empty lines were found and skipped.'
        );
      }

      if ($results['errors'] > 0) {
        // Print out how many warnings were generated during the import.
        $message .= '<br>';
        $message .= \Drupal::translation()->formatPlural($results['errors'],
          '1 error was encountered during the processing of these records, please see the generated notices for more information.',
          '@count errors were encountered during the processing of these records, please see the generated notices for more information.'
        );
      }
      $messenger->addStatus($message);
    }
    else {
      if (isset($results['error_message'])) {
        $error_message = $results['error_message'];
      }
      else {
        $error_message = t('An error occurred and processing did not complete.');
      }
      $messenger->addError($error_message);
      
      if (function_exists('_sentinel_portal_entities_create_notice')) {
        _sentinel_portal_entities_create_notice($current_user, t('CSV upload error'), $error_message);
      }

      \Drupal::logger('sentinel_portal_bulk_upload')->error(
        'An error occurred during the bulk upload process:<br>error: @error.<br>results: @results', [
          '@error' => $error_message,
          '@results' => print_r($results, TRUE)
        ]
      );
    }

    if (isset($results['uploaded_csv_file'])) {
      // Remove the file.
      $file = $results['uploaded_csv_file'];
      $config = \Drupal::config('sentinel_portal_bulk_upload.settings');
      $delete_uploaded_files = $config->get('delete_file') ?? TRUE;
      
      $file_system = \Drupal::service('file_system');
      $file_path = $file_system->realpath($file->getFileUri());
      
      if ($delete_uploaded_files === TRUE && file_exists($file_path)) {
        $file->delete();
      }
    }
  }

  /**
   * Format a set of errors on a data row into a message.
   *
   * @param array $line
   *   The line data that caused an error.
   * @param array $context
   *   The batch job context array.
   * @param array $errors
   *   The errors found on this data item.
   */
  protected static function createFormattedSentinelNotice($line, array &$context, array $errors) {
    $current_user = \Drupal::currentUser();

    $headers = $context['sandbox']['headers'];
    $headers_rejected = $context['sandbox']['headers_rejected'] ?? [];
    $rows = [];

    // Format the rows into a headed array.
    foreach ($line as $position => $item) {
      if (!isset($headers_rejected[$position]) && isset($headers[$position])) {
        $rows[] = [$headers[$position], $item];
      }
    }

    $error_message = '';

    $error_message .= '<p>' . t('Please correct these errors on the CSV file and re-upload the file to run the import again.') . '</p>';
    $error_message = '<div id="bulk-upload-error">' . $error_message . '</div>';

    $error_message .= '<p>' . t('The following line encountered one or more errors whilst being processed.') . '</p>';

    $error_message .= '<strong>' . t('Raw') . '</strong>';
    $error_message .= '<pre>';
    $error_message .= '"' . implode('","', $headers) . '"' . PHP_EOL . '<br>';
    $error_message .= '"' . implode('","', $line) . '"';
    $error_message .= '</pre>';

    $error_message .= '<strong>' . t('Table') . '</strong>';

    // Build a simple table
    $error_message .= '<table class="table-bordered table-hover"><tbody>';
    foreach ($rows as $row) {
      $error_message .= '<tr><td>' . $row[0] . '</td><td>' . $row[1] . '</td></tr>';
    }
    $error_message .= '</tbody></table>';

    $error_message .= '<p>' . t('The following validation errors occurred') . '</p>';

    foreach ($errors as $error) {
      $error_message .= '<p><strong>' . $error['title'] . '</strong><br>' . $error['message'] . '</p>';
    }

    if (function_exists('_sentinel_portal_entities_create_notice')) {
      _sentinel_portal_entities_create_notice($current_user, t('CSV upload error'), $error_message);
    }

    $context['sandbox']['errors']++;
  }

}



