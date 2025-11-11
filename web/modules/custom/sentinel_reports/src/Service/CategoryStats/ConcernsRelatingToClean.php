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

    $this->query = "
      SELECT
        CONCAT(
          COUNT(CASE WHEN tmp2.not_cleaned_flushes > 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp2.not_cleaned_flushes > 0 THEN tmp2.pid END SEPARATOR '+')
        ) AS needed_more_thorough_flushing,
        CONCAT(
          COUNT(CASE WHEN tmp2.not_cleaned_cleans > 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp2.not_cleaned_cleans > 0 THEN tmp2.pid END SEPARATOR '+')
        ) AS needed_more_thorough_cleaning,
        CONCAT(
          COUNT(CASE WHEN tmp2.x100_inhibitor_too_low > 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp2.x100_inhibitor_too_low > 0 THEN tmp2.pid END SEPARATOR '+')
        ) AS needed_more_inhibitor,
        CONCAT(
          COUNT(CASE WHEN tmp2.not_x100 > 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp2.not_x100 > 0 THEN tmp2.pid END SEPARATOR '+')
        ) AS a_non_sentinel_inhibitor_was_used,
        CONCAT(
          COUNT(CASE WHEN tmp2.not_enough_circulation > 0 THEN 1 END),
          '#',
          GROUP_CONCAT(CASE WHEN tmp2.not_enough_circulation > 0 THEN tmp2.pid END SEPARATOR '+')
        ) AS inhibitor_needed_more_thorough_circulation
      FROM (
        SELECT
          tmp.pid,
          COUNT(CASE WHEN tmp.pid IS NOT NULL AND rec.field_stat_recommendation_value IS NOT NULL THEN 1 END) AS not_cleaned,
          COUNT(CASE WHEN rec.field_stat_recommendation_value LIKE '%flush%'
            AND rec.field_stat_recommendation_value NOT LIKE '%clean%'
            THEN 1 END) AS not_cleaned_flushes,
          COUNT(CASE WHEN rec.field_stat_recommendation_value LIKE '%clean%'
            AND rec.field_stat_recommendation_value NOT LIKE '%Adequate protection can only be confirmed by the manufacturer of the detected inhibitor%'
            THEN 1 END) AS not_cleaned_cleans,
          COUNT(CASE WHEN ic.field_stat_individual_comment_value LIKE '%X100 inhibitor is too low%'
            AND rec.field_stat_recommendation_value LIKE '%too low%'
            THEN 1 END) AS x100_inhibitor_too_low,
          COUNT(CASE WHEN ic.field_stat_individual_comment_value LIKE '%not X100%'
            AND rec.field_stat_recommendation_value LIKE '%Adequate protection can only be confirmed by the manufacturer of the detected inhibitor%'
            THEN 1 END) AS not_x100,
          COUNT(CASE WHEN rec.field_stat_recommendation_value LIKE '%Sentinel X100 level is very high%'
            THEN 1 END) AS not_enough_circulation
        FROM (
          SELECT
            rid.field_stat_pack_reference_id_target_id AS pid,
            COUNT(CASE WHEN en.field_stat_element_name_value = 'X100'
              AND ttfd.name = 'Pass' THEN 1 END) AS x100_pass,
            COUNT(CASE WHEN en.field_stat_element_name_value <> 'X100'
              AND ttfd.name = 'Fail' THEN 1 END) AS other_fail_results
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
          HAVING x100_pass >= 1 AND other_fail_results > 0
        ) AS tmp
        LEFT JOIN sentinel_stat__field_stat_pack_reference_id pr
          ON tmp.pid = pr.field_stat_pack_reference_id_target_id AND pr.bundle = 'sentinel_stat'
        LEFT JOIN sentinel_stat__field_stat_recommendation rec
          ON pr.entity_id = rec.entity_id AND rec.bundle = 'sentinel_stat' AND rec.deleted = 0
        LEFT JOIN sentinel_stat__field_stat_individual_comment ic
          ON pr.entity_id = ic.entity_id AND ic.bundle = 'sentinel_stat' AND ic.deleted = 0
        GROUP BY tmp.pid
      ) AS tmp2";
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








