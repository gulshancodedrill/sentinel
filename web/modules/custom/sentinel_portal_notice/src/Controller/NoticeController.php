<?php

namespace Drupal\sentinel_portal_notice\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for notice pages.
 */
class NoticeController extends ControllerBase {

  /**
   * List all notices for current user.
   */
  public function listPage() {
    $current_user = \Drupal::currentUser();
    $user_id = $current_user->id();

    $output = [];

    // Query notices for current user
    $query = \Drupal::entityTypeManager()
      ->getStorage('sentinel_notice')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $user_id)
      ->sort('created', 'DESC')
      ->pager(25);

    $notice_ids = $query->execute();

    if (empty($notice_ids)) {
      $output[] = [
        '#markup' => '<div class="alert alert-warning">' . $this->t('No notices found') . '</div>',
      ];
    }
    else {
      $headers = [
        $this->t('Title'),
        $this->t('Created'),
        $this->t('Read'),
      ];

      $rows = [];
      $notices = \Drupal::entityTypeManager()
        ->getStorage('sentinel_notice')
        ->loadMultiple($notice_ids);

      foreach ($notices as $notice) {
        $notice_read = $notice->get('notice_read')->value;
        $created = $notice->get('created')->value;
        $created_formatted = date('d/m/Y H:i:s', $created);

        $class = $notice_read ? 'notice-read' : 'notice-unread';

        $title_link = Link::createFromRoute(
          $notice->get('title')->value,
          'sentinel_portal_notice.notice_view',
          ['sentinel_notice' => $notice->id()]
        );

        if ($notice_read) {
          $rows[] = [
            'data' => [
              $title_link->toRenderable(),
              $created_formatted,
              $this->t('Read'),
            ],
            'class' => [$class],
          ];
        }
        else {
          $rows[] = [
            'data' => [
              ['data' => ['#markup' => '<strong>' . $title_link->toString() . '</strong>']],
              ['data' => ['#markup' => '<strong>' . $created_formatted . '</strong>']],
              $this->t('Unread'),
            ],
            'class' => [$class],
          ];
        }
      }

      $output[] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['table-bordered', 'table-hover'],
        ],
      ];

      $output[] = [
        '#type' => 'pager',
      ];
    }

    return $output;
  }

  /**
   * View a single notice.
   */
  public function viewPage($sentinel_notice) {
    $current_user = \Drupal::currentUser();
    $user_id = $current_user->id();

    // Query to check if notice exists and belongs to current user
    $query = \Drupal::entityTypeManager()
      ->getStorage('sentinel_notice')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('nid', $sentinel_notice)
      ->condition('uid', $user_id);

    // If user has permission to view all notices, skip user check
    if ($current_user->hasPermission('sentinel view all sentinel_notice')) {
      $query = \Drupal::entityTypeManager()
        ->getStorage('sentinel_notice')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('nid', $sentinel_notice);
    }

    $notice_ids = $query->execute();

    if (empty($notice_ids)) {
      throw new NotFoundHttpException();
    }

    $notice = \Drupal::entityTypeManager()
      ->getStorage('sentinel_notice')
      ->load($sentinel_notice);

    if (!$notice) {
      throw new NotFoundHttpException();
    }

    // Mark as read if not already
    if (!$notice->get('notice_read')->value) {
      $notice->set('notice_read', TRUE);
      $notice->save();
    }

    $output = [];

    $output['title'] = [
      '#markup' => '<h2>' . $notice->get('title')->value . '</h2>',
    ];

    $output['notice'] = [
      '#markup' => '<div class="notice-content">' . nl2br($notice->get('notice')->value) . '</div>',
    ];

    $output['created'] = [
      '#markup' => '<p><small>' . $this->t('Created: @date', [
        '@date' => date('d/m/Y H:i:s', $notice->get('created')->value)
      ]) . '</small></p>',
    ];

    // Add delete link if user has permission
    if ($current_user->hasPermission('sentinel portal notice')) {
      $output['delete'] = [
        '#markup' => '<p>' . Link::createFromRoute(
          $this->t('Delete'),
          'sentinel_portal_notice.notice_delete',
          ['sentinel_notice' => $notice->id()],
          ['attributes' => ['class' => ['btn', 'btn-danger']]]
        )->toString() . '</p>',
      ];
    }

    $output['back'] = [
      '#markup' => '<p>' . Link::createFromRoute(
        $this->t('← Back to notices'),
        'sentinel_portal_notice.notices'
      )->toString() . '</p>',
    ];

    return $output;
  }

}





