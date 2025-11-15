<?php

namespace Drupal\sentinel_portal_entities\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a download link for Sentinel Sample PDFs.
 *
 * @ViewsField("sentinel_sample_pdf_link")
 */
class SamplePdfLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No additional query requirements beyond the base table.
    parent::query();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $sample = $this->getEntity($values);
    if (!$sample instanceof SentinelSample) {
      return ['#markup' => ''];
    }

    $file_id = (int) ($sample->get('fileid')->value ?? 0);
    if ($file_id <= 0) {
      return ['#markup' => $this->t('No report')];
    }

    $file = File::load($file_id);
    if (!$file instanceof File) {
      return ['#markup' => $this->t('No report')];
    }

    $uri = $file->getFileUri();
    if (!$uri) {
      return ['#markup' => $this->t('No report')];
    }

    $url = \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
    if (!$url) {
      return ['#markup' => $this->t('No report')];
    }

    $url .= (str_contains($url, '?') ? '&' : '?') . 'itok=' . $sample->getPdfToken();

    $link = Link::fromTextAndUrl($this->t('Download PDF'), Url::fromUri($url, [
      'attributes' => [
        'class' => ['link-download', 'icon-pdf'],
        'target' => '_blank',
        'rel' => 'noopener',
      ],
    ]));

    return $link->toRenderable();
  }

}

