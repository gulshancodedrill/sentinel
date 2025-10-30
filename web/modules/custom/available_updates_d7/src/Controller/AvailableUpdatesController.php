<?php

namespace Drupal\available_updates_d7\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Update\UpdateManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Available Updates D7 module.
 */
class AvailableUpdatesController extends ControllerBase {

  /**
   * The update manager service.
   *
   * @var \Drupal\Core\Update\UpdateManagerInterface
   */
  protected $updateManager;

  /**
   * Constructs a new AvailableUpdatesController.
   *
   * @param \Drupal\Core\Update\UpdateManagerInterface $update_manager
   *   The update manager service.
   */
  public function __construct(UpdateManagerInterface $update_manager) {
    $this->updateManager = $update_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('update.manager')
    );
  }

  /**
   * Returns all available updates as JSON.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing available updates or 'no'.
   */
  public function getUpdates(Request $request) {
    try {
      // Get the client IP address
      $user_ip = $request->getClientIp();
      
      // Check if the IP is in the allowed list
      $allowed_ips = [
        '18.130.46.80',
        '172.16.7.73',
        '172.16.6.160'
      ];

      if (in_array($user_ip, $allowed_ips)) {
        // Get available updates
        $available = $this->updateManager->getAvailableUpdatesData();
        
        // Calculate project data (similar to update_calculate_project_data)
        $new = $this->calculateProjectData($available);
        
        return new JsonResponse($new);
      } else {
        return new JsonResponse('no');
      }
    } catch (\Exception $e) {
      return new JsonResponse('no');
    }
  }

  /**
   * Calculate project data from available updates.
   *
   * @param array $available
   *   Available updates data.
   *
   * @return array
   *   Calculated project data.
   */
  protected function calculateProjectData(array $available) {
    $projects = [];
    
    foreach ($available as $project_name => $project_data) {
      if (isset($project_data['releases'])) {
        $latest_release = null;
        $recommended_release = null;
        
        foreach ($project_data['releases'] as $version => $release) {
          if ($release['status'] === 'published') {
            if (!$latest_release || version_compare($version, $latest_release['version'], '>')) {
              $latest_release = $release;
              $latest_release['version'] = $version;
            }
            
            if ($release['version_major'] == $project_data['existing_version_major'] && 
                $release['version_minor'] > $project_data['existing_version_minor']) {
              if (!$recommended_release || version_compare($version, $recommended_release['version'], '>')) {
                $recommended_release = $release;
                $recommended_release['version'] = $version;
              }
            }
          }
        }
        
        $projects[$project_name] = [
          'name' => $project_name,
          'existing_version' => $project_data['existing_version'] ?? '',
          'latest_version' => $latest_release['version'] ?? '',
          'recommended_version' => $recommended_release['version'] ?? '',
          'status' => $this->getProjectStatus($project_data, $latest_release, $recommended_release),
          'releases' => $project_data['releases'] ?? [],
        ];
      }
    }
    
    return $projects;
  }

  /**
   * Get project status based on available updates.
   *
   * @param array $project_data
   *   Project data.
   * @param array|null $latest_release
   *   Latest release data.
   * @param array|null $recommended_release
   *   Recommended release data.
   *
   * @return string
   *   Project status.
   */
  protected function getProjectStatus(array $project_data, $latest_release, $recommended_release) {
    if (!$latest_release) {
      return 'not-updated';
    }
    
    $existing_version = $project_data['existing_version'] ?? '';
    
    if (version_compare($existing_version, $latest_release['version'], '>=')) {
      return 'up-to-date';
    }
    
    if ($recommended_release && version_compare($existing_version, $recommended_release['version'], '<')) {
      return 'update-available';
    }
    
    return 'update-available';
  }

}


