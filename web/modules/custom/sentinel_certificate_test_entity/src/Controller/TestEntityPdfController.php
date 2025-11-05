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
    // Load the test entity.
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
    
    // Get the template path.
    $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate');
    $template_path = $module_path . '/templates/sentinel_certificate.html.twig';
    
    if (!file_exists($template_path)) {
      throw new NotFoundHttpException('PDF template not found at: ' . $template_path);
    }

    // Get CSS for PDF styling.
    $css = '';
    try {
      $theme_path = \Drupal::service('extension.list.theme')->getPath('sentinel_portal');
      $css_path = \Drupal::root() . '/' . $theme_path . '/css/pdf-only.css';
      
      if (file_exists($css_path)) {
        $css = '<style>' . file_get_contents($css_path) . '</style>';
      }
    } catch (\Exception $e) {
      // Theme or CSS file not found, continue without styling.
      \Drupal::logger('sentinel_certificate_test_entity')->warning('PDF CSS not found: @message', ['@message' => $e->getMessage()]);
    }

    // Build the HTML for the PDF.
    $html = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="Generator" content="Drupal 11" />
  ' . $css . '
</head>
<body>';

    // Mark as PDF rendering.
    $theme_vars['pdf'] = TRUE;

    // Render the template with the variables.
    $html .= \Drupal::service('twig')->render($template_path, $theme_vars);

    $html .= '</body></html>';

    // Generate PDF using dompdf.
    if (function_exists('sentinel_systemcheck_certificate_get_dompdf_object')) {
      $dompdf = sentinel_systemcheck_certificate_get_dompdf_object($html);
      $dompdf->render();
      
      // Output the PDF.
      $response = new \Symfony\Component\HttpFoundation\Response();
      $response->setContent($dompdf->output());
      $response->headers->set('Content-Type', 'application/pdf');
      $response->headers->set('Content-Disposition', 'inline; filename="test-entity-' . $test_entity_id . '.pdf"');
      
      return $response;
    }

    // Fallback: return HTML if dompdf is not available.
    return [
      '#markup' => $html,
    ];
  }

}


