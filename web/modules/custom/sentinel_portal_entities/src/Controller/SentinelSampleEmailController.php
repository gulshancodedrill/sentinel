<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for sending sample PDF emails from the admin UI.
 */
class SentinelSampleEmailController extends ControllerBase {

  use MessengerTrait;

  /**
   * Sends the PDF report email for a sample.
   *
   * @param \Drupal\sentinel_portal_entities\Entity\SentinelSample $sentinel_sample
   *   The sample entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the sample view page.
   */
  public function sendEmail(SentinelSample $sentinel_sample): RedirectResponse {
    $result = FALSE;

    if (function_exists('_sentinel_portal_queue_process_email')) {
      try {
        $result = _sentinel_portal_queue_process_email($sentinel_sample, 'report');
      }
      catch (\Throwable $e) {
        $this->getLogger('sentinel_portal_entities')->error('Failed to send sample report email for @id: @message', [
          '@id' => $sentinel_sample->id(),
          '@message' => $e->getMessage(),
        ]);
        $this->messenger()->addError($this->t('Report failed to email.'));
      }
    }

    if ($result) {
      $this->getLogger('sentinel_portal_entities')->info('Sample entity mail sent! @id', ['@id' => $sentinel_sample->id()]);
      $this->messenger()->addStatus($this->t('Report emailed.'));
    }
    else {
      if (!$this->messenger()->messagesByType('error')) {
        $this->getLogger('sentinel_portal_entities')->warning('Failed to send entity mail! @id', ['@id' => $sentinel_sample->id()]);
        $this->messenger()->addError($this->t('Report failed to email.'));
      }
    }

    return $this->redirect('entity.sentinel_sample.canonical', ['sentinel_sample' => $sentinel_sample->id()]);
  }

}


