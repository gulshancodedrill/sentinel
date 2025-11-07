<?php

namespace Drupal\hold_states\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides download responses for on-hold sample exports.
 */
class OnHoldSamplesExportController extends ControllerBase {

  /**
   * Serves the generated CSV file for download.
   */
  public function download(Request $request) {
    $encoded_path = $request->query->get('file');
    $filename = $request->query->get('name', 'on-hold-samples.csv');

    if (empty($encoded_path)) {
      throw new NotFoundHttpException();
    }

    $path = base64_decode($encoded_path, TRUE);
    if ($path === FALSE) {
      throw new NotFoundHttpException();
    }

    $file_system = \Drupal::service('file_system');
    $realpath = $file_system->realpath($path);
    if (!$realpath || !file_exists($realpath)) {
      throw new NotFoundHttpException();
    }

    $allowed_directories = [
      $file_system->realpath('public://hold_state_exports'),
      $file_system->realpath('temporary://hold_state_exports'),
    ];

    $is_allowed = FALSE;
    foreach ($allowed_directories as $directory) {
      if ($directory && strpos($realpath, $directory) === 0) {
        $is_allowed = TRUE;
        break;
      }
    }

    if (!$is_allowed) {
      throw new NotFoundHttpException();
    }

    $response = new StreamedResponse(function () use ($realpath) {
      $handle = fopen($realpath, 'rb');
      if ($handle) {
        while (!feof($handle)) {
          print fread($handle, 1024 * 64);
          flush();
        }
        fclose($handle);
      }
    });

    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename));
    $response->headers->set('Content-Length', (string) filesize($realpath));

    return $response;
  }

  /**
   * Displays a ready page with download link after batch completion.
   */
  public function ready(Request $request) {
    $store = \Drupal::service('tempstore.private')->get('hold_states_export');
    $data = $store->get(\Drupal::currentUser()->id());

    $encoded_filters = $data['filters'] ?? NULL;
    $download = $data['file'] ?? NULL;
    $name = $data['name'] ?? 'on-hold-samples.csv';

    $listing_url = Url::fromRoute('hold_states.on_hold_samples');
    if ($encoded_filters) {
      $listing_url->setOption('query', ['filters' => $encoded_filters]);
    }

    if (!$download) {
      return [
        '#markup' => $this->t('No export file found. @link', [
          '@link' => Link::fromTextAndUrl($this->t('Return to on hold samples'), $listing_url)->toString(),
        ]),
      ];
    }

    $download_url = Url::fromRoute('hold_states.export_download', [], [
      'query' => [
        'file' => $download,
        'name' => $name,
      ],
    ]);

    // Clear stored data so repeated visits don't reuse stale paths.
    $store->delete(\Drupal::currentUser()->id());

    return [
      '#theme' => 'item_list',
      '#items' => [
        Link::fromTextAndUrl($this->t('Download the export file (@name)', ['@name' => $name]), $download_url)->toRenderable(),
        Link::fromTextAndUrl($this->t('Back to on hold samples'), $listing_url)->toRenderable(),
      ],
      '#title' => $this->t('On hold samples export'),
    ];
  }

}


