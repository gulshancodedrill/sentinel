<?php

namespace Drupal\sentinel_addresses\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Sentinel Addresses module.
 */
class AddressController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, CountryManagerInterface $country_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('country_manager')
    );
  }

  /**
   * Autocomplete callback for sample addresses.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with matching addresses.
   */
  public function autocomplete(Request $request) {
    $string = $request->query->get('q', '');
    
    $addresses = get_sentinel_sample_addresses_for_cids($string);
    $addresses = array_slice($addresses, 0, 10);

    $suggestions = [];

    if ($addresses) {
      foreach ($addresses as $row) {
        $address_parts = [];

        if (!empty($row->field_address_address_line1)) {
          $address_parts[] = $row->field_address_address_line1;
        }
        if (!empty($row->field_address_address_line2)) {
          $address_parts[] = $row->field_address_address_line2;
        }
        if (!empty($row->field_address_locality)) {
          $address_parts[] = $row->field_address_locality;
        }
        if (!empty($row->field_address_postal_code)) {
          $address_parts[] = $row->field_address_postal_code;
        }
        if (!empty($row->field_address_country_code)) {
          $address_parts[] = $row->field_address_country_code;
        }

        $address_string = implode(', ', array_filter($address_parts));
        $value = '(' . $row->entity_id . ') ' . $address_string;

        $suggestions[] = [
          'value' => $value,
          'label' => Html::escape($address_string),
        ];
      }
    }

    return new JsonResponse($suggestions);
  }

  /**
   * Address canonical view (/address/address/{address}).
   */
  public function view($address) {
   // dd($address);
    $address_entity = $this->entityTypeManager->getStorage('address')->load($address);
    if (!$address_entity) {
      throw new NotFoundHttpException();
    }

    $build = [];

    // Actions similar to Drupal 7.
    $actions = [];
    $actions[] = Link::fromTextAndUrl($this->t('View'), Url::fromRoute('sentinel_addresses.address_view', ['address' => $address]))->toRenderable();
    if ($address_entity->access('update')) {
      $actions[] = Link::fromTextAndUrl($this->t('Edit'), Url::fromRoute('entity.address.edit_form', ['address' => $address]))->toRenderable();
    }
    if ($address_entity->access('delete')) {
      $actions[] = Link::fromTextAndUrl($this->t('Delete'), Url::fromRoute('entity.address.delete_form', ['address' => $address]))->toRenderable();
    }

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentinel-address-actions']],
    ];
    foreach ($actions as $key => $action) {
      $build['actions'][$key] = $action;
    }

    // Address summary.
    $lines = [];
    if ($address_entity->hasField('field_address') && !$address_entity->get('field_address')->isEmpty()) {
      $address_item = $address_entity->get('field_address')->first();
      if ($address_item) {
        $country_list = $this->countryManager->getList();
        $lines = array_filter([
          $address_item->address_line1 ?? '',
          $address_item->address_line2 ?? '',
          $address_item->locality ?? '',
          $address_item->administrative_area ?? '',
          $address_item->postal_code ?? '',
        ]);

        if (!empty($address_item->country_code)) {
          $country_name = $country_list[$address_item->country_code] ?? NULL;
          if ($country_name) {
            $lines[] = $country_name;
          }
        }
      }
    }

    // Fallback to legacy field storage if present.
    if (!$lines) {
      foreach (['field_address_address_line1', 'field_address_address_line2', 'field_address_locality', 'field_address_postal_code', 'field_address_country_code'] as $field_name) {
        if ($address_entity->hasField($field_name) && !$address_entity->get($field_name)->isEmpty()) {
          $value = $address_entity->get($field_name)->value;
          if ($field_name === 'field_address_country_code') {
            $country_list = $this->countryManager->getList();
            $value = $country_list[$value] ?? $value;
          }
          $lines[] = $value;
        }
      }
    }

    $build['address'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Address'),
      '#items' => $lines,
      '#attributes' => ['class' => ['sentinel-address-summary']],
    ];

    // Packs table.
    $rows = [];
    $query = $this->database->select('sentinel_sample', 'ss')
      ->fields('ss', ['pid', 'pack_reference_number', 'company_name', 'date_reported', 'pass_fail'])
      ->condition('sentinel_sample_address_target_id', $address)
      ->orderBy('date_reported', 'DESC');

    $result = $query->execute();
    foreach ($result as $row) {
      $pass_fail = 'Pending';
      if ((string) $row->pass_fail === '1') {
        $pass_fail = $this->t('Pass');
      }
      elseif ((string) $row->pass_fail === '0') {
        $pass_fail = $this->t('Fail');
      }

      $date_reported = $row->date_reported ? $row->date_reported : '';
      if ($date_reported) {
        try {
          $date_reported = (new \DateTime($date_reported))->format('d/m/Y');
        }
        catch (\Exception $e) {
          // Leave raw string.
        }
      }

      $rows[] = [
        ['data' => Link::fromTextAndUrl($row->pack_reference_number ?: $row->pid, Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $row->pid]))->toRenderable()],
        ['data' => ['#plain_text' => $row->company_name ?? '']],
        ['data' => ['#plain_text' => $date_reported]],
        ['data' => ['#plain_text' => $pass_fail]],
      ];
    }

    $build['packs'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Pack Reference'),
        $this->t('Company Name'),
        $this->t('Date Reported'),
        $this->t('Pass/Fail'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No packs found for this address.'),
      '#attributes' => ['class' => ['sentinel-address-packs']],
    ];

    // Address note form.
    $build['notes_form'] = $this->formBuilder->getForm('Drupal\sentinel_addresses\Form\AddressNoteForm', $address_entity);

    // Existing notes list similar to Drupal 7.
    if ($address_entity->hasField('field_address_note') && !$address_entity->get('field_address_note')->isEmpty()) {
      $note_rows = [];
      foreach ($address_entity->get('field_address_note') as $delta => $item) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
        $paragraph = $item->entity;
        if (!$paragraph) {
          continue;
        }

        $note_date = '';
        if ($paragraph->hasField('field_address_note_date') && !$paragraph->get('field_address_note_date')->isEmpty()) {
          $date_value = $paragraph->get('field_address_note_date')->value;
          if ($date_value) {
            try {
              $note_date = (new \DateTime($date_value))->format('d/m/Y');
            }
            catch (\Exception $e) {
              $note_date = $date_value;
            }
          }
        }

        $note_type = '';
        if ($paragraph->hasField('field_field_address_note_type') && !$paragraph->get('field_field_address_note_type')->isEmpty()) {
          $term = $paragraph->get('field_field_address_note_type')->entity;
          $note_type = $term ? $term->label() : '';
        }

        $note_rows[] = [
          ['data' => ['#plain_text' => $note_date]],
          ['data' => ['#plain_text' => $note_type]],
          ['data' => ['#plain_text' => $paragraph->get('field_address_note_details')->value ?? '']],
          ['data' => Link::fromTextAndUrl($this->t('Edit'), Url::fromRoute('sentinel_addresses.edit_note', [
            'address' => $address_entity->id(),
            'note_delta' => $delta,
          ]))->toRenderable()],
        ];
      }

      $build['notes_table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Date'),
          $this->t('Device Fitted'),
          $this->t('Device Name'),
          $this->t('Actions'),
        ],
        '#rows' => $note_rows,
        '#empty' => $this->t('No notes available for this address.'),
        '#attributes' => ['class' => ['sentinel-address-notes']],
      ];
    }
 
    return $build;
  }

  /**
   * Edit note callback.
   *
   * @param int $address_id
   *   The address entity ID.
   * @param int $note_delta
   *   The note delta.
   *
   * @return array
   *   A render array with the form.
   */
  public function editNote($address, $note_delta) {
    if (!is_numeric($note_delta)) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Load the address entity.
    $address_storage = $this->entityTypeManager->getStorage('address');
    $address_entity = $address_storage->load($address);

    if (!$address_entity) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Build the form.
    $form = $this->formBuilder->getForm(
      'Drupal\sentinel_addresses\Form\AddressNoteForm',
      $address_entity,
      $note_delta
    );

    return $form;
  }

}