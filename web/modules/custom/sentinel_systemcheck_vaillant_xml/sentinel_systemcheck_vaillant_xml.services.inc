<?php

/**
 * @file
 * Service functions for Sentinel Vaillant XML module.
 */

/**
 * Given a sample, generate XML for it.
 *
 * @param \Drupal\Core\Entity\EntityInterface $sample
 *   The sentinel sample entity.
 *
 * @return string
 *   The rendered XML string.
 */
function sentinel_systemcheck_vaillant_xml_generate($sample) {
  $xml = '';
  $xml .= '<?xml version="1.0" encoding="UTF-8"?>';
  $xml .= '<WaterSample>';

  $xml .= '<ProjectID>' . htmlspecialchars($sample->get('project_id')->value ?? '') . '</ProjectID>';
  $xml .= '<CustomerID>' . htmlspecialchars($sample->get('customer_id')->value ?? '') . '</CustomerID>';
  $xml .= '<CustomerName>' . htmlspecialchars($sample->get('company_name')->value ?? '') . '</CustomerName>';
  $xml .= '<CustomerEmail>' . htmlspecialchars($sample->get('company_email')->value ?? '') . '</CustomerEmail>';
  $xml .= '<BoilerSerialID>' . htmlspecialchars($sample->get('boiler_id')->value ?? '') . '</BoilerSerialID>';
  $xml .= '<UPRN>' . htmlspecialchars($sample->get('uprn')->value ?? '') . '</UPRN>';
  $xml .= '<Landlord>' . htmlspecialchars($sample->get('landlord')->value ?? '') . '</Landlord>';
  $xml .= '<PropertyNumber>' . htmlspecialchars($sample->get('property_number')->value ?? '') . '</PropertyNumber>';
  $xml .= '<Street>' . htmlspecialchars($sample->get('street')->value ?? '') . '</Street>';
  $xml .= '<Town>' . htmlspecialchars($sample->get('town_city')->value ?? '') . '</Town>';
  $xml .= '<County>' . htmlspecialchars($sample->get('county')->value ?? '') . '</County>';
  $xml .= '<Postcode>' . htmlspecialchars($sample->get('postcode')->value ?? '') . '</Postcode>';

  // The tester is always "Sentinel".
  $xml .= '<Tester>Sentinel</Tester>';

  $xml .= '<LabReference>' . htmlspecialchars($sample->get('pack_reference_number')->value ?? '') . '</LabReference>';

  // Dates must be in the format "YYYY-MM-DD".
  $date_installed = $sample->get('date_installed')->value;
  if (!empty($date_installed)) {
    try {
      $date_obj = new \DateTime($date_installed);
      $xml .= '<DateInstalled>' . htmlspecialchars($date_obj->format('Y-m-d')) . '</DateInstalled>';
    }
    catch (\Exception $e) {
      $xml .= '<DateInstalled></DateInstalled>';
    }
  }
  else {
    $xml .= '<DateInstalled></DateInstalled>';
  }

  // Dates must be in the format "YYYY-MM-DD".
  $date_processed = $sample->get('date_processed')->value;
  if (!empty($date_processed)) {
    try {
      $date_obj = new \DateTime($date_processed);
      $xml .= '<DateTested>' . htmlspecialchars($date_obj->format('Y-m-d')) . '</DateTested>';
    }
    catch (\Exception $e) {
      $xml .= '<DateTested></DateTested>';
    }
  }
  else {
    $xml .= '<DateTested></DateTested>';
  }

  $pass_fail = $sample->get('pass_fail')->value ?? 0;
  switch ($pass_fail) {
    case 0:
      $xml .= '<Result>F</Result>';
      break;
    case 1:
      $xml .= '<Result>P</Result>';
      break;
  }

  // Get the sample recommendations.
  $sentinel_sample_result = new \stdClass();

  // We don't want to send the original sample as this would be changed所欲 so we clone it.
  if (function_exists('sentinel_systemcheck_certificate_populate_results')) {
    sentinel_systemcheck_certificate_populate_results($sentinel_sample_result, $sample, new \stdClass());
    $recommendations = isset($sentinel_sample_result->recommendations) ? $sentinel_sample_result->recommendations : '';
  }
  else {
    $recommendations = '';
  }

  // Add notes.
  $xml .= '<Notes>' . htmlspecialchars($recommendations) . '</Notes>';

  $xml .= '</WaterSample>';

  return $xml;
}

/**
 * Generate an xml file.
 *
 * @param \Drupal\Core\Entity\EntityInterface $sample_entity
 *   The sample entity.
 * @param string $filename
 *   The filename.
 *
 * @return \Drupal\file\FileInterface|bool
 *   File object or FALSE on failure.
 */
function sentinel_systemcheck_vaillant_xml_generate_file($sample_entity, $filename) {
  $xml_string = sentinel_systemcheck_vaillant_xml_generate($sample_entity);

  $file_system = \Drupal::service('file_system');
  $private_path = 'private://' . $filename;
  $directory = dirname($private_path);
  
  if ($file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS)) {
    $file = file_save_data($xml_string, $private_path, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
    if ($file) {
      return $file;
    }
  }

  return FALSE;
}

/**
 * Implements hook_sentinel_sendresults().
 *
 * This hook is invoked when a sample result needs to be sent.
 * This implementation handles Vaillant samples.
 *
 * @param \Drupal\Core\Entity\EntityInterface $sample_entity
 *   The sample entity.
 *
 * @return bool
 *   TRUE if everything worked, otherwise FALSE.
 */
function sentinel_systemcheck_vaillant_xml_sentinel_sendresults($sample_entity) {
  if (!is_object($sample_entity)) {
    return FALSE;
  }

  // Check if this is a Vaillant pack type.
  $pack_type = $sample_entity->get('pack_type')->value ?? '';
  if ($pack_type !== 'vaillant') {
    return FALSE;
  }

  $pass_fail = $sample_entity->get('pass_fail')->value ?? 0;
  $is_pass = ($pass_fail == 1);
  
  $pack_ref = strip_tags($sample_entity->get('pack_reference_number')->value ?? '');
  $system_address = '';
  
  // Get system address - try to get from address field or construct from fields.
  $address_field = $sample_entity->get('field_sentinel_sample_address');
  if (!$address_field->isEmpty()) {
    $address = $address_field->entity;
    if ($address) {
      $address_parts = [];
      if ($address->hasField('address_line1')) {
        $address_parts[] = $address->get('address_line1')->value;
      }
      if ($address->hasField('locality')) {
        $address_parts[] = $address->get('locality')->value;
      }
      $system_address = implode(', ', array_filter($address_parts));
    }
  }
  
  if (empty($system_address)) {
    // Fallback to constructing from sample fields.
    $parts = [];
    if ($sample_entity->get('street')->value) {
      $parts[] = $sample_entity->get('street')->value;
    }
    if ($sample_entity->get('town_city')->value) {
      $parts[] = $sample_entity->get('town_city')->value;
    }
    $system_address = implode(', ', array_filter($parts));
  }

  $subject = t('Sentinel System Check Report: @pack_ref - @pass_fail - @address', [
    '@pass_fail' => $is_pass ? 'PASS' : 'FAIL',
    '@pack_ref' => $pack_ref,
    '@address' => strip_tags($system_address),
  ]);

  $file = sentinel_systemcheck_vaillant_xml_generate_file($sample_entity, $sample_entity->id() . time() . '.xml');

  if (!$file) {
    return FALSE;
  }

  $message_html = '';
  $installer_name = $sample_entity->get('installer_name')->value ?? '';

  // We have different messages for passed and failed samples.
  if ($is_pass) {
    $message_html .= '<p>Dear ' . htmlspecialchars($installer_name) . '</p>';
    $message_html .= '<p>Please find attached a report on your recently submitted sample - the second taken from this particular system.</p>';
    $message_html .= '<p>As you will see, it has been allocated a PASS designation and so no further action is needed.</p>';
    $message_html .= '<p>Regards,</p>';
    $message_html .= '<p>Sentinel Laboratory</p>';

    $email = 'watersamples@vaillant-solutions.co.uk';
  }
  else {
    // Sample failed.
    // Check if this is a repeated failed test.
    $repeated_test = 0;
    if (function_exists('sentinel_portal_sample_repeated_failed_test')) {
      $repeated_test = sentinel_portal_sample_repeated_failed_test($sample_entity);
    }

    if ($repeated_test >= 2) {
      $message_html .= '<p>Dear ' . htmlspecialchars($installer_name) . '</p>';
      $message_html .= '<p>Please find attached a report on your recently submitted sample. This is the second time that a sample ';
      $message_html .= 'has been submitted from this particular system and it has again been allocated a FAIL designation. ';
      $message_html .= 'Additional action is necessary and a summary of our recommendations is given in the report. ';
      $message_html .= 'No further samples from this system can be analysed Free of Charge and you are requested to contact Vaillant for further ';
      $message_html .= 'instructions/training/advice.</p>';
      $message_html .= '<p>For further testing of this system, you should purchase a standard Sentinel System Check from a merchant. ';
      $message_html .= 'Please DO NOT submit this with the paperwork supplied with the System Check but print off the attached ';
      $message_html .= 'form to accompany the samples. Also please note that the freepost address for these samples MUST be:</p>';

      $message_html .= '<p>FREEPOST RSCS-SJHL-GHAU<br>';
      $message_html .= 'Scientifics Environmental Chemistry<br>';
      $message_html .= 'PO Box 100<br>';
      $message_html .= 'Bretby Business Park<br>';
      $message_html .= 'Burton-upon-Trent<br>';
      $message_html .= 'DE15 0XD</p>';

      $message_html .= '<p>Please ensure that the existing Freepost label is covered.</p>';
      $message_html .= '<p>If further advice is required, please contact 01928 588330</p>';

      $message_html .= '<p>Regards,</p>';
      $message_html .= '<p>Sentinel Laboratory</p>';
    }
    else {
      $message_html .= '<p>Dear ' . htmlspecialchars($installer_name) . '</p>';
      $message_html .= '<p>Please find attached a report on your recently submitted sample.</p>';
      $message_html .= '<p>As you will see, it has been allocated a FAIL designation and additional action is ';
      $message_html .= 'necessary - a summary of our recommendations is given in the report.</p>';
      $message_html .= '<p>If further advice is required, please contact 01928 588330.</p>';
      $message_html .= '<p>Regards,</p>';
      $message_html .= '<p>Sentinel Laboratory</p>';
    }

    $email = 'watersamples@vaillant-solutions.co.uk';
  }

  $message_plain = strip_tags($message_html);

  // Dispatch event instead of Rules.
  if (function_exists('sentinel_systemcheck_custom_rule_events_invoke_sample_result_calculated')) {
    sentinel_systemcheck_custom_rule_events_invoke_sample_result_calculated($email, $file->getFileUri(), $subject, $message_html, $message_plain);
  }

  // Delete the temporary file.
  $file->delete();

  return TRUE;
}
