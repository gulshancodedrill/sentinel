<?php

namespace Drupal\sentinel_reports\Service\CategoryStats;

/**
 * Interface for category statistics collectors.
 */
interface CategoryStatsInterface {

  /**
   * Build the SQL query used to retrieve statistics.
   */
  public function setQuery(): void;

  /**
   * Return the result object formatted for the hierarchical chart.
   *
   * @return \stdClass
   *   The formatted result object.
   */
  public function getResultObject(): \stdClass;
}













