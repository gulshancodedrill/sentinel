<?php

namespace Drupal\securelogin\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Implements a SecureLogin Config form.
 */
class SecureLoginConfigForm extends ConfigFormBase {

  /**
   * Creates a new SecureLoginConfigForm object.
   */
  final public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected EventDispatcherInterface $eventDispatcher,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
      $container->get('cache_tags.invalidator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'securelogin_config_form';
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   The editable config names.
   */
  protected function getEditableConfigNames() {
    return ['securelogin.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed[]
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    global $base_secure_url;
    $states = ['invisible' => [':input[name="all_forms"]' => ['checked' => TRUE]]];

    $form['base_url'] = [
      '#type'          => 'url',
      '#title'         => $this->t('Secure base URL'),
      '#config_target' => 'securelogin.settings:base_url',
      '#description'   => $this->t('The base URL for secure pages. Leave blank to allow Drupal to determine it automatically (recommended). It is not allowed to have a trailing slash; Drupal will add it for you. For example: %base_secure_url%.', ['%base_secure_url%' => $base_secure_url]),
    ];
    $form['secure_forms'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Redirect form pages to secure URL'),
      '#config_target' => 'securelogin.settings:secure_forms',
      '#description'   => $this->t('If enabled, any pages containing the forms enabled below will be redirected to the secure URL. Users can be assured that they are entering their private data on a secure URL, the contents of which have not been tampered with.'),
    ];
    $form['all_forms'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Submit all forms to secure URL'),
      '#config_target' => 'securelogin.settings:all_forms',
      '#description'   => $this->t('If enabled, all forms will be submitted to the secure URL.'),
    ];
    $forms = [];
    $options = [];
    $this->moduleHandler->alter('securelogin', $forms);
    if (\is_array($forms)) {
      foreach ($forms as $id => $item) {
        if (\is_array($item)) {
          $options[$id] = $item['#title'];
        }
      }
    }
    $form['forms'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Forms'),
      '#description'   => $this->t('If checked, these forms will be submitted to the secure URL. Note, the user forms must be checked to enforce secure authenticated sessions.'),
      '#states'        => $states,
      '#options'       => $options,
      '#config_target' => new ConfigTarget(
        'securelogin.settings',
        'forms',
        NULL,
        fn($value) => array_values(array_filter($value)),
      ),
    ];
    $form['other_forms'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Other forms to secure'),
      '#config_target' => new ConfigTarget(
        'securelogin.settings',
        'other_forms',
        fn($value) => implode(' ', $value),
        [static::class, 'stringToList'],
      ),
      '#description'   => $this->t('List the form IDs of any other forms that you want secured, separated by a space. If the form has a base form ID, you must list the base form ID rather than the form ID.'),
      '#states'        => $states,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $listener = function () {
      $this->cacheTagsInvalidator->invalidateTags(['rendered', 'http_response']);
    };
    $this->eventDispatcher->addListener(KernelEvents::TERMINATE, $listener, 400);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed[] $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $base_url = $form_state->getValue('base_url');
    if ($base_url === '') {
      $form_state->setValue('base_url', static::stringToNullable($base_url));
    }
    elseif (\is_string($base_url)) {
      $scheme = parse_url($base_url, PHP_URL_SCHEME);
      if (!\is_string($scheme) || strtolower($scheme) !== 'https') {
        $form_state->setErrorByName('base_url', $this->t('The secure base URL must start with <em>https://</em>.'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * Converts space-delimited string to array.
   *
   * @return string[]
   *   Configuration as an array of strings.
   */
  public static function stringToList(string $string): array {
    return array_values(array_filter(explode(' ', $string)));
  }

  /**
   * Converts empty string to null.
   */
  public static function stringToNullable(?string $string): ?string {
    return $string === '' ? NULL : $string;
  }

  /**
   * Migrates form_* configs to a forms sequence.
   *
   * @return string[]
   *   The forms config as a list of form IDs.
   */
  public static function migrateForms(?string ...$forms): array {
    $form_ids = [
      'comment_form',
      'contact_message_form',
      'node_form',
      'user_form',
      'user_login_form',
      'user_pass',
      'user_pass_reset',
      'user_register_form',
      'webform_client_form',
    ];
    $config = [];
    foreach ($forms as $key => $value) {
      if ($value) {
        $config[] = $form_ids[$key];
      }
    }
    return $config;
  }

}
