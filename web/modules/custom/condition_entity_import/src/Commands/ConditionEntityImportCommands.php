<?php

namespace Drupal\condition_entity_import\Commands;

use Drupal\Core\Queue\QueueFactory;
use Drush\Commands\DrushCommands;
use SplFileObject;

/**
 * Drush commands for Condition Entity import.
 */
class ConditionEntityImportCommands extends DrushCommands {

  /**
   * Constructs the command class.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
  ) {
    parent::__construct();
  }

  /**
   * Enqueue condition entity records from a CSV export.
   *
   * @param string $csv_path
   *   Absolute path to the CSV file. Use "default" to read from
   *   /var/www/html/sentinel11/condition_entities_d7.csv.
   * @param array $options
   *   Additional CLI options (start, limit).
   *
   * @command condition-entity-import:enqueue-csv
   *
   * @option start
   *   Zero-based row to start processing (skips header automatically).
   * @option limit
   *   Maximum number of rows to enqueue (0 = no limit).
   *
   * @usage drush condition-entity-import:enqueue-csv default
   *   Enqueue all rows from the default export file.
   *
   * @validate-module-enabled condition_entity_import
   */
  public function enqueueFromCsv(string $csv_path, array $options = ['start' => 0, 'limit' => 0]): void {
    if ($csv_path === '' || $csv_path === 'default') {
      $csv_path = '/var/www/html/sentinel11/condition_entities_d7.csv';
    }
    elseif ($csv_path[0] !== '/') {
      $csv_path = DRUPAL_ROOT . '/' . ltrim($csv_path, '/');
    }

    if (!is_readable($csv_path)) {
      throw new \RuntimeException(sprintf('CSV file not readable: %s', $csv_path));
    }

    $start = (int) ($options['start'] ?? 0);
    $limit = (int) ($options['limit'] ?? 0);

    $file = new SplFileObject($csv_path, 'r');
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

    $headers = $file->fgetcsv();
    if ($headers === false) {
      throw new \RuntimeException('Unable to read CSV header row.');
    }

    $header_flip = array_flip($headers);
    $required = [
      'id',
      'type',
      'uid',
      'created',
      'changed',
      'language',
      'event_number',
      'event_element',
      'event_string',
      'event_individual_comment',
      'event_individual_recommendation',
      'number_of_white_spaces',
      'condition_event_result_tid',
    ];
    foreach ($required as $column) {
      if (!isset($header_flip[$column])) {
        throw new \RuntimeException(sprintf('Required column "%s" missing from CSV.', $column));
      }
    }

    $queue = $this->queueFactory->get('condition_entity_import');
    $processed = 0;
    $queued = 0;

    foreach ($file as $row_index => $row) {
      if ($row === false || $row === [null]) {
        continue;
      }

      // Skip header row (already consumed) and apply start/limit offsets.
      if ($row_index - 1 < $start) {
        continue;
      }
      if ($limit > 0 && $queued >= $limit) {
        break;
      }

      $record = $this->normalizeRow($row, $header_flip);
      $queue->createItem($record);

      $processed++;
      $queued++;
    }

    $this->logger()->success(dt('Enqueued @queued condition entities from @file (processed rows: @processed).', [
      '@queued' => $queued,
      '@processed' => $processed,
      '@file' => $csv_path,
    ]));
  }

  /**
   * Normalize a CSV row into the array expected by the queue worker.
   */
  protected function normalizeRow(array $row, array $header_flip): array {
    $value = fn(string $column) => $row[$header_flip[$column]] ?? '';

    $id = (int) $value('id');
    return [
      'id' => $id,
      'type' => $value('type') ?: 'condition_entity',
      'uid' => (int) $value('uid'),
      'created' => (int) $value('created'),
      'changed' => (int) $value('changed'),
      'language' => $value('language') ?: 'und',
      'field_condition_event_number' => [
        'und' => [
          ['value' => $value('event_number')],
        ],
      ],
      'field_condition_event_element' => [
        'und' => [
          ['value' => $value('event_element')],
        ],
      ],
      'field_condition_event_string' => [
        'und' => [
          ['value' => $value('event_string')],
        ],
      ],
      'field_event_individual_comment' => [
        'und' => [
          ['value' => $value('event_individual_comment')],
        ],
      ],
      'field_individual_recommend' => [
        'und' => [
          ['value' => $value('event_individual_recommendation')],
        ],
      ],
      'field_number_of_white_spaces' => [
        'und' => [
          ['value' => $value('number_of_white_spaces')],
        ],
      ],
      'field_condition_event_result' => [
        'und' => [
          ['tid' => $value('condition_event_result_tid')],
        ],
      ],
    ];
  }

}


