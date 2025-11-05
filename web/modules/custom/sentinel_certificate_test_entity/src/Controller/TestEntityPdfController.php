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
   * @return array
   *   Render array with PDF HTML.
   */
  public function viewPdf($test_entity_id) {
    // Check if entity exists.
    $entity_storage = \Drupal::entityTypeManager()->getStorage('test_entity');
    $entity = $entity_storage->load($test_entity_id);
    
    if (!$entity) {
      throw new NotFoundHttpException('Test entity not found.');
    }

    // Check if _get_result_content function exists.
    if (!function_exists('_get_result_content')) {
      throw new NotFoundHttpException('PDF generation function _get_result_content not found. Please ensure sentinel_systemcheck_certificate module is enabled.');
    }

    // Get the content for the PDF using the same function as sentinel samples.
    $theme_vars = _get_result_content($test_entity_id, 'test_entity');
    $theme_vars['pdf'] = TRUE;
    
    // Get template path.
    $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate');
    $template_path = $module_path . '/templates/sentinel_certificate.html.twig';
    
    // Get CSS.
    $css_path = \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('sentinel_portal') . '/css/pdf-only.css';
    $css = '';
    if (file_exists($css_path)) {
      $css = '<style>' . file_get_contents($css_path) . '</style>';
    }
    
    // Build complete HTML.
    $html = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  ' . $css . '
</head>
<body>';
    
    $html .= \Drupal::service('twig')->render($template_path, $theme_vars);
    $html .= '</body></html>';
    
    // Create DomPDF object.
    if (function_exists('sentinel_systemcheck_certificate_get_dompdf_object')) {
      $dompdf = sentinel_systemcheck_certificate_get_dompdf_object($html);
      
      // Stream PDF to browser.
      $response = new \Symfony\Component\HttpFoundation\Response($dompdf->output());
      $response->headers->set('Content-Type', 'application/pdf');
      $response->headers->set('Content-Disposition', 'inline; filename="test_certificate_result.pdf"');
      
      return $response;
    }
    
    // If functions not available, throw error.
    throw new NotFoundHttpException('PDF generation not available.');
  }

}


