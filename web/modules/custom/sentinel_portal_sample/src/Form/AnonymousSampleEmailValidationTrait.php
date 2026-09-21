<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Shared email validation for anonymous sample forms.
 */
trait AnonymousSampleEmailValidationTrait {

  /**
   * Validates an email form value (required or optional).
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param string $element_name
   *   Element name for setErrorByName (e.g. "email" or "company_details][company_email").
   * @param mixed $value
   *   Raw value.
   * @param bool $required
   *   Whether a non-empty value is required.
   * @param string $invalid_message
   *   Error when the value is non-empty but invalid.
   * @param string|null $required_message
   *   Error when required and empty; defaults to a generic required message.
   */
  protected function validateEmailFormValue(
    FormStateInterface $form_state,
    string $element_name,
    $value,
    bool $required = FALSE,
    string $invalid_message = 'Please enter a valid email address.',
    ?string $required_message = NULL,
  ): void {
    $email = trim((string) $value);
    if ($email === '') {
      if ($required) {
        $form_state->setErrorByName(
          $element_name,
          $required_message ?? 'Email is required.'
        );
      }
      return;
    }
    if (!\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName($element_name, $invalid_message);
    }
  }

}
