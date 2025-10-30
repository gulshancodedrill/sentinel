<?php

namespace Drupal\sentinel_certificate_test_entity\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for test entity PDF generation.
 */
class TestEntityPdfController extends ControllerBase {

  /**
   * View PDF for test entity.
   *
   * @param int $test_entity_id
   *   The test entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   PDF response.
   */
  public function viewPdf($test_entity_id) {
    // This function calls the same function from sentinel_systemcheck_certificate.
    // Check if the function exists.
    if (function_exists('sentinel_systemcheck_certificate_view_pdf')) {
      // Call the PDF generation function with test_entity type.
      return sentinel_systemcheck_certificate_view_pdf($test_entity_id, 'test_entity');
    }
    
    // Fallback: if the function doesn't exist, try to call it via the controller.
    $entity_storage = \Drupal::entityTypeManager()->getStorage('test_entity');
    $entity = $entity_storage->load($test_entity_id);
    
    if (!$entity) {
      throw new NotFoundHttpException();
    }
    
    // Try to use the certificate controller if available.
    if (class_exists('\Drupal\sentinel_systemcheck_certificate\Controller\CertificateController')) {
      $controller = \Drupal::getContainer()->get('sentinel_systemcheck_certificate.controller.certificate');
      if (method_exists($controller, 'viewPdf')) {
        return $controller->viewPdf($test_entity_id);
      }
    }
    
    // If nothing works, return an error.
    throw new NotFoundHttpException('PDF generation function not found.');
  }

}


