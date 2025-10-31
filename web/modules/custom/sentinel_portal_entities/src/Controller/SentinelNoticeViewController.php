<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sentinel_portal_entities\Entity\SentinelNotice;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for viewing Sentinel Notice entities.
 */
class SentinelNoticeViewController extends ControllerBase {

  /**
   * Displays a single notice.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelNotice $sentinel_notice
   *   The notice entity.
   *
   * @return array
   *   A render array.
   */
  public function view(SentinelNotice $sentinel_notice) {
    $current_user = \Drupal::currentUser();
    
    // Check access - users can only view their own notices unless admin
    if (!$current_user->hasPermission('administer sentinel_notice')) {
      if ($sentinel_notice->get('uid')->target_id != $current_user->id()) {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
      }
    }
    
    // Mark notice as read
    if (!$sentinel_notice->get('notice_read')->value) {
      $sentinel_notice->set('notice_read', TRUE);
      $sentinel_notice->save();
    }
    
    $created_timestamp = $sentinel_notice->get('created')->value;
    $created_date = $created_timestamp ? \Drupal::service('date.formatter')->format((int) $created_timestamp, 'custom', 'Y-m-d H:i:s') : '';
    
    $user_id = $sentinel_notice->get('uid')->target_id;
    $user = \Drupal\user\Entity\User::load($user_id);
    $user_link = $user ? \Drupal\Core\Link::fromTextAndUrl($user_id, \Drupal\Core\Url::fromRoute('entity.user.canonical', ['user' => $user_id]))->toString() : $user_id;
    
    // Build as a table for better formatting
    $rows = [
      [
        ['data' => $this->t('Notice ID'), 'header' => TRUE],
        $sentinel_notice->id(),
      ],
      [
        ['data' => $this->t('User ID'), 'header' => TRUE],
        ['data' => ['#markup' => $user_link]],
      ],
      [
        ['data' => $this->t('Title'), 'header' => TRUE],
        $sentinel_notice->label(),
      ],
      [
        ['data' => $this->t('Notice'), 'header' => TRUE],
        ['data' => ['#markup' => $sentinel_notice->get('notice')->value]],
      ],
      [
        ['data' => $this->t('Read'), 'header' => TRUE],
        $sentinel_notice->get('notice_read')->value ? $this->t('Yes') : $this->t('No'),
      ],
      [
        ['data' => $this->t('Created'), 'header' => TRUE],
        $created_date,
      ],
      [
        ['data' => $this->t('Operations'), 'header' => TRUE],
        ['data' => ['#markup' => \Drupal\Core\Link::fromTextAndUrl($this->t('Delete'), $sentinel_notice->toUrl('delete-form'))->toString()]],
      ],
    ];
    
    $build = [
      '#theme' => 'table',
      '#header' => [],
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['notice-details-table'],
      ],
    ];
    
    return $build;
  }

}

