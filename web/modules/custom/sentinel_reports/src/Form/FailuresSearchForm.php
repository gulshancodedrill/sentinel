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
    // Set cookie for app server.
    if (!isset($_COOKIE['ah_app_server']) && !\Drupal::service('kernel')->isCLI()) {
      if ($hostname = strtok(gethostname(), '.')) {
        setcookie('ah_app_server', $hostname, time() + (86400), '/');
      }
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
      '#ajax' => [
        'callback' => '::failureAnalysisAjax',
        'wrapper' => 'failures-search-form__graphs-and-data',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['container']['date-range']['date_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('To date'),
      '#ajax' => [
        'callback' => '::failureAnalysisAjax',
        'wrapper' => 'failures-search-form__graphs-and-data',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['container']['installer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Installer Name'),
      '#ajax' => [
        'callback' => '::failureAnalysisAjax',
        'wrapper' => 'failures-search-form__graphs-and-data',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['container']['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#ajax' => [
        'callback' => '::failureAnalysisAjax',
        'wrapper' => 'failures-search-form__graphs-and-data',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $form['container']['#suffix'] = '</div></div>';

    $form['stats'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'failures-search-form__graphs-and-data'],
    ];

    // Attach libraries.
    $form['#attached']['library'][] = 'sentinel_reports/daterangepicker';
    $form['#attached']['library'][] = 'sentinel_reports/custom-js';

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

    if (empty($installer_name)) {
      $installer_name = FALSE;
    }
    if (empty($location)) {
      $location = FALSE;
    }

    // Build the stats output.
    $html = _sentinel_reports_get_failure_analysis_output($date_from, $date_to, $installer_name, $location);

    $response->addCommand(new ReplaceCommand('#failures-search-form__graphs-and-data', $html['content']));
    
    if (!empty($html['export_link'])) {
      $response->addCommand(new ReplaceCommand('#failures-search-form__graphs-and-data_exportlink', $html['export_link']));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // No direct submission - handled via AJAX.
  }

}
