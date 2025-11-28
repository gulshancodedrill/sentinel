<?php

namespace Drupal\sentinel_systemcheck_certificate\Controller;

use DateTime;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

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
    $sample = NULL;
    if (\Drupal::entityTypeManager()->hasDefinition('sentinel_sample')) {
      $sample = \Drupal::entityTypeManager()->getStorage('sentinel_sample')->load($sample_id);
    }

    if ($sample instanceof SentinelSample) {
      $pdf_uri = $this->resolveExistingPdfUri($sample);
      if ($pdf_uri) {
        $pdf_url = \Drupal::service('file_url_generator')->generateAbsoluteString($pdf_uri);
        $pdf_url .= (str_contains($pdf_url, '?') ? '&' : '?') . 'itok=' . $sample->getPdfToken();
        return new RedirectResponse($pdf_url);
      }
    }

    $content = _get_result_content($sample_id, 'sentinel_sample');
    return [
      '#theme' => 'sentinel_certificate',
      '#content' => $content,
    ];
  }

  /**
   * View result as HTML page only (no PDF redirect).
   *
   * This preserves existing behaviour on /view-my-results while providing
   * a dedicated HTML endpoint similar to Drupal 7 output.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return array
   *   Render array with page title and content.
   */
  public function viewResultHtml($sample_id) {
    $vars = _get_result_content($sample_id, 'sentinel_sample');
    // Ensure we are not in PDF mode for this endpoint.
    $vars['pdf'] = FALSE;
    $vars['show_download'] = true;

    // Map language codes: gb -> en, de -> de, fr -> fr, it -> it
    $lang_map = [
      'gb' => 'en',
      'de' => 'de',
      'fr' => 'fr',
      'it' => 'it',
    ];
    $lang_code = $vars['lang'] ?? 'gb';
    $drupal_lang_code = $lang_map[$lang_code] ?? 'en';

    // Set the language context for translations
    $language_manager = \Drupal::languageManager();
    $language = $language_manager->getLanguage($drupal_lang_code);
    if ($language) {
      $language_manager->setConfigOverrideLanguage($language);
      $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    }

    // Render the template directly to avoid render array constraints on objects.
    $twig = \Drupal::service('twig');
    $module_path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate');
    $template_path = $module_path . '/templates/sentinel_certificate_html.html.twig';
    
    // Load the template file content and render it.
    $template_content = file_get_contents($template_path);
    $html = $twig->createTemplate($template_content)->render($vars);

    // Return as markup so it integrates with Drupal's page rendering.
    return [
      '#type' => 'markup',
      '#markup' => $html,
      '#cache' => [
        'contexts' => ['languages:language_content'],
      ],
    ];
  }

  /**
   * Title callback for HTML view route.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return string
   *   The page title.
   */
  public function viewResultHtmlTitle($sample_id) {
    return $this->t('View my results');
  }

  /**
   * Find existing PDF file for a sample if one was pre-generated.
   */
  protected function resolveExistingPdfUri(SentinelSample $sample): ?string {
    $file_system = \Drupal::service('file_system');

    if ($sample->hasField('fileid') && !$sample->get('fileid')->isEmpty()) {
      $file_id = (int) $sample->get('fileid')->value;
      if ($file_id) {
        $file = File::load($file_id);
        if ($file instanceof File) {
          $real_path = $file_system->realpath($file->getFileUri());
          if ($real_path && file_exists($real_path)) {
            return $file->getFileUri();
          }
        }
      }
    }

    $filename = NULL;
    if ($sample->hasField('filename') && !$sample->get('filename')->isEmpty()) {
      $filename = $sample->get('filename')->value;
    }

    if (empty($filename)) {
      return NULL;
    }

    $directory = 'private://new-pdf-certificates/other/';
    if ($sample->hasField('created') && !$sample->get('created')->isEmpty()) {
      try {
        $date = new DateTime($sample->get('created')->value);
        $directory = 'private://new-pdf-certificates/' . $date->format('m-Y') . '/';
      }
      catch (\Exception $e) {
        // Ignore and use fallback directory.
      }
    }

    $locations = [
      $directory . $filename,
      'private://legacy-pdf-certificates/' . $filename,
    ];

    foreach ($locations as $uri) {
      $real_path = $file_system->realpath($uri);
      if ($real_path && file_exists($real_path)) {
        return $uri;
      }
    }

    return NULL;
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
      // Delete existing file if it exists.
      $fileid = $sample_entity->get('fileid')->value;

      if ($fileid) {
        $file_entity = \Drupal::entityTypeManager()->getStorage('file')->load($fileid);

        if ($file_entity) {
          $file_entity->delete();
        }
      }

      // Clear the fileid and filename fields on the entity before regenerating.
      // This ensures the SavePdf method will properly attach the new file.
      $sample_entity->set('fileid', NULL);
      $sample_entity->set('filename', NULL);

      try {
        // Generate and save the new PDF file.
        if (method_exists($sample_entity, 'SavePdf')) {
          $file = $sample_entity->SavePdf();
          
          if ($file instanceof FileInterface) {
            // Verify the file was attached to the sample.
            // SavePdf() updates the database directly, but we should ensure
            // the entity is properly updated in memory.
            $sample_entity->set('fileid', (string) $file->id());
            $sample_entity->set('filename', $file->getFilename());
            
            // Save the entity to ensure all changes are persisted.
            $sample_entity->save();
            
            $this->messenger()->addStatus($this->t('A new PDF certificate has been generated and attached to the sample.'));
          } else {
            $this->messenger()->addError($this->t('A problem occurred whilst trying to generate a new file. The PDF could not be created.'));
          }
        } else {
          $this->messenger()->addError($this->t('A problem occurred whilst trying to generate a new file. The SavePdf method is not available.'));
        }
      } catch (\Exception $e) {
        \Drupal::logger('sentinel_systemcheck_certificate')->error('Error regenerating PDF for sample @id: @message', [
          '@id' => $sample_id,
          '@message' => $e->getMessage(),
        ]);
        $this->messenger()->addError($this->t('A problem occurred whilst trying to generate a new file: @message', [
          '@message' => $e->getMessage(),
        ]));
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


