<?php

namespace Drupal\sentinel_portal_entities\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\sentinel_portal_entities\Entity\SentinelSample;

/**
 * Access checker for sentinel sample canonical pages.
 */
class SentinelSampleAccess extends ControllerBase {

  /**
   * Determines access to the sample edit form.
   */
  public function accessEdit(AccountInterface $account, SentinelSample $sentinel_sample) {
    // Use the same logic as view access for edit.
    return $this->access($account, $sentinel_sample);
  }

  /**
   * Determines access to the sample view.
   */
  public function access(AccountInterface $account, SentinelSample $sentinel_sample) {
    $allowed = AccessResult::allowed()->cachePerPermissions()->cachePerUser();
    $forbidden = AccessResult::forbidden()->cachePerPermissions()->cachePerUser();

    // Admins can view any sample.
    if ($account->hasPermission('sentinel view all sentinel_sample') || $account->hasPermission('sentinel portal administration')) {
      return $allowed;
    }

    // Anonymous users cannot view samples.
    if (!$account->isAuthenticated()) {
      return $forbidden;
    }

    // Get sample UCR from either 'ucr' or 'customer_id' field.
    $sample_ucr = NULL;
    if ($sentinel_sample->hasField('ucr') && !$sentinel_sample->get('ucr')->isEmpty()) {
      $sample_ucr = $sentinel_sample->get('ucr')->value;
    }
    elseif ($sentinel_sample->hasField('customer_id') && !$sentinel_sample->get('customer_id')->isEmpty()) {
      $sample_ucr = $sentinel_sample->get('customer_id')->value;
    }
    
    // Fallback: Query database directly if entity field is empty (UCR is stored in base table).
    if (($sample_ucr === NULL || $sample_ucr === '') && $sentinel_sample->id()) {
      $db_result = \Drupal::database()->select('sentinel_sample', 'ss')
        ->fields('ss', ['ucr', 'customer_id'])
        ->condition('ss.pid', $sentinel_sample->id())
        ->execute()
        ->fetchObject();
      
      if ($db_result) {
        if (!empty($db_result->ucr)) {
          $sample_ucr = $db_result->ucr;
        }
        elseif (!empty($db_result->customer_id)) {
          $sample_ucr = $db_result->customer_id;
        }
      }
    }

    $client = sentinel_portal_entities_get_client_by_user($account);
    
    // Get client UCR - use getRealUcr() method if available, otherwise fallback to field value.
    $client_ucr = NULL;
    if ($client) {
      if (method_exists($client, 'getRealUcr')) {
        $client_ucr = $client->getRealUcr();
      }
      else {
        $client_ucr = $client->get('ucr')->value ?? NULL;
      }
    }
    
    // Debug logging (remove after testing).
    \Drupal::logger('sentinel_sample_access')->debug('Access check: User @uid, Sample @pid, Sample UCR: @sample_ucr, Client: @client, Client UCR: @client_ucr', [
      '@uid' => $account->id(),
      '@pid' => $sentinel_sample->id(),
      '@sample_ucr' => $sample_ucr ?? 'NULL',
      '@client' => $client ? 'Found (CID: ' . $client->id() . ')' : 'Not found',
      '@client_ucr' => $client_ucr ?? 'NULL',
    ]);

    // Logged-in users can view their own samples (UCR match) without needing a specific permission.
    if ($client && $sample_ucr !== NULL && $sample_ucr !== '') {
      // Normalize both values to strings and trim whitespace for comparison.
      $client_ucr_str = $client_ucr !== NULL ? trim((string) $client_ucr) : NULL;
      $sample_ucr_str = trim((string) $sample_ucr);
      
      if ($client_ucr_str !== NULL && $client_ucr_str !== '' && $client_ucr_str === $sample_ucr_str) {
        \Drupal::logger('sentinel_sample_access')->debug('Access granted: UCR match (@client_ucr === @sample_ucr)', [
          '@client_ucr' => $client_ucr_str,
          '@sample_ucr' => $sample_ucr_str,
        ]);
        return $allowed;
      }
      else {
        \Drupal::logger('sentinel_sample_access')->debug('UCR mismatch: Client UCR "@client_ucr" !== Sample UCR "@sample_ucr"', [
          '@client_ucr' => $client_ucr_str ?? 'NULL',
          '@sample_ucr' => $sample_ucr_str,
        ]);
      }
    }

    // Check cohort hierarchy for users with the permission.
    if ($account->hasPermission('sentinel view own sentinel_sample') && $client && $sample_ucr !== NULL && $sample_ucr !== '') {
      $sample_client = sentinel_portal_entities_get_client_by_ucr($sample_ucr);
      if ($sample_client && function_exists('get_more_clients_based_client_cohorts')) {
        $cohort_ids = get_more_clients_based_client_cohorts($client) ?: [];
        if (in_array($sample_client->id(), $cohort_ids, TRUE)) {
          return $allowed;
        }
      }
    }

    \Drupal::logger('sentinel_sample_access')->warning('Access denied: User @uid, Sample @pid, Sample UCR: @sample_ucr, Client UCR: @client_ucr', [
      '@uid' => $account->id(),
      '@pid' => $sentinel_sample->id(),
      '@sample_ucr' => $sample_ucr ?? 'NULL',
      '@client_ucr' => $client_ucr ?? 'NULL',
    ]);

    return $forbidden;
  }

}

