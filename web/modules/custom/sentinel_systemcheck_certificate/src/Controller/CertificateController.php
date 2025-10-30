<?php

namespace Drupal\sentinel_systemcheck_certificate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
   * View PDF page callback.
   *
   * @param int $sample_id
   *   The sample ID.
   *
   * @return array
   *   The render array.
   */
  public function viewPdf($sample_id) {
    $theme_vars = _get_result_content($sample_id, 'sentinel_sample');
    $path = \Drupal::service('extension.list.module')->getPath('sentinel_systemcheck_certificate') . '/templates/sentinel_certificate.html.twig';

    $css = '<style>' . file_get_contents(\Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('sentinelportal_theme') . '/css/pdf-only.css') . '</style>';

    $html = '
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="Generator" content="Drupal 11" />
    ';
    $html .= $css;
    $html .= '</head>
      <body>
      ';

    $theme_vars['pdf'] = TRUE;

    $html .= \Drupal::service('twig')->render($path, $theme_vars);

    $html .= '</body></html>';

    return [
      '#markup' => $html,
    ];
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

}


