<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Utility\PackTypeFilter;

/**
 * Defines a class to build a listing of Sentinel Sample entities.
 */
class SentinelSampleListBuilder extends EntityListBuilder implements FormInterface {

  use MessengerTrait;

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

    // Views exposed form structure
    $form['#attributes'] = [
      'class' => ['views-exposed-form'],
      'id' => 'views-exposed-form-test-page',
      'method' => 'get',
      'action' => '/portal/admin/sample',
    ];

    $form['views_exposed_form_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-exposed-form'],
      ],
    ];

    $form['views_exposed_form_wrapper']['views_exposed_widgets'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-exposed-widgets', 'clearfix'],
        'style' => 'display: flex; flex-wrap: wrap; gap: 10px 15px; align-items: flex-end;',
      ],
    ];

    $wrapper = &$form['views_exposed_form_wrapper']['views_exposed_widgets'];

    // Pack Reference Number
    $wrapper['pack_reference_number_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-pack-reference-number-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-pack_reference_number'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['pack_reference_number_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Search Pack ID'),
      '#for' => 'edit-pack-reference-number',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['pack_reference_number_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['pack_reference_number_wrapper']['views_widget']['pack_reference_number'] = [
      '#type' => 'textfield',
      '#id' => 'edit-pack-reference-number',
      '#name' => 'pack_reference_number',
      '#default_value' => $filters['pack_reference_number'] ?? '',
      '#attributes' => [
        'size' => 30,
        'maxlength' => 128,
        'class' => ['form-text'],
      ],
    ];

    // Pass/Fail
    $wrapper['pass_fail_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-pass-fail-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-pass_fail'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['pass_fail_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('The Sample Result'),
      '#for' => 'edit-pass-fail',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['pass_fail_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['pass_fail_wrapper']['views_widget']['pass_fail'] = [
      '#type' => 'select',
      '#id' => 'edit-pass-fail',
      '#name' => 'pass_fail',
      '#options' => [
        'All' => $this->t('- Any -'),
        '0' => $this->t('Fail'),
        '1' => $this->t('Pass'),
        'p' => $this->t('Pending'),
      ],
      '#default_value' => $filters['pass_fail'] ?? 'All',
      '#attributes' => ['class' => ['form-select']],
    ];

    // Pack Type (pack_reference_number_1)
    $pack_type_options = ['All' => $this->t('- Any -')];
    foreach (PackTypeFilter::getDefinitions() as $key => $definition) {
      $pack_type_options[$key] = $this->t($definition['label']);
    }

    $wrapper['pack_reference_number_1_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-pack-reference-number-1-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-pack_reference_number_1'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['pack_reference_number_1_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Pack Type'),
      '#for' => 'edit-pack-reference-number-1',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['pack_reference_number_1_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['pack_reference_number_1_wrapper']['views_widget']['pack_reference_number_1'] = [
      '#type' => 'select',
      '#id' => 'edit-pack-reference-number-1',
      '#name' => 'pack_reference_number_1',
      '#options' => $pack_type_options,
      '#default_value' => $filters['pack_reference_number_1'] ?? 'All',
      '#attributes' => ['class' => ['form-select']],
    ];

    // Email
    $wrapper['email_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-email-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-email'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['email_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('The email of the client'),
      '#for' => 'edit-email',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['email_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['email_wrapper']['views_widget']['email'] = [
      '#type' => 'textfield',
      '#id' => 'edit-email',
      '#name' => 'email',
      '#default_value' => $filters['email'] ?? '',
      '#attributes' => [
        'size' => 30,
        'maxlength' => 128,
        'class' => ['form-text'],
      ],
    ];

    // Date Reported (simple select)
    $wrapper['date_reported_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-date-reported-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-date_reported'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_reported_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Reported'),
      '#for' => 'edit-date-reported',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['date_reported_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_reported_wrapper']['views_widget']['date_reported'] = [
      '#type' => 'select',
      '#id' => 'edit-date-reported',
      '#name' => 'date_reported',
      '#options' => [
        'All' => $this->t('- Any -'),
        'reported' => $this->t('Reported'),
        'not_reported' => $this->t('Not reported'),
      ],
      '#default_value' => $filters['date_reported'] ?? 'All',
      '#attributes' => ['class' => ['form-select']],
    ];

    // Date Booked (simple select)
    $wrapper['date_booked_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-date-booked-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-date_booked'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_booked_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Booked'),
      '#for' => 'edit-date-booked',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['date_booked_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_booked_wrapper']['views_widget']['date_booked'] = [
      '#type' => 'select',
      '#id' => 'edit-date-booked',
      '#name' => 'date_booked',
      '#options' => [
        'All' => $this->t('- Any -'),
        '5_plus_days_booked' => $this->t('5+ days booked'),
      ],
      '#default_value' => $filters['date_booked'] ?? 'All',
      '#attributes' => ['class' => ['form-select']],
    ];

    // Postcode
    $wrapper['postcode_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-postcode-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-postcode'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['postcode_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('System postcode'),
      '#for' => 'edit-postcode',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['postcode_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['postcode_wrapper']['views_widget']['postcode'] = [
      '#type' => 'textfield',
      '#id' => 'edit-postcode',
      '#name' => 'postcode',
      '#default_value' => $filters['postcode'] ?? '',
      '#attributes' => [
        'size' => 30,
        'maxlength' => 128,
        'class' => ['form-text'],
      ],
    ];

    // Combine (System address)
    $wrapper['combine_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-combine-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-combine'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 200px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['combine_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('System address'),
      '#for' => 'edit-combine',
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['combine_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['combine_wrapper']['views_widget']['combine'] = [
      '#type' => 'textfield',
      '#id' => 'edit-combine',
      '#name' => 'combine',
      '#default_value' => $filters['combine'] ?? '',
      '#attributes' => [
        'size' => 30,
        'maxlength' => 128,
        'class' => ['form-text'],
      ],
    ];

    // Date Reported Range (date_reported_1)
    $date_reported_1_id = 'date_views_exposed_filter-ea03d926b0370320cd6b403e9806ea2e';
    $wrapper['date_reported_1_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $date_reported_1_id . '-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-date_reported_1'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 300px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_reported_1_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Date Reported'),
      '#for' => $date_reported_1_id,
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $date_reported_1_id,
        'class' => ['form-wrapper'],
      ],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['min'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-reported-min-wrapper'],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['min']['inside'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-reported-min-inside-wrapper'],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline-date']],
    ];
    // Only show date if it's set in filters (from query parameters)
    $date_reported_min = '';
    if (isset($filters['date_reported_1']['min']['date'])) {
      $date_reported_min = $filters['date_reported_1']['min']['date'];
    }
    
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container']['date_reported_1_min'] = [
      '#type' => 'textfield',
      '#id' => 'edit-date-reported-1-min-datepicker-popup-0',
      '#name' => 'date_reported_1[min][date]',
      '#default_value' => $date_reported_min,
      '#attributes' => [
        'size' => 20,
        'maxlength' => 30,
        'class' => ['form-text', 'datepicker-popup'],
        'placeholder' => 'E.g., 11/19/2025',
        'autocomplete' => 'off',
      ],
      '#description' => $this->t('E.g., 11/19/2025'),
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['max'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-reported-max-wrapper'],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['max']['inside'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-reported-max-inside-wrapper'],
    ];
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline-date']],
    ];
    // Only show date if it's set in filters (from query parameters)
    $date_reported_max = '';
    if (isset($filters['date_reported_1']['max']['date'])) {
      $date_reported_max = $filters['date_reported_1']['max']['date'];
    }
    
    $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container']['date_reported_1_max'] = [
      '#type' => 'textfield',
      '#id' => 'edit-date-reported-1-max-datepicker-popup-0',
      '#name' => 'date_reported_1[max][date]',
      '#default_value' => $date_reported_max,
      '#attributes' => [
        'size' => 20,
        'maxlength' => 30,
        'class' => ['form-text', 'datepicker-popup'],
        'placeholder' => 'E.g., 11/19/2025',
        'autocomplete' => 'off',
      ],
      '#description' => $this->t('E.g., 11/19/2025'),
    ];

    // Date Booked Range (date_booked_1)
    $date_booked_1_id = 'date_views_exposed_filter-4201a9cbc3d0ae23268e507b4fa9e77b';
    $wrapper['date_booked_1_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $date_booked_1_id . '-wrapper',
        'class' => ['views-exposed-widget', 'views-widget-filter-date_booked_1'],
        'style' => 'flex: 0 0 calc(25% - 12px); min-width: 300px; margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_booked_1_wrapper']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Date Booked In'),
      '#for' => $date_booked_1_id,
      '#attributes' => ['style' => 'display: block; margin-bottom: 5px;'],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['views-widget'],
        'style' => 'margin-bottom: 0 !important;',
      ],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $date_booked_1_id,
        'class' => ['form-wrapper'],
      ],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['min'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-booked-min-wrapper'],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['min']['inside'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-booked-min-inside-wrapper'],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline-date']],
    ];
    $date_booked_min = $filters['date_booked_1']['min']['date'] ?? '';
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container']['date_booked_1_min'] = [
      '#type' => 'textfield',
      '#id' => 'edit-date-booked-1-min-datepicker-popup-0',
      '#name' => 'date_booked_1[min][date]',
      '#default_value' => $date_booked_min,
      '#attributes' => [
        'size' => 20,
        'maxlength' => 30,
        'class' => ['form-text', 'datepicker-popup'],
        'placeholder' => 'E.g., 11/19/2025',
        'autocomplete' => 'off',
      ],
      '#description' => $this->t('E.g., 11/19/2025'),
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['max'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-booked-max-wrapper'],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['max']['inside'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'edit-date-booked-max-inside-wrapper'],
    ];
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline-date']],
    ];
    $date_booked_max = $filters['date_booked_1']['max']['date'] ?? '';
    $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container']['date_booked_1_max'] = [
      '#type' => 'textfield',
      '#id' => 'edit-date-booked-1-max-datepicker-popup-0',
      '#name' => 'date_booked_1[max][date]',
      '#default_value' => $date_booked_max,
      '#attributes' => [
        'size' => 20,
        'maxlength' => 30,
        'class' => ['form-text', 'datepicker-popup'],
        'placeholder' => 'E.g., 11/19/2025',
        'autocomplete' => 'off',
      ],
      '#description' => $this->t('E.g., 11/19/2025'),
    ];

    // Submit button
    $wrapper['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#id' => 'edit-submit-test',
      '#name' => '',
      '#attributes' => ['class' => ['btn', 'form-submit']],
      '#prefix' => '<div class="views-exposed-widget views-submit-button" style="flex: 0 0 calc(25% - 12px); min-width: 100px;">',
      '#suffix' => '</div>',
    ];

    // Reset button
    $wrapper['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
      '#id' => 'edit-reset',
      '#name' => 'op',
      '#attributes' => ['class' => ['btn', 'form-submit']],
        '#submit' => ['::resetForm'],
      '#prefix' => '<div class="views-exposed-widget views-reset-button" style="flex: 0 0 calc(25% - 12px); min-width: 100px;">',
      '#suffix' => '</div>',
      ];

    // Operations section - place after date fields
    $form['operations'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Operations'),
      '#attributes' => ['class' => ['sentinel-sample-operations']],
      '#tree' => TRUE,
    ];

    $form['operations']['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => [
        'email_report' => $this->t('Email Report'),
        'email_vaillant_report' => $this->t('Email Vaillant Report'),
      ],
      '#empty_option' => $this->t('- Choose an operation -'),
    ];

    $form['operations']['execute'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
      '#submit' => ['::submitOperations'],
      '#limit_validation_errors' => [['operations', 'action'], ['samples_table']],
    ];

    // Build the table of samples within the form so row selections can be submitted.
    $form['samples_table'] = [
      '#type' => 'tableselect',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('No samples found.'),
      '#attributes' => [
        'class' => ['sentinel-sample-list'],
      ],
      '#multiple' => TRUE,
      '#sticky' => TRUE,
      '#options' => [],
    ];

    $entity_ids = $this->getEntityIds();
    if (!empty($entity_ids)) {
      $entities = $this->getStorage()->loadMultiple($entity_ids);
      foreach ($entities as $entity) {
        $form['samples_table']['#options'][$entity->id()] = $this->buildRow($entity);
      }
    }

    $form['pager'] = [
      '#type' => 'pager',
    ];

    // Add CSV export button
    $form['export_csv'] = [
      '#type' => 'link',
      '#title' => $this->t('Export CSV'),
      '#url' => Url::fromRoute('sentinel_portal.sample_export_csv', [], [
        'query' => \Drupal::request()->query->all(),
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.tableselect';
    $form['#attached']['library'][] = 'sentinel_portal_entities/sample-list-styling';
    $form['#attached']['library'][] = 'sentinel_portal_entities/sample_list';
    $form['#attached']['library'][] = 'sentinel_portal_entities/sample_datepicker';
    
    $form['#attached']['drupalSettings']['sentinelSampleList'] = [
      'selectAllClass' => 'select-all',
      'entitySelectClass' => 'entity-select',
    ];
    
    // Override Bootstrap Barrio mb-3 margins
    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '.views-exposed-widgets .mb-3,
                      .views-exposed-widgets .views-exposed-widget,
                      .views-exposed-widgets .views-widget,
                      .views-exposed-widgets .form-item,
                      .views-exposed-widgets .js-form-wrapper,
                      .views-exposed-widgets .form-wrapper {
                        margin-bottom: 0 !important;
                      }',
      ],
      'remove_mb3_margins',
    ];

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

    // Extract filter values from the views exposed form structure
    $wrapper = $form_values['views_exposed_form_wrapper']['views_exposed_widgets'] ?? [];

    // Pack reference number
    if (isset($wrapper['pack_reference_number_wrapper']['views_widget']['pack_reference_number'])) {
      $value = trim((string) $wrapper['pack_reference_number_wrapper']['views_widget']['pack_reference_number']);
      if ($value !== '') {
        $query_params['pack_reference_number'] = $value;
      }
    }

    // Pass/Fail
    if (isset($wrapper['pass_fail_wrapper']['views_widget']['pass_fail'])) {
      $value = trim((string) $wrapper['pass_fail_wrapper']['views_widget']['pass_fail']);
      if ($value !== '' && $value !== 'All') {
        $query_params['pass_fail'] = $value;
      }
    }

    // Pack Type (pack_reference_number_1)
    if (isset($wrapper['pack_reference_number_1_wrapper']['views_widget']['pack_reference_number_1'])) {
      $value = trim((string) $wrapper['pack_reference_number_1_wrapper']['views_widget']['pack_reference_number_1']);
      if ($value !== '' && $value !== 'All') {
        $query_params['pack_reference_number_1'] = $value;
      }
    }

    // Email
    if (isset($wrapper['email_wrapper']['views_widget']['email'])) {
      $value = trim((string) $wrapper['email_wrapper']['views_widget']['email']);
        if ($value !== '') {
        $query_params['email'] = $value;
      }
    }

    // Date Reported (simple select)
    if (isset($wrapper['date_reported_wrapper']['views_widget']['date_reported'])) {
      $value = trim((string) $wrapper['date_reported_wrapper']['views_widget']['date_reported']);
      if ($value !== '' && $value !== 'All') {
        $query_params['date_reported'] = $value;
      }
    }

    // Date Booked (simple select)
    if (isset($wrapper['date_booked_wrapper']['views_widget']['date_booked'])) {
      $value = trim((string) $wrapper['date_booked_wrapper']['views_widget']['date_booked']);
      if ($value !== '' && $value !== 'All') {
        $query_params['date_booked'] = $value;
      }
    }

    // Postcode
    if (isset($wrapper['postcode_wrapper']['views_widget']['postcode'])) {
      $value = trim((string) $wrapper['postcode_wrapper']['views_widget']['postcode']);
      if ($value !== '') {
        $query_params['postcode'] = $value;
      }
    }

    // Combine (System address)
    if (isset($wrapper['combine_wrapper']['views_widget']['combine'])) {
      $value = trim((string) $wrapper['combine_wrapper']['views_widget']['combine']);
      if ($value !== '') {
        $query_params['combine'] = $value;
      }
    }

    // Date Reported Range (date_reported_1)
    $date_reported_1 = [];
    if (isset($wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container']['date_reported_1_min'])) {
      $value = trim((string) $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container']['date_reported_1_min']);
      if ($value !== '') {
        $date_reported_1['min']['date'] = $value;
      }
    }
    if (isset($wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container']['date_reported_1_max'])) {
      $value = trim((string) $wrapper['date_reported_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container']['date_reported_1_max']);
      if ($value !== '') {
        $date_reported_1['max']['date'] = $value;
      }
    }
    if (!empty($date_reported_1)) {
      $query_params['date_reported_1'] = $date_reported_1;
    }

    // Date Booked Range (date_booked_1)
    $date_booked_1 = [];
    if (isset($wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container']['date_booked_1_min'])) {
      $value = trim((string) $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['min']['inside']['container']['date_booked_1_min']);
      if ($value !== '') {
        $date_booked_1['min']['date'] = $value;
      }
    }
    if (isset($wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container']['date_booked_1_max'])) {
      $value = trim((string) $wrapper['date_booked_1_wrapper']['views_widget']['form_wrapper']['max']['inside']['container']['date_booked_1_max']);
      if ($value !== '') {
        $date_booked_1['max']['date'] = $value;
      }
    }
    if (!empty($date_booked_1)) {
      $query_params['date_booked_1'] = $date_booked_1;
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
   * Submit handler for bulk operations.
   */
  public function submitOperations(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue(['operations', 'action']);
    if (empty($action)) {
      $this->messenger()->addWarning($this->t('Please choose an operation.'));
      return;
    }

    $table_values = $form_state->getValue('samples_table') ?? [];
    $selected_ids = array_keys(array_filter($table_values));

    if (empty($selected_ids)) {
      $this->messenger()->addWarning($this->t('Please select at least one sample.'));
      return;
    }
    
    $storage = $this->getStorage();
    $samples = $storage->loadMultiple($selected_ids);
    //dd($samples);
    $processed = 0;
    $errors = 0;
    $skipped = 0;

    foreach ($samples as $sample) {
      try {
        if ($action === 'email_report') {
          $result = function_exists('_sentinel_portal_queue_process_email') ? _sentinel_portal_queue_process_email($sample, 'report') : FALSE;
          if ($result) {
            $processed++;
          }
          else {
            $errors++;
          }
        }
        elseif ($action === 'email_vaillant_report') {
          $sample_type = method_exists($sample, 'getSampleType') ? $sample->getSampleType() : ($sample->get('pack_type')->value ?? '');
          if ($sample_type !== 'vaillant') {
            $skipped++;
            continue;
          }

          if (function_exists('sentinel_systemcheck_vaillant_xml_sentinel_sendresults')) {
            $result = sentinel_systemcheck_vaillant_xml_sentinel_sendresults($sample);
            if ($result) {
              $processed++;
            }
            else {
              $errors++;
            }
          }
          else {
            $errors++;
          }
        }
      }
      catch (\Throwable $e) {
        $this->getLogger('sentinel_portal_entities')->error('Bulk operation failed for sample @id: @message', [
          '@id' => $sample->id(),
          '@message' => $e->getMessage(),
        ]);
        $errors++;
      }
    }

    if ($processed > 0) {
      $this->messenger()->addStatus($this->t('Operation executed for @count sample(s).', ['@count' => $processed]));
    }
    if ($errors > 0) {
      $this->messenger()->addWarning($this->t('Unable to process @count sample(s).', ['@count' => $errors]));
    }
    if ($skipped > 0) {
      $this->messenger()->addWarning($this->t('Skipped @count non-Vaillant sample(s).', ['@count' => $skipped]));
    }

    $current_query = \Drupal::request()->query->all();
    $form_state->setRedirect('sentinel_portal.admin_sample', [], ['query' => $current_query]);
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
    
    $filters = [];
    
    // Pack reference number
    if (isset($query_params['pack_reference_number'])) {
      $value = trim((string) $query_params['pack_reference_number']);
        if ($value !== '') {
        $filters['pack_reference_number'] = $value;
      }
    }
    
    // Pass/Fail - handle "All" as empty
    if (isset($query_params['pass_fail'])) {
      $value = trim((string) $query_params['pass_fail']);
      if ($value !== '' && $value !== 'All') {
        $filters['pass_fail'] = $value;
      }
    }
    
    // Pack Type (pack_reference_number_1) - handle "All" as empty
    if (isset($query_params['pack_reference_number_1'])) {
      $value = trim((string) $query_params['pack_reference_number_1']);
      if ($value !== '' && $value !== 'All') {
        $filters['pack_reference_number_1'] = $value;
      }
    }
    
    // Email
    if (isset($query_params['email'])) {
      $value = trim((string) $query_params['email']);
      if ($value !== '') {
        $filters['email'] = $value;
      }
    }
    
    // Date Reported (simple select) - handle "All" as empty
    if (isset($query_params['date_reported'])) {
      $value = trim((string) $query_params['date_reported']);
      if ($value !== '' && $value !== 'All') {
        $filters['date_reported'] = $value;
      }
    }
    
    // Date Booked (simple select) - handle "All" as empty
    if (isset($query_params['date_booked'])) {
      $value = trim((string) $query_params['date_booked']);
      if ($value !== '' && $value !== 'All') {
        $filters['date_booked'] = $value;
      }
    }
    
    // Postcode
    if (isset($query_params['postcode'])) {
      $value = trim((string) $query_params['postcode']);
      if ($value !== '') {
        $filters['postcode'] = $value;
      }
    }
    
    // Combine (System address)
    if (isset($query_params['combine'])) {
      $value = trim((string) $query_params['combine']);
      if ($value !== '') {
        $filters['combine'] = $value;
      }
    }
    
    // Date Reported Range (date_reported_1)
    if (isset($query_params['date_reported_1']) && is_array($query_params['date_reported_1'])) {
      $date_reported_1 = [];
      if (isset($query_params['date_reported_1']['min']['date'])) {
        $value = trim((string) $query_params['date_reported_1']['min']['date']);
        if ($value !== '') {
          $date_reported_1['min']['date'] = $value;
        }
      }
      if (isset($query_params['date_reported_1']['max']['date'])) {
        $value = trim((string) $query_params['date_reported_1']['max']['date']);
        if ($value !== '') {
          $date_reported_1['max']['date'] = $value;
        }
      }
      if (!empty($date_reported_1)) {
        $filters['date_reported_1'] = $date_reported_1;
      }
    }
    
    // Date Booked Range (date_booked_1)
    if (isset($query_params['date_booked_1']) && is_array($query_params['date_booked_1'])) {
      $date_booked_1 = [];
      if (isset($query_params['date_booked_1']['min']['date'])) {
        $value = trim((string) $query_params['date_booked_1']['min']['date']);
        if ($value !== '') {
          $date_booked_1['min']['date'] = $value;
        }
      }
      if (isset($query_params['date_booked_1']['max']['date'])) {
        $value = trim((string) $query_params['date_booked_1']['max']['date']);
        if ($value !== '') {
          $date_booked_1['max']['date'] = $value;
        }
      }
      if (!empty($date_booked_1)) {
        $filters['date_booked_1'] = $date_booked_1;
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
    if (!empty($filters['pack_reference_number'])) {
      $query->condition('ss.pack_reference_number', '%' . $connection->escapeLike($filters['pack_reference_number']) . '%', 'LIKE');
    }
    
    // The Sample Result - handle 'p' for pending (maps to 2)
    if (isset($filters['pass_fail']) && $filters['pass_fail'] !== '') {
      $pass_fail_value = $filters['pass_fail'];
      if ($pass_fail_value === 'p') {
        $pass_fail_value = '2';
      }
      $query->condition('ss.pass_fail', $pass_fail_value, '=');
    }
    
    // Pack Type (pack_reference_number_1) - apply combined pack type / prefix filters.
    if (!empty($filters['pack_reference_number_1'])) {
      PackTypeFilter::applyFilterConditions($query, $connection, $filters['pack_reference_number_1']);
    }
    
    // Date Reported (simple select) - check if date_reported is not null
    if (isset($filters['date_reported']) && $filters['date_reported'] !== '') {
      if ($filters['date_reported'] === 'reported') {
        $query->isNotNull('ss.date_reported');
      }
      elseif ($filters['date_reported'] === 'not_reported') {
        $query->isNull('ss.date_reported');
      }
    }
    
    // Date Booked (simple select) - check if date_booked is not null
    if (isset($filters['date_booked']) && $filters['date_booked'] !== '') {
      if ($filters['date_booked'] === '5_plus_days_booked') {
        $threshold = (new \DateTime('-5 days'))->format('Y-m-d H:i:s');
        $query->isNotNull('ss.date_booked');
        $query->condition('ss.date_booked', $threshold, '<=');
      }
    }
    
    // System postcode
    if (!empty($filters['postcode'])) {
      $query->condition('ss.postcode', '%' . $connection->escapeLike($filters['postcode']) . '%', 'LIKE');
    }
    
    // System address (combine) - combine street, county, town_city, system_location
    if (!empty($filters['combine'])) {
      $or_group = $query->orConditionGroup()
        ->condition('ss.street', '%' . $connection->escapeLike($filters['combine']) . '%', 'LIKE')
        ->condition('ss.county', '%' . $connection->escapeLike($filters['combine']) . '%', 'LIKE')
        ->condition('ss.town_city', '%' . $connection->escapeLike($filters['combine']) . '%', 'LIKE')
        ->condition('ss.system_location', '%' . $connection->escapeLike($filters['combine']) . '%', 'LIKE');
      $query->condition($or_group);
    }
    
    // Date Reported range (date_reported_1)
    if (!empty($filters['date_reported_1'])) {
      if (!empty($filters['date_reported_1']['min']['date'])) {
        if ($from = $this->normalizeFilterDate($filters['date_reported_1']['min']['date'])) {
        $query->condition('ss.date_reported', $from, '>=');
      }
    }
      if (!empty($filters['date_reported_1']['max']['date'])) {
        if ($to = $this->normalizeFilterDate($filters['date_reported_1']['max']['date'], TRUE)) {
        $query->condition('ss.date_reported', $to, '<=');
        }
      }
    }
    
    // Date Booked range (date_booked_1)
    if (!empty($filters['date_booked_1'])) {
      if (!empty($filters['date_booked_1']['min']['date'])) {
        if ($from = $this->normalizeFilterDate($filters['date_booked_1']['min']['date'])) {
        $query->condition('ss.date_booked', $from, '>=');
      }
    }
      if (!empty($filters['date_booked_1']['max']['date'])) {
        if ($to = $this->normalizeFilterDate($filters['date_booked_1']['max']['date'], TRUE)) {
        $query->condition('ss.date_booked', $to, '<=');
        }
      }
    }

    // Email filter uses installer_email field
    if (!empty($filters['email'])) {
      $query->join('sentinel_client', 'sc', 'ss.ucr = sc.ucr');
      $query->condition('sc.email', '%' . $connection->escapeLike($filters['email']) . '%', 'LIKE');
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
    return [
      'pack_reference_number' => $this->t('Pack Reference No'),
      'pass_fail' => $this->t('The Sample Result'),
      'pack_type' => $this->t('Pack Type'),
      'client_email' => $this->t('The email of the client'),
      'date_booked' => $this->t('Date booked'),
      'date_reported' => $this->t('Date reported'),
      'operations' => $this->t('Operations'),
    ];
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
    $row['pack_reference_number'] = Link::createFromRoute(
      $entity->get('pack_reference_number')->value ?: $entity->id(),
      'entity.sentinel_sample.canonical',
      ['sentinel_sample' => $entity->id()]
    )->toString();
    $row['pass_fail'] = Markup::create('<span class="sentinel-sample-result ' . $pass_fail_class . '">' . $pass_fail_display . '</span>');
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
    $row['pack_type'] = $pack_type_value;
    $row['client_email'] = $client_email;
    $row['date_booked'] = $date_booked;
    $row['date_reported'] = $date_reported;
    
    $operations = $this->buildOperations($entity);
    // Extract Edit and Delete links separately
    $ops_links = [];
    $current_user = \Drupal::currentUser();
    /*
    if ($current_user->hasPermission('sentinel portal send email report')) {
      $ops_links[] = Link::fromTextAndUrl(
        $this->t('Email Report'),
        Url::fromRoute('sentinel_portal.sample_email', ['sentinel_sample' => $entity->id()])
      )->toString();
 
      if (\Drupal::moduleHandler()->moduleExists('sentinel_systemcheck_vaillant_xml')) {
        $sample_type = method_exists($entity, 'getSampleType') ? $entity->getSampleType() : ($entity->hasField('pack_type') ? $entity->get('pack_type')->value : NULL);
        if ($sample_type === 'vaillant') {
          $ops_links[] = Link::fromTextAndUrl(
            $this->t('Email Vaillant Report'),
            Url::fromRoute('sentinel_systemcheck_vaillant_xml.email', ['sample_id' => $entity->id()])
          )->toString();
        }
      }
    }
    */
 
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
 
    $row['operations'] = Markup::create(implode(' | ', $ops_links));
    
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

    // Support multiple date formats including MM/DD/YYYY from datepicker
    $formats = ['m/d/Y', 'd/m/Y', 'Y-m-d', 'Y-m-d H:i:s'];
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
    return \Drupal::formBuilder()->getForm($this);
  }

}

