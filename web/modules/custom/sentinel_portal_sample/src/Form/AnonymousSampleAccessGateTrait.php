<?php

namespace Drupal\sentinel_portal_sample\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sentinel_portal_sample\AnonymousSampleWizardProgress;

/**
 * Shared verification / session gate and PRN-based sample loading for anonymous flows.
 */
trait AnonymousSampleAccessGateTrait {

  /**
   * Pack reference number from the current request query string.
   */
  protected function getAnonymousPrn(): string {
    return AnonymousSampleWizardProgress::normalizeAnonymousPrn(
      (string) $this->getRequest()->query->get('prn', '')
    );
  }

  /**
   * Loads the sample for the current ?prn= value.
   */
  protected function loadAnonymousSampleByPrn(string $prn): ?EntityInterface {
    return AnonymousSampleWizardProgress::loadSampleByPrn($prn);
  }

  /**
   * Whether the user must enter a verification code for this sample.
   */
  protected function anonymousSampleRequiresVerification(int $sample_id): bool {
    $session = $this->getRequest()->getSession();
    $whitelist_key = 'sentinel_sample_add_details_whitelist_' . $sample_id;
    $whitelist_ts = $session->get($whitelist_key);

    if ($whitelist_ts !== NULL) {
      $age = \Drupal::time()->getRequestTime() - (int) $whitelist_ts;
      if ($age >= 0 && $age <= 1800) {
        $session->set('sentinel_sample_verified_' . $sample_id, TRUE);
        return FALSE;
      }
      $session->remove($whitelist_key);
    }

    return !$session->has('sentinel_sample_verified_' . $sample_id)
      || $session->get('sentinel_sample_verified_' . $sample_id) !== TRUE;
  }

  /**
   * Whitelist same-session access after step 1 submit.
   */
  protected function whitelistAnonymousSampleSession(int $sample_id): void {
    $session = $this->getRequest()->getSession();
    $session->set(
      'sentinel_sample_add_details_whitelist_' . $sample_id,
      \Drupal::time()->getRequestTime()
    );
    $session->set('sentinel_sample_verified_' . $sample_id, TRUE);
  }

  /**
   * Builds the verification code subform; caller should return early if non-empty.
   */
  protected function buildAnonymousVerificationForm(int $sample_id, array &$form, FormStateInterface $form_state): array {
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
    $form_state->set('gated_sample_id', $sample_id);
    return $form;
  }

  /**
   * Submit handler: validate verification code against the sample entity.
   */
  public function submitAnonymousVerificationCode(array &$form, FormStateInterface $form_state) {
    $sample_id = (int) $form_state->get('gated_sample_id');
    if ($sample_id <= 0) {
      $this->messenger()->addError(AnonymousSampleWizardProgress::trans('Invalid sample.'));
      return;
    }

    $sample = $this->entityTypeManager->getStorage('sentinel_sample')->load($sample_id);
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

    $this->getRequest()->getSession()->set('sentinel_sample_verified_' . $sample_id, TRUE);
    $form_state->setRebuild(TRUE);
  }

}

