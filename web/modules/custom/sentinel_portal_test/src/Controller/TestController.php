<?php

namespace Drupal\sentinel_portal_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sentinel_portal_entities\Entity\SentinelNotice;

/**
 * Controller for test functionality.
 */
class TestController extends ControllerBase {

  /**
   * Generate test content.
   *
   * @return string
   *   The output message.
   */
  public function generateContent() {
    $output = '';

    $notice_data = [
      'uid' => 1,
      'title' => $this->t('You got a message') . ' ' . time(),
      'notice' => $this->t('Pannawonica was built on Yalleen Station in 1970 by Cleveland-Cliffs Robe River Iron (it then became Robe River Iron Associates and was then brought out by Rio Tinto Iron Ore) it was gazetted as a townsite in 1972.The township\'s name was derived from nearby Pannawonica Hill, named by a surveyor in 1885 after the corresponding Aboriginal placename which is said to mean "the hill that came from the sea". The traditional legend is that two local Aboriginal tribes were arguing over the ownership of the hill which was located by the sea. The sea spirit decided to resolve the dispute by moving the hill inland. As the hill was dragged over the land it left a deep indentation which became the Robe River.'),
      'notice_read' => FALSE,
      'created' => \Drupal::time()->getRequestTime(),
    ];

    $notice = SentinelNotice::create($notice_data);
    $notice->save();

    $output .= 'Done';

    return [
      '#markup' => $output,
    ];
  }

}


