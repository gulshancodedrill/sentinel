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
      ->sort('nid', 'ASC');

    // If not admin, only show notices for current user
    if (!$current_user->hasPermission('administer sentinel_notice')) {
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
    $header['label'] = $this->t('Label');
    $header['operations'] = $this->t('Operations');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\sentinel_portal_entities\Entity\SentinelNotice $entity */
    $notice_read = $entity->get('notice_read')->value;
    
    $row['label']['data'] = [
      '#markup' => $notice_read ? 
        Link::createFromRoute($entity->label(), 'entity.sentinel_notice.canonical', ['sentinel_notice' => $entity->id()])->toString() : 
        '<strong>' . Link::createFromRoute($entity->label(), 'entity.sentinel_notice.canonical', ['sentinel_notice' => $entity->id()])->toString() . '</strong>',
    ];
    
    $row['operations']['data'] = $this->buildOperations($entity);
    
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
    $build['#attached']['library'][] = 'sentinel_portal_entities/notice-styling';
    
    // Add message if no notices found
    $current_user_id = \Drupal::currentUser()->id();
    if (empty($build['table']['#rows'])) {
      $build['no_notices'] = [
        '#markup' => '<div class="alert alert-warning">' . $this->t('No notices found for user ID: @uid', ['@uid' => $current_user_id]) . '</div>',
        '#weight' => -1,
      ];
    }
    
    return $build;
  }

}

