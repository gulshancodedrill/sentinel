<?php

namespace Drupal\sentinel_portal_entities\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for revision overview page.
 */
class SentinelSampleRevisionOverviewForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a SentinelSampleRevisionOverviewForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_sample_revision_overview_form';
  }

  /**
   * Title callback for the revision overview page.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $sentinel_sample
   *   The sample entity.
   *
   * @return string
   *   The page title.
   */
  public function title(SentinelSample $sentinel_sample) {
    $title = '';
    if ($sentinel_sample->hasField('pack_reference_number') && !$sentinel_sample->get('pack_reference_number')->isEmpty()) {
      $title = $sentinel_sample->get('pack_reference_number')->value;
      // Remove "-FINAL" suffix if present.
      $title = preg_replace('/-FINAL$/i', '', $title);
    }
    if (empty($title)) {
      $title = $sentinel_sample->label();
      // Remove "-FINAL" suffix if present.
      $title = preg_replace('/-FINAL$/i', '', $title);
    }
    if (empty($title)) {
      $title = $this->t('Sample @id', ['@id' => $sentinel_sample->id()]);
    }
    return $this->t('Revisions for <em class="placeholder">@title</em>', ['@title' => $title]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SentinelSample $sentinel_sample = NULL) {
    if (!$sentinel_sample) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_sample');
    
    // Get all revisions with pagination.
    $query = $storage->getQuery()
      ->condition('pid', $sentinel_sample->id())
      ->allRevisions()
      ->sort('vid', 'DESC')
      ->pager(50)
      ->accessCheck(FALSE)
      ->execute();
    
    $vids = array_keys($query);
    $revision_count = count($vids);

    // Build title - use pack_reference_number if available.
    $title = '';
    if ($sentinel_sample->hasField('pack_reference_number') && !$sentinel_sample->get('pack_reference_number')->isEmpty()) {
      $title = $sentinel_sample->get('pack_reference_number')->value;
      // Remove "-FINAL" suffix if present.
      $title = preg_replace('/-FINAL$/i', '', $title);
    }
    if (empty($title)) {
      $title = $sentinel_sample->label();
      // Remove "-FINAL" suffix if present.
      $title = preg_replace('/-FINAL$/i', '', $title);
    }
    if (empty($title)) {
      $title = $this->t('Sample @id', ['@id' => $sentinel_sample->id()]);
    }
    $form['#title'] = $this->t('Revisions for @title', ['@title' => $title]);

    // Hidden field for sample ID.
    $form['sample_id'] = [
      '#type' => 'hidden',
      '#value' => $sentinel_sample->id(),
    ];


    // Build the table header - match Drupal 7 structure exactly.
    $table_header = [
      'revision' => $this->t('Revision'),
    ];
    
    // Add Compare button to header if we have multiple revisions.
    if ($revision_count > 1) {
      // Add the button as a header cell with colspan.
      $table_header['compare'] = [
        'data' => [
          '#type' => 'submit',
          '#value' => $this->t('Compare'),
          '#attributes' => [
            'class' => ['btn', 'form-submit'],
            'id' => 'edit-submit',
            'name' => 'op',
          ],
          '#button_type' => 'primary',
        ],
        'colspan' => 2,
      ];
    }
    
    // Build the table with exact Drupal 7 structure.
    $form['revisions_table'] = [
      '#type' => 'table',
      '#header' => $table_header,
      '#attributes' => [
        'class' => ['diff-revisions', 'table', 'table-striped'],
      ],
    ];
    
    // Also add the submit button as a regular form element for proper processing.
    // This ensures the form submission works even if the header button doesn't.
    if ($revision_count > 1) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Compare'),
        '#attributes' => [
          'style' => 'display: none;',
        ],
        '#name' => 'op',
      ];
      
      // Add JavaScript to handle button click in header.
      $form['#attached']['library'][] = 'sentinel_portal_entities/revision_form';
    }

    $default_revision = $sentinel_sample->getRevisionId();
    
    // Add rows to the table.
    foreach ($vids as $key => $vid) {
      /** @var \Drupal\sentinel_portal_entities\Entity\SentinelSample $revision */
      $revision = $storage->loadRevision($vid);
      if (!$revision) {
        continue;
      }

      // Get revision date from 'changed' field - format as YYYY-MM-DD HH:MM:SS like Drupal 7.
      $revision_date = '';
      if ($revision->hasField('changed') && !$revision->get('changed')->isEmpty()) {
        $changed_value = $revision->get('changed')->value;
        if ($changed_value) {
          try {
            $timestamp = strtotime($changed_value);
            if ($timestamp) {
              $revision_date = date('Y-m-d H:i:s', $timestamp);
            }
            else {
              $revision_date = $changed_value;
            }
          }
          catch (\Exception $e) {
            $revision_date = $changed_value;
          }
        }
      }
      
      // If no date, use created field.
      if (empty($revision_date) && $revision->hasField('created') && !$revision->get('created')->isEmpty()) {
        $created_value = $revision->get('created')->value;
        if ($created_value) {
          try {
            $timestamp = strtotime($created_value);
            if ($timestamp) {
              $revision_date = date('Y-m-d H:i:s', $timestamp);
            }
            else {
              $revision_date = $created_value;
            }
          }
          catch (\Exception $e) {
            $revision_date = $created_value;
          }
        }
      }

      // Default to vid if no date available.
      if (empty($revision_date)) {
        $revision_date = $this->t('Revision @vid', ['@vid' => $vid]);
      }

      // Revision information cell.
      $form['revisions_table'][$vid]['revision'] = [
        '#markup' => $revision_date,
        '#wrapper_attributes' => ['class' => ['diff-revision']],
      ];

      // Radio buttons for comparison (only if more than 1 revision).
      if ($revision_count > 1) {
        $is_current = ($vid == $default_revision);
        $is_previous = ($key === 1);
        
        // Old (source) radio button column - checked for second revision.
        $old_checked = $is_previous ? $vid : FALSE;
        $form['revisions_table'][$vid]['old'] = [
          '#type' => 'radio',
          '#title_display' => 'invisible',
          '#name' => 'old',
          '#return_value' => $vid,
          '#default_value' => $old_checked,
          '#attributes' => [
            'id' => 'edit-old-' . $vid,
            'class' => ['form-radio'],
            'style' => $is_current ? 'visibility: hidden;' : 'visibility: visible;',
          ],
          '#wrapper_attributes' => ['class' => ['diff-revision']],
          '#prefix' => '<div class="form-item form-type-radio form-item-old">',
          '#suffix' => '</div>',
        ];
        
        // New (target) radio button column - checked for current revision.
        $new_checked = $is_current ? $vid : FALSE;
        $form['revisions_table'][$vid]['new'] = [
          '#type' => 'radio',
          '#title_display' => 'invisible',
          '#name' => 'new',
          '#return_value' => $vid,
          '#default_value' => $new_checked,
          '#attributes' => [
            'id' => 'edit-new-' . $vid,
            'class' => ['form-radio'],
            'style' => $is_current ? 'visibility: visible;' : ($is_previous ? 'visibility: hidden;' : 'visibility: visible;'),
          ],
          '#wrapper_attributes' => ['class' => ['diff-revision']],
          '#prefix' => '<div class="form-item form-type-radio form-item-new">',
          '#suffix' => '</div>',
        ];
      }

      // Row attributes - match Drupal 7 structure.
      $row_classes = ['diff-revision'];
      $row_classes[] = ($key % 2 == 0) ? 'odd' : 'even';
      
      // Mark selected rows (current and second revision).
      if ($vid == $default_revision) {
        $row_classes[] = 'selected';
      }
      elseif ($key === 1) {
        $row_classes[] = 'selected';
      }
      
      $form['revisions_table'][$vid]['#attributes']['class'] = $row_classes;
    }


    // Pager.
    $form['pager'] = ['#type' => 'pager'];

    // Back to Sample view button at the end.
    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('← Back to Sample view'),
      '#url' => Url::fromRoute('entity.sentinel_sample.canonical', [
        'sentinel_sample' => $sentinel_sample->id(),
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    if (!isset($input['old']) || !isset($input['new'])) {
      $form_state->setError($form['revisions_table'], $this->t('Select two revisions to compare.'));
    }
    elseif ($input['old'] == $input['new']) {
      $form_state->setError($form['revisions_table'], $this->t('Select different revisions to compare.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $old_vid = $input['old'] ?? NULL;
    $new_vid = $input['new'] ?? NULL;
    $sample_id = $form_state->getValue('sample_id');

    if (!$old_vid || !$new_vid) {
      $this->messenger()->addError($this->t('Please select two revisions to compare.'));
      return;
    }

    if ($old_vid == $new_vid) {
      $this->messenger()->addError($this->t('Please select two different revisions to compare.'));
      return;
    }

    // Redirect to comparison page.
    // old = source, new = target.
    try {
      $url = \Drupal\Core\Url::fromRoute('entity.sentinel_sample.revision_compare', [
        'sentinel_sample' => $sample_id,
        'source_revision' => $old_vid,
        'target_revision' => $new_vid,
      ]);
      $form_state->setRedirectUrl($url);
    }
    catch (\Exception $e) {
      \Drupal::logger('sentinel_portal_entities')->error('Error redirecting: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Error redirecting to comparison page.'));
    }
  }

}

