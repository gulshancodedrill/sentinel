<?php

namespace Drupal\sentinel_portal_queue\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for queue administration pages.
 */
class QueueAdminController extends ControllerBase {

  /**
   * Admin page callback.
   *
   * @return array
   *   The contents of the page.
   */
  public function adminPage() {
    $output = [];

    $output[] = [
      '#markup' => '<p><em>' . $this->t('This queue will be processed by the system during normal operations, there is no way to force this process.') . '</em></p>',
    ];

    $form = \Drupal::formBuilder()->getForm('\Drupal\sentinel_portal_queue\Form\QueueAdminForm');

    $output[] = [
      'queue_admin_form' => $form,
    ];

    return $output;
  }

  /**
   * View queue item callback.
   *
   * @param int $item_id
   *   The queue item ID.
   *
   * @return array
   *   The queue item details.
   */
  public function viewItem($item_id) {
    $database = \Drupal::database();
    
    $query = $database->select('sentinel_portal_queue', 'q')
      ->fields('q')
      ->condition('item_id', $item_id)
      ->execute();
    
    $item = $query->fetchAssoc();

    if (!$item) {
      $this->messenger()->addError($this->t('Queue item not found.'));
      return $this->redirect('sentinel_portal_queue.admin');
    }

    $output = [];

    $output[] = [
      '#markup' => '<h2>' . $this->t('Queue Item Details') . '</h2>',
    ];

    $output[] = [
      '#type' => 'table',
      '#header' => [$this->t('Field'), $this->t('Value')],
      '#rows' => [
        [$this->t('Item ID'), $item['item_id']],
        [$this->t('Queue Name'), $item['name']],
        [$this->t('Sample ID'), $item['pid']],
        [$this->t('Action'), $item['action']],
        [$this->t('Expires'), date('Y-m-d H:i:s', $item['expire'])],
        [$this->t('Created'), date('Y-m-d H:i:s', $item['created'])],
        [$this->t('Failed'), $item['failed']],
      ],
    ];

    // Add action links
    $links = [];
    $links[] = Link::createFromRoute($this->t('Release Item'), 'sentinel_portal_queue.release_item', ['item_id' => $item_id]);
    $links[] = Link::createFromRoute($this->t('Delete Item'), 'sentinel_portal_queue.delete_item', ['item_id' => $item_id]);
    $links[] = Link::createFromRoute($this->t('Back to Queue'), 'sentinel_portal_queue.admin');

    $output[] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Actions'),
      '#items' => $links,
    ];

    return $output;
  }

}

