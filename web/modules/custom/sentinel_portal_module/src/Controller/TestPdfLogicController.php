<?php

namespace Drupal\sentinel_portal_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Controller for Test PDF logic page.
 */
class TestPdfLogicController extends ControllerBase {

  /**
   * Displays the Test PDF logic page.
   *
   * @return array
   *   A render array.
   */
  public function page() {
    $build = [];
    
    $build['header'] = [
      '#markup' => '<div class="test-pdf-header">' .
        Link::fromTextAndUrl($this->t('Add test'), Url::fromRoute('entity.sentinel_sample.add_form'))->toString() . ' | ' .
        Link::fromTextAndUrl($this->t('Import tests'), Url::fromRoute('<front>'))->toString() .
        '</div>',
      '#weight' => -10,
    ];
    
    // Query sentinel_sample entities (50 per page like D7)
    $entity_type_manager = \Drupal::entityTypeManager();
    $current_user = \Drupal::currentUser();
    
    $query = $entity_type_manager->getStorage('sentinel_sample')->getQuery()
      ->accessCheck(FALSE)
      ->sort('pid', 'ASC');
    
    // Admins see all records, others see their own (if applicable)
    // For now, show all records to admins
    if (!$current_user->hasPermission('administer sentinel_sample')) {
      // Regular users might be filtered in the future
    }
    
    $query->pager(50);
    $entity_ids = $query->execute();
    
    // Build table
    $header = [
      $this->t('Test Number'),
      $this->t('Pack Reference Number'),
      $this->t('View Link'),
      $this->t('Edit link'),
      $this->t('Delete link'),
      $this->t('PDF link'),
    ];
    
    $rows = [];
    
    if (!empty($entity_ids)) {
      $entities = $entity_type_manager->getStorage('sentinel_sample')->loadMultiple($entity_ids);
      
      foreach ($entities as $entity) {
        $rows[] = [
          $entity->id(),
          $entity->get('pack_reference_number')->value,
          Link::fromTextAndUrl($this->t('View'), $entity->toUrl())->toString(),
          Link::fromTextAndUrl($this->t('Edit'), $entity->toUrl('edit-form'))->toString(),
          Link::fromTextAndUrl($this->t('Delete'), $entity->toUrl('delete-form'))->toString(),
          '<a href="/test_entity/condition_entity/' . $entity->id() . '/pdf">View PDF</a>',
        ];
      }
    }
    
    $build['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No test entities available.'),
      '#attributes' => [
        'class' => ['test-pdf-logic-table'],
      ],
    ];
    
    // Add pager
    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 100,
    ];
    
    return $build;
  }

}

