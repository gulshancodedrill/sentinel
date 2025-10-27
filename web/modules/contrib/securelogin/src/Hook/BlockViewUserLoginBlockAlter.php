<?php

namespace Drupal\securelogin\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements hook_block_view_BASE_BLOCK_ID_alter().
 */
#[Hook('block_view_user_login_block_alter')]
class BlockViewUserLoginBlockAlter {

  /**
   * Implements hook_block_view_BASE_BLOCK_ID_alter().
   *
   * @param mixed[] $build
   *   The build array.
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block.
   */
  public function __invoke(array &$build, BlockPluginInterface $block): void {
    // User module alters the form action after the user login block is built,
    // so now we may need to re-secure it.
    if (!isset($build['#pre_render']) || \is_array($build['#pre_render'])) {
      $build['#pre_render'][] = 'securelogin.manager:userLoginBlockPreRender';
    }
  }

}
