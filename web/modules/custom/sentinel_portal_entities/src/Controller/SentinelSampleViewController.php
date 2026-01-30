<?php

namespace Drupal\sentinel_portal_entities\Controller;

use DateTime;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;

/**
 * Controller for sentinel sample canonical pages.
 */
class SentinelSampleViewController extends ControllerBase {

  /**
   * Title callback mirroring Drupal 7 implementation.
   */
  public function title(SentinelSample $sentinel_sample) {
    $reference = $sentinel_sample->get('pack_reference_number')->value ?? $sentinel_sample->id();
    return $this->t('Pack Information - @ref', ['@ref' => $reference]);
  }

  /**
   * Render the sentinel sample using the Drupal 7 style layout.
   */
  public function view(SentinelSample $sentinel_sample) {
    // Ensure the canonical render honours the raw pass/fail value:
    if ($sentinel_sample->hasField('pass_fail')) {
      $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
      $fresh = $storage->load($sentinel_sample->id());
      if ($fresh && $fresh->hasField('pass_fail') && !$fresh->get('pass_fail')->isEmpty()) {
        $raw = $fresh->get('pass_fail')->value;
        $normalized = ($raw === NULL || $raw === '') ? NULL : (int) $raw;
        $sentinel_sample->set('pass_fail', ['value' => $normalized]);
        // Keep legacy property in sync to avoid fallback returning stale data.
        $sentinel_sample->pass_fail = $normalized;
      }
    }

    $fields = sentinel_portal_entities_get_sample_fields();

    $actions_markup = $this->buildActionLinks($sentinel_sample);
    if ($actions_markup !== NULL) {
      $build['actions'] = [
        '#type' => 'container',
        '#weight' => 0,
        '#attributes' => ['class' => ['sentinel-sample-actions']],
        'links' => ['#markup' => $actions_markup],
      ];
    }

    $address_section = $this->buildAddressSection($sentinel_sample);
    if ($address_section !== NULL) {
      $address_section['#weight'] = 5;
      $build['address'] = $address_section;
    }

    $help_text = $this->buildHelpText($sentinel_sample);
    if ($help_text !== NULL) {
      $build['help_top'] = [
        '#markup' => $help_text,
        '#weight' => 2,
      ];
    }

    $pending_reasons = $this->buildPendingReasons($sentinel_sample, $fields);
    if ($pending_reasons !== NULL) {
      $build['pending_reasons'] = [
        '#markup' => $pending_reasons,
        '#weight' => 3,
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [],
      '#rows' => $this->buildFieldRows($sentinel_sample, $fields),
      '#attributes' => ['class' => ['table-bordered', 'table-hover']],
      '#weight' => 4,
    ];

    if ($help_text !== NULL) {
      $build['help_bottom'] = [
        '#markup' => $help_text,
        '#weight' => 6,
      ];
    }

    return $build;
  }

  /**
   * Build the action buttons markup.
   */
  protected function buildActionLinks(SentinelSample $sample): ?Markup {
   // dd($sample->get('pass_fail')->value);
   // dd($sample->isPass());
    $links = [];
    $module_handler = $this->moduleHandler();
    $sample_id = (string) $sample->id();
    if ($sample_id === '' && $sample->hasField('pid') && !$sample->get('pid')->isEmpty()) {
      $sample_id = (string) $sample->get('pid')->value;
    }
    if ($sample_id === '') {
      return NULL;
    }

    $is_pass = $sample->isPass();
    $is_fail = $sample->isFail();
    $is_pending = $sample->isPending();

    if (!$is_pending) {
      $links[] = Link::fromTextAndUrl(
        $this->t('View report in browser'),
        Url::fromRoute(
          'sentinel_systemcheck_certificate.view_result_html',
          ['sample_id' => $sample_id],
          [
            'attributes' => [
              'class' => ['mbtn', 'mbtn-6', 'mbtn-6c', 'link-browser', 'icon-browser'],
              'target' => '_blank',
            ],
          ],
        )
      )->toString();

      // Match the logic from /portal/samples list page
      $fileid = NULL;
      if ($sample->hasField('fileid') && !$sample->get('fileid')->isEmpty()) {
        $fileid = (int) $sample->get('fileid')->value;
      }
      
      if ($fileid) {
        $file = File::load($fileid);
        if ($file instanceof File) {
          $pdf_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
          $links[] = Link::fromTextAndUrl(
            $this->t('Download PDF'),
            Url::fromUri($pdf_url, [
              'attributes' => [
                'class' => ['mbtn', 'mbtn-6', 'mbtn-6c', 'link-download', 'icon-pdf'],
                'download' => '',
              ],
            ])
          )->toString();
        }
      }
   
      $links[] = Link::fromTextAndUrl(
        $this->t('Email Report'),
        Url::fromRoute(
          'sentinel_portal.sample_email',
          ['sentinel_sample' => $sample_id],
          ['attributes' => ['class' => ['mbtn', 'mbtn-6', 'mbtn-6c', 'link-download', 'icon-pdf']]]
        )
      )->toString();

      if ($module_handler->moduleExists('sentinel_systemcheck_vaillant_xml') && $sample->getSampleType() === 'vaillant') {
        $links[] = Link::fromTextAndUrl(
          $this->t('Email Vaillant Report'),
          Url::fromRoute(
            'sentinel_systemcheck_vaillant_xml.email',
            ['sample_id' => $sample_id],
            ['attributes' => ['class' => ['mbtn', 'mbtn-6', 'mbtn-6c', 'link-download', 'icon-pdf']]]
          )
        )->toString();
      }
    
      if ($module_handler->moduleExists('sentinel_systemcheck_vaillant_api') && $sample->getSampleType() === 'vaillant') {
        $links[] = Link::fromTextAndUrl(
          $this->t('Send Vaillant Report'),
          Url::fromRoute(
            'sentinel_systemcheck_vaillant_api.submit',
            ['sample_id' => $sample_id],
            ['attributes' => ['class' => ['mbtn', 'mbtn-6', 'mbtn-6c', 'link-download', 'icon-pdf']]]
          )
        )->toString();
      }
    }
  
    if ($this->currentUser()->hasPermission('sentinel portal administration')) {
      try {
        $links[] = Link::fromTextAndUrl(
          $this->t('Revisions'),
          Url::fromRoute(
            'entity.sentinel_sample.version_history',
            ['sentinel_sample' => $sample_id],
            ['attributes' => ['class' => ['link-download', 'mbtn', 'mbtn-6', 'mbtn-6c']]]
          )
        )->toString();
      }
      catch (\Exception $e) {
        // Ignore missing route.
      }
    }

    $status_class = 'pending';
    $status_label = $this->t('PENDING');
    if ($is_pass) {
      $status_class = 'passed';
      $status_label = $this->t('PASSED');
    }
    elseif ($is_fail) {
      $status_class = 'failed';
      $status_label = $this->t('FAILED');
    }
    $links[] = '<span class="lozenge--' . Html::escape($status_class) . '">' . Html::escape($status_label) . '</span>';

    $output = implode(' ', $links);
    return $output !== '' ? Markup::create($output) : NULL;
  }

  /**
   * Build the help text that appears above and below the table.
   */
  protected function buildHelpText(SentinelSample $sample): ?string {
    $sample_id = (string) $sample->id();
    if ($sample_id === '' && $sample->hasField('pid') && !$sample->get('pid')->isEmpty()) {
      $sample_id = (string) $sample->get('pid')->value;
    }
    if ($sample_id === '') {
      return NULL;
    }

    $edit_link = Link::fromTextAndUrl(
      $this->t('Edit this pack'),
      Url::fromRoute('entity.sentinel_sample.edit_form', ['sentinel_sample' => $sample_id])
    )->toString();

    $actions = [$edit_link];

    if (!$sample->isPending() && $this->currentUser()->hasPermission('sentinel portal regenerate any certificate')) {
      $actions[] = Link::fromTextAndUrl(
        $this->t('Regenerate PDF file'),
        Url::fromRoute('sentinel_systemcheck_certificate.regenerate_pdf', ['sample_id' => $sample_id])
      )->toString();
    }

    $actions_markup = implode(' ', $actions);
    $intro = $this->t('Some information on this pack can be added or amended. It will not be possible to change this information once this pack has been processed at the laboratory.');

    return '<p>' . $intro . '</p><p>' . $actions_markup . '</p>';
  }

  /**
   * Build the pending reasons markup when a sample has not been reported.
   */
  protected function buildPendingReasons(SentinelSample $sample, array $fields): ?string {
    if (!$sample->isPending()) {
      return NULL;
    }

    $sample_type = $sample->getSampleType();
    $missing = [];

    foreach ($fields as $field_name => $info) {
      if (!empty($info['portal_config']['required_results_field'][$sample_type])) {
        $value = $this->getRawFieldValue($sample, $field_name);
        if ($value === NULL || $value === '') {
          $title = $info['portal_config']['title'] ?? $field_name;
          $missing[] = '"' . Html::escape($title) . '" ' . $this->t('is missing.');
        }
      }
    }

    if (empty($missing)) {
      $missing[] = $this->t('None found. Sample might be in the process of being reported.');
    }

    return '<p><strong>' . $this->t('Pending Reasons:') . '</strong><ul><li>' . implode('</li><li>', $missing) . '</li></ul></p>';
  }

  /**
   * Build the table rows for the sample field data.
   */
  protected function buildFieldRows(SentinelSample $sample, array $fields): array {
    // Define the exact field order as requested by the user.
    $field_order = [
      'pid' => 'Pack ID',
      'pack_reference_number' => 'The pack reference number',
      'project_id' => 'Project ID',
      'installer_name' => 'Installer Name',
      'installer_email' => 'Installer Email',
      'company_name' => 'Company Name',
      'company_email' => 'Company Email',
      'company_address1' => 'Company Address 1',
      'company_address2' => 'Company Address 2',
      'company_town' => 'Company Town',
      'company_county' => 'Company County',
      'company_postcode' => 'Company Postcode',
      'company_tel' => 'Company Telephone',
      'system_location' => 'System Location',
      'system_age' => 'System Age',
      'system_6_months' => 'System > 6 Months Old?',
      'uprn' => 'UPRN',
      'property_number' => 'Property Number',
      'street' => 'Street',
      'town_city' => 'Town/City',
      'county' => 'County',
      'postcode' => 'Postcode',
      'landlord' => 'Landlord',
      'boiler_manufacturer' => 'Boiler Manufacturer',
      'boiler_id' => 'Boiler ID',
      'boiler_type' => 'Boiler Type',
      'engineers_code' => 'Engineers Code',
      'service_call_id' => 'Service Call ID',
      'date_installed' => 'Date Installed',
      'date_sent' => 'Date Sent',
      'date_booked' => 'Date Booked In',
      'date_processed' => 'Date Processed',
      'date_reported' => 'Date Reported',
      'fileid' => 'File ID',
      'filename' => 'Filename',
      'client_id' => 'The Client ID',
      'client_name' => 'Client Name',
      'customer_id' => 'Sentinel Customer ID',
      'lab_ref' => 'Lab Ref',
      'pack_type' => 'Pack Type',
      'card_complete' => 'Card Complete',
      'on_hold' => 'On Hold',
      'pass_fail' => 'Overall Pass/Fail',
      'appearance_result' => 'Appearance Result',
      'appearance_pass_fail' => 'Appearance Pass/Fail',
      'mains_cond_result' => 'Mains Conductivity Result',
      'sys_cond_result' => 'System Conductivity Result',
      'cond_pass_fail' => 'Conductivity Pass/Fail',
      'mains_cl_result' => 'Mains Chlorine Result',
      'sys_cl_result' => 'System Chlorine Result',
      'cl_pass_fail' => 'Chlorine Pass/Fail',
      'iron_result' => 'Iron Result',
      'iron_pass_fail' => 'Iron Pass/Fail',
      'copper_result' => 'Copper Result',
      'copper_pass_fail' => 'Copper Pass/Fail',
      'aluminium_result' => 'Aluminium Result',
      'aluminium_pass_fail' => 'Aluminium Pass/Fail',
      'mains_calcium_result' => 'Mains Calcium Result',
      'sys_calcium_result' => 'System Calcium Result',
      'calcium_pass_fail' => 'Calcium Pass/Fail',
      'ph_result' => 'pH Result',
      'ph_pass_fail' => 'pH Pass/Fail',
      'sentinel_x100_result' => 'Inhibitor Result',
      'sentinel_x100_pass_fail' => 'Inhibitor Pass/Fail',
      'molybdenum_result' => 'Molybdenum Result',
      'molybdenum_pass_fail' => 'Molybdenum Pass/Fail',
      'boron_result' => 'XXX Result',
      'boron_pass_fail' => 'XXX Pass/Fail',
      'manganese_result' => 'Manganese Result',
      'manganese_pass_fail' => 'Manganese Pass/Fail',
      'nitrate_result' => 'Nitrate Result',
      'mob_ratio' => 'Molybdenum and XXX Ratio',
      'created' => 'Created',
      'updated' => 'Updated',
      'ucr' => 'The UCR',
      'installer_company' => 'Installer Company',
      'old_pack_reference_number' => 'The old pack reference number',
      'duplicate_of' => 'Duplicate Of',
      'legacy' => 'Legacy Sample',
      'api_created_by' => 'API Created By',
    ];

    $rows = [];

    // Process fields in the specified order.
    foreach ($field_order as $field_name => $display_title) {
      // Skip if field doesn't exist in the fields array.
      if (!isset($fields[$field_name])) {
        continue;
      }

      $info = $fields[$field_name];

      // Skip internal fields that shouldn't be displayed.
      if ($field_name === 'sentinel_sample_address_target_id') {
        // Render via system_location row.
        continue;
      }

      // Check access permissions for sample results fields.
      if (!empty($info['portal_config']['access']['sample results required'])
        && !$this->currentUser()->hasPermission('sentinel sample results')) {
        continue;
      }

      // Use the predefined title from the order array.
      $title = $display_title;

      $value = $this->formatFieldValue($sample, $field_name, $info);

      $rows[] = [
        ['data' => ['#plain_text' => (string) $title]],
        sentinel_portal_entities_render_field_value($value),
      ];
    }

    return $rows;
  }

  /**
   * Format a field value including callbacks and date handling.
   */
  protected function formatFieldValue(SentinelSample $sample, string $field_name, array $info) {
    if ($field_name === 'pass_fail') {
      
      if ($sample->isPass()) {
        return $this->t('Pass');
      }
      if ($sample->isFail()) {
        return $this->t('Fail');
      }
      return $this->t('Pending');
    }

    $value = $this->getRawFieldValue($sample, $field_name);

    if ($value !== NULL) {
      $value = $this->formatValueByType($field_name, $info, $value);
    }

    if (!empty($info['portal_config']['value callback']) && function_exists($info['portal_config']['value callback'])) {
      $callback = $info['portal_config']['value callback'];
      $value = $callback($value, $sample);
    }
    else {
      if ($field_name === 'system_location') {
        $value = $this->renderSystemLocation($sample, $value);
      }
      elseif ($field_name === 'nitrate_result') {
        $value = sentinel_portal_entities_nitrate_result($value, $sample);
      }
      elseif (str_ends_with($field_name, '_pass_fail')) {
        $value = sentinel_portal_entities_print_pass_fail($value, $sample);
      }
    }

    return $value;
  }

  /**
   * Extract the raw value from the sample entity.
   */
  protected function getRawFieldValue(SentinelSample $sample, string $field_name) {
    if ($sample->hasField($field_name)) {
      $field = $sample->get($field_name);
      if ($field->isEmpty()) {
        return NULL;
      }
      $values = [];
      foreach ($field as $item) {
        $values[] = $item->getString();
      }
      return count($values) === 1 ? $values[0] : $values;
    }

    if (isset($sample->{$field_name}) && !is_object($sample->{$field_name})) {
      return $sample->{$field_name};
    }

    return NULL;
  }

  /**
   * Apply type-specific formatting to a field value.
   */
  protected function formatValueByType(string $field_name, array $info, $value) {
    $type = $info['type'] ?? NULL;
    if ($value === NULL || $type === NULL) {
      return $value;
    }

    if ($type === 'datetime') {
      $date_string = is_array($value) ? ($value['value'] ?? NULL) : $value;
      if (!empty($date_string)) {
        try {
          $date = new DateTime(str_replace('/', '-', (string) $date_string));
          return $date->format('d/m/Y');
        }
        catch (\Exception $e) {
          return $value;
        }
      }
    }

    if ($type === 'boolean') {
      if ($field_name === 'pass_fail' || str_ends_with($field_name, '_pass_fail')) {
        return $value;
      }
      return ((int) $value === 1) ? $this->t('Yes') : $this->t('No');
    }

    return $value;
  }

  /**
   * Prepare a table cell render array for the provided value.
   */
  protected function buildValueCell($value): array {
    if (is_array($value)) {
      $value = implode(', ', array_filter(array_map('strval', $value), static function ($item) {
        return $item !== '';
      }));
    }

    if ($value === NULL || $value === '') {
      return ['#markup' => '&nbsp;'];
    }

    return ['#plain_text' => (string) $value];
  }

  /**
   * Build the address summary section for the sample view.
   */
  protected function buildAddressSection(SentinelSample $sample): ?array {
    $address_id = NULL;
    if ($sample->hasField('field_sentinel_sample_address') && !$sample->get('field_sentinel_sample_address')->isEmpty()) {
      $address_id = (int) $sample->get('field_sentinel_sample_address')->target_id;
    }

    if (!$address_id && $sample->hasField('sentinel_sample_address_target_id') && !$sample->get('sentinel_sample_address_target_id')->isEmpty()) {
      $address_id = (int) $sample->get('sentinel_sample_address_target_id')->value;
    }

    if (!$address_id) {
      return NULL;
    }

    $entity_type_manager = \Drupal::entityTypeManager();
    if (!$entity_type_manager->hasDefinition('address')) {
      return NULL;
    }

    $address = $entity_type_manager->getStorage('address')->load($address_id);
    if (!$address) {
      return NULL;
    }

    $country_list = \Drupal::service('country_manager')->getList();
    $lines = [];

    if ($address->hasField('field_address') && !$address->get('field_address')->isEmpty()) {
      $address_item = $address->get('field_address')->first();
      if ($address_item) {
        $lines = array_filter([
          $address_item->address_line1 ?? '',
          $address_item->address_line2 ?? '',
          $address_item->locality ?? '',
          $address_item->administrative_area ?? '',
          $address_item->postal_code ?? '',
        ]);

        if (!empty($address_item->country_code)) {
          $lines[] = $country_list[$address_item->country_code] ?? $address_item->country_code;
        }
      }
    }

    if (!$lines) {
      foreach (['field_address_address_line1', 'field_address_address_line2', 'field_address_locality', 'field_address_administrative_area', 'field_address_postal_code'] as $field_name) {
        if ($address->hasField($field_name) && !$address->get($field_name)->isEmpty()) {
          $lines[] = $address->get($field_name)->value ?? '';
        }
      }

      if ($address->hasField('field_address_country_code') && !$address->get('field_address_country_code')->isEmpty()) {
        $country_code = $address->get('field_address_country_code')->value;
        if ($country_code) {
          $lines[] = $country_list[$country_code] ?? $country_code;
        }
      }
    }

    if (!$lines) {
      return NULL;
    }

    $items = [];
    foreach ($lines as $line) {
      if ($line === '') {
        continue;
      }
      $items[] = ['#plain_text' => $line];
    }

    $url = Url::fromUri('internal:/address/address/' . $address_id);
    $items[] = Link::fromTextAndUrl($this->t('View address details'), $url)->toRenderable();

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Address'),
      '#items' => $items,
      '#attributes' => ['class' => ['sentinel-sample-address']],
      '#wrapper_attributes' => ['class' => ['sentinel-sample-address-wrapper']],
    ];
  }

  /**
   * Render system location as a link to the address entity where possible.
   */
  protected function renderSystemLocation(SentinelSample $sample, $value) {
    if ($value === NULL || $value === '') {
      return $value;
    }

    $address_id = NULL;
    if ($sample->hasField('sentinel_sample_address_target_id') && !$sample->get('sentinel_sample_address_target_id')->isEmpty()) {
      $address_id = $sample->get('sentinel_sample_address_target_id')->value;
    }

    if ($address_id) {
      $address = NULL;
      if (\Drupal::entityTypeManager()->hasDefinition('address')) {
        $address = \Drupal::entityTypeManager()->getStorage('address')->load($address_id);
      }

      return Link::fromTextAndUrl($value, Url::fromUri('internal:/address/address/' . $address_id));
    }

    return $value;
  }

  /**
   * Locate an existing PDF certificate without mutating the sample entity.
   */
  protected function getExistingPdfUri(SentinelSample $sample): ?string {
    $file_system = \Drupal::service('file_system');

    if ($sample->hasField('fileid') && !$sample->get('fileid')->isEmpty()) {
      $file_id = (int) $sample->get('fileid')->value;
      if ($file_id) {
        $file = File::load($file_id);
        if ($file instanceof File) {
          $real_path = $file_system->realpath($file->getFileUri());
          if ($real_path && file_exists($real_path)) {
            return $file->getFileUri();
          }
        }
      }
    }

    $filename = NULL;
    if ($sample->hasField('filename') && !$sample->get('filename')->isEmpty()) {
      $filename = $sample->get('filename')->value;
    }

    if (empty($filename)) {
      return NULL;
    }

    $directory = 'private://new-pdf-certificates/other/';
    if ($sample->hasField('created') && !$sample->get('created')->isEmpty()) {
      try {
        $date = new DateTime($sample->get('created')->value);
        $directory = 'private://new-pdf-certificates/' . $date->format('m-Y') . '/';
      }
      catch (\Exception $e) {
        // Fall back to default directory.
      }
    }

    $locations = [
      $directory . $filename,
      'private://legacy-pdf-certificates/' . $filename,
    ];

    foreach ($locations as $uri) {
      $real_path = $file_system->realpath($uri);
      if ($real_path && file_exists($real_path)) {
        return $uri;
      }
    }

    return NULL;
  }
}


