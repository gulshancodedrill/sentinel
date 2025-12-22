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

  $pass_fail = 0;
  if ($sample->hasField('pass_fail') && !$sample->get('pass_fail')->isEmpty()) {
    $pass_fail = (int) ($sample->get('pass_fail')->value ?? 0);
  }
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
  $file_repository = \Drupal::service('file.repository');
  $private_path = 'private://' . $filename;
  $directory = dirname($private_path);

  // Normalize root private:// directory to avoid "private:" mkdir warnings.
  if ($directory === 'private:' || $directory === 'private:/') {
    $directory = 'private://';
  }

  $prepare_flag = \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS;
  $real_directory = $file_system->realpath($directory);
  if ($real_directory && is_dir($real_directory)) {
    $prepare_flag = \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS;
  }

  if ($file_system->prepareDirectory($directory, $prepare_flag)) {
    try {
      $file = $file_repository->writeData($xml_string, $private_path, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        return $file;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_systemcheck_vaillant_xml')->error('Failed to create Vaillant XML file for sample @id: @message', [
        '@id' => method_exists($sample_entity, 'id') ? $sample_entity->id() : 'unknown',
        '@message' => $e->getMessage(),
      ]);
    }
  }
  else {
    \Drupal::logger('sentinel_systemcheck_vaillant_xml')->error('Unable to prepare directory @dir for sample @id.', [
      '@dir' => $directory,
      '@id' => method_exists($sample_entity, 'id') ? $sample_entity->id() : 'unknown',
    ]);
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
  if (method_exists($sample_entity, 'getSampleType')) {
    $pack_type = $sample_entity->getSampleType();
  }
  else {
    $pack_type = $sample_entity->get('pack_type')->value ?? '';
  }
  if ($pack_type !== 'vaillant') {
    return FALSE;
  }

  $pass_fail = 0;
  if ($sample_entity->hasField('pass_fail') && !$sample_entity->get('pass_fail')->isEmpty()) {
    $pass_fail = (int) ($sample_entity->get('pass_fail')->value ?? 0);
  }
  $is_pass = ($pass_fail == 1);
  
  $pack_ref = strip_tags($sample_entity->get('pack_reference_number')->value ?? '');
  $system_address = '';
  
  // Get system address - try to get from address field or construct from fields.
  if ($sample_entity->hasField('field_sentinel_sample_address')) {
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

  $recipients = [$email];

  $mail_sent = sentinel_systemcheck_vaillant_xml_send_email($recipients, $subject, $message_html, $message_plain, $file, $sample_entity->id());

  if ($mail_sent && function_exists('sentinel_systemcheck_custom_rule_events_invoke_sample_result_calculated')) {
    sentinel_systemcheck_custom_rule_events_invoke_sample_result_calculated($email, $file->getFileUri(), $subject, $message_html, $message_plain);
  }

  // Delete the temporary file.
  $file->delete();

  return $mail_sent;
}

/**
 * Sends the Vaillant XML email to the configured recipients.
 *
 * @param array $recipients
 *   List of email addresses to notify.
 * @param string $subject
 *   Email subject.
 * @param string $message_html
 *   Email body (HTML version).
 * @param string $message_plain
 *   Email body (plain text version).
 * @param \Drupal\file\FileInterface $file
 *   File entity representing the XML attachment.
 * @param int|string|null $sample_id
 *   The sample identifier for logging context.
 *
 * @return bool
 *   TRUE on success, FALSE on failure.
 */
function sentinel_systemcheck_vaillant_xml_send_email(array $recipients, $subject, $message_html, $message_plain, $file, $sample_id = NULL) {
  $mailer = sentinel_portal_queue_get_mailer();

  $site_config = \Drupal::config('system.site');
  $from_address = $site_config->get('mail') ?: 'noreply@sentinel.com';
  $from_name = $site_config->get('name') ?: 'Sentinel';

  $email = new \Symfony\Component\Mime\Email();
  $email->from(new \Symfony\Component\Mime\Address($from_address, $from_name));

  $reroute_address = '';
  if (\Drupal::hasContainer()) {
    try {
      $reroute_address = \Drupal::config('smtp.settings')->get('smtp_reroute_address') ?? '';
    }
    catch (\Exception $e) {
      $reroute_address = '';
    }
  }

  if (!empty($reroute_address)) {
    \Drupal::logger('sentinel_systemcheck_vaillant_xml')->info('Vaillant XML email rerouted to @address for sample @sample.', ['@address' => $reroute_address, '@sample' => $sample_id]);
    $email->addTo($reroute_address);
  }
  else {
    \Drupal::logger('sentinel_systemcheck_vaillant_xml')->info('Vaillant XML email recipients for sample @sample: @recipients', ['@recipients' => implode(', ', $recipients), '@sample' => $sample_id]);
    foreach ($recipients as $recipient) {
      $email->addTo($recipient);
    }
  }

  $email->subject($subject);
  $email->html($message_html);
  $email->text($message_plain ?: \Drupal\Core\Mail\MailFormatHelper::htmlToText($message_html));

  $file_system = \Drupal::service('file_system');
  $attachment_path = $file_system->realpath($file->getFileUri());

  if (!$attachment_path || !file_exists($attachment_path)) {
    \Drupal::logger('sentinel_systemcheck_vaillant_xml')->error('Unable to attach XML report for sample @id; file missing at @uri.', [
      '@id' => $sample_id ?? (method_exists($file, 'getOwnerId') ? $file->getOwnerId() : 'unknown'),
      '@uri' => $file->getFileUri(),
    ]);
    return FALSE;
  }

  $email->attachFromPath($attachment_path, $file->getFilename(), 'application/xml');

  try {
    $mailer->send($email);
    \Drupal::logger('sentinel_systemcheck_vaillant_xml')->info('Vaillant XML email dispatched for sample @sample with subject @subject.', ['@subject' => $subject, '@sample' => $sample_id]);
    return TRUE;
  }
  catch (\Exception $e) {
    \Drupal::logger('sentinel_systemcheck_vaillant_xml')->error('Failed to send Vaillant XML email for sample @sample: @message', ['@message' => $e->getMessage(), '@sample' => $sample_id]);
    return FALSE;
  }
}
