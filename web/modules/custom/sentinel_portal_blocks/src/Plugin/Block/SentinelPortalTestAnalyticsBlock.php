<?php

namespace Drupal\sentinel_portal_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->currentUser = $current_user;
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
      $container->get('current_user')
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

    // For now, return sample data since we don't have the full entity structure
    $passed = 0;
    $failed = 0;
    $pending = 0;
    $total = 0;

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

}
