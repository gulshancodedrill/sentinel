<?php

namespace Drupal\sentinel_systemcheck_vaillant_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Controller for Vaillant API submissions.
 */
class VaillantApiController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $sampleStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a VaillantApiController.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $sample_storage
   *   The sample entity storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityStorageInterface $sample_storage, ConfigFactoryInterface $config_factory) {
    $this->sampleStorage = $sample_storage;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('sentinel_sample'),
      $container->get('config.factory')
    );
  }

  /**
   * Submit sample to Vaillant API.
   *
   * @param int $sample_id
   *   The sample entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to sample view page.
   */
  public function submitSample($sample_id) {
    $sample = $this->sampleStorage->load($sample_id);

    if ($sample) {
      try {
        sentinel_systemcheck_vaillant_api_send($sample);
        $this->messenger()->addStatus($this->t('Report sent to Vaillant API.'));
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
      }
    }
    else {
      $this->messenger()->addError($this->t('Report not found'));
    }

    // Redirect back to sample view page.
    $url = Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $sample_id]);
    return new RedirectResponse($url->toString());
  }

}


