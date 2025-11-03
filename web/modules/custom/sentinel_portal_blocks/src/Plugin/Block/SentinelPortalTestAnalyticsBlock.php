<?php

namespace Drupal\sentinel_portal_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\sentinel_portal_entities\Entity\SentinelClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Sentinel Portal Test Analytics block.
 *
 * @Block(
 *   id = "sentinel_portal_test_analytics",
 *   admin_label = @Translation("Sentinel Portal Test Analytics"),
 *   category = @Translation("Sentinel Portal")
 * )
 */
class SentinelPortalTestAnalyticsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SentinelPortalTestAnalyticsBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Check if user has access to sentinel portal.
    if (!$this->currentUser->hasPermission('sentinel portal')) {
      return $build;
    }

    $client = $this->loadClientForUser($this->currentUser);
    if (!$client instanceof SentinelClient) {
      return $build;
    }

    $ucr = $client->get('ucr')->value ?? NULL;
    if (empty($ucr)) {
      return $build;
    }

    $client_ids = $this->getAccessibleClientIds($client);
    if (empty($client_ids)) {
      return $build;
    }

    $passed = $this->countSamplesByStatus($client_ids, 'passed');
    $failed = $this->countSamplesByStatus($client_ids, 'failed');
    $pending = $this->countSamplesByStatus($client_ids, 'pending');
    $total = $passed + $failed + $pending;

    $build = [
      '#theme' => 'sentinel_portal_analytics_block',
      '#passed' => $passed,
      '#failed' => $failed,
      '#pending' => $pending,
      '#total' => $total,
      '#cache' => [
        'contexts' => ['user'],
        'max-age' => 300, // 5 minutes cache.
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'sentinel portal');
  }

  /**
   * Load the sentinel client associated with the given account.
   */
  protected function loadClientForUser(AccountInterface $account): ?SentinelClient {
    if ($account->isAnonymous()) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('sentinel_client');

    $cid = $this->database->select('sentinel_client', 'sc')
      ->fields('sc', ['cid'])
      ->condition('sc.uid', $account->id())
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$cid) {
      $mail = $account->getEmail();
      if (!empty($mail)) {
        $cid = $this->database->select('sentinel_client', 'sc')
          ->fields('sc', ['cid'])
          ->where('LOWER(sc.email) = LOWER(:mail)', [':mail' => $mail])
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }
    }

    if (empty($cid)) {
      return NULL;
    }

    $client = $storage->load($cid);
    return $client instanceof SentinelClient ? $client : NULL;
  }

  /**
   * Get the list of client IDs accessible to the provided client via cohorts.
   */
  protected function getAccessibleClientIds(SentinelClient $client): array {
    $ids = [];
    if (function_exists('get_more_clients_based_client_cohorts')) {
      $ids = get_more_clients_based_client_cohorts($client) ?: [];
    }

    $ids[] = (int) $client->id();
    $ids = array_unique(array_filter($ids));

    return $ids;
  }

  /**
   * Count samples by status for the provided client ids.
   */
  protected function countSamplesByStatus(array $client_ids, string $status): int {
    if (empty($client_ids)) {
      return 0;
    }

    $query = $this->database->select('sentinel_sample', 'ss');
    $query->addExpression('COUNT(DISTINCT ss.pid)', 'count');
    $query->leftJoin('sentinel_client', 'sc', 'sc.ucr = ss.ucr');
    $query->condition('sc.cid', $client_ids, 'IN');

    switch ($status) {
      case 'passed':
        $query->condition('ss.pass_fail', 1);
        break;

      case 'failed':
        $query->condition('ss.pass_fail', 0);
        break;

      case 'pending':
        $query->isNull('ss.pass_fail');
        break;
    }

    return (int) $query->execute()->fetchField();
  }

}
