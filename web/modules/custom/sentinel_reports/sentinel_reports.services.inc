<?php

/**
 * @file
 * Service functions for Sentinel Reports module.
 */

// Define chart category constants.
if (!defined('CHART_ID_CATEGORY_FAILS')) {
  define('CHART_ID_CATEGORY_FAILS', 0);
}
if (!defined('CHART_ID_CATEGORY_PASSES')) {
  define('CHART_ID_CATEGORY_PASSES', 1);
}
if (!defined('CHART_ID_CATEGORY_CONCERN_RELATING_TO_INHIBITOR')) {
  define('CHART_ID_CATEGORY_CONCERN_RELATING_TO_INHIBITOR', 3);
}
if (!defined('CHART_ID_CATEGORY_CONCERN_RELATING_TO_CLEAN')) {
  define('CHART_ID_CATEGORY_CONCERN_RELATING_TO_CLEAN', 4);
}
if (!defined('CHART_ID_CATEGORY_CONCERN_RELATING_TO_BOTH_CLEAN_AND_INHIBITOR')) {
  define('CHART_ID_CATEGORY_CONCERN_RELATING_TO_BOTH_CLEAN_AND_INHIBITOR', 5);
}

/**
 * Gets an array of headers that are to be exported as part of the CSV file.
 *
 * @return array
 *   Array of header names.
 */
function _sentinel_reports_get_csv_export_headers() {
  return [
    'id',
    'pack_reference_number',
    'pack_type',
    'date_installed',
    'date_sent',
    'date_reported',
    'system_location',
    'property_number',
    'street',
    'town_city',
    'county',
    'postcode',
    'system_6_months',
    'system_age',
    'boiler_id',
    'boiler_manufacturer',
    'client_name',
    'landlord',
    'engineers_code',
    'project_id',
    'uprn',
    'company_address1',
    'company_address2',
    'company_town',
    'company_county',
    'company_postcode',
    'company_tel',
    'company_name',
    'company_email',
    'installer_name',
    'installer_email',
    'installer_company',
  ];
}

/**
 * Gather all the failure statistics and return render array.
 *
 * @param string|bool $date_from
 *   Start date or FALSE.
 * @param string|bool $date_to
 *   End date or FALSE.
 * @param string|bool $installer_name
 *   Installer name or FALSE.
 * @param string|bool $location
 *   Location or FALSE.
 *
 * @return array
 *   Array with 'content' => render array, 'export_link' => link HTML.
 */
function _sentinel_reports_get_failure_analysis_output($date_from, $date_to, $installer_name, $location) {
  $html = [
    '#theme' => 'failure_analysis_without_data',
  ];
  $link = '';

  if (!empty($date_from) && !empty($date_to)) {
    $current_user = \Drupal::currentUser();
    
    if (function_exists('sentinel_portal_entities_get_client_by_user')) {
      $client = sentinel_portal_entities_get_client_by_user($current_user);
    }
    else {
      return ['content' => render($html), 'export_link' => $link];
    }

    if (!$client) {
      return ['content' => render($html), 'export_link' => $link];
    }

    if (function_exists('get_more_clients_based_client_cohorts')) {
      $cids = get_more_clients_based_client_cohorts($client);
    }
    else {
      $cids = [];
    }
    
    $cids[] = $client->id();

    // Get all pass and fail stats for the user.
    $data_passes = _sentinel_reports_get_pass_fail_stats($cids, [
      $date_from,
      $date_to,
    ], $location, $installer_name);

    if (!empty($data_passes)) {
      // Build render array with data.
      $build = [
        '#theme' => 'failure_analysis_with_data',
        '#data' => $data_passes,
        '#attached' => [
          'library' => [
            'sentinel_reports/d3-hierarchical-pie',
          ],
          'drupalSettings' => [
            'sentinel_reports' => [
              'pass_fails' => $data_passes,
            ],
          ],
        ],
      ];

      $html = $build;

      // Create export link.
      try {
        $date_obj_from = \DateTime::createFromFormat('Y-m-d', $date_from);
        $date_obj_to = \DateTime::createFromFormat('Y-m-d', $date_to);

        if ($date_obj_from !== FALSE && $date_obj_to !== FALSE) {
          $url = Url::fromRoute('sentinel_portal_sample.export', [], [
            'query' => [
              'date_reported_1[min][date]' => $date_obj_from->format('m/d/Y'),
              'date_reported_1[max][date]' => $date_obj_to->format('m/d/Y'),
              'installer_name' => $installer_name,
            ],
          ]);
          
          $link = [
            '#type' => 'link',
            '#title' => t('Export all data'),
            '#url' => $url,
            '#attributes' => [
              'target' => '_blank',
            ],
          ];
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('sentinel_reports')->error('Error creating export link: @message', ['@message' => $e->getMessage()]);
      }
    }
  }

  return [
    'content' => render($html),
    'export_link' => !empty($link) ? render($link) : '',
  ];
}

/**
 * Get all the pass stats for packs.
 *
 * @param array $cids
 *   Client ids linked via the ucr.
 * @param array $date_range
 *   Date ranges [from, to].
 * @param string|bool $location
 *   Location that the user has entered as a string.
 * @param string|bool $installer_name
 *   Installer name that the user has entered.
 *
 * @return array
 *   The result info of passes and fails.
 */
function _sentinel_reports_get_pass_fail_stats($cids, $date_range, $location, $installer_name = '') {
  $database = \Drupal::database();
  
  // Set group_concat_max_len.
  $database->query('SET SESSION group_concat_max_len = 18446744073709551615');

  $query = $database->select('sentinel_sample', 'ss');
  $query->addExpression('count(CASE WHEN ss.pass_fail = 1 THEN 1 END)', 'total_passed');

  // If the user does not have permission to view all the stats then restrict.
  $current_user = \Drupal::currentUser();
  if (!$current_user->hasPermission('sentinel view all sentinel_sample')) {
    $query->leftJoin('sentinel_client', 'sc', 'sc.ucr = ss.ucr');
    $query->condition('sc.id', $cids, 'IN');
  }

  if (!empty($date_range)) {
    $query->condition('ss.date_reported', $date_range, 'BETWEEN');
  }

  // Add in location condition.
  if (!empty($location)) {
    $query->condition('ss.town_city', '%' . $database->escapeLike($location) . '%', 'LIKE');
  }

  // Add the installer_name field to the search conditions.
  if (!empty($installer_name)) {
    $query->condition('ss.installer_name', '%' . $database->escapeLike($installer_name) . '%', 'LIKE');
  }

  $result = $query->execute()->fetchObject();

  $all_result_info = [];

  if (!empty($result->total_passed)) {
    $categories = [
      'ConcernsRelatingToInhibitor',
      'ConcernsRelatingToClean',
      'ConcernsRelatingToBothCleanAndInhibitor',
    ];
    
    $total_fails = 0;
    $segments = [];
    $pids = [];
    
    // Load classes if they exist.
    $class_path = \Drupal::service('module_handler')->getModuleDirectories()['sentinel_reports'] . '/src/Service/CategoryStats';
    
    foreach ($categories as $category) {
      $class_name = $category;
      if (class_exists($class_name)) {
        $category_stat_object = new $class_name($cids, $date_range, $location, $installer_name);
        $category_result_object = $category_stat_object->getResultObject();
        $pids[] = $category_stat_object->getPids();
        $segments[] = $category_result_object;
        $total_fails += $category_result_object->total;
      }
    }

    $overall_passes_object = (object) [
      'category' => 'Passes',
      'id_category' => CHART_ID_CATEGORY_PASSES,
      'value' => $result->total_passed,
    ];
    $key = \Drupal::service('uuid')->generate();
    $all_pids = implode('+', $pids);

    \Drupal::cache()->set('sentinel_reports_' . $key, [
      'pids' => $all_pids,
      'category' => 'all-fails',
      'date_from' => $date_range[0],
      'date_to' => $date_range[1],
    ], Cache::PERMANENT);

    $overall_failed_object = (object) [
      'category' => 'Fails',
      'id_category' => CHART_ID_CATEGORY_FAILS,
      'value' => $total_fails,
      'categories' => $segments,
      'export_link' => _sentinel_reports_render_export_link($key),
    ];

    $all_result_info = [$overall_passes_object, $overall_failed_object];
  }

  return $all_result_info;
}

/**
 * Render out the export link.
 *
 * @param string $uuid_key
 *   The uuid key for storing cached data.
 *
 * @return string
 *   A link HTML.
 */
function _sentinel_reports_render_export_link($uuid_key) {
  $url = Url::fromRoute('sentinel_reports.export_stats', ['key_name' => $uuid_key], [
    'attributes' => [
      'target' => '_blank',
    ],
  ]);
  
  $link = [
    '#type' => 'link',
    '#title' => '<span>Download</span>',
    '#url' => $url,
    '#attributes' => [
      'target' => '_blank',
    ],
  ];
  
  return render($link);
}

