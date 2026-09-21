<?php

namespace Drupal\sentinel_portal_sample\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\sentinel_portal_sample\Ajax\GenericDataCommand;
use Drupal\sentinel_portal_sample\AnonymousSampleLanguageRedirect;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;
use Drupal\sentinel_portal_sample\GoAddressClient;
use Drupal\sentinel_sample\Entity\SentinelSample;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Sentinel Sample pages.
 */
class SentinelSampleController extends ControllerBase {

  /**
   * View sample callback.
   *
   * @param \Drupal\sentinel_sample\Entity\SentinelSample $sentinel_sample
   *   The sample entity.
   *
   * @return array
   *   A renderable array.
   */
  public function view(SentinelSample $sentinel_sample) {
    // For now, return a simple message
    return [
      '#markup' => $this->t('Sample View page for @id', ['@id' => $sentinel_sample->id()]),
    ];
  }

  /**
   * Landlord autocomplete callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with autocomplete suggestions.
   */
  public function landlordAutocomplete(Request $request) {
    $string = trim((string) $request->query->get('q', ''));
    $matches = [];

    if ($string !== '') {
      $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
      $tids = $storage->getQuery()
        ->condition('vid', 'landlords')
        ->condition('name', $string, 'CONTAINS')
        ->range(0, 10)
        ->accessCheck(FALSE)
        ->execute();

      if ($tids) {
        $terms = $storage->loadMultiple($tids);
        foreach ($terms as $term) {
          $name = $term->getName();
          $value = $name;
          if (str_contains($name, ',') || str_contains($name, '"')) {
            $value = '"' . str_replace('"', '""', $name) . '"';
          }

          $matches[] = [
            'value' => $value,
            'label' => Html::escape($name),
            'data' => [
              'raw' => $name,
            ],
          ];
        }
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Sample address autocomplete callback.
   */
  public function sampleAddressAutocomplete(Request $request) {
    $string = trim((string) $request->query->get('q', ''));
    $matches = [];

    $cids = $this->getAccessibleClientIds();
    if (empty($cids)) {
      return new JsonResponse($matches);
    }

    $addresses = $this->loadSampleAddresses($cids, $string);

    foreach ($addresses as $row) {
      $address_string = $this->buildAddressString($row);
      if ($address_string === '') {
        continue;
      }

      $matches[] = [
        'value' => '(' . $row->entity_id . ') ' . $address_string,
        'label' => Html::escape($address_string),
        'data' => [
          'raw' => $address_string,
        ],
      ];
    }

    return new JsonResponse($matches);
  }

  /**
   * Property address autocomplete via GoAddress (house number + postcode).
   */
  public function propertyAddressAutocomplete(Request $request, $user_type = 'any') {
    $house_no = trim((string) $request->query->get('q', ''));
    $postcode = trim((string) $request->query->get('postcode', ''));
    $matches = [];

    if ($house_no !== '' && $postcode !== '') {
      $results = GoAddressClient::search($this->httpClient(), $house_no, $postcode);
      foreach ($results as $id => $row) {
        $label = $row['label'] ?? $id;
        $matches[] = [
          'value' => $label,
          'label' => Html::escape($label),
          'data' => [
            'addressid' => $id,
          ],
        ];
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * AJAX callback for company address selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function selectCompanyAddress(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $selection = $form_state->getValue([
      'company_details',
      'company_address',
      'company_address_selection',
    ]);

    if (empty($selection)) {
      return $response;
    }

    $cids = [];
    if (function_exists('sentinel_portal_entities_get_client_by_user')) {
      $client = sentinel_portal_entities_get_client_by_user();
      if ($client) {
        $cids = function_exists('get_more_clients_based_client_cohorts') ? get_more_clients_based_client_cohorts($client) : [];
        $cids[] = $client->id();
      }
    }

    $addresses = function_exists('get_company_addresses_for_cids')
      ? get_company_addresses_for_cids($cids, (int) $selection)
      : [];

    $address = $addresses ? reset($addresses) : FALSE;

    if ($address) {
      $data = [
        'addresstype' => 'company',
        'entity_id' => (int) $address->entity_id,
        'field_address_country' => $address->field_address_country_code ?? '',
        'field_address_administrative_area' => $address->field_address_administrative_area ?? '',
        'field_address_locality' => $address->field_address_locality ?? '',
        'field_address_postal_code' => $address->field_address_postal_code ?? '',
        'field_address_thoroughfare' => $address->field_address_address_line1 ?? '',
        'field_address_premise' => $address->field_address_address_line2 ?? '',
        'field_address_sub_premise' => $address->field_address_address_line3 ?? '',
        'field_address_organisation_name' => $address->field_address_organization ?? '',
      ];

      $response->addCommand(new GenericDataCommand('company_address_update', $data));
    }

    return $response;
  }

  /**
   * AJAX callback for sample address selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function selectSampleAddress(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $selection = $form_state->getValue([
      'system_details',
      'address',
      'sample_address_selection',
    ]);

    if (empty($selection)) {
      return $response;
    }

    $address_id = NULL;
    if (preg_match('/^\((\d+)\)/', $selection, $matches)) {
      $address_id = (int) $matches[1];
    }

    if (!$address_id) {
      return $response;
    }

    $cids = $this->getAccessibleClientIds();

    $addresses = [];
    if (function_exists('get_sentinel_sample_addresses_for_cids')) {
      $addresses = get_sentinel_sample_addresses_for_cids('', $address_id);
    }

    if (empty($addresses)) {
      $addresses = $this->loadSampleAddresses($cids, '', $address_id);
    }

    $address = $addresses ? reset($addresses) : FALSE;

    if ($address) {
      $data = [
        'addresstype' => 'sample',
        'entity_id' => (int) $address->entity_id,
        'field_address_country' => $address->field_address_country_code ?? '',
        'field_address_administrative_area' => $address->field_address_administrative_area ?? '',
        'field_address_locality' => $address->field_address_locality ?? '',
        'field_address_dependent_locality' => $address->field_address_dependent_locality ?? '',
        'field_address_postal_code' => $address->field_address_postal_code ?? '',
        'field_address_thoroughfare' => $address->field_address_address_line1 ?? '',
        'field_address_premise' => $address->field_address_address_line2 ?? '',
        'field_address_sub_premise' => $address->field_address_address_line3 ?? '',
        'field_address_organisation_name' => $address->field_address_organization ?? '',
        'field_address_name_line' => $address->field_address_address_line1 ?? '',
        'field_address_first_name' => $address->field_address_given_name ?? '',
        'field_address_last_name' => $address->field_address_family_name ?? '',
      ];

      $response->addCommand(new GenericDataCommand('sample_address_update', $data));
    }

    return $response;
  }

  /**
   * AJAX callback for landlord selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function selectLandlord(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $term_name = $form_state->getValue([
      'system_details',
      'landlord_selection',
    ]) ?? '';

    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#value']) && $trigger['#value'] !== '') {
      $term_name = $trigger['#value'];
    }

    $form['system_details']['landlord_wrapper']['landlord']['#value'] = $term_name;

    $response->addCommand(new GenericDataCommand('sample_landlord_update', [
      'term_name' => $term_name,
      'raw' => $term_name,
      'landlord_field_selector' => '#edit-landlord',
    ]));

    return $response;
  }

  /**
   * Build the list of accessible client IDs for the current user.
   */
  protected function getAccessibleClientIds(): array {
    $cids = [];

    if (function_exists('sentinel_portal_entities_get_client_by_user')) {
      $client = sentinel_portal_entities_get_client_by_user();
      if ($client) {
        if (function_exists('get_more_clients_based_client_cohorts')) {
          $cids = get_more_clients_based_client_cohorts($client) ?: [];
        }
        $cids[] = $client->id();
      }
    }

    return array_values(array_unique(array_filter($cids)));
  }

  /**
   * Load sample addresses filtered by accessible client IDs.
   */
  protected function loadSampleAddresses(array $cids, string $search = '', ?int $address_id = NULL): array {
    if (empty($cids)) {
      return [];
    }

    $database = \Drupal::database();
    $query = $database->select('address__field_address', 'address');
    $query->fields('address', [
      'entity_id',
      'field_address_country_code',
      'field_address_administrative_area',
      'field_address_dependent_locality',
      'field_address_locality',
      'field_address_postal_code',
      'field_address_address_line1',
      'field_address_address_line2',
      'field_address_address_line3',
      'field_address_organization',
      'field_address_given_name',
      'field_address_family_name',
    ]);

    $query->join('sentinel_sample__field_sentinel_sample_address', 'ssa', 'ssa.field_sentinel_sample_address_target_id = address.entity_id');
    $query->join('sentinel_sample', 'sample', 'sample.pid = ssa.entity_id');
    $query->join('sentinel_client', 'sc', 'sc.ucr = sample.ucr');
    $query->condition('sc.cid', $cids, 'IN');

    if ($address_id !== NULL) {
      $query->condition('address.entity_id', $address_id, '=');
    }

    if ($search !== '') {
      $or = $query->orConditionGroup();
      $or->condition('address.field_address_address_line1', '%' . $database->escapeLike($search) . '%', 'LIKE');
      $or->condition('address.field_address_address_line2', '%' . $database->escapeLike($search) . '%', 'LIKE');
      $or->condition('address.field_address_locality', '%' . $database->escapeLike($search) . '%', 'LIKE');
      $or->condition('address.field_address_postal_code', '%' . $database->escapeLike($search) . '%', 'LIKE');
      $query->condition($or);
    }

    $query->range(0, 10);

    return $query->execute()->fetchAll();
  }

  /**
   * Build a human readable address string from a query row.
   */
  protected function buildAddressString($row): string {
    $parts = array_filter([
      $row->field_address_address_line1 ?? '',
      $row->field_address_address_line2 ?? '',
      $row->field_address_locality ?? '',
      $row->field_address_postal_code ?? '',
      $row->field_address_country_code ?? '',
    ]);

    return implode(', ', $parts);
  }

  /**
   * Check if user can view their own sample.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   * @param \Drupal\sentinel_sample\Entity\SentinelSample $sentinel_sample
   *   The sample entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, SentinelSample $sentinel_sample = NULL) {
    if (!$sentinel_sample) {
      return AccessResult::forbidden();
    }
    
    // TODO: Add proper access check logic here
    return AccessResult::allowedIfHasPermission($account, 'sentinel view own sentinel_sample');
  }

  /**
   * Anonymous sample submission page.
   *
   * Handles redirects based on PRN query parameter and existing sample status.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Either a redirect response or the form render array.
   */
  public function anonymousSubmit(Request $request) {
    $raw_prn = trim((string) $request->query->get('prn', ''));
    $prn = AnonymousSampleWizardProgress::normalizeAnonymousPrn($raw_prn);

    // Canonicalize hyphen/underscore QR PRNs to colon in the URL.
    if ($prn !== '' && $prn !== $raw_prn) {
      $query = $request->query->all();
      $query['prn'] = $prn;
      $options = AnonymousSampleLanguageRedirect::options();
      $options['query'] = $query;
      return $this->redirect('sentinel_portal_sample.anonymous_submit', [], $options);
    }

    // PRN is mandatory in query string
    if (empty($prn)) {
      return [
        '#title' => AnonymousSampleWizardProgress::trans('Invalid QR Code'),
        'error_message' => [
          '#markup' => '<div class="messages messages--error">' .
            '<p><strong>' . AnonymousSampleWizardProgress::trans('Invalid QR, please scan the correct QR') . '</strong></p>' .
            '<p>' . AnonymousSampleWizardProgress::trans('The QR code is missing or invalid. Please scan the correct QR code from your pack.') . '</p>' .
            '</div>',
        ],
      ];
    }

    // Check if sample with this PRN already exists
    $storage = $this->entityTypeManager()->getStorage('sentinel_sample');
    $query = $storage->getQuery()
      ->condition('pack_reference_number', $prn)
      ->accessCheck(FALSE)
      ->range(0, 1);
    $existing_ids = $query->execute();

    if (!empty($existing_ids)) {
      // Sample exists - load it and check addresses
      $sample_id = reset($existing_ids);
      $sample = $storage->load($sample_id);

      if ($sample) {
        // Check if sample has both company address and system address
        // Check new entity reference fields first
        $has_company_address = FALSE;
        if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
          $has_company_address = TRUE;
        }
        // Fall back to legacy field
        elseif ($sample->hasField('sentinel_company_address_target_id')) {
          $legacy_company_id = $sample->get('sentinel_company_address_target_id')->value;
          $has_company_address = !empty($legacy_company_id);
        }

        $has_system_address = FALSE;
        if ($sample->hasField('field_sentinel_sample_address') && !$sample->get('field_sentinel_sample_address')->isEmpty()) {
          $has_system_address = TRUE;
        }
        // Fall back to legacy field
        elseif ($sample->hasField('sentinel_sample_address_target_id')) {
          $legacy_system_id = $sample->get('sentinel_sample_address_target_id')->value;
          $has_system_address = !empty($legacy_system_id);
        }

        if ($has_company_address && $has_system_address) {
          // Sample is complete - show message
          return [
            '#title' => AnonymousSampleWizardProgress::trans('Sample Already Submitted'),
            'message' => [
              '#markup' => '<div class="messages messages--warning">' .
                '<p><strong>' . AnonymousSampleWizardProgress::trans('This record already exists.') . '</strong></p>' .
                '<p>' . AnonymousSampleWizardProgress::trans('A sample with Packet Reference Number @prn has already been submitted with complete details.', [
                  '@prn' => $prn,
                ]) . '</p>' .
                '</div>',
            ],
          ];
        }
      }
    }

    $form = $this->formBuilder()->getForm('\Drupal\sentinel_portal_sample\Form\AnonymousSampleSubmissionForm');
    return $form;
  }

  /**
   * Legacy /sample/company/{token} → PRN-based company step.
   */
  public function redirectLegacyCompanyRoute($token = NULL) {
    return $this->redirectLegacyWizardRoute($token, 'sentinel_portal_sample.anonymous_submit_company');
  }

  /**
   * Legacy /sample/individual/{token} → PRN-based individual step.
   */
  public function redirectLegacyIndividualRoute($token = NULL) {
    return $this->redirectLegacyWizardRoute($token, 'sentinel_portal_sample.anonymous_submit_individual');
  }

  /**
   * Legacy /sample/details/{token} → PRN-based property step.
   */
  public function redirectLegacyDetailsRoute($token = NULL) {
    return $this->redirectLegacyWizardRoute($token, 'sentinel_portal_sample.anonymous_submit_other_details');
  }

  /**
   * Redirects old token URLs to the matching ?prn= submit route.
   */
  protected function redirectLegacyWizardRoute($token, string $target_route): RedirectResponse {
    $prn = $this->resolvePrnFromLegacyToken((string) $token);
    if ($prn === '') {
      return new RedirectResponse(Url::fromRoute('<front>')->setAbsolute()->toString());
    }
    $url = Url::fromRoute(
      $target_route,
      [],
      AnonymousSampleWizardProgress::prnRedirectOptions($prn)
    )->setAbsolute();
    return new RedirectResponse($url->toString());
  }

  /**
   * Resolves PRN from a numeric sample id or draft session token.
   */
  protected function resolvePrnFromLegacyToken(string $token): string {
    $token = trim($token);
    if ($token === '') {
      return '';
    }
    if (str_starts_with($token, 'draft_')) {
      $draft = \Drupal::request()->getSession()->get('sentinel_draft_' . $token, []);
      if (is_array($draft) && !empty($draft['pack_reference_number'])) {
        return trim((string) $draft['pack_reference_number']);
      }
      return '';
    }
    if (ctype_digit($token)) {
      $sample = $this->entityTypeManager()->getStorage('sentinel_sample')->load((int) $token);
      if ($sample && $sample->hasField('pack_reference_number') && !$sample->get('pack_reference_number')->isEmpty()) {
        return trim((string) $sample->get('pack_reference_number')->value);
      }
    }
    return '';
  }

  /**
   * Thank you page for anonymous sample submission.
   */
  public function thankYou(Request $request) {
    $sid = $request->query->get('sid');
    $flow_hint = NULL;
    if ($sid !== NULL && $sid !== '') {
      $sample = $this->entityTypeManager()->getStorage('sentinel_sample')->load((int) $sid);
      if ($sample && $sample->hasField('user_type') && !$sample->get('user_type')->isEmpty()) {
        $flow_hint = AnonymousSampleWizardProgress::normalizeUserTypeKey((string) $sample->get('user_type')->value);
      }
    }

    return [
      '#title' => AnonymousSampleWizardProgress::trans('Submission complete'),
      'message' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['sentinel-anonymous-thankyou']],
        'icon' => [
          '#markup' => '<p class="sentinel-anonymous-thankyou__icon" aria-hidden="true">✅</p>',
        ],
        'text' => [
          '#markup' => '<p class="sentinel-anonymous-thankyou__lead"><strong>' .
            Html::escape(AnonymousSampleWizardProgress::trans('Your details have been saved.')) . '</strong></p>' .
            '<p>' . Html::escape(AnonymousSampleWizardProgress::trans('You can close this page.')) . '</p>',
        ],
        'done' => [
          '#type' => 'link',
          '#title' => AnonymousSampleWizardProgress::trans('Done'),
          '#url' => Url::fromRoute('<front>', [], AnonymousSampleLanguageRedirect::options()),
          '#attributes' => [
            'class' => ['button', 'button--primary', 'btn', 'btn-success', 'sentinel-anonymous-thankyou__done'],
          ],
        ],
      ],
    ];
  }

}
