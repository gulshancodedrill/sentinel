<?php

namespace Drupal\sentinel_reports\Service\CategoryStats;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;

/**
 * Abstract base class for generating hierarchical statistics.
 */
abstract class CategoryStatsBase implements CategoryStatsInterface {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The logger channel.
   */
  protected $logger;

  /**
   * Query string used for the statistics.
   */
  protected string $query = '';

  /**
   * Arguments for the query.
   */
  protected array $arguments = [];

  /**
   * Result set from the query.
   */
  protected array $queryResult = [];

  /**
   * Cached list of PID values for the most recent calculation.
   */
  protected string $pids = '';

  /**
   * Location filter value.
   */
  protected ?string $location;

  /**
   * Installer name filter value.
   */
  protected ?string $installerName;

  /**
   * Start date for filtering.
   */
  protected string $dateFrom;

  /**
   * End date for filtering.
   */
  protected string $dateTo;

  /**
   * Client IDs used to restrict results.
   */
  protected array $cids;

  /**
   * Constructs a category statistics object.
   */
  public function __construct(array $cids, array $dateRange, ?string $location, ?string $installerName) {
    $this->database = \Drupal::database();
    $this->logger = \Drupal::logger('sentinel_reports');
    $this->cids = $cids;
    $this->dateFrom = $dateRange[0];
    $this->dateTo = $dateRange[1];
    $this->location = $location ?: NULL;
    $this->installerName = $installerName ?: NULL;

    // Build the SQL query for the concrete implementation.
    $this->setQuery();
    $this->setArguments();
    $this->executeQuery();
  }

  /**
   * Get the list of PID values collected for this category.
   */
  public function getPids(): string {
    return $this->pids;
  }

  /**
   * Execute the statistics query and store the result set.
   */
  protected function executeQuery(): void {
    // Ensure the connection can concat long strings of PIDs.
    try {
      $this->database->query('SET SESSION group_concat_max_len = 4294967295');
      $result = $this->database->query($this->query, $this->arguments);
      $this->queryResult = $result ? (array) $result->fetchAssoc() : [];
    }
    catch (\Exception $exception) {
      $this->logger->error('Unable to execute statistics query: @message', ['@message' => $exception->getMessage()]);
      $this->queryResult = [];
    }
  }

  /**
   * Sets the prepared statement arguments.
   */
  protected function setArguments(): void {
    $this->arguments = $this->getDbQueryOptionsArray(
      $this->location,
      $this->cids,
      $this->dateFrom,
      $this->dateTo,
      $this->installerName
    );
  }

  /**
   * Build an array of query arguments based on the supplied filters.
   */
  protected function getDbQueryOptionsArray($location, array $cids, $date_from, $date_to, $installer_name = ''): array {
    $arguments = [];

    if (!empty($location)) {
      $arguments['location'] = '%' . $this->database->escapeLike($location) . '%';
    }

    if (!empty($cids) && !\Drupal::currentUser()->hasPermission('sentinel view all sentinel_sample')) {
      $arguments['cids[]'] = $cids;
    }

    $arguments['date_from'] = $date_from;
    $arguments['date_to'] = $date_to;

    if (!empty($installer_name)) {
      $arguments['installer_name'] = '%' . $this->database->escapeLike($installer_name) . '%';
    }

    return $arguments;
  }

  /**
   * Additional SQL conditions based on user access and filters.
   */
  protected function getClientIdOrInstallerNameOrLocationConditions(): string {
    $conditions = '';

    if (!empty($this->cids) && !\Drupal::currentUser()->hasPermission('sentinel view all sentinel_sample')) {
      $conditions .= ' AND (sc.cid IN (:cids[]))';
    }

    if (!empty($this->installerName)) {
      $conditions .= ' AND ss.installer_name LIKE :installer_name';
    }

    if (!empty($this->location)) {
      $conditions .= ' AND ss.town_city LIKE :location';
    }

    return $conditions;
  }

  /**
   * Normalise the result set into objects expected by the frontend visualisation.
   */
  protected function sortData(int &$total_count, string &$key, array &$infoArrayPidsKeys, string $categoryName): void {
    if (empty($this->queryResult)) {
      return;
    }

    $cache = \Drupal::cache();
    $uuid = \Drupal::service('uuid');
    $allPids = [];

    foreach ($this->queryResult as $columnName => $value) {
      $valueString = (string) $value;
      [$totalRaw, $pidsRaw] = array_pad(explode('#', $valueString), 2, '');

      $total = (int) $totalRaw;
      $pids = trim($pidsRaw);

      if ($pids !== '') {
        $allPids[] = $pids;
      }

      $total_count += $total;

      $uuidKey = '';
      if ($pids !== '') {
        $uuidKey = $uuid->generate();
        $cache->set('sentinel_reports_' . $uuidKey, [
          'pids' => $pids,
          'category' => $columnName,
          'date_from' => $this->dateFrom,
          'date_to' => $this->dateTo,
        ], Cache::PERMANENT);
      }

      $infoArrayPidsKeys[$columnName] = $uuidKey;
      $this->queryResult[$columnName] = $total;
    }

    $key = '';
    $this->pids = implode('+', array_filter($allPids));

    if ($this->pids !== '') {
      $key = $uuid->generate();
      $cache->set('sentinel_reports_' . $key, [
        'pids' => $this->pids,
        'category' => $categoryName,
        'date_from' => $this->dateFrom,
        'date_to' => $this->dateTo,
      ], Cache::PERMANENT);
    }
  }

  /**
   * Helper to build statistic objects for the chart visualisation.
   */
  protected function getStatObjectBasedOnValues(string $categoryName, int $id, int $count, array $categories = [], string $link = ''): \stdClass {
    $statObject = new \stdClass();
    $statObject->category = $categoryName;
    $statObject->export_link = $link;
    $statObject->id_category = $id;
    $statObject->value = $count;

    if (!empty($categories)) {
      $statObject->categories = $categories;
      $statObject->total = $count;
    }

    return $statObject;
  }

  /**
   * Build the nested segment data for the chart.
   */
  protected function getSegments(array $infoArray): array {
    $segments = [];

    foreach ($infoArray as $objectInfo) {
      $export_key = $objectInfo['pids'] ?? '';
      $segments[] = $this->getStatObjectBasedOnValues(
        $objectInfo['category_name'],
        $objectInfo['chart_id'],
        $this->queryResult[$objectInfo['db_query_result_name']] ?? 0,
        [],
        $export_key ? _sentinel_reports_render_export_link($export_key) : ''
      );
    }

    return $segments;
  }
}


