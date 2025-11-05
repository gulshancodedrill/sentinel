<?php

namespace Drupal\sentinel_systemcheck_certificate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;

/**
 * Controller for certificate-related pages.
 */
class CertificateController extends ControllerBase {

  /**
   * View result page callback.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return array
   *   The render array.
   */
  public function viewResult($sample_id) {
    $content = _get_result_content($sample_id, 'sentinel_sample');
    return [
      '#theme' => 'sentinel_certificate',
      '#content' => $content,
    ];
  }

  /**
   * View PDF page callback - Generates and streams PDF.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   PDF response.
   */
  public function viewPdf($sample_id) {
    // Generate HTML for PDF
    $html = $this->generatePdfHtml($sample_id);
    
    // Create DomPDF object
    $dompdf = sentinel_systemcheck_certificate_get_dompdf_object($html);
    
    // Stream PDF to browser
    $response = new Response($dompdf->output());
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'inline; filename="sentinel_certificate_result.pdf"');
    
    return $response;
  }

  /**
   * Download PDF page callback - Forces download.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   PDF response.
   */
  public function downloadPdf($sample_id) {
    // Generate HTML for PDF
    $html = $this->generatePdfHtml($sample_id);
    
    // Create DomPDF object
    $dompdf = sentinel_systemcheck_certificate_get_dompdf_object($html);
    
    // Force download
    $response = new Response($dompdf->output());
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="sentinel_certificate_result.pdf"');
    
    return $response;
  }

  /**
   * Generate HTML for PDF rendering.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return string
   *   HTML string.
   */
  protected function generatePdfHtml($sample_id) {
    $theme_vars = _get_result_content($sample_id, 'sentinel_sample');
    $theme_vars['pdf'] = TRUE;
    
    // Get template path
    $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate');
    $template_path = $module_path . '/templates/sentinel_certificate.html.twig';
    
    // Get CSS
    $css_path = \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('sentinel_portal') . '/css/pdf-only.css';
    $css = '';
    if (file_exists($css_path)) {
      $css = '<style>' . file_get_contents($css_path) . '</style>';
    }
    
    // Build complete HTML
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
    
    // Render template
    $html .= \Drupal::service('twig')->render($template_path, $theme_vars);
    
    $html .= '</body></html>';
    
    return $html;
  }

  /**
   * Regenerate PDF callback.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function regeneratePdf($sample_id) {
    $sample_entity = \Drupal::entityTypeManager()->getStorage('sentinel_sample')->load($sample_id);

    if ($sample_entity) {
      $fileid = $sample_entity->get('fileid')->value;

      if ($fileid) {
        $file_entity = \Drupal::entityTypeManager()->getStorage('file')->load($fileid);

        if ($file_entity) {
          $file_entity->delete();
        }
      }

      try {
        if (method_exists($sample_entity, 'SavePdf') && is_object($sample_entity->SavePdf())) {
          $this->messenger()->addStatus($this->t('A new PDF certificate has been generated'));
        } else {
          $this->messenger()->addError($this->t('A problem occurred whilst trying to generate a new file'));
        }
      } catch (\Exception $e) {
        $this->messenger()->addError($this->t('A problem occurred whilst trying to generate a new file'));
      }

      $url = Url::fromRoute('entity.sentinel_sample.canonical', ['sentinel_sample' => $sample_id]);
      return new RedirectResponse($url->toString());
    } else {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
  }

  /**
   * View test entity PDF callback.
   *
   * @param int $entity_id
   *   The test entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   PDF response.
   */
  public function viewTestEntityPdf($entity_id) {
    // Generate HTML for test entity PDF using test_entity type
    $html = $this->generatePdfHtmlWithType($entity_id, 'test_entity');
    
    // Create DomPDF object
    $dompdf = sentinel_systemcheck_certificate_get_dompdf_object($html);
    
    // Stream PDF to browser
    $response = new Response($dompdf->output());
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'inline; filename="test_certificate_result.pdf"');
    
    return $response;
  }

  /**
   * Generate HTML for PDF rendering with entity type support.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type (defaults to 'sentinel_sample').
   *
   * @return string
   *   HTML string.
   */
  protected function generatePdfHtmlWithType($entity_id, $entity_type = 'sentinel_sample') {
    $theme_vars = _get_result_content($entity_id, $entity_type);
    $theme_vars['pdf'] = TRUE;
    
    // Get template path
    $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate');
    $template_path = $module_path . '/templates/sentinel_certificate.html.twig';
    
    // Get CSS
    $css_path = \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('sentinel_portal') . '/css/pdf-only.css';
    $css = '';
    if (file_exists($css_path)) {
      $css = '<style>' . file_get_contents($css_path) . '</style>';
    }
    
    // Build complete HTML
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
    
    // Render template
    $html .= \Drupal::service('twig')->render($template_path, $theme_vars);
    
    $html .= '</body></html>';
    
    return $html;
  }

}


