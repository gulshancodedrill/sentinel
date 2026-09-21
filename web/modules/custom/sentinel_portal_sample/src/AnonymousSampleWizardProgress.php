<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Progress bar, step counts, and flow language for the anonymous QR sample wizard.
 *
 * Company: 1 account choice → 2 company ID → 3 company details → 4 property → 5 complete.
 * Individual: 1 account choice → 2 your details → 3 property → 4 complete.
 */
final class AnonymousSampleWizardProgress {

  public const STEP_ACCOUNT = 1;

  public const STEP_COMPANY_ID = 2;

  public const STEP_COMPANY_DETAILS = 3;

  public const STEP_INDIVIDUAL_DETAILS = 2;

  public const STEP_PROPERTY = 4;

  public const STEP_PROPERTY_INDIVIDUAL = 3;

  public const STEP_COMPLETE_COMPANY = 5;

  public const STEP_COMPLETE_INDIVIDUAL = 4;

  /**
   * Interface language code for anonymous flow strings (session or negotiated).
   */
  public static function flowLanguageCode(): string {
    $current = AnonymousSampleFormTranslations::normalizeLangcode(
      (string) \Drupal::languageManager()->getCurrentLanguage()->getId()
    );
    if ($current !== '') {
      return $current;
    }

    $session = \Drupal::request()->getSession();
    $lang = AnonymousSampleFormTranslations::normalizeLangcode(
      (string) $session->get('sentinel_anonymous_language')
    );
    if ($lang !== '') {
      return $lang;
    }

    return 'en';
  }

  /**
   * Translates using the flow language (session) so labels match the chosen locale.
   */
  public static function trans(string $string, array $args = []): string {
    $translated = AnonymousSampleFormTranslations::translate(
      $string,
      [],
      static::flowLanguageCode()
    );
    return (string) \Drupal::translation()->translate(
      $translated,
      $args,
      []
    );
  }

  /**
   * Normalizes user_type / session flow values to "company" or "individual".
   */
  public static function normalizeUserTypeKey(?string $value): string {
    $v = strtolower(trim((string) $value));
    if ($v === '' || $v === '0') {
      return '';
    }
    if ($v === 'company' || str_starts_with($v, 'company') || $v === '1') {
      return 'company';
    }
    if ($v === 'individual' || str_starts_with($v, 'individual') || $v === 'indiv' || $v === '2') {
      return 'individual';
    }
    return $v;
  }

  /**
   * Whether the flow is the company path (5 steps); FALSE = individual (4 steps).
   */
  public static function isCompanyFlow(?string $user_type): bool {
    return static::normalizeUserTypeKey($user_type) === 'company';
  }

  /**
   * Resolves company vs individual from sample, form state, session, or optional query sid.
   */
  public static function resolveUserType(?EntityInterface $sample, FormStateInterface $form_state, string $form_id): ?string {
    // Prefer the live account-type choice and the sample being edited over
    // session / ?sid= hints so step counts match what the user sees on screen.
    $candidates = [];
    if ($form_id === 'anonymous_sample_submission_form' || $form_id === 'anonymous_sample_options_form') {
      $v = $form_state->getValue('user_type');
      if (is_string($v) && $v !== '') {
        $candidates[] = $v;
      }
    }
    $session_flow = \Drupal::request()->getSession()->get('sentinel_anonymous_last_flow');
    if ($session_flow !== NULL && $session_flow !== '') {
      $candidates[] = (string) $session_flow;
    }
    if ($sample && $sample->hasField('user_type') && !$sample->get('user_type')->isEmpty()) {
      $candidates[] = (string) $sample->get('user_type')->value;
    }
    $sid = \Drupal::request()->query->get('sid');
    if ($sid !== NULL && $sid !== '') {
      $loaded = \Drupal::entityTypeManager()->getStorage('sentinel_sample')->load((int) $sid);
      if ($loaded && $loaded->hasField('user_type') && !$loaded->get('user_type')->isEmpty()) {
        $candidates[] = (string) $loaded->get('user_type')->value;
      }
    }
    if ($sample) {
      $inferred = static::inferUserTypeFromSample($sample);
      if ($inferred !== NULL) {
        $candidates[] = $inferred;
      }
    }
    foreach ($candidates as $raw) {
      $n = static::normalizeUserTypeKey($raw);
      if ($n === 'company' || $n === 'individual') {
        return $n;
      }
    }
    return NULL;
  }

  /**
   * Converts QR/query PRN separators (- or _) to the canonical colon format.
   *
   * Examples: 456-345333 or 456_3A45333 → 456:3A45333
   */
  public static function normalizeAnonymousPrn(string $prn): string {
    $prn = trim($prn);
    if ($prn === '') {
      return '';
    }
    if (preg_match('/^(\d{3})[-_](.+)$/', $prn, $matches)) {
      return $matches[1] . ':' . $matches[2];
    }
    return $prn;
  }

  /**
   * Loads a sentinel_sample by pack reference number.
   */
  public static function loadSampleByPrn(string $prn): ?EntityInterface {
    $prn = static::normalizeAnonymousPrn($prn);
    if ($prn === '') {
      return NULL;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('sentinel_sample');
    $ids = $storage->getQuery()
      ->condition('pack_reference_number', $prn)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if (empty($ids)) {
      return NULL;
    }
    return $storage->load((int) reset($ids));
  }

  /**
   * Redirect/query options preserving ?prn= and flow language.
   */
  public static function prnRedirectOptions(string $prn, $language = NULL): array {
    $options = AnonymousSampleLanguageRedirect::options($language);
    $options['query'] = ['prn' => static::normalizeAnonymousPrn($prn)];
    return $options;
  }

  /**
   * Infers company vs individual from persisted sample fields (no user_type column).
   */
  public static function inferUserTypeFromSample(?EntityInterface $sample): ?string {
    if (!$sample) {
      return NULL;
    }
    // Company wizard: saved UCR or company address reference.
    if ($sample->hasField('ucr') && !$sample->get('ucr')->isEmpty()) {
      if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
        return 'company';
      }
      if ($sample->hasField('company_name') && trim((string) $sample->get('company_name')->value) !== '') {
        return 'company';
      }
    }
    if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
      return 'company';
    }
    if ($sample->hasField('sentinel_company_address_target_id') && !empty($sample->get('sentinel_company_address_target_id')->value)) {
      return 'company';
    }
    // Individual step copies name into company_name; installer_email is the reliable signal.
    if (static::sampleHasIndividualStepData($sample)) {
      return 'individual';
    }
    if ($sample->hasField('company_name') && trim((string) $sample->get('company_name')->value) !== '') {
      return 'company';
    }
    return NULL;
  }

  /**
   * Whether step 2 company data exists on the sample.
   */
  public static function sampleHasCompanyStepData(EntityInterface $sample): bool {
    if ($sample->hasField('customer_id') && !$sample->get('customer_id')->isEmpty()) {
      return TRUE;
    }
    if ($sample->hasField('company_name') && trim((string) $sample->get('company_name')->value) !== '') {
      return TRUE;
    }
    if ($sample->hasField('field_company_address') && !$sample->get('field_company_address')->isEmpty()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Whether step 2 individual data exists on the sample.
   */
  public static function sampleHasIndividualStepData(EntityInterface $sample): bool {
    if ($sample->hasField('installer_email') && trim((string) $sample->get('installer_email')->value) !== '') {
      return TRUE;
    }
    if ($sample->hasField('installer_name') && trim((string) $sample->get('installer_name')->value) !== '') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Whether property / system address step is complete.
   */
  public static function sampleHasPropertyStepData(EntityInterface $sample): bool {
    if ($sample->hasField('field_sentinel_sample_address') && !$sample->get('field_sentinel_sample_address')->isEmpty()) {
      return TRUE;
    }
    if ($sample->hasField('sentinel_sample_address_target_id') && !empty($sample->get('sentinel_sample_address_target_id')->value)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Whether the sample is fully submitted (company + system addresses).
   */
  public static function sampleIsFullySubmitted(EntityInterface $sample): bool {
    return static::sampleHasPropertyStepData($sample);
  }

  /**
   * Which wizard steps may be opened (1 = account, 2 = contact/company, 3 = property).
   *
   * @return array<int, bool>
   */
  public static function wizardStepAccess(?EntityInterface $sample, ?string $flow): array {
    $access = [
      1 => TRUE,
      2 => FALSE,
      3 => FALSE,
    ];
    if (!$sample || !$sample->id()) {
      return $access;
    }
    $access[2] = TRUE;
    $flow = static::normalizeUserTypeKey($flow ?? static::inferUserTypeFromSample($sample) ?? '');
    if ($flow === 'company' && static::sampleHasCompanyStepData($sample)) {
      $access[3] = TRUE;
    }
    elseif ($flow === 'individual' && static::sampleHasIndividualStepData($sample)) {
      $access[3] = TRUE;
    }
    return $access;
  }

  /**
   * Route name for step 2 for the given flow.
   */
  public static function step2RouteName(string $flow): string {
    return static::isCompanyFlow($flow)
      ? 'sentinel_portal_sample.anonymous_submit_company'
      : 'sentinel_portal_sample.anonymous_submit_individual';
  }

  /**
   * Clears fields for the flow the user is leaving when account type changes.
   */
  public static function clearOppositeFlowData(EntityInterface $sample, string $new_flow): void {
    $new_flow = static::normalizeUserTypeKey($new_flow);
    if ($new_flow === 'company') {
      foreach (['installer_name', 'installer_email'] as $field) {
        if ($sample->hasField($field)) {
          $sample->set($field, NULL);
        }
      }
    }
    elseif ($new_flow === 'individual') {
      foreach (['customer_id', 'company_name', 'company_email', 'company_tel', 'sentinel_company_address_target_id', 'company_address1', 'company_address2', 'company_town', 'company_county', 'company_postcode'] as $field) {
        if ($sample->hasField($field)) {
          $sample->set($field, NULL);
        }
      }
      if ($sample->hasField('field_company_address')) {
        $sample->set('field_company_address', NULL);
      }
    }
  }

  /**
   * Resume URL for an in-progress sample (next incomplete step).
   */
  public static function resumeUrl(EntityInterface $sample): Url {
    $prn = '';
    if ($sample->hasField('pack_reference_number') && !$sample->get('pack_reference_number')->isEmpty()) {
      $prn = trim((string) $sample->get('pack_reference_number')->value);
    }
    $flow = static::resolveUserType($sample, new FormState(), 'anonymous_sample_submission_form')
      ?? static::inferUserTypeFromSample($sample)
      ?? 'company';
    $lang = NULL;
    if ($sample->hasField('language') && !$sample->get('language')->isEmpty()) {
      $lang = \Drupal::languageManager()->getLanguage((string) $sample->get('language')->value);
    }
    $options = static::prnRedirectOptions($prn, $lang);

    if (static::sampleIsFullySubmitted($sample)) {
      return Url::fromRoute('sentinel_portal_sample.anonymous_submit', [], $options);
    }
    if (static::isCompanyFlow($flow)) {
      if (!static::sampleHasCompanyStepData($sample)) {
        return Url::fromRoute('sentinel_portal_sample.anonymous_submit_company', [], $options);
      }
    }
    elseif (!static::sampleHasIndividualStepData($sample)) {
      return Url::fromRoute('sentinel_portal_sample.anonymous_submit_individual', [], $options);
    }
    return Url::fromRoute('sentinel_portal_sample.anonymous_submit_other_details', [], $options);
  }

  /**
   * Prepends progress markup and attaches the wizard CSS/JS library.
   */
  public static function prependToForm(array &$form, FormStateInterface $form_state, string $form_id, ?EntityInterface $sample = NULL): void {
    $data = static::computeSteps($form_id, $form_state, $sample);
    if ($data === NULL) {
      return;
    }

    $form['#attached']['library'][] = 'sentinel_portal_sample/anonymous-wizard';

    $line = static::trans('Step @current of @total', [
      '@current' => (string) $data['current'],
      '@total' => (string) $data['total'],
    ]);
    $subtitle = static::phaseSubtitle($data['phase']);

    $wrap_attributes = [
      'class' => ['sentinel-anon-progress-wrap'],
      'aria-label' => $line,
    ];
    if (!empty($data['dynamic_step1'])) {
      $wrap_attributes['data-anon-progress-step1'] = '1';
      $tc = (int) ($data['total_company'] ?? 5);
      $ti = (int) ($data['total_individual'] ?? 4);
      $form['#attached']['drupalSettings']['sentinelAnonWizardProgressStep1'] = [
        'companyLine' => static::trans('Step @current of @total', [
          '@current' => '1',
          '@total' => (string) $tc,
        ]),
        'individualLine' => static::trans('Step @current of @total', [
          '@current' => '1',
          '@total' => (string) $ti,
        ]),
        'companyPercent' => (1 / max($tc, 1)) * 100,
        'individualPercent' => (1 / max($ti, 1)) * 100,
      ];
    }

    $prn = static::normalizeAnonymousPrn((string) \Drupal::request()->query->get('prn', ''));
    if ($prn === '' && $sample && $sample->hasField('pack_reference_number') && !$sample->get('pack_reference_number')->isEmpty()) {
      $prn = trim((string) $sample->get('pack_reference_number')->value);
    }

    $resolved_type = static::resolveUserType($sample, $form_state, $form_id);
    if ($resolved_type === NULL) {
      $session_hint = static::normalizeUserTypeKey((string) \Drupal::request()->getSession()->get('sentinel_anonymous_last_flow'));
      if ($session_hint === 'company' || $session_hint === 'individual') {
        $resolved_type = $session_hint;
      }
    }
    $is_company_flow = static::isCompanyFlow($resolved_type);
    $step_access = static::wizardStepAccess($sample, $resolved_type);

    if ($is_company_flow) {
      $steps = [
        1 => [
          'title' => static::trans('Account'),
          'route' => 'sentinel_portal_sample.anonymous_submit',
        ],
        2 => [
          'title' => static::trans('Company details'),
          'route' => 'sentinel_portal_sample.anonymous_submit_company',
        ],
        3 => [
          'title' => static::trans('Property'),
          'route' => 'sentinel_portal_sample.anonymous_submit_other_details',
        ],
      ];
    }
    else {
      $steps = [
        1 => [
          'title' => static::trans('Account'),
          'route' => 'sentinel_portal_sample.anonymous_submit',
        ],
        2 => [
          'title' => static::trans('Your details'),
          'route' => 'sentinel_portal_sample.anonymous_submit_individual',
        ],
        3 => [
          'title' => static::trans('Property'),
          'route' => 'sentinel_portal_sample.anonymous_submit_other_details',
        ],
      ];
    }

    $lang = NULL;
    if ($sample && $sample->hasField('language') && !$sample->get('language')->isEmpty()) {
      $lang = \Drupal::languageManager()->getLanguage((string) $sample->get('language')->value);
    }

    $step_markup = '<div class="sentinel-step-navigation">';
    foreach ($steps as $num => $step) {
      $class = '';
      if ($num == $data['current']) {
        $class = 'active-step';
      }
      elseif ($num < $data['current']) {
        $class = 'completed-step';
      }

      $enabled = !empty($step_access[$num]) && $prn !== '';
      $title = Html::escape($step['title']);
      if ($enabled) {
        $url = Url::fromRoute(
          $step['route'],
          [],
          static::prnRedirectOptions($prn, $lang)
        )->toString();
        $step_markup .= '<a class="sentinel-step-item ' . $class . '" href="' . Html::escape($url) . '">'
          . '<span class="step-number">' . $num . '</span>'
          . '<span class="step-title">' . $title . '</span></a>';
      }
      else {
        $step_markup .= '<span class="sentinel-step-item ' . $class . ' disabled-step" aria-disabled="true">'
          . '<span class="step-number">' . $num . '</span>'
          . '<span class="step-title">' . $title . '</span></span>';
      }
    }
    $step_markup .= '</div>';
    $form['sentinel_anon_wizard_progress'] = [
      '#type' => 'container',
      '#attributes' => $wrap_attributes,
      '#weight' => -200,
      'steps' => [
  '#markup' => $step_markup,
],
      'meta' => [
        '#markup' => '<p class="sentinel-anon-progress-meta"><span class="sentinel-anon-progress-line">' . Html::escape($line) . '</span></p>',
      ],
      'subtitle' => [
        '#markup' => '<p class="sentinel-anon-progress-subtitle">' . Html::escape($subtitle) . '</p>',
      ],
    ];
  }

  /**
   * Human-readable current phase under the step counter.
   */
  public static function phaseSubtitle(string $phase): string {
    $map = [
      'account' => static::trans('Account type and language'),
      'company_id' => static::trans('Client UCR'),
      'company_details' => static::trans('Company details'),
      'your_details' => static::trans('Your details'),
      'property' => static::trans('Property details'),
      'complete' => static::trans('Submission complete'),
    ];
    return $map[$phase] ?? '';
  }

  /**
   * Computes current step, total steps, fill percentage, and phase key.
   *
   * @return array{
   *   current: int,
   *   total: int,
   *   percent: float,
   *   phase: string,
   *   dynamic_step1?: bool,
   *   total_company?: int,
   *   total_individual?: int
   * }|null
   */
  public static function computeSteps(string $form_id, FormStateInterface $form_state, ?EntityInterface $sample = NULL): ?array {
    $resolved = static::resolveUserType($sample, $form_state, $form_id);
    $is_company = static::isCompanyFlow($resolved);
    $total_company = 4;
    $total_individual = 4;

    switch ($form_id) {
      case 'anonymous_sample_submission_form':
      case 'anonymous_sample_options_form':
        $total = ($resolved === NULL || $is_company) ? $total_company : $total_individual;
        $percent = (1 / max($total, 1)) * 100;
        return [
          'current' => 1,
          'total' => $total,
          'percent' => $percent,
          'phase' => 'account',
          'dynamic_step1' => TRUE,
          'total_company' => $total_company,
          'total_individual' => $total_individual,
        ];

      case 'anonymous_sample_company_wizard_form':
        $total = $total_company;
        return [
          'current' => 2,
          'total' => $total,
          'percent' => (2 / $total) * 100,
          'phase' => 'company_details',
        ];

      case 'anonymous_sample_individual_contact_form':
        $total = $total_individual;
        return [
          'current' => 2,
          'total' => $total,
          'percent' => (2 / $total) * 100,
          'phase' => 'your_details',
        ];

      case 'anonymous_sample_property_details_form':
        if ($resolved === NULL) {
          $session_hint = static::normalizeUserTypeKey((string) \Drupal::request()->getSession()->get('sentinel_anonymous_last_flow'));
          if ($session_hint === 'company' || $session_hint === 'individual') {
            $resolved = $session_hint;
          }
        }
        $company_path = static::isCompanyFlow($resolved);
        if ($company_path) {
          return [
            'current' => 3,
            'total' => $total_company,
            'percent' => (3 / $total_company) * 100,
            'phase' => 'property',
          ];
        }
        return [
          'current' => 3,
          'total' => $total_individual,
          'percent' => (3 / $total_individual) * 100,
          'phase' => 'property',
        ];

      default:
        return NULL;
    }
  }

  /**
   * Thank-you page progress (100% fill, correct final step label).
   *
   * @param string|null $user_type
   *   Normalized 'company' or 'individual' from query/session.
   */
  public static function thankYouBar(?string $user_type = NULL): array {
    $resolved = static::normalizeUserTypeKey((string) $user_type);
    if ($resolved !== 'company' && $resolved !== 'individual') {
      $resolved = static::normalizeUserTypeKey((string) (\Drupal::request()->getSession()->get('sentinel_anonymous_last_flow') ?? ''));
    }
    if ($resolved !== 'company' && $resolved !== 'individual') {
      $resolved = 'company';
    }
    $is_company = ($resolved === 'company');
    $total = $is_company ? 4 : 4;
    $current = $total;
    $percent = 100.0;
    $line = static::trans('Step @current of @total', [
      '@current' => (string) $current,
      '@total' => (string) $total,
    ]);
    $subtitle = static::phaseSubtitle('complete');

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['sentinel-anon-progress-wrap'],
      ],
      '#attached' => [
        'library' => ['sentinel_portal_sample/anonymous-wizard'],
      ],
      'meta' => [
        '#markup' => '<p class="sentinel-anon-progress-meta"><span class="sentinel-anon-progress-line">' . Html::escape($line) . '</span></p>',
      ],
      'subtitle' => [
        '#markup' => '<p class="sentinel-anon-progress-subtitle">' . Html::escape($subtitle) . '</p>',
      ],
      'track' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['sentinel-anon-progress-track']],
        'fill' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['sentinel-anon-progress-fill'],
            'style' => 'width: ' . $percent . '%;',
          ],
        ],
      ],
    ];
  }

}
