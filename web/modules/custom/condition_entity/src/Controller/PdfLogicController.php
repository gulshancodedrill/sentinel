<?php

namespace Drupal\condition_entity\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\condition_entity\ConditionEntityInstaller;
use Drupal\condition_entity\Form\PdfLogicFilterForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Link;

/**
 * Provides the PDF logic listing page.
 */
class PdfLogicController extends ControllerBase {

  /**
   * Displays the PDF logic listing.
   */
  public function page(): array {
    $build = [];

    // Ensure the condition entity type exists before attempting to query it.
    if (!$this->entityTypeManager()->hasDefinition('condition_entity')) {
      ConditionEntityInstaller::ensureConfiguration($this->entityTypeManager());
      if (!$this->entityTypeManager()->hasDefinition('condition_entity')) {
        $build['warning'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--error']],
          'content' => [
            '#markup' => $this->t('Condition entities are not available. Please ensure the Condition Entity module and its dependencies are enabled.'),
          ],
        ];
        return $build;
      }
    }

    $request = $this->getRequest();
    $event_number = $request->query->get('event_number');
    $event_element = $request->query->get('event_element');
    $event_string = $request->query->get('event_string');
    $event_string_and = $request->query->get('event_string_and');

    // Render the exposed filter form.
    $build['filter_form'] = $this->formBuilder()->getForm(PdfLogicFilterForm::class);

    $storage = $this->entityTypeManager()->getStorage('condition_entity');
    try {
      $query = $storage->getQuery()
        ->condition('type', 'condition_entity')
        ->accessCheck(TRUE)
        ->sort('field_condition_event_number', 'ASC')
        ->pager(20);

      if ($event_number !== NULL && $event_number !== '') {
        $query->condition('field_condition_event_number', (int) $event_number);
      }
      if ($event_element !== NULL && $event_element !== '') {
        $query->condition('field_condition_event_element', trim($event_element), 'CONTAINS');
      }
      if ($event_string !== NULL && $event_string !== '') {
        $query->condition('field_condition_event_string', trim($event_string), 'CONTAINS');
      }
      if ($event_string_and !== NULL && $event_string_and !== '') {
        $query->condition('field_condition_event_string', trim($event_string_and), 'CONTAINS');
      }

      $ids = $query->execute();
    }
    catch (QueryException $exception) {
      $build['warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--error']],
        'content' => [
          '#markup' => $this->t('Condition entity fields are not available. Please run the Condition Entity migrations or recreate the fields before using this page.'),
        ],
      ];
      $ids = [];
    }

    $header = [
      $this->t('Event Number (Taken from the matrix spreadsheet)'),
      $this->t('Event element'),
      $this->t('Event String'),
      $this->t('Condition Event Result'),
      $this->t('View Link'),
      $this->t('Edit link'),
    ];

    $rows = [];

    if (!empty($ids)) {
      $entities = $storage->loadMultiple($ids);
      $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');

      foreach ($entities as $entity) {
        $number = $entity->get('field_condition_event_number')->value;
        $element = $entity->get('field_condition_event_element')->value;
        $event_string = $entity->get('field_condition_event_string')->value;
        $event_result = '';

        if ($entity->get('field_condition_event_result')->entity) {
          $event_result = $entity->get('field_condition_event_result')->entity->label();
        }
        elseif (!$entity->get('field_condition_event_result')->isEmpty()) {
          $target_id = $entity->get('field_condition_event_result')->target_id;
          if ($target_id) {
            $term = $term_storage->load($target_id);
            $event_result = $term ? $term->label() : '';
          }
        }

        $rows[] = [
          $number,
          $element,
          $event_string ? Unicode::truncate($event_string, 30, TRUE, TRUE) : '',
          $event_result,
          Link::fromTextAndUrl($this->t('View'), $entity->toUrl())->toString(),
          Link::fromTextAndUrl($this->t('Edit'), $entity->toUrl('edit-form'))->toString(),
        ];
      }
    }

    $build['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No condition events found.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    // Ensure the listing is never cached so filters/pagers stay in sync.
    $build['#cache'] = ['max-age' => 0];

    return $build;
  }

  /**
   * Helper to get current request.
   */
  protected function getRequest() {
    return $this->getRequestStack()->getCurrentRequest();
  }

  /**
   * Shortcut for request stack service.
   */
  protected function getRequestStack() {
    return \Drupal::service('request_stack');
  }

}


