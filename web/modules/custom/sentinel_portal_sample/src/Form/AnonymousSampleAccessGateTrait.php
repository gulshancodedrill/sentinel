<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;

/**
 * Shared verification / session gate for anonymous sample flows.
 */
trait AnonymousSampleAccessGateTrait {

  /**
   * Whether the user must enter a verification code for this sample.
   */
  protected function anonymousSampleRequiresVerification($token): bool {
    $session = $this->getRequest()->getSession();
    $whitelist_key = 'sentinel_sample_add_details_whitelist_' . $token;
    $whitelist_ts = $session->get($whitelist_key);

    if ($whitelist_ts !== NULL) {
      $age = \Drupal::time()->getRequestTime() - (int) $whitelist_ts;
      if ($age >= 0 && $age <= 1800) {
        $session->set('sentinel_sample_verified_' . $token, TRUE);
        return FALSE;
      }
      $session->remove($whitelist_key);
    }

    return !$session->has('sentinel_sample_verified_' . $token)
      || $session->get('sentinel_sample_verified_' . $token) !== TRUE;
  }

  /**
   * Builds the verification code subform; caller should return early if non-empty.
   */
  protected function buildAnonymousVerificationForm($token, array &$form, FormStateInterface $form_state): array {
    $form['#title'] = AnonymousSampleWizardProgress::trans('Enter Verification Code');
    $form['verification_code'] = [
      '#type' => 'textfield',
      '#title' => AnonymousSampleWizardProgress::trans('Verification code'),
      '#required' => TRUE,
      '#maxlength' => 7,
      '#description' => AnonymousSampleWizardProgress::trans('Enter the verification code sent to your email.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => AnonymousSampleWizardProgress::trans('Verify'),
      '#submit' => ['::submitAnonymousVerificationCode'],
    ];
    $form_state->set('gated_token', $token);
    return $form;
  }

  /**
   * Submit handler: validate verification code against the sample entity.
   */
  public function submitAnonymousVerificationCode(array &$form, FormStateInterface $form_state) {
    $token = $form_state->get('gated_token');
    if (!$token) {
      $this->messenger()->addError(AnonymousSampleWizardProgress::trans('Invalid sample.'));
      return;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    if (str_starts_with((string) $token, 'draft_')) {
      $draft_data = $this->getRequest()->getSession()->get('sentinel_draft_' . $token, []);
      $sample = $storage->create($draft_data);
    } else {
      $sample = $storage->load((int) $token);
    }
    
    if (!$sample) {
      $this->messenger()->addError(AnonymousSampleWizardProgress::trans('Sample not found.'));
      return;
    }

    $entered = trim((string) $form_state->getValue('verification_code'));
    $stored = '';
    if ($sample->hasField('verification_code') && !$sample->get('verification_code')->isEmpty()) {
      $stored = (string) $sample->get('verification_code')->value;
    }

    if ($stored === '' || $entered !== $stored) {
      $form_state->setErrorByName('verification_code', AnonymousSampleWizardProgress::trans('Verification code is incorrect.'));
      return;
    }

    $this->getRequest()->getSession()->set('sentinel_sample_verified_' . $token, TRUE);
    $form_state->setRebuild(TRUE);
  }

}
