<?php

namespace Drupal\sentinel_portal_queue\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for deleting queue items.
 */
class QueueDeleteForm extends ConfirmFormBase {

  /**
   * The queue item ID.
   *
   * @var int
   */
  protected $itemId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_queue_delete_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete queue item %queue_item?', ['%queue_item' => $this->itemId]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('sentinel_portal_queue.view_item', ['item_id' => $this->itemId]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone and will force the deletion of the item even if it is currently being processed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete item');
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
  public function buildForm(array $form, FormStateInterface $form_state, $item_id = NULL) {
    $this->itemId = $item_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $database = \Drupal::database();
    
    // Delete the queue item
    $database->delete('sentinel_portal_queue')
      ->condition('item_id', $this->itemId)
      ->execute();

    $this->messenger()->addStatus($this->t('Queue item @item_id has been deleted.', ['@item_id' => $this->itemId]));
    
    $form_state->setRedirectUrl(Url::fromRoute('sentinel_portal_queue.admin'));
  }

}

