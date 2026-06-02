<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sentinel_portal_sample\AnonymousSampleFlowTranslationTrait;
use Drupal\sentinel_portal_sample\AnonymousSampleFormTranslations;
use Drupal\sentinel_portal_sample\AnonymousSampleLanguageRedirect;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Options form after anonymous sample submission.
 */
class AnonymousSampleOptionsForm extends FormBase {

  use AnonymousSampleFlowTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AnonymousSampleOptionsForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'anonymous_sample_options_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $token = NULL) {
    if (!$token) {
      $this->messenger()->addError($this->tFlow('Invalid sample.'));
      return $form;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if (str_starts_with($token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }

    if (!$sample) {
      $this->messenger()->addError($this->tFlow('Sample not found.'));
      return $form;
    }

    if (AnonymousSampleWizardProgress::sampleIsFullySubmitted($sample)) {
      $form['#title'] = $this->tFlow('Sample Already Submitted');
      $form['message'] = [
        '#markup' => '<div class="messages messages--warning">' .
          '<p><strong>' . $this->tFlow('This record already exists.') . '</strong></p>' .
          '<p>' . $this->tFlow('This sample has already been submitted with complete details.') . '</p></div>',
        '#weight' => -10,
      ];
      return $form;
    }

    $this->getRequest()->getSession()->set('sentinel_anonymous_entry', 'options');

    $lang_options = AnonymousSampleFormTranslations::languageOptions();

    $resolved_lang = AnonymousSampleWizardProgress::flowLanguageCode();
    if (!isset($lang_options[$resolved_lang])) {
      $resolved_lang = 'en';
    }

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'anonymous-options-ajax-wrapper'],
      '#weight' => -20,
    ];

    $form['wrapper']['message'] = [
      '#markup' => '<p class="anonymous-sample-options-intro">' . $this->tFlow('Please choose one of the options below.') . '</p>',
      '#weight' => -10,
    ];

    $form['wrapper']['language'] = [
      '#type' => 'select',
      '#title' => $this->tFlow('Select Language'),
      '#options' => $lang_options,
      '#default_value' => $resolved_lang,
      '#required' => TRUE,
      // Keep values at the form root so submit handlers and language resolution work.
      '#parents' => ['language'],
      '#submit' => ['::persistLanguageSelectionAjax'],
      '#ajax' => [
        'callback' => '::ajaxLanguageChange',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $user_type_default = NULL;
    if ($sample->hasField('user_type') && !$sample->get('user_type')->isEmpty()) {
      $normalized = AnonymousSampleWizardProgress::normalizeUserTypeKey((string) $sample->get('user_type')->value);
      if ($normalized === 'company' || $normalized === 'individual') {
        $user_type_default = $normalized;
      }
    }

    $form['wrapper']['user_type'] = [
      '#type' => 'radios',
      '#title' => $this->tFlow('Please select your account type'),
      '#options' => [
        'company' => $this->tFlow('Company'),
        'individual' => $this->tFlow('Individual'),
      ],
      '#required' => TRUE,
      '#parents' => ['user_type'],
      '#default_value' => $user_type_default,
    ];

    // Add Details Now button
    // $form['actions']['add_details_now'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Add More Details'),
    //   '#submit' => ['::addDetailsNow'],
    //   '#attributes' => [
    //     'class' => ['button--primary'],
    //   ],
    // ];

    // Add Details Later button
    // $form['actions']['add_details_later'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Add Later'),
    //   '#submit' => ['::addDetailsLater'],
    // ];

    $form['wrapper']['actions'] = [
      '#type' => 'actions',
      '#parents' => ['actions'],
    ];
    $form['wrapper']['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->tFlow('Next'),
      '#button_type' => 'primary',
      '#parents' => ['actions', 'next'],
    ];

    $form_state->set('options_token', $token);

    AnonymousSampleWizardProgress::prependToForm($form, $form_state, 'anonymous_sample_options_form', $sample);

    return $form;
  }

  /**
   * Saves language choice on the sample before the interface reloads in that language.
   */
  public function persistLanguageSelectionAjax(array &$form, FormStateInterface $form_state): void {
    $token = $form_state->get('options_token');
    $langcode = AnonymousSampleFormTranslations::normalizeLangcode((string) $form_state->getValue('language'));
    if (!$token || $langcode === NULL || $langcode === '') {
      return;
    }
    
    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if (str_starts_with($token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }
    
    if ($sample && $sample->hasField('language')) {
      $sample->set('language', $langcode);
      if (str_starts_with($token, 'draft_')) {
        $this->getRequest()->getSession()->set('sentinel_draft_' . $token, $sample->toArray());
      } else {
        $sample->save();
      }
    }
  }

  /**
   * Full page redirect to the URL language prefix (site uses path-based negotiation).
   */
public function ajaxLanguageChange(array &$form, FormStateInterface $form_state) {

  $token = $form_state->get('options_token');

  $langcode = AnonymousSampleFormTranslations::normalizeLangcode((string) $form_state->getValue('language'));

  $language = $langcode
    ? \Drupal::languageManager()->getLanguage($langcode)
    : NULL;

  if (!$token || !$language) {
    return $form['wrapper'];
  }

  // Store selected language in session.
  $this->getRequest()
    ->getSession()
    ->set('sentinel_anonymous_language', $langcode);

  $url = Url::fromRoute(
    'sentinel_portal_sample.anonymous_options',
    [
      'token' => $token,
    ],
    [
      'language' => $language,
    ]
  )->setAbsolute(TRUE);

  $response = new AjaxResponse();

  $response->addCommand(
    new RedirectCommand($url->toString())
  );

  return $response;
}

  /**
   * Submit handler for "Add Details Now".
   */
  public function addDetailsNow(array &$form, FormStateInterface $form_state) {
    $token = $form_state->get('options_token');
    
    // Redirect to anonymous details form
    $langcode = AnonymousSampleFormTranslations::normalizeLangcode((string) $form_state->getValue('language'));
    $target_lang = $langcode ? \Drupal::languageManager()->getLanguage($langcode) : NULL;
    $form_state->setRedirect('sentinel_portal_sample.anonymous_details', [
      'token' => $token,
    ], AnonymousSampleLanguageRedirect::options($target_lang));
  }

  /**
   * Submit handler for "Add Details Later".
   */
  public function addDetailsLater(array &$form, FormStateInterface $form_state) {
    // Redirect to thank you page
    $form_state->setRedirect('sentinel_portal_sample.anonymous_thank_you', [], AnonymousSampleLanguageRedirect::options());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $token = $form_state->get('options_token');
  
    $storage = $this->entityTypeManager
      ->getStorage('sentinel_sample');
  
    if (str_starts_with($token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }
  
    $langcode = $form_state->getValue('language');
  
    // Store selected language in session.
    $this->getRequest()
      ->getSession()
      ->set('sentinel_anonymous_language', $langcode);
    $this->getRequest()->getSession()->set(
      'sentinel_anonymous_last_flow',
      AnonymousSampleWizardProgress::normalizeUserTypeKey((string) $form_state->getValue('user_type'))
    );
  
    if ($sample) {
  
      // Save user type.
      if ($sample->hasField('user_type')) {
        $sample->set(
          'user_type',
          $form_state->getValue('user_type')
        );
      }
  
      // Save language.
      if ($sample->hasField('language')) {
        $sample->set('language', $langcode);
      }
  
      $sample->save();
      
      if (str_starts_with($token, 'draft_')) {
        $this->getRequest()->getSession()->set('sentinel_draft_' . $token, $sample->toArray());
      } else {
        $sample->save();
      }
    }
  
    $user_type = $form_state->getValue('user_type');
  
    $target_lang = $langcode
      ? \Drupal::languageManager()->getLanguage($langcode)
      : NULL;
  
    $redirect_options = AnonymousSampleLanguageRedirect::options($target_lang);
  
    if ($user_type === 'company') {
  
      $form_state->setRedirect(
        'sentinel_portal_sample.anonymous_company_wizard',
        [
          'token' => $token,
        ],
        $redirect_options
      );
    }
    else {
  
      $form_state->setRedirect(
        'sentinel_portal_sample.anonymous_individual_contact',
        [
          'token' => $token,
        ],
        $redirect_options
      );
    }
  }
}
