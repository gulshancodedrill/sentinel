<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

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
    $session = \Drupal::request()->getSession();
    $lang = $session->get('sentinel_anonymous_language');
    if (is_string($lang) && $lang !== '') {
      return $lang;
    }
    return \Drupal::languageManager()->getCurrentLanguage()->getId();
  }

  /**
   * Translates using the flow language (session) so labels match the chosen locale.
   */
  public static function trans(string $string, array $args = []): string {
    return (string) \Drupal::translation()->translate(
      $string,
      $args,
      ['langcode' => static::flowLanguageCode()]
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
    $session_flow = \Drupal::request()->getSession()->get('sentinel_anonymous_last_flow');
    if ($session_flow !== NULL && $session_flow !== '') {
      $candidates[] = (string) $session_flow;
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

    // $pct = $data['percent'];//here
    $current_sid = NULL;

if ($sample) {
  $current_sid = $sample->id();
}

$steps = [];

$resolved_type = static::resolveUserType($sample, $form_state, $form_id);
$is_company_flow = static::isCompanyFlow($resolved_type) || $resolved_type === NULL;

if ($is_company_flow) {

  // Company flow.
  $steps = [
    1 => [
      'title' => 'Account',
      'route' => 'sentinel_portal_sample.anonymous_options',
    ],
    2 => [
      'title' => 'Company Details basis of id',
      'route' => 'sentinel_portal_sample.anonymous_company_wizard',
    ],
    3 => [
      'title' => 'Property',
      'route' => 'sentinel_portal_sample.anonymous_details',
    ],
  ];
}
else {
//here
  // Individual flow.
  $steps = [
    1 => [
      'title' => 'Account',
      'route' => 'sentinel_portal_sample.anonymous_options',
    ],
    2 => [
      'title' => 'Your Details',
      'route' => 'sentinel_portal_sample.anonymous_individual_contact',
    ],
    3 => [
      'title' => 'Property',
      'route' => 'sentinel_portal_sample.anonymous_details',
    ],
  ];
}//here

$step_markup = '<div class="sentinel-step-navigation">';

foreach ($steps as $num => $step) {

  $class = '';

  if ($num == $data['current']) {
    $class = 'active-step';
  }
  elseif ($num < $data['current']) {
    $class = 'completed-step';
  }

  $url = '#';
  // $disabled = FALSE;

  // if ($current_sid) {

  //   if ($num == 4 && $data['current'] < 3) {
  //     $disabled = TRUE;
  //   }

  //   // if ($num >= 3) {

  //   //   $company_ready = FALSE;
    
  //   //   if (
  //   //     $sample &&
  //   //     $sample->hasField('company_name') &&
  //   //     !$sample->get('company_name')->isEmpty()
  //   //   ) {
  //   //     $company_ready = TRUE;
  //   //   }
    
  //   //   if (!$company_ready) {
  //   //     $disabled = TRUE;
  //   //   }
  //   // }

  //   $route_params = [];

  //   $route_params['sample_id'] = $current_sid;///here 

  //   $options = [];



  //   if (!empty($step['wizard_step'])) {
  //     $options['query'] = [
  //       'wizard_step' => $step['wizard_step'],
  //     ];
  //   }
    
  //   $url = \Drupal\Core\Url::fromRoute(
  //     $step['route'],
  //     $route_params,
  //     $options
  //   )->toString();///here
  // }

  // $disabled_class = $disabled ? 'disabled-step' : '';

  // $link = $disabled ? 'div' : 'a';
  
  // $href = $disabled ? '' : 'href="' . $url . '"';


  
  // $step_markup .= '
  //   <' . $link . ' class="sentinel-step-item ' . $class . ' ' . $disabled_class . '" ' . $href . '>
  //     <span class="step-number">' . $num . '</span>
  //     <span class="step-title">' . $step['title'] . '</span>
  //   </' . $link . '>
  // ';

  $url = '#';

if ($current_sid) {

  $route_params = [];
  $route_params['sample_id'] = $current_sid;

  $options = [];

  if (!empty($step['wizard_step'])) {
    $options['query'] = [
      'wizard_step' => $step['wizard_step'],
    ];
  }

  $url = \Drupal\Core\Url::fromRoute(
    $step['route'],
    $route_params,
    $options
  )->toString();
}

$step_markup .= '
  <a class="sentinel-step-item ' . $class . '" href="' . $url . '">
    <span class="step-number">' . $num . '</span>
    <span class="step-title">' . $step['title'] . '</span>
  </a>
';
}
//////here
$step_markup .= '</div>';////here
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
      // 'track' => [
      //   '#type' => 'container',
      //   '#attributes' => ['class' => ['sentinel-anon-progress-track']],
      //   'fill' => [
      //     '#type' => 'container',
      //     '#attributes' => [
      //       'class' => ['sentinel-anon-progress-fill'],
      //       'style' => 'width: ' . $pct . '%;',
      //     ],
      //   ],
      // ],
    ];
  }

  /**
   * Human-readable current phase under the step counter.
   */
  public static function phaseSubtitle(string $phase): string {
    $map = [
      'account' => static::trans('Account type and language'),
      'company_id' => static::trans('Company ID'),
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
        $company_path = ($resolved === NULL || $is_company);
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
