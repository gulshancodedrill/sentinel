<?php

namespace Drupal\sentinel_reports\Service\CategoryStats;

/**
 * Statistics collector for concerns relating to both clean and inhibitor.
 */
class ConcernsRelatingToBothCleanAndInhibitor extends CategoryStatsBase {

  /**
   * {@inheritdoc}
   */
  public function setQuery(): void {
    $additionalCondition = $this->getClientIdOrInstallerNameOrLocationConditions();

    $this->query = "
      SELECT
        CONCAT(
          COUNT(CASE WHEN tmp.not_cleaned_flushes >= 1 AND tmp.inhibitor_low >= 1 AND tmp.unknown_inhib = 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp.not_cleaned_flushes >= 1 AND tmp.inhibitor_low >= 1 AND tmp.unknown_inhib = 0 THEN tmp.pid END SEPARATOR '+')
        ) AS needed_more_thorough_flushing_and_needed_more_inhibitor,
        CONCAT(
          COUNT(CASE WHEN tmp.not_cleaned_flushes >= 1 AND (tmp.not_enough_circulation >= 1 OR tmp.not_enough_circulation2 >= 1) AND tmp.unknown_inhib = 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp.not_cleaned_flushes >= 1 AND (tmp.not_enough_circulation >= 1 OR tmp.not_enough_circulation2 >= 1) AND tmp.unknown_inhib = 0 THEN tmp.pid END SEPARATOR '+')
        ) AS needed_more_thorough_flushing_and_inhibitor_needed_more_thorough_circulation,
        CONCAT(
          COUNT(CASE WHEN tmp.not_cleaned >= 1 AND tmp.inhibitor_low >= 1 AND tmp.unknown_inhib = 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp.not_cleaned >= 1 AND tmp.inhibitor_low >= 1 AND tmp.unknown_inhib = 0 THEN tmp.pid END SEPARATOR '+')
        ) AS needed_more_thorough_cleaning_and_needed_more_inhibitor,
        CONCAT(
          COUNT(CASE WHEN tmp.not_cleaned >= 1 AND (tmp.not_enough_circulation >= 1 OR tmp.not_enough_circulation2 >= 1) AND tmp.unknown_inhib = 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp.not_cleaned >= 1 AND (tmp.not_enough_circulation >= 1 OR tmp.not_enough_circulation2 >= 1) AND tmp.unknown_inhib = 0 THEN tmp.pid END SEPARATOR '+')
        ) AS needed_more_thorough_cleaning_and_inhibitor_needed_more_thorough_circulation,
        CONCAT(
          COUNT(CASE WHEN tmp.unknown_inhib > 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp.unknown_inhib > 0 THEN tmp.pid END SEPARATOR '+')
        ) AS a_non_sentinel_inhibitor_was_used
      FROM (
        SELECT
          agg.pid,
          SUM(CASE WHEN ic.field_stat_individual_comment_value LIKE '%Unknown inhibitor%' THEN 1 ELSE 0 END) AS unknown_inhib,
          SUM(CASE WHEN rec.field_stat_recommendation_value LIKE '%clean%'
            AND rec.field_stat_recommendation_value NOT LIKE '%Adequate protection can only be confirmed by the manufacturer of the detected inhibitor%'
            THEN 1 ELSE 0 END) AS not_cleaned,
          SUM(CASE WHEN ic.field_stat_individual_comment_value LIKE '%X100 inhibitor is too low%' THEN 1 ELSE 0 END) AS inhibitor_low,
          SUM(CASE WHEN rec.field_stat_recommendation_value LIKE '%System flush required%' THEN 1 ELSE 0 END) AS not_cleaned_flushes,
          SUM(CASE WHEN rec.field_stat_recommendation_value LIKE '%Sentinel X100 level is very high%' THEN 1 ELSE 0 END) AS not_enough_circulation,
          SUM(CASE WHEN ic.field_stat_individual_comment_value LIKE '%X100 level very high%' THEN 1 ELSE 0 END) AS not_enough_circulation2
        FROM (
          SELECT
            rid.field_stat_pack_reference_id_target_id AS pid,
            SUM(CASE WHEN en.field_stat_element_name_value = 'X100' AND (ttfd.name = 'Fail' OR ttfd.name = 'Warning') THEN 1 ELSE 0 END) AS x100_fail,
            SUM(CASE WHEN en.field_stat_element_name_value <> 'X100' AND ttfd.name = 'Fail' THEN 1 ELSE 0 END) AS other_fail_results
          FROM sentinel_stat__field_stat_pack_reference_id rid
          LEFT OUTER JOIN sentinel_stat__field_stat_element_name en
            ON en.entity_id = rid.entity_id AND en.bundle = 'sentinel_stat' AND en.deleted = 0
          LEFT OUTER JOIN sentinel_stat__field_stat_result res
            ON res.entity_id = rid.entity_id AND res.bundle = 'sentinel_stat' AND res.deleted = 0
          LEFT OUTER JOIN taxonomy_term_field_data ttfd
            ON ttfd.tid = res.field_stat_result_target_id AND ttfd.vid = 'condition_event_results'
          LEFT OUTER JOIN sentinel_sample ss
            ON ss.pid = rid.field_stat_pack_reference_id_target_id AND rid.bundle = 'sentinel_stat'
          LEFT OUTER JOIN sentinel_client sc ON sc.ucr = ss.ucr
          WHERE (ss.date_reported BETWEEN :date_from AND :date_to) AND ss.pass_fail = 0 {$additionalCondition}
          GROUP BY rid.field_stat_pack_reference_id_target_id
          HAVING x100_fail >= 1 AND other_fail_results > 0
        ) AS agg
        LEFT JOIN sentinel_stat__field_stat_pack_reference_id pr
          ON agg.pid = pr.field_stat_pack_reference_id_target_id AND pr.bundle = 'sentinel_stat'
        LEFT JOIN sentinel_stat__field_stat_recommendation rec
          ON pr.entity_id = rec.entity_id AND rec.bundle = 'sentinel_stat' AND rec.deleted = 0
        LEFT JOIN sentinel_stat__field_stat_individual_comment ic
          ON pr.entity_id = ic.entity_id AND ic.bundle = 'sentinel_stat' AND ic.deleted = 0
        GROUP BY agg.pid
      ) AS tmp";
  }

  /**
   * {@inheritdoc}
   */
  public function getResultObject(): \stdClass {
    $total_count = 0;
    $infoArrayPidsKeys = [];
    $key = '';
    $categoryName = 'concern_relating_to_both_clean_and_inhibitor';
    $this->sortData($total_count, $key, $infoArrayPidsKeys, $categoryName);

    $infoArray = [
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING_AND_NEEDED_MORE_CLEANING,
        'category_name' => 'Needed more thorough flushing and needed more inhibitor',
        'db_query_result_name' => 'needed_more_thorough_flushing_and_needed_more_inhibitor',
        'pids' => $infoArrayPidsKeys['needed_more_thorough_flushing_and_needed_more_inhibitor'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_FLUSHING_AND_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION,
        'category_name' => 'Needed more thorough flushing and inhibitor needed more thorough circulation',
        'db_query_result_name' => 'needed_more_thorough_flushing_and_inhibitor_needed_more_thorough_circulation',
        'pids' => $infoArrayPidsKeys['needed_more_thorough_flushing_and_inhibitor_needed_more_thorough_circulation'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING_NEEDED_MORE_INHIBITOR,
        'category_name' => 'Needed more thorough cleaning and needed more inhibitor',
        'db_query_result_name' => 'needed_more_thorough_cleaning_and_needed_more_inhibitor',
        'pids' => $infoArrayPidsKeys['needed_more_thorough_cleaning_and_needed_more_inhibitor'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_NEEDED_MORE_THOROUGH_CLEANING_AND_INHIBITOR_NEEDED_MORE_THOROUGH_CIRCULATION,
        'category_name' => 'Needed more thorough cleaning and inhibitor needed more thorough circulation',
        'db_query_result_name' => 'needed_more_thorough_cleaning_and_inhibitor_needed_more_thorough_circulation',
        'pids' => $infoArrayPidsKeys['needed_more_thorough_cleaning_and_inhibitor_needed_more_thorough_circulation'] ?? '',
      ],
      [
        'chart_id' => CHART_ID_CATEGORY_A_NON_SENTINEL_INHIBITOR_WAS_USED,
        'category_name' => 'A non-Sentinel inhibitor was used',
        'db_query_result_name' => 'A_non_Sentinel_inhibitor_was_used',
        'pids' => $infoArrayPidsKeys['A_non_Sentinel_inhibitor_was_used'] ?? '',
      ],
    ];

    $segments = $this->getSegments($infoArray);

    return $this->getStatObjectBasedOnValues(
      'Concern relating to both clean and inhibitor',
      CHART_ID_CATEGORY_CONCERN_RELATING_TO_BOTH_CLEAN_AND_INHIBITOR,
      $total_count,
      $segments,
      _sentinel_reports_render_export_link($key)
    );
  }
}














