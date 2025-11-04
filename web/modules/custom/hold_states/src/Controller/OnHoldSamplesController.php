<?php

namespace Drupal\hold_states\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for on-hold samples listing.
 */
class OnHoldSamplesController extends ControllerBase {

  /**
   * Display list of on-hold samples.
   */
  public function listPage(Request $request) {
    // Return the form which includes filters, bulk operations, and table
    return \Drupal::formBuilder()->getForm('\Drupal\hold_states\Form\OnHoldSamplesForm');
  }

  /**
   * Build filter form.
   */
  protected function buildFilters($selected_tid, $pack_reference) {
    $form = \Drupal::formBuilder()->getForm('\Drupal\hold_states\Form\OnHoldSamplesFilterForm', $selected_tid, $pack_reference);
    return $form;
  }

  /**
   * Build samples table.
   */
  protected function buildSamplesTable($hold_state_tid, $pack_reference) {
    $database = \Drupal::database();

    // Base query
    $query = $database->select('sentinel_sample', 'ss')
      ->fields('ss', ['pid', 'pack_reference_number']);

    // Join with field_sample_hold_state if it exists
    if ($database->schema()->tableExists('sentinel_sample__field_sample_hold_state')) {
      $query->leftJoin('sentinel_sample__field_sample_hold_state', 'hs', 'ss.pid = hs.entity_id');
      $query->addField('hs', 'field_sample_hold_state_target_id', 'hold_state_tid');
      
      // Filter: only samples with hold state
      $query->isNotNull('hs.field_sample_hold_state_target_id');

      // Filter by specific hold state if selected
      if (!empty($hold_state_tid)) {
        $query->condition('hs.field_sample_hold_state_target_id', $hold_state_tid);
      }
    }
    else {
      // Fallback to old on_hold column if field doesn't exist
      $query->condition('ss.on_hold', 1);
      $query->addExpression('NULL', 'hold_state_tid');
    }

    // Filter by pack reference if provided
    if (!empty($pack_reference)) {
      $query->condition('ss.pack_reference_number', '%' . $database->escapeLike($pack_reference) . '%', 'LIKE');
    }

    // Add pager
    $pager = $query->extend(PagerSelectExtender::class)->limit(10);
    $result = $pager->execute();

    $rows = [];
    foreach ($result as $row) {
      // Get hold state term name if available
      $hold_state_name = '';
      if (!empty($row->hold_state_tid)) {
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($row->hold_state_tid);
        if ($term) {
          $hold_state_name = $term->getName();
        }
      }

      $pack_link = Link::fromTextAndUrl(
        $row->pack_reference_number,
        Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $row->pid])
      );

      $rows[] = [
        ['data' => ['#markup' => '<input type="checkbox" name="samples[]" value="' . $row->pid . '">']],
        ['data' => $pack_link->toRenderable()],
        $hold_state_name ?: 'On Hold',
      ];
    }

    $header = [
      ['data' => ['#markup' => '<input type="checkbox" id="select-all">']],
      $this->t('Pack reference number'),
      $this->t('Sample hold state'),
    ];

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No Samples on hold'),
      '#attributes' => ['class' => ['on-hold-samples-table']],
    ];
  }

}

