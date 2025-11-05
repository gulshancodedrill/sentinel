<?php

namespace Drupal\sentinel_reports\Service\CategoryStats;

/**
 * Statistics collector for concerns relating to cleaning.
 */
class ConcernsRelatingToClean extends CategoryStatsBase {

  /**
   * {@inheritdoc}
   */
  public function setQuery(): void {
    $additionalCondition = $this->getClientIdOrInstallerNameOrLocationConditions();

    $this->query = "SELECT
      CONCAT(count(CASE WHEN tmp2.not_cleaned_flushes > 0 THEN 1 END), '#', GROUP_CONCAT(CASE WHEN tmp2.not_cleaned_flushes > 0 THEN pid END SEPARATOR '+')) as needed_more_thorough_flushing,
      CONCAT(count(CASE WHEN tmp2.not_cleaned_cleans > 0 THEN 1 END), '#', GROUP_CONCAT(CASE WHEN tmp2.not_cleaned_cleans > 0 THEN pid END SEPARATOR '+')) as needed_more_thorough_cleaning,
      CONCAT(count(CASE WHEN tmp2.X100_inhibitor_too_low > 0 THEN 1 END), '#', GROUP_CONCAT(CASE WHEN tmp2.X100_inhibitor_too_low > 0 THEN pid END SEPARATOR '+')) as needed_more_inhibitor,
      CONCAT(count(CASE WHEN tmp2.not_x100 > 0 THEN 1 END), '#', GROUP_CONCAT(CASE WHEN tmp2.not_x100 > 0 THEN pid END SEPARATOR '+')) as a_non_sentinel_inhibitor_was_used,
      CONCAT(count(CASE WHEN tmp2.not_enough_circulation > 0 THEN 1 END), '#', GROUP_CONCAT(CASE WHEN tmp2.not_enough_circulation > 0 THEN pid END SEPARATOR '+')) as inhibitor_needed_more_thorough_circulation
    FROM  (
      SELECT
        pid,
        count(CASE WHEN tmp.pid is not null AND sr.field_stat_recommendation_value is not null THEN 1 END)as not_cleaned,
        count(CASE WHEN sr.field_stat_recommendation_value like '%flush%' AND sr.field_stat_recommendation_value not like '%clean%' THEN 1 END) as not_cleaned_flushes,
        count(CASE WHEN sr.field_stat_recommendation_value like '%clean%' AND sr.field_stat_recommendation_value not like '%Adequate protection can only be confirmed by the manufacturer of the detected inhibitor%' THEN 1 END) as not_cleaned_cleans,
        count(CASE WHEN ic.field_stat_individual_comment_value like '%X100 inhibitor is too low%' AND sr.field_stat_recommendation_value like '%too low%' THEN 1 END) as X100_inhibitor_too_low,
        count(CASE WHEN ic.field_stat_individual_comment_value like '%not X100%' AND sr.field_stat_recommendation_value like '%Adequate protection can only be confirmed by the manufacturer of the detected inhibitor%' THEN 1 END) as not_x100,
        count(CASE WHEN sr.field_stat_recommendation_value like '%Sentinel X100 level is very high%' THEN 1 END) as not_enough_circulation
        FROM (
          SELECT
            pid,
            count(CASE WHEN field_stat_element_name_value = 'X100' AND td.name = 'Pass' THEN 1 END) AS x100_pass,
            count(CASE WHEN en.field_stat_element_name_value != 'X100' AND td.name = 'Fail' THEN 1 END) AS other_fail_results
          FROM field_data_field_stat_pack_reference_id rid
          LEFT OUTER JOIN field_data_field_stat_element_name en ON en.entity_id=rid.entity_id and en.bundle = 'sentinel_stat' and en.entity_type= 'sentinel_stat'
          LEFT OUTER JOIN field_data_field_stat_result sr ON sr.entity_id=rid.entity_id and sr.bundle = 'sentinel_stat' and sr.entity_type= 'sentinel_stat'
          LEFT OUTER JOIN taxonomy_term_data td ON td.tid=sr.field_stat_result_tid and td.vid = 2
          LEFT OUTER JOIN sentinel_sample ss ON ss.pid = rid.field_stat_pack_reference_id_target_id and rid.bundle = 'sentinel_stat' and rid.entity_type= 'sentinel_stat'
          LEFT OUTER JOIN sentinel_client sc ON sc.ucr = ss.ucr
          WHERE (ss.date_reported BETWEEN :date_from AND :date_to) AND (ss.pass_fail = 0) {$additionalCondition}
          GROUP BY field_stat_pack_reference_id_target_id HAVING (x100_pass >= 1 and other_fail_results > 0)
      ) AS tmp
      LEFT JOIN field_data_field_stat_pack_reference_id pr on tmp.pid=pr.field_stat_pack_reference_id_target_id and pr.bundle='sentinel_stat' and pr.entity_type='sentinel_stat'
      LEFT JOIN field_data_field_stat_recommendation sr on pr.entity_id=sr.entity_id and sr.bundle='sentinel_stat' and sr.entity_type='sentinel_stat'
      LEFT JOIN field_data_field_stat_individual_comment ic on pr.entity_id=ic.entity_id and ic.bundle='sentinel_stat' and ic.entity_type='sentinel_stat'
      GROUP BY field_stat_pack_reference_id_target_id
    ) as tmp2";
  }

  /**
   * {@inheritdoc}
   */
  public function getResultObject(): \stdClass {
    $total_count = 0;
    $infoArrayPidsKeys = [];
    $key = '';
    $categoryName = 'concern_relating_to_clean';
    $this->sortData($total_count, $key, $infoArrayPidsKeys, $categoryName);

    $infoArray = [
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING,
        'category_name' => 'Needed more thorough flushing',
        'db_query_result_name' => 'needed_more_thorough_flushing',
        'pids' => $infoArrayPidsKeys['needed_more_thorough_flushing'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING,
        'category_name' => 'Needed more thorough cleaning',
        'db_query_result_name' => 'needed_more_thorough_cleaning',
        'pids' => $infoArrayPidsKeys['needed_more_thorough_cleaning'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_INHIBITOR,
        'category_name' => 'Needed more inhibitor',
        'db_query_result_name' => 'needed_more_inhibitor',
        'pids' => $infoArrayPidsKeys['needed_more_inhibitor'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_A_NON_SENTINEL_INHIBITOR_WAS_USED,
        'category_name' => 'A non-Sentinel inhibitor was used',
        'db_query_result_name' => 'a_non_sentinel_inhibitor_was_used',
        'pids' => $infoArrayPidsKeys['a_non_sentinel_inhibitor_was_used'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION,
        'category_name' => 'Inhibitor needed more thorough circulation',
        'db_query_result_name' => 'inhibitor_needed_more_thorough_circulation',
        'pids' => $infoArrayPidsKeys['inhibitor_needed_more_thorough_circulation'] ?? '',
      ],
    ];

    $segments = $this->getSegments($infoArray);

    return $this->getStatObjectBasedOnValues(
      'Concern relating to clean',
      CHART_ID_CATEGORY_CONCERN_RELATING_TO_CLEAN,
      $total_count,
      $segments,
      _sentinel_reports_render_export_link($key)
    );
  }
}




