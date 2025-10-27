<?php

namespace Drupal\securelogin\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\securelogin\SecureLoginManager;

/**
 * Implements hook_form_alter().
 */
#[Hook('form_alter')]
class FormAlter {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected SecureLoginManager $secureLoginManager,
  ) {
  }

  /**
   * Implements hook_form_alter().
   *
   * @param mixed[] $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $formId
   *   The form ID.
   */
  public function __invoke(array &$form, FormStateInterface $formState, string $formId): void {
    // Load secure login configuration.
    $config = $this->configFactory->get('securelogin.settings');
    $other_forms = $config->get('other_forms');
    $forms = $config->get('forms');
    // Changing the form id to the base form allows us to match all node forms
    // since the form id will be 'node_form'.
    if (isset($formState->getBuildInfo()['base_form_id'])) {
      $formId = $formState->getBuildInfo()['base_form_id'];
    }
    if ($config->get('all_forms')) {
      $form['#https'] = TRUE;
    }
    elseif (\is_array($forms) && \in_array($formId, $forms)) {
      $form['#https'] = TRUE;
    }
    elseif (\is_array($other_forms) && \in_array($formId, $other_forms)) {
      $form['#https'] = TRUE;
    }
    elseif (\is_array($forms) && \in_array('webform_client_form', $forms) && isset($form['#webform_id']) && \is_string($form['#webform_id']) && $formId === "webform_submission_{$form['#webform_id']}_form") {
      $form['#https'] = TRUE;
    }
    if (!empty($form['#https'])) {
      $this->secureLoginManager->secureForm($form);
    }
  }

}
