<?php

namespace Drupal\sentinel_monitor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\eck\Entity\EckEntity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for Sentinel Monitor pages.
 */
class MonitorController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MonitorController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the add form for a Sentinel Monitor entity.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array or redirect response.
   */
  public function addForm() {
    $entity_type = $this->entityTypeManager->getStorage('sentinel_monitor');
    $entity = $entity_type->create(['type' => 'sentinel_monitor']);

    $form = \Drupal::entityTypeManager()
      ->getFormObject('sentinel_monitor', 'add')
      ->setEntity($entity);

    return \Drupal::formBuilder()->getForm($form);
  }

}
