<?php

namespace Drupal\sentinel_portal_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
    $header['title'] = $this->t('Label');
    $header['operations'] = $this->t('Operations');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\sentinel_portal_entities\Entity\SentinelNotice $entity */
    $notice_read = $entity->get('notice_read')->value;
    
    $class = $notice_read ? 'notice-read' : 'notice-unread';
    
    $title_markup = Link::createFromRoute($entity->label(), 'sentinel_portal_notice.notice_view', ['sentinel_notice' => $entity->id()])->toString();
    if (!$notice_read) {
      $title_markup = '<strong>' . $title_markup . '</strong>';
    }
    $row['title'] = [
      'data' => [
        '#markup' => $title_markup,
      ],
      'class' => [$class],
    ];

    // Build delete link only
    $row['operations']['data'] = $this->buildDeleteLink($entity);

    return $row;
  }

  /**
   * Build a simple delete link instead of operations dropdown.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   A render array for the delete link.
   */
  protected function buildDeleteLink(EntityInterface $entity) {
    if (!$entity->access('delete') || !$entity->hasLinkTemplate('delete-form')) {
      return ['#markup' => ''];
    }

    $delete_url = $entity->toUrl('delete-form');
    $delete_url->setOption('query', \Drupal::destination()->getAsArray());

    return [
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#url' => $delete_url,
      '#attributes' => [
        'class' => ['button', 'button--small'],
      ],
    ];
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

