<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Sentinel Notice entities.
 */
class SentinelNoticeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $current_user = \Drupal::currentUser();
    $query = $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->sort('created', 'DESC');

    // If not admin, only show notices for current user
    if (!$current_user->hasPermission('sentinel view all sentinel_notice')) {
      $query->condition('uid', $current_user->id());
    }
    // Admin sees all notices

    // Add pager with 25 items per page (matching D7)
    $query->pager(25);
    
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Title');
    $header['created'] = $this->t('Created');
    $header['read'] = $this->t('Read');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\sentinel_portal_entities\Entity\SentinelNotice $entity */
    $notice_read = $entity->get('notice_read')->value;
    $created = $entity->get('created')->value;
    $created_formatted = $created ? date('d/m/Y H:i:s', $created) : '';
    
    $class = $notice_read ? 'notice-read' : 'notice-unread';
    
    if ($notice_read) {
      $row['title']['data'] = [
        '#markup' => Link::createFromRoute($entity->label(), 'sentinel_portal_notice.notice_view', ['sentinel_notice' => $entity->id()])->toString(),
      ];
      $row['created']['data'] = [
        '#markup' => $created_formatted,
      ];
      $row['read']['data'] = [
        '#markup' => $this->t('Read'),
      ];
    } else {
      $row['title']['data'] = [
        '#markup' => '<strong>' . Link::createFromRoute($entity->label(), 'sentinel_portal_notice.notice_view', ['sentinel_notice' => $entity->id()])->toString() . '</strong>',
      ];
      $row['created']['data'] = [
        '#markup' => '<strong>' . $created_formatted . '</strong>',
      ];
      $row['read']['data'] = [
        '#markup' => $this->t('Unread'),
      ];
    }
    
    $row['#attributes']['class'][] = $class;
    
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    
    // Change the title
    $build['#title'] = $this->t('Sentinel Notices');
    
    // Add pager
    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 100,
    ];
    
    // Add custom CSS
    $build['#attached']['library'][] = 'sentinel_portal_notice/notice-styling';
    
    // Add table attributes for styling
    if (isset($build['table'])) {
      $build['table']['#attributes']['class'][] = 'table-bordered';
      $build['table']['#attributes']['class'][] = 'table-hover';
    }
    
    // Add message if no notices found
    $current_user_id = \Drupal::currentUser()->id();
    if (empty($build['table']['#rows'])) {
      $build['no_notices'] = [
        '#markup' => '<div class="alert alert-warning">' . $this->t('No notices found') . '</div>',
        '#weight' => -1,
      ];
    }
    
    return $build;
  }

}

