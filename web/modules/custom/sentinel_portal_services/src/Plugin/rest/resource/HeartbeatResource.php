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
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the heartbeat timestamp.
   */
  public function get() {
    $response_data = [
      'status' => '200',
      'message' => date('Y-m-d H:i:s'),
    ];

    $response = new ResourceResponse($response_data, Response::HTTP_OK);
    
    // Disable caching for this response.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    
    return $response;
  }

}



