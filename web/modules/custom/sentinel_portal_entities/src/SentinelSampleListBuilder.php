<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Sentinel Sample entities.
 */
class SentinelSampleListBuilder extends EntityListBuilder implements FormInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Cached sentinel client emails keyed by client ID.
   *
   * @var array
   */
  protected $clientEmailCache = [];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_sample_list_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $filters = $this->getFilters();

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter samples'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['filters']['pack_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Pack ID'),
      '#default_value' => $filters['pack_id'] ?? '',
    ];

    $form['filters']['pass_fail'] = [
      '#type' => 'select',
      '#title' => $this->t('The Sample Result'),
      '#options' => [
        '' => $this->t('-Any-'),
        '1' => $this->t('Pass'),
        '0' => $this->t('Fail'),
        '2' => $this->t('Pending'),
      ],
      '#default_value' => $filters['pass_fail'] ?? '',
    ];

    $form['filters']['pack_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Pack Type'),
      '#options' => [
        '' => $this->t('-Any-'),
        'Domestic' => $this->t('Domestic'),
        'Commercial' => $this->t('Commercial'),
      ],
      '#default_value' => $filters['pack_type'] ?? '',
    ];

    $form['filters']['client_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The email of the client'),
      '#default_value' => $filters['client_email'] ?? '',
    ];

    $form['filters']['reported'] = [
      '#type' => 'select',
      '#title' => $this->t('Reported'),
      '#options' => [
        '' => $this->t('- Any -'),
        'reported' => $this->t('Reported'),
        'not_reported' => $this->t('Not reported'),
      ],
      '#default_value' => $filters['reported'] ?? '',
    ];

    $form['filters']['booked'] = [
      '#type' => 'select',
      '#title' => $this->t('Booked'),
      '#options' => [
        '' => $this->t('- Any -'),
        '5_plus_days_booked' => $this->t('5+ days booked'),
      ],
      '#default_value' => $filters['booked'] ?? '',
    ];

    $form['filters']['system_postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('System postcode'),
      '#default_value' => $filters['system_postcode'] ?? '',
    ];

    $form['filters']['system_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('System address'),
      '#default_value' => $filters['system_address'] ?? '',
    ];

    $form['filters']['date_reported_from'] = [
      '#type' => 'date',
      '#title' => $this->t('Date Reported'),
      '#default_value' => $filters['date_reported_from'] ?? '',
      '#attributes' => ['placeholder' => $this->t('E.g. 10/31/2025')],
    ];

    $form['filters']['date_reported_to'] = [
      '#type' => 'date',
      '#title' => '',
      '#default_value' => $filters['date_reported_to'] ?? '',
      '#attributes' => ['placeholder' => $this->t('E.g. 10/31/2025')],
    ];

    $form['filters']['date_booked_from'] = [
      '#type' => 'date',
      '#title' => $this->t('Date Booked In'),
      '#default_value' => $filters['date_booked_from'] ?? '',
      '#attributes' => ['placeholder' => $this->t('E.g. 10/31/2025')],
    ];

    $form['filters']['date_booked_to'] = [
      '#type' => 'date',
      '#title' => '',
      '#default_value' => $filters['date_booked_to'] ?? '',
      '#attributes' => ['placeholder' => $this->t('E.g. 10/31/2025')],
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    if (!empty($filters)) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No validation needed.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    // Create a new query parameter bag
    $query_params = [];

    // Extract filter values - try both nested and direct access
    $filter_keys = [
      'pack_id',
      'pass_fail',
      'pack_type',
      'client_email',
      'reported',
      'booked',
      'system_postcode',
      'system_address',
      'date_reported_from',
      'date_reported_to',
      'date_booked_from',
      'date_booked_to',
    ];

    foreach ($filter_keys as $key) {
      // Try accessing via getValue with nested path first
      $value = $form_state->getValue(['filters', $key]);
      
      // If that doesn't work, try direct access
      if ($value === NULL && isset($form_values['filters'][$key])) {
        $value = $form_values['filters'][$key];
      }
      
      // If still nothing, try top-level access (in case filters aren't nested)
      if ($value === NULL && isset($form_values[$key])) {
        $value = $form_values[$key];
      }
      
      if ($value !== NULL) {
        $value = trim((string) $value);
        if ($value !== '') {
          $query_params[$key] = $value;
        }
      }
    }

    $form_state->setRedirect('sentinel_portal.admin_sample', [], ['query' => $query_params]);
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('sentinel_portal.admin_sample');
  }

  /**
   * Retrieves the current filters from the request.
   *
   * @return array
   *   An array of active filters.
   */
  protected function getFilters() {
    // Always get fresh request to ensure we have current query parameters
    $request = \Drupal::request();
    
    $query_params = $request->query->all();

    // Filter out pager and other non-filter query parameters
    $filter_keys = [
      'pack_id',
      'pass_fail',
      'pack_type',
      'client_email',
      'reported',
      'booked',
      'system_postcode',
      'system_address',
      'date_reported_from',
      'date_reported_to',
      'date_booked_from',
      'date_booked_to',
    ];
    
    $filters = [];
    foreach ($filter_keys as $key) {
      if (isset($query_params[$key])) {
        $value = trim((string) $query_params[$key]);
        if ($value !== '') {
          $filters[$key] = $value;
        }
      }
    }
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $filters = $this->getFilters();
   
    // Use direct database query for complex filtering
    $connection = \Drupal::database();
    $query = $connection->select('sentinel_sample', 'ss')
      ->fields('ss', ['pid'])
      ->orderBy('ss.pid', 'ASC');

    // Search Pack ID - filter on pack_reference_number
    if (!empty($filters['pack_id'])) {
      $query->condition('ss.pack_reference_number', '%' . $connection->escapeLike($filters['pack_id']) . '%', 'LIKE');
    }
    
    // The Sample Result
    if (isset($filters['pass_fail']) && $filters['pass_fail'] !== '') {
      $query->condition('ss.pass_fail', $filters['pass_fail'], '=');
    }
    
    // Pack Type - if pack_type is set, filter by pack_reference_number pattern
    if (!empty($filters['pack_type'])) {
      $query->condition('ss.pack_reference_number', $connection->escapeLike($filters['pack_type']) . '%', 'LIKE');
    }
    
    // Reported - check if date_reported is not null
    if (isset($filters['reported']) && $filters['reported'] !== '') {
      if ($filters['reported'] === 'reported') {
        $query->isNotNull('ss.date_reported');
      }
      elseif ($filters['reported'] === 'not_reported') {
        $query->isNull('ss.date_reported');
      }
    }
    
    // Booked - check if date_booked is not null
    if (isset($filters['booked']) && $filters['booked'] !== '') {
      if ($filters['booked'] === '5_plus_days_booked') {
        $threshold = (new \DateTime('-5 days'))->format('Y-m-d H:i:s');
        $query->isNotNull('ss.date_booked');
        $query->condition('ss.date_booked', $threshold, '<=');
      }
    }
    
    // System postcode
    if (!empty($filters['system_postcode'])) {
      $query->condition('ss.postcode', '%' . $connection->escapeLike($filters['system_postcode']) . '%', 'LIKE');
    }
    
    // System address - combine street, county, town_city, system_location
    if (!empty($filters['system_address'])) {
      $or_group = $query->orConditionGroup()
        ->condition('ss.street', '%' . $connection->escapeLike($filters['system_address']) . '%', 'LIKE')
        ->condition('ss.county', '%' . $connection->escapeLike($filters['system_address']) . '%', 'LIKE')
        ->condition('ss.town_city', '%' . $connection->escapeLike($filters['system_address']) . '%', 'LIKE')
        ->condition('ss.system_location', '%' . $connection->escapeLike($filters['system_address']) . '%', 'LIKE');
      $query->condition($or_group);
    }
    
    // Date Reported range
    if (!empty($filters['date_reported_from'])) {
      if ($from = $this->normalizeFilterDate($filters['date_reported_from'])) {
        $query->condition('ss.date_reported', $from, '>=');
      }
    }
    if (!empty($filters['date_reported_to'])) {
      if ($to = $this->normalizeFilterDate($filters['date_reported_to'], TRUE)) {
        $query->condition('ss.date_reported', $to, '<=');
      }
    }
    
    // Date Booked range
    if (!empty($filters['date_booked_from'])) {
      if ($from = $this->normalizeFilterDate($filters['date_booked_from'])) {
        $query->condition('ss.date_booked', $from, '>=');
      }
    }
    if (!empty($filters['date_booked_to'])) {
      if ($to = $this->normalizeFilterDate($filters['date_booked_to'], TRUE)) {
        $query->condition('ss.date_booked', $to, '<=');
      }
    }

    // Client email filter uses installer_email field
    if (!empty($filters['client_email'])) {
      $query->join('sentinel_client', 'sc', 'ss.ucr = sc.ucr');
      $query->condition('sc.email', '%' . $connection->escapeLike($filters['client_email']) . '%', 'LIKE');
    }

    // Add pager with current query parameters preserved
    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(25);
    
    // Preserve query parameters in pager links
    if (!empty($filters)) {
      $query->addTag('sentinel_sample_filter');
    }
    
    $result = $query->execute();
    $entity_ids = [];
    foreach ($result as $row) {
      $entity_ids[] = $row->pid;
    }
   
    return $entity_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'select' => [
        'data' => [
          '#type' => 'checkbox',
          '#attributes' => [
            'class' => ['select-all'],
            'title' => $this->t('Select all rows'),
          ],
        ],
      ],
      'pack_reference_number' => $this->t('Pack Reference No'),
      'pass_fail' => $this->t('The Sample Result'),
      'pack_type' => $this->t('Pack Type'),
      'client_email' => $this->t('The email of the client'),
      'date_booked' => $this->t('Date booked'),
      'date_reported' => $this->t('Date reported'),
      'operations' => $this->t('Operations'),
    ];
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\sentinel_portal_entities\Entity\SentinelSample $entity */
    
    $client_email = '-';
    $ucr_value = $entity->hasField('ucr') && !$entity->get('ucr')->isEmpty() ? $entity->get('ucr')->value : NULL;
    if ($ucr_value) {
      if (!array_key_exists($ucr_value, $this->clientEmailCache)) {
        $email = \Drupal::database()->select('sentinel_client', 'sc')
          ->fields('sc', ['email'])
          ->condition('sc.ucr', $ucr_value)
          ->execute()
          ->fetchField();
        if ($email) {
          $first_email = preg_split('/[;,]/', $email);
          $email = trim($first_email[0]);
        }
        $this->clientEmailCache[$ucr_value] = $email ?: '-';
      }
      $client_email = $this->clientEmailCache[$ucr_value];
    }

    // Format pass_fail
    $pass_fail_value = $entity->hasField('pass_fail') && !$entity->get('pass_fail')->isEmpty() ? $entity->get('pass_fail')->value : NULL;
    $pass_fail_display = '-';
    $pass_fail_class = 'sentinel-sample-result--unknown';
    if ($pass_fail_value !== NULL && $pass_fail_value !== '') {
      $normalized = strtoupper(trim((string) $pass_fail_value));
      switch ($normalized) {
        case '1':
        case 'PASS':
        case 'PASSED':
          $pass_fail_display = 'PASSED';
          $pass_fail_class = 'sentinel-sample-result--passed';
          break;

        case '0':
        case 'FAIL':
        case 'FAILED':
          $pass_fail_display = 'FAILED';
          $pass_fail_class = 'sentinel-sample-result--failed';
          break;

        case 'PENDING':
        case 'PEND':
        case '2':
          $pass_fail_display = 'PENDING';
          $pass_fail_class = 'sentinel-sample-result--pending';
          break;

        default:
          $pass_fail_display = strtoupper($pass_fail_value);
          $pass_fail_class = 'sentinel-sample-result--unknown';
          break;
      }
    } else {
      $pass_fail_display = 'PENDING';
      $pass_fail_class = 'sentinel-sample-result--pending';
    }

    // Format dates
    $date_booked = $this->formatSampleDate($entity->hasField('date_booked') && !$entity->get('date_booked')->isEmpty() ? $entity->get('date_booked')->value : NULL);
    $date_reported = $this->formatSampleDate($entity->hasField('date_reported') && !$entity->get('date_reported')->isEmpty() ? $entity->get('date_reported')->value : NULL);

    $row = [];
    $row['select'] = [
      'data' => [
        '#type' => 'checkbox',
        '#return_value' => $entity->id(),
        '#attributes' => ['class' => ['entity-select']],
      ],
    ];
    $row['pack_reference_number']['data'] = [
      '#markup' => Link::createFromRoute(
        $entity->get('pack_reference_number')->value ?: $entity->id(),
        'entity.sentinel_sample.canonical',
        ['sentinel_sample' => $entity->id()]
      )->toString(),
    ];
    $row['pass_fail']['data'] = [
      '#markup' => '<span class="sentinel-sample-result ' . $pass_fail_class . '">' . $pass_fail_display . '</span>',
    ];
    $pack_type_value = '-';
    $pack_reference = $entity->get('pack_reference_number')->value;
    if (!empty($pack_reference)) {
      $parts = explode(':', $pack_reference, 2);
      if (!empty($parts[0])) {
        $pack_type_value = strtoupper($parts[0]);
      }
    }
    if ($pack_type_value === '-' && $entity->hasField('pack_type') && !$entity->get('pack_type')->isEmpty()) {
      $pack_type_value = strtoupper((string) $entity->get('pack_type')->value);
    }
    $row['pack_type']['data'] = ['#markup' => $pack_type_value];
    $row['client_email']['data'] = ['#markup' => $client_email];
    $row['date_booked']['data'] = ['#markup' => $date_booked];
    $row['date_reported']['data'] = ['#markup' => $date_reported];
    
    $operations = $this->buildOperations($entity);
    // Extract Edit and Delete links separately
    $ops_links = [];
    if (isset($operations['#links'])) {
      foreach ($operations['#links'] as $key => $link) {
        if ($key === 'edit') {
          $ops_links[] = Link::fromTextAndUrl($this->t('Edit'), $link['url'])->toString();
        }
        elseif ($key === 'delete') {
          $ops_links[] = Link::fromTextAndUrl($this->t('Delete'), $link['url'])->toString();
        }
      }
    }
    $row['operations']['data'] = ['#markup' => implode(' | ', $ops_links)];
    
    return $row;
  }

  /**
   * Converts filter date values to database format.
   *
   * @param string|null $value
   *   The user-entered value.
   * @param bool $is_end
   *   TRUE to set time to 23:59:59 (inclusive range end).
   *
   * @return string|null
   *   Normalized date string or NULL if invalid.
   */
  protected function normalizeFilterDate($value, $is_end = FALSE) {
    if ($value === NULL) {
      return NULL;
    }

    $value = trim((string) $value);
    if ($value === '') {
      return NULL;
    }

    $formats = ['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $value);
      if ($date instanceof \DateTime) {
        $date->setTime($is_end ? 23 : 0, $is_end ? 59 : 0, $is_end ? 59 : 0);
        return $date->format('Y-m-d H:i:s');
      }
    }

    try {
      $date = new \DateTime($value);
      $date->setTime($is_end ? 23 : 0, $is_end ? 59 : 0, $is_end ? 59 : 0);
      return $date->format('Y-m-d H:i:s');
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Formats sample dates to a D7-style display.
   *
   * @param string|int|null $value
   *   The value to format.
   *
   * @return string
   *   The formatted date or '-' if not available.
   */
  protected function formatSampleDate($value) {
    if ($value === NULL || $value === '') {
      return '-';
    }

    try {
      if (is_numeric($value)) {
        return date('d/m/Y', (int) $value);
      }

      $date = new \DateTime($value);
      return $date->format('d/m/Y');
    }
    catch (\Exception $e) {
      return '-';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = [];
    
    // Add the filter form
    $build['form'] = \Drupal::formBuilder()->getForm($this);
    
    // Build the table - use children pattern for proper rendering
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('No samples found.'),
      '#attributes' => [
        'class' => ['sentinel-sample-list'],
      ],
    ];
    
    // Get entity IDs
    $entity_ids = $this->getEntityIds();
    
    
    if (!empty($entity_ids)) {
      $entities = $this->getStorage()->loadMultiple($entity_ids);
      
      foreach ($entities as $entity) {
        $row = $this->buildRow($entity);
        // Add the row as children of the table
        foreach ($row as $key => $cell) {
          $build['table'][$entity->id()][$key] = $cell;
        }
      }
    }
    
    // Add pager
    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 100,
    ];
    
    // Add JavaScript for select all functionality
    $build['#attached']['library'][] = 'core/drupal.tableselect';
    $build['#attached']['library'][] = 'sentinel_portal_entities/sample-list-styling';
    $build['#attached']['drupalSettings']['sentinelSampleList'] = [
      'selectAllClass' => 'select-all',
      'entitySelectClass' => 'entity-select',
    ];
    $build['#attached']['library'][] = 'sentinel_portal_entities/sample_list';
    
    return $build;
  }

}

