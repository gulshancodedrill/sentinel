<?php

namespace Drupal\sentinel_customerservice\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Sentinel main endpoint controller (GET /sentinel).
 */
class SentinelController extends ControllerBase {

  /**
   * Handles GET /sentinel?customer_id=xxxx
   */
  public function main(Request $request) {
    $customer_id = $request->query->get('customer_id');

    if (empty($customer_id)) {
      return new JsonResponse([
        "status" => 400,
        "error" => "Missing parameter: customer_id"
      ], 400);
    }

    try {
      $connection = \Drupal::database();

      // Query your REAL table: sentinel_sample
      $record = $connection->select('sentinel_sample', 's')
        ->fields('s')
        ->condition('customer_id', $customer_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if (!$record) {
        return new JsonResponse([
          "status" => 404,
          "error" => "No record found for customer_id " . $customer_id
        ], 404);
      }

      return new JsonResponse([
        "status" => 200,
        "data" => $record
      ]);

    } catch (\Exception $e) {
      return new JsonResponse([
        "status" => 500,
        "error" => "Server error",
        "details" => $e->getMessage()
      ], 500);
    }
  }
}
