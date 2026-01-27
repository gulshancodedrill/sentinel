<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Anonymous sample submission form.
 */
class AnonymousSampleSubmissionForm extends FormBase {

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

    // PRN should be present (controller handles validation, but we need it for the form)
    $form['#title'] = $this->t('Submit Sample');

    $form['help_text'] = [
      '#markup' => '<p>' . $this->t('Please enter your details to submit your sample.') . '</p>',
      '#weight' => -10,
    ];

    // Pack Reference Number (pre-filled from query string)
    $form['pack_reference_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Packet Reference Number'),
      '#default_value' => $prn,
      '#required' => TRUE,
      '#disabled' => TRUE, // Always disabled since it comes from query string
      '#weight' => 0,
    ];

    // Name field
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#weight' => 10,
    ];

    // Email field
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#description' => $this->t('You can enter multiple emails separated by ";" or ",".'),
      '#required' => TRUE,
      '#weight' => 20,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = trim($form_state->getValue('email'));

    $filtered_emails = $this->filterEmails($email);
    if (empty($filtered_emails)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }
    else {
      $form_state->setValue('email', implode('; ', $filtered_emails));
    }

    // Note: PRN is always from query string and validated in buildForm()
    // Note: Duplicate check removed - we handle it in buildForm() with address validation
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pack_reference_number = trim($form_state->getValue('pack_reference_number'));
    $name = trim($form_state->getValue('name'));
    $email = trim($form_state->getValue('email'));
    $filtered_emails = $this->filterEmails($email);
    if (empty($filtered_emails)) {
      $this->messenger()->addError($this->t('Unable to process email address. Please try again or contact support.'));
      return;
    }
    $primary_email = $filtered_emails[0];

    try {
      // Get or create client via customer service logic
      $ucr = $this->getOrCreateClientUcr($name, $primary_email);

      if (!$ucr) {
        $this->messenger()->addError($this->t('Unable to create customer record. Please try again or contact support.'));
        return;
      }

      // Get client entity to retrieve client_id and client_name
      $client_storage = $this->entityTypeManager->getStorage('sentinel_client');
      $client_query = $client_storage->getQuery()
        ->condition('ucr', $ucr)
        ->accessCheck(FALSE)
        ->range(0, 1);
      $client_ids = $client_query->execute();
      
      $client_id = NULL;
      $client_name = NULL;
      if (!empty($client_ids)) {
        $client = $client_storage->load(reset($client_ids));
        if ($client) {
          $client_id = $client->id();
          if ($client->hasField('name') && !$client->get('name')->isEmpty()) {
            $client_name = $client->get('name')->value;
          }
        }
      }

      // Determine pack_type from pack_reference_number
      $pack_type = SentinelSample::getPackType([
        'pack_reference_number' => $pack_reference_number,
      ]);

      // Create sample entity
      $storage = $this->entityTypeManager->getStorage('sentinel_sample');
      $sample = $storage->create([
        'pack_reference_number' => $pack_reference_number,
        'ucr' => $ucr,
        'installer_email' => $primary_email,
        'installer_name' => $name,
      ]);

      // Set client_id, client_name, and pack_type if fields exist
      if ($client_id !== NULL && $sample->hasField('client_id')) {
        $sample->set('client_id', $client_id);
      }
      if ($client_name !== NULL && $sample->hasField('client_name')) {
        $sample->set('client_name', $client_name);
      }
      if ($sample->hasField('pack_type')) {
        $sample->set('pack_type', $pack_type);
      }

      if (!$sample->hasField('verification_code')) {
        $this->messenger()->addError($this->t('Unable to assign verification code. Please try again or contact support.'));
        \Drupal::logger('sentinel_portal_sample')->error('Anonymous sample missing verification_code field.');
        return;
      }

      $verification_code = $this->generateUniqueVerificationCode();
      if (!$verification_code) {
        $this->messenger()->addError($this->t('Unable to generate verification code. Please try again or contact support.'));
        return;
      }

      $sample->set('verification_code', $verification_code);

      $sample->save();

      $details_url = Url::fromRoute('sentinel_portal_sample.anonymous_details', [
        'sample_id' => $sample->id(),
      ], [
        'absolute' => TRUE,
      ])->toString();

      $mail_manager = \Drupal::service('plugin.manager.mail');
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $mail_params = [
        'verification_code' => $verification_code,
        'details_url' => $details_url,
        'pack_reference_number' => $pack_reference_number,
      ];
      foreach ($filtered_emails as $recipient) {
        $mail_result = $mail_manager->mail(
          'sentinel_portal_sample',
          'anonymous_submission',
          $recipient,
          $langcode,
          $mail_params
        );
        if (empty($mail_result['result'])) {
          \Drupal::logger('sentinel_portal_sample')->warning('Anonymous sample email failed to send for pack @pack, UCR @ucr to @email', [
            '@pack' => $pack_reference_number,
            '@ucr' => $ucr,
            '@email' => $recipient,
          ]);
        }
      }

      \Drupal::logger('sentinel_portal_sample')->info('Anonymous sample created: Pack @pack, UCR @ucr', [
        '@pack' => $pack_reference_number,
        '@ucr' => $ucr,
      ]);

      // Store sample ID in form state for redirect
      $form_state->set('sample_id', $sample->id());
      $form_state->set('pack_reference_number', $pack_reference_number);

      // Redirect to options page
      $form_state->setRedirect('sentinel_portal_sample.anonymous_options', [
        'sample_id' => $sample->id(),
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_portal_sample')->error('Error creating anonymous sample: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while submitting your sample. Please try again or contact support.'));
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
