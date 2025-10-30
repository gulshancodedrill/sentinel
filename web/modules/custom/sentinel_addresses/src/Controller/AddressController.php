<?php

namespace Drupal\sentinel_addresses\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
   * {@inheritdoc}
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('form_builder')
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

    $matches = [];

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
        $matches['(' . $row->entity_id . ') ' . $address_string] = $address_string;
      }
    }

    return new JsonResponse($matches);
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
  public function editNote($address_id, $note_delta) {
    if (!is_numeric($note_delta)) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Load the address entity.
    $address_storage = $this->entityTypeManager->getStorage('address');
    $address = $address_storage->load($address_id);

    if (!$address) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Build the form.
    $form = $this->formBuilder->getForm(
      'Drupal\sentinel_addresses\Form\AddressNoteForm',
      $address,
      $note_delta
    );

    return $form;
  }

}