<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Drupal\sentinel_portal_sample\AnonymousSampleFlowTranslationTrait;
use Drupal\sentinel_portal_sample\AnonymousSampleLanguageRedirect;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;
use Drupal\sentinel_portal_entities\Utility\PackTypeFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Anonymous sample submission form.
 */
class AnonymousSampleSubmissionForm extends FormBase {

  use AnonymousSampleFlowTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AnonymousSampleSubmissionForm.
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
    return 'anonymous_sample_submission_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $prn = trim($request->query->get('prn', ''));

    $request->getSession()->set('sentinel_anonymous_entry', 'submit');

    // PRN should be present (controller handles validation, but we need it for the form)
    $form['#title'] = $this->tFlow('Submit Sample');

    $form['help_text'] = [
      '#markup' => '<p>' . $this->tFlow('Confirm your pack reference number, choose your language and account type, then continue.') . '</p>',
      '#weight' => -10,
    ];

    // Pack Reference Number (pre-filled from query string)
    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->tFlow('Packet Reference Number'),
      '#default_value' => $prn,
      '#required' => TRUE,
      '#disabled' => TRUE, // Always disabled since it comes from query string
      '#weight' => 0,
    ];

    $languages = \Drupal::languageManager()->getLanguages();
    $lang_options = [];
    foreach ($languages as $code => $language) {
      $lang_options[$code] = $language->getName();
    }
    if ($lang_options === []) {
      $lang_options = ['en' => 'English'];
    }
    $current_lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if (!isset($lang_options[$current_lang])) {
      $current_lang = array_key_first($lang_options);
    }

    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->tFlow('Language'),
      '#options' => $lang_options,
      '#default_value' => $current_lang,
      '#required' => TRUE,
      '#weight' => 5,
      '#ajax' => [
        'callback' => '::ajaxSubmissionLanguageChange',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['user_type'] = [
      '#type' => 'radios',
      '#title' => $this->tFlow('Please select your account type'),
      '#options' => [
        'company' => $this->tFlow('Company'),
        'individual' => $this->tFlow('Individual'),
      ],
      '#required' => TRUE,
      '#weight' => 10,
    ];

    // // Name field
    // $form['name'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Enter your Name:'),
    //   '#required' => TRUE,
    //   '#weight' => 10,
    // ];

    // // Email field
    // $form['email'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Enter your email address to receive the report:'),
    //   '#description' => $this->t('You can enter multiple emails separated by ";" or ",".'),
    //   '#required' => TRUE,
    //   '#weight' => 20,
    // ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];

    // $form['actions']['submit'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Submit'),
    // ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->tFlow('Next'),
    ];

    AnonymousSampleWizardProgress::prependToForm($form, $form_state, 'anonymous_sample_submission_form', NULL);

    return $form;
  }

  /**
   * Redirects to this form under the selected language prefix (keeps ?prn=).
   */
  public function ajaxSubmissionLanguageChange(array &$form, FormStateInterface $form_state) {
    $prn = trim((string) $this->getRequest()->query->get('prn', ''));
    $langcode = $form_state->getValue('language');
    $language = $langcode ? \Drupal::languageManager()->getLanguage($langcode) : NULL;
    if ($prn === '' || !$language) {
      return $form;
    }
    $url = Url::fromRoute('sentinel_portal_sample.anonymous_submit', [], [
      'language' => $language,
      'query' => ['prn' => $prn],
    ])->setAbsolute(TRUE);
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Email capture was moved to the individual contact step; no fields to validate here.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pack_reference_number = trim($form_state->getValue('pack_reference_number'));
    // $name = trim($form_state->getValue('name'));
    // $email = trim($form_state->getValue('email'));
    // $filtered_emails = $this->filterEmails($email);
    // if (empty($filtered_emails)) {
    //   $this->messenger()->addError($this->t('Unable to process email address. Please try again or contact support.'));
    //   return;
    // }
    // $primary_email = $filtered_emails[0];
    // $stored_email = implode(';', $filtered_emails);

    try {
      // Get or create client via customer service logic
      // $ucr = $this->getOrCreateClientUcr($name, $primary_email);

      // if (!$ucr) {
      //   $this->messenger()->addError($this->t('Unable to create customer record. Please try again or contact support.'));
      //   return;
      // }

      // Get client entity to retrieve client_id and client_name
      // $client_storage = $this->entityTypeManager->getStorage('sentinel_client');
      // $client_query = $client_storage->getQuery()
      //   ->condition('ucr', $ucr)
      //   ->accessCheck(FALSE)
      //   ->range(0, 1);
      // $client_ids = $client_query->execute();
      
      // $client_id = NULL;
      // $client_name = NULL;
      // if (!empty($client_ids)) {
      //   $client = $client_storage->load(reset($client_ids));
      //   if ($client) {
      //     $client_id = $client->id();
      //     if ($client->hasField('name') && !$client->get('name')->isEmpty()) {
      //       $client_name = $client->get('name')->value;
      //     }
      //   }
      // }

      // Determine pack_type from pack_reference_number (map to short DB value: SEN/VAL)
      $sample_type_key = SentinelSample::getPackType([
        'pack_reference_number' => $pack_reference_number,
      ]);

      // Create sample entity
      $storage = $this->entityTypeManager->getStorage('sentinel_sample');
      $sample = $storage->create([
        'pack_reference_number' => $pack_reference_number,
        // 'ucr' => $ucr,
        // 'installer_email' => $stored_email,
        // 'installer_name' => $name,
      ]);

      // Set client_id, client_name, and pack_type if fields exist
      // if ($client_id !== NULL && $sample->hasField('client_id')) {
      //   $sample->set('client_id', $client_id);
      // }
      // if ($client_name !== NULL && $sample->hasField('client_name')) {
      //   $sample->set('client_name', $client_name);
      // // }
      if ($sample->hasField('pack_type') && $sample_type_key !== NULL) {
        $sample->set('pack_type', PackTypeFilter::getPackTypeDbValue($sample_type_key));
      }

      // if (!$sample->hasField('verification_code')) {
      //   $this->messenger()->addError($this->t('Unable to assign verification code. Please try again or contact support.'));
      //   \Drupal::logger('sentinel_portal_sample')->error('Anonymous sample missing verification_code field.');
      //   return;
      // }

      // $verification_code = $this->generateUniqueVerificationCode();
      // if (!$verification_code) {
      //   $this->messenger()->addError($this->t('Unable to generate verification code. Please try again or contact support.'));
      //   return;
      // }

      // $sample->set('verification_code', $verification_code);

      // We no longer save the sample here. Instead, we use a session draft token.
      $token = 'draft_' . md5(uniqid('sentinel', true));
      
      $session = $this->getRequest()->getSession();
      
      // Store the initial values in the session under the token
      $draft_data = [
        'pack_reference_number' => $pack_reference_number,
        'user_type' => $form_state->getValue('user_type'),
        'language' => $form_state->getValue('language'),
      ];
      if ($sample_type_key !== NULL) {
        $draft_data['pack_type'] = PackTypeFilter::getPackTypeDbValue($sample_type_key);
      }
      $session->set('sentinel_draft_' . $token, $draft_data);

      $session->set(
        'sentinel_anonymous_last_flow',
        AnonymousSampleWizardProgress::normalizeUserTypeKey((string) $form_state->getValue('user_type'))
      );

      $langcode = $form_state->getValue('language');
      if (is_string($langcode) && $langcode !== '') {
        $session->set('sentinel_anonymous_language', $langcode);
      }

      // $details_url = Url::fromRoute('sentinel_portal_sample.anonymous_details', [
      //   'sample_id' => $sample->id(),
      // ], [
      //   'absolute' => TRUE,
      // ])->toString();

      // $mail_manager = \Drupal::service('plugin.manager.mail');
      // $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      // $mail_params = [
      //   'verification_code' => $verification_code,
      //   'details_url' => $details_url,
      //   'pack_reference_number' => $pack_reference_number,
      // ];
      // foreach ($filtered_emails as $recipient) {
      // $mail_result = $mail_manager->mail(
      //   'sentinel_portal_sample',
      //   'anonymous_submission',
      //     $recipient,
      //   $langcode,
      //   $mail_params
      // );
      // if (empty($mail_result['result'])) {
      //     \Drupal::logger('sentinel_portal_sample')->warning('Anonymous sample email failed to send for pack @pack, UCR @ucr to @email', [
      //     '@pack' => $pack_reference_number,
      //     '@ucr' => $ucr,
      //       '@email' => $recipient,
      //   ]);
      //   }
      // }

      // \Drupal::logger('sentinel_portal_sample')->info('Anonymous sample created: Pack @pack, UCR @ucr', [
      //   '@pack' => $pack_reference_number,
      //   '@ucr' => $ucr,
      // ]);

      \Drupal::logger('sentinel_portal_sample')->info(
        'Anonymous sample created: Pack @pack',
        [
          '@pack' => $pack_reference_number,
        ]
      );

      // Set session flag to allow same-session access to details form without verification code
      $session_key = 'sentinel_sample_add_details_whitelist_' . $token;
      $session->set($session_key, \Drupal::time()->getRequestTime());

      \Drupal::logger('sentinel_portal_sample')->info('Session flag set for draft sample @token to allow same-session bypass of verification code', [
        '@token' => $token,
      ]);

      // Store token in form state for redirect
      $form_state->set('sample_id', $token);
      $form_state->set('pack_reference_number', $pack_reference_number);

      $user_type = $form_state->getValue('user_type');
      $langcode = $form_state->getValue('language');
      $target_lang = $langcode ? \Drupal::languageManager()->getLanguage($langcode) : NULL;
      $redirect_options = AnonymousSampleLanguageRedirect::options($target_lang);
      if ($user_type === 'company') {
        $form_state->setRedirect('sentinel_portal_sample.anonymous_company_wizard', [
          'token' => $token,
        ], $redirect_options);
      }
      else {
        $form_state->setRedirect('sentinel_portal_sample.anonymous_individual_contact', [
          'token' => $token,
        ], $redirect_options);
      }

    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_portal_sample')->error('Error creating anonymous sample: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->tFlow('An error occurred while submitting your sample. Please try again or contact support.'));
    }
  }

  /**
   * Get or create client and return UCR.
   *
   * @param string $name
   *   The customer name.
   * @param string $email
   *   The customer email.
   *
   * @return string|false
   *   The UCR or FALSE on failure.
   */
  protected function getOrCreateClientUcr($name, $email) {
    $storage = $this->entityTypeManager->getStorage('sentinel_client');

    // Query for existing client by email
    $query = $storage->getQuery()
      ->condition('email', $email)
      ->accessCheck(FALSE)
      ->range(0, 1);

    $result = $query->execute();

    if (!empty($result)) {
      // Client exists, load it
      $client_ids = array_values($result);
      $clients = $storage->loadMultiple($client_ids);
      $client = reset($clients);

      // Update name if provided
      if (!empty($name)) {
        $client->set('name', $name);
        $client->save();
      }
    }
    else {
      // Client doesn't exist, create new one
      $client = $storage->create([
        'email' => $email,
        'name' => $name,
      ]);
      $client->save();
    }

    // Get UCR - use the real (non-luhn) UCR value
    // Ensure UCR exists (will create if needed)
    if (method_exists($client, 'ensureRealUcr')) {
      $ucr_value = $client->ensureRealUcr();
      return $ucr_value ? (string) $ucr_value : FALSE;
    }
    else {
      $ucr_value = $client->get('ucr')->value;
      return $ucr_value ? (string) $ucr_value : FALSE;
    }
  }

  /**
   * Split, validate, and filter emails using the stoplist.
   *
   * @param string $email_input
   *   Raw email input (may contain multiple addresses).
   *
   * @return string[]
   *   Valid, non-blocked email addresses (preserves order).
   */
  protected function filterEmails($email_input) {
    $email_input = trim((string) $email_input);
    if ($email_input === '') {
      return [];
    }

    $parts = preg_split('/[;,]+/', $email_input);
    $emails = [];
    foreach ($parts as $part) {
      $address = trim($part);
      if ($address === '') {
        continue;
      }
      if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
        $emails[] = $address;
      }
    }

    if (empty($emails)) {
      return [];
    }

    $stop_list = \Drupal::config('sentinel_portal.settings')->get('stop_emails') ?: '';
    $blocked = [];
    foreach (preg_split('/\r\n|\r|\n/', $stop_list) as $line) {
      $line = trim($line);
      if ($line !== '') {
        $blocked[] = strtolower($line);
      }
    }

    $filtered = [];
    foreach ($emails as $address) {
      $domain = strtolower(substr($address, strrpos($address, '@') + 1));
      if (!in_array($domain, $blocked, TRUE)) {
        $filtered[] = $address;
      }
    }

    return $filtered;
  }

  /**
   * Generate a unique verification code for sentinel_sample.
   *
   * @return string|false
   *   Unique verification code or FALSE on failure.
   */
  protected function generateUniqueVerificationCode() {
    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    $max_attempts = 25;

    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
      $code = $this->generateVerificationCode();
      if ($this->isVerificationCodeUnique($storage, $code)) {
        return $code;
      }
    }

    \Drupal::logger('sentinel_portal_sample')->error('Failed to generate unique verification code after @attempts attempts.', [
      '@attempts' => $max_attempts,
    ]);

    return FALSE;
  }

  /**
   * Generate a verification code with required character mix.
   *
   * @return string
   *   The generated code.
   */
  protected function generateVerificationCode() {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';
    $specials = '@#$%&';
    $all_chars = $alphabet . $digits . $specials;

    $length = random_int(6, 7);

    $chars = [
      $alphabet[random_int(0, strlen($alphabet) - 1)],
      $digits[random_int(0, strlen($digits) - 1)],
      $specials[random_int(0, strlen($specials) - 1)],
    ];

    $remaining = $length - count($chars);
    for ($i = 0; $i < $remaining; $i++) {
      $chars[] = $all_chars[random_int(0, strlen($all_chars) - 1)];
    }

    // Shuffle using random_int for better randomness.
    for ($i = count($chars) - 1; $i > 0; $i--) {
      $j = random_int(0, $i);
      $tmp = $chars[$i];
      $chars[$i] = $chars[$j];
      $chars[$j] = $tmp;
    }

    return implode('', $chars);
  }

  /**
   * Check whether a verification code is unique.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The sentinel_sample storage.
   * @param string $code
   *   The verification code to check.
   *
   * @return bool
   *   TRUE when unique, FALSE otherwise.
   */
  protected function isVerificationCodeUnique($storage, $code) {
    $existing = $storage->getQuery()
      ->condition('verification_code', $code)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    return empty($existing);
  }
}
