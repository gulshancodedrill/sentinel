<?php

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;

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
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING', 6);
}
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING', 7);
}
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_INHIBITOR')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_INHIBITOR', 8);
}
if (!defined('CHART_ID_CATEGORY_A_NON_SENTINEL_INHIBITOR_WAS_USED')) {
  define('CHART_ID_CATEGORY_A_NON_SENTINEL_INHIBITOR_WAS_USED', 9);
}
if (!defined('CHART_ID_CATEGORY_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION')) {
  define('CHART_ID_CATEGORY_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION', 10);
}
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING_AND_NEEDED_MORE_CLEANING')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING_AND_NEEDED_MORE_CLEANING', 11);
}
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING_AND_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING_AND_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION', 12);
}
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING_NEEDED_MORE_INHIBITOR')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING_NEEDED_MORE_INHIBITOR', 13);
}
if (!defined('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING_AND_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION')) {
  define('CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING_AND_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION', 14);
}

/**
 * Gets an array of headers that are to be exported as part of the CSV file.
 *
 * @return array
 *   Array of header names.
 */
function _sentinel_reports_get_csv_export_headers() {
  return [
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
      $renderer = \Drupal::service('renderer');
      return ['content' => $renderer->render($html), 'export_link' => $link];
    }

    if (!$client) {
      $renderer = \Drupal::service('renderer');
      return ['content' => $renderer->render($html), 'export_link' => $link];
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

      // Create export link for "Export all data".
      // Get the export key from the data.
      $export_key = !empty($data_passes[1]->export_key) ? $data_passes[1]->export_key : '';
      
      if (!empty($export_key)) {
        try {
          $url = Url::fromRoute('sentinel_reports.export_stats', ['key_name' => $export_key]);
          
          $link = [
            '#type' => 'link',
            '#title' => t('Export all data'),
            '#url' => $url,
            '#attributes' => [
              'class' => ['button', 'button--primary'],
            ],
          ];
        }
        catch (\Exception $e) {
          \Drupal::logger('sentinel_reports')->error('Error creating export link: @message', ['@message' => $e->getMessage()]);
        }
      }
    }
  }

  $attachments = [];
  if (is_array($html) && isset($html['#attached'])) {
    $attachments = $html['#attached'];
    unset($html['#attached']);
  }

  $renderer = \Drupal::service('renderer');

  $content = is_array($html) ? $renderer->renderRoot($html) : (string) $html;
  $export = (!empty($link) && is_array($link)) ? $renderer->renderRoot($link) : '';

  return [
    'content' => $content,
    'export_link' => $export,
    'attachments' => $attachments,
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
  if (empty($cids)) {
    return [];
  }

  $database = \Drupal::database();
  
  // Set group_concat_max_len.
  $database->query('SET SESSION group_concat_max_len = 18446744073709551615');

  // First, get the counts.
  $query = $database->select('sentinel_sample', 'ss');
  $query->addExpression('COUNT(CASE WHEN ss.pass_fail = 1 THEN 1 END)', 'total_passed');
  $query->addExpression('COUNT(CASE WHEN ss.pass_fail = 0 THEN 1 END)', 'total_failed');

  $query->leftJoin('sentinel_client', 'sc', 'sc.ucr = ss.ucr');
  $query->condition('sc.cid', $cids, 'IN');

  if (!empty($date_range)) {
    // Add time to dates to properly compare with datetime field.
    $date_from = $date_range[0] . ' 00:00:00';
    $date_to = $date_range[1] . ' 23:59:59';
    $query->condition('ss.date_reported', [$date_from, $date_to], 'BETWEEN');
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

  // Now get all PIDs separately to avoid GROUP_CONCAT size issues.
  $pids_query = $database->select('sentinel_sample', 'ss');
  $pids_query->fields('ss', ['pid']);
  
  $pids_query->leftJoin('sentinel_client', 'sc', 'sc.ucr = ss.ucr');
  $pids_query->condition('sc.cid', $cids, 'IN');
  
  if (!empty($date_range)) {
    $pids_query->condition('ss.date_reported', [$date_from, $date_to], 'BETWEEN');
  }
  
  if (!empty($location)) {
    $pids_query->condition('ss.town_city', '%' . $database->escapeLike($location) . '%', 'LIKE');
  }
  
  if (!empty($installer_name)) {
    $pids_query->condition('ss.installer_name', '%' . $database->escapeLike($installer_name) . '%', 'LIKE');
  }
  
  $all_pids_result = $pids_query->execute()->fetchCol();
  $all_sample_pids = implode('+', $all_pids_result);

  $all_result_info = [];

  if (!empty($result->total_passed) || !empty($result->total_failed)) {
    // Use the actual failed count from the query instead of category stats.
    $total_fails = (int) $result->total_failed;

    $categories = [
      'ConcernsRelatingToInhibitor',
      'ConcernsRelatingToClean',
      'ConcernsRelatingToBothCleanAndInhibitor',
    ];

    $namespace = 'Drupal\\sentinel_reports\\Service\\CategoryStats\\';
    $segments = [];
    $pids = [];

    foreach ($categories as $category) {
      $class_name = $namespace . $category;
      if (!class_exists($class_name)) {
        continue;
      }

      try {
        $category_stat_object = new $class_name($cids, $date_range, $location, $installer_name);
        $category_result_object = $category_stat_object->getResultObject();
        $pids[] = $category_stat_object->getPids();
        $segments[] = $category_result_object;
      }
      catch (\Exception $e) {
        \Drupal::logger('sentinel_reports')->warning('Category stats error for @category: @message', [
          '@category' => $category,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $overall_passes_object = (object) [
      'category' => 'Passes',
      'id_category' => CHART_ID_CATEGORY_PASSES,
      'value' => $result->total_passed,
    ];
    
    // Create export key for ALL samples (passed + failed).
    $key_all = \Drupal::service('uuid')->generate();

    \Drupal::cache()->set('sentinel_reports_' . $key_all, [
      'cids' => $cids,
      'category' => 'all-samples',
      'date_from' => $date_range[0],
      'date_to' => $date_range[1],
      'location' => $location,
      'installer_name' => $installer_name,
      'pass_fail_filter' => NULL, // No filter - export all
    ], Cache::PERMANENT);

    // Create separate export key for FAILED samples only.
    $key_failed = \Drupal::service('uuid')->generate();

    \Drupal::cache()->set('sentinel_reports_' . $key_failed, [
      'cids' => $cids,
      'category' => 'failed-samples',
      'date_from' => $date_range[0],
      'date_to' => $date_range[1],
      'location' => $location,
      'installer_name' => $installer_name,
      'pass_fail_filter' => 0, // Only failed samples
    ], Cache::PERMANENT);

    $overall_failed_object = (object) [
      'category' => 'Fails',
      'id_category' => CHART_ID_CATEGORY_FAILS,
      'value' => $total_fails,
      'categories' => $segments,
      'export_link' => _sentinel_reports_render_export_link($key_failed),
      'export_key' => $key_all, // For "Export all data" link
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
  if (empty($uuid_key)) {
    return '';
  }

  $url = Url::fromRoute('sentinel_reports.export_stats', ['key_name' => $uuid_key], [
    'attributes' => [
      'target' => '_blank',
    ],
  ]);
  
  $link = [
    '#type' => 'link',
    '#title' => t('Download'),
    '#url' => $url,
    '#attributes' => [
      'target' => '_blank',
    ],
  ];

  return \Drupal::service('renderer')->render($link);
}

