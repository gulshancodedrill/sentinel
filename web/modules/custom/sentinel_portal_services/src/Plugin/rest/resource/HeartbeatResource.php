<?php

namespace Drupal\sentinel_portal_services\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a Heartbeat Resource.
 *
 * @RestResource(
 *   id = "sentinel_heartbeat",
 *   label = @Translation("Sentinel Heartbeat"),
 *   uri_paths = {
 *     "canonical" = "/sentinel/heartbeat"
 *   }
 * )
 */
class HeartbeatResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * Returns the current server time to verify API is alive.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing current timestamp.
   */
  public function get() {
    // Return current date/time - matches D7 format exactly
    $current_time = date('Y-m-d H:i:s');

    $response_data = [
      'status' => '200',
      'message' => $current_time,
    ];

    $response = new ResourceResponse($response_data, Response::HTTP_OK);
    
    // Never cache the heartbeat
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    
    return $response;
  }

}
