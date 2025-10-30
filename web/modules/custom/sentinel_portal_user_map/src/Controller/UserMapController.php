<?php

namespace Drupal\sentinel_portal_user_map\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for user map functionality.
 */
class UserMapController extends ControllerBase {

  /**
   * Display the user list page.
   *
   * @return array
   *   The render array.
   */
  public function userList() {
    $locations = sentinel_portal_user_map_values();

    $fields = [
      'name',
      'email',
      'cid',
      'uid',
    ];

    $headers = [
      'Location',
      'Users',
      'Links'
    ];

    $rows = [];

    foreach ($locations as $location) {
      $row = [];

      // Add the location text.
      $row[] = ['data' => $location->name . ' (' . $location->tid . ')'];

      // Find out which users are connected to this list.
      $query = \Drupal::database()->select('sentinel_client', 'sc');
      $query->fields('sc', $fields);
      $query->join('field_data_field_user_cohorts', 'uc', 'uc.entity_id = sc.cid');
      $query->condition('uc.field_user_cohorts_tid', $location->tid, '=');
      $query->orderBy('sc.name', 'ASC');
      $result = $query->execute()->fetchAll();

      $users_list = [];
      foreach ($result as $users) {
        $user_details = $users->email;
        if ($this->currentUser()->hasPermission('administer users')) {
          $user_details .= ' (UID: ' . $users->cid . ')';
        }
        $users_list[] = $user_details;
      }
      $row[] = ['data' => ['#theme' => 'item_list', '#items' => $users_list]];

      // Add a link to allow people to add items this entry.
      $add_url = Url::fromRoute('sentinel_portal_user_map.update_user', [], ['query' => ['location' => $location->tid]]);
      $row[] = ['data' => Link::fromTextAndUrl($this->t('Add existing user'), $add_url)->toRenderable()];

      $rows[] = $row;
    }

    $table = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
    ];

    $add_new_url = Url::fromRoute('sentinel_portal_user_map.add_user');
    $add_new_link = Link::fromTextAndUrl($this->t('Add new user'), $add_new_url)->toRenderable();

    return [
      'table' => $table,
      'add_new' => [
        '#markup' => '<p>' . render($add_new_link) . '</p>',
      ],
    ];
  }

  /**
   * Autocomplete callback for user search.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with matching users.
   */
  public function autocomplete(Request $request) {
    $string = $request->query->get('q', '');
    
    if (empty($string)) {
      return new JsonResponse([]);
    }

    // Get a list together of the user cohorts that this user has access to.
    $map_values = sentinel_portal_user_map_values();

    $tids = [];
    foreach ($map_values as $map) {
      $tids[] = $map->tid;
    }

    if (empty($tids)) {
      return new JsonResponse([]);
    }

    $query = \Drupal::database()->select('users_field_data', 'u')->distinct();
    $query->fields('u', ['uid', 'name', 'mail']);
    $query->leftJoin('sentinel_client', 'sc', 'sc.uid = u.uid');
    $query->leftJoin('field_data_field_user_cohorts', 'uc', 'sc.cid = uc.entity_id');
    $query->condition('u.mail', '%' . $query->escapeLike($string) . '%', 'LIKE');
    $query->condition('uc.field_user_cohorts_tid', $tids, 'IN');
    $query->fields('sc', ['uid', 'cid']);
    $result = $query->execute()->fetchAll();

    $matches = [];

    if ($result) {
      // Save the query to matches.
      foreach ($result as $row) {
        $matches[$row->mail . ' | ' . $row->uid . ' | ' . $row->cid] = $row->mail . ' | ' . $row->uid . ' | ' . $row->cid;
      }
    }

    // Return the result to the form in json.
    return new JsonResponse($matches);
  }

}


