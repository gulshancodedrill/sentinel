<?php

namespace Drupal\sentinel_systemcheck_vaillant_xml\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for Vaillant XML email sending.
 */
class VaillantXmlController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $sampleStorage;

  /**
   * Constructs a VaillantXmlController.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $sample_storage
   *   The sample entity storage.
   */
  public function __construct(EntityStorageInterface $sample_storage) {
    $this->sampleStorage = $sample_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('sentinel_sample')
    );
  }

  /**
   * Send XML email.
   *
   * @param int $sample_id
   *   The sample entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to sample view page.
   */
  public function sendEmail($sample_id) {
    $sample = $this->sampleStorage->load($sample_id);

    if ($sample) {
      if (function_exists('sentinel_systemcheck_vaillant_xml_sentinel_sendresults')) {
        sentinel_systemcheck_vaillant_xml_sentinel_sendresults($sample);
        $this->messenger()->addStatus($this->t('XML report emailed.'));
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
