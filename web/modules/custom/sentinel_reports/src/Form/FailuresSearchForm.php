<?php

namespace Drupal\sentinel_reports\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Form for searching failures and displaying stats.
 */
class FailuresSearchForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a FailuresSearchForm.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'failures_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Set cookie for app server when running via web request.
    if (!isset($_COOKIE['ah_app_server']) && PHP_SAPI !== 'cli') {
      if ($hostname = strtok(gethostname(), '.')) {
        setcookie('ah_app_server', $hostname, time() + (86400), '/');
      }
    }

    $analysis = $form_state->get('analysis_output');
    if (!$analysis) {
      $analysis = [
        'content' => '',
        'export_link' => '',
      ];
    }

    $form['container'] = [
      '#type' => 'markup',
      '#markup' => '<div id="failures-search-form__form-inputs" class="views-exposed-widgets clearfix"><h4 style="font-weight: 900;">Filter reports by date</h4><div id="failures-search-form__form-inputs__container">',
    ];

    $form['container']['date-range'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'failures-search-form__date-range'],
    ];

    // Earliest date available.
    $earliest_date = '2015-12-30';

    $form['container']['date-range']['date_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From date'),
      '#attributes' => [
        'data-min-date' => [$earliest_date],
      ],
    ];

    $form['container']['date-range']['date_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To date'),
    ];

    $form['container']['installer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Installer Name'),
    ];

    $form['container']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
    ];

    // $form['actions'] = [
    //   '#type' => 'actions',
    // ];

    $form['container']['apply_filters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply filters'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::failureAnalysisAjax',
        'wrapper' => 'failures-search-form__graphs-and-data',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];
    $form['container']['#suffix'] = '</div></div>';

    $form['stats'] = [
      '#type' => 'markup',
      '#markup' => $analysis['content'],
      '#prefix' => '<div id="failures-search-form__graphs-and-data">',
      '#suffix' => '</div>',
    ];

    $form['export_link'] = [
      '#type' => 'markup',
      '#markup' => $analysis['export_link'],
      '#prefix' => '<div id="failures-search-form__graphs-and-data_exportlink">',
      '#suffix' => '</div>',
    ];

   

    // Attach libraries.
    $form['#attached']['library'][] = 'sentinel_reports/daterangepicker';
    $form['#attached']['library'][] = 'sentinel_reports/custom-js';

    // Attach drupalSettings from the analysis if available.
    if (!empty($analysis['attachments'])) {
      if (!empty($analysis['attachments']['library'])) {
        foreach ($analysis['attachments']['library'] as $library) {
          $form['#attached']['library'][] = $library;
        }
      }
      if (!empty($analysis['attachments']['drupalSettings'])) {
        $form['#attached']['drupalSettings'] = $analysis['attachments']['drupalSettings'];
      }
    }

    return $form;
  }

  /**
   * AJAX callback for failure analysis.
   */
  public function failureAnalysisAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    
    // Extract form values.
    $date_from = $form_state->getValue('date_from', FALSE);
    $date_to = $form_state->getValue('date_to', FALSE);
    $installer_name = trim($form_state->getValue('installer_name', ''));
    $location = trim($form_state->getValue('location', ''));

    // Convert date format if needed (e.g., from mm/dd/yyyy to yyyy-mm-dd).
    if (!empty($date_from) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_from, $matches)) {
      $date_from = sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
    }
    if (!empty($date_to) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_to, $matches)) {
      $date_to = sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
    }

    if (empty($installer_name)) {
      $installer_name = FALSE;
    }
    if (empty($location)) {
      $location = FALSE;
    }

    // Build the stats output.
    $html = _sentinel_reports_get_failure_analysis_output($date_from, $date_to, $installer_name, $location);
    $form_state->set('analysis_output', $html);

    // Replace the stats container with new content.
    $stats_html = '<div id="failures-search-form__graphs-and-data">' . $html['content'] . '</div>';
    $response->addCommand(new ReplaceCommand('#failures-search-form__graphs-and-data', $stats_html));
    
    // Replace the export link container.
    $export_html = '<div id="failures-search-form__graphs-and-data_exportlink">' . ($html['export_link'] ?? '') . '</div>';
    $response->addCommand(new ReplaceCommand('#failures-search-form__graphs-and-data_exportlink', $export_html));

    // Attach drupalSettings and libraries for the chart.
    if (!empty($html['attachments']['drupalSettings'])) {
      $response->addCommand(new \Drupal\Core\Ajax\SettingsCommand($html['attachments']['drupalSettings'], FALSE));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $date_from = $form_state->getValue('date_from');
    $date_to = $form_state->getValue('date_to');
    $installer_name = trim($form_state->getValue('installer_name', ''));
    $location = trim($form_state->getValue('location', ''));

    if (!empty($date_from) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_from, $matches)) {
      $date_from = sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
    }
    if (!empty($date_to) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_to, $matches)) {
      $date_to = sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
    }

    if ($installer_name === '') {
      $installer_name = FALSE;
    }

    if ($location === '') {
      $location = FALSE;
    }

    $html = _sentinel_reports_get_failure_analysis_output($date_from, $date_to, $installer_name, $location);
    $form_state->set('analysis_output', $html);
    $form_state->setRebuild(TRUE);
  }

}
