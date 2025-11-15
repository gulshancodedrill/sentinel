<?php

namespace Drupal\sentinel_portal_notice\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for deleting a notice.
 */
class NoticeDeleteForm extends ConfirmFormBase {

  protected $noticeId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_notice_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sentinel_notice = NULL) {
    $this->noticeId = $sentinel_notice;

    // Verify the notice exists and belongs to current user
    $current_user = \Drupal::currentUser();
    $notice = \Drupal::entityTypeManager()
      ->getStorage('sentinel_notice')
      ->load($sentinel_notice);

    if (!$notice) {
      \Drupal::messenger()->addError($this->t('Notice not found.'));
      return $this->redirect('sentinel_portal_notice.notices');
    }

    // Check if user owns this notice
    if ($notice->get('uid')->target_id != $current_user->id() && !$current_user->hasPermission('sentinel view all sentinel_notice')) {
      \Drupal::messenger()->addError($this->t('You do not have permission to delete this notice.'));
      return $this->redirect('sentinel_portal_notice.notices');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this item?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('sentinel_portal_notice.notices');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $notice = \Drupal::entityTypeManager()
      ->getStorage('sentinel_notice')
      ->load($this->noticeId);

    if ($notice) {
      $notice->delete();
      \Drupal::messenger()->addMessage($this->t('Notice deleted.'));
    }

    $form_state->setRedirect('sentinel_portal_notice.notices');
  }

}














