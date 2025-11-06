<?php

namespace Drupal\sentinel_portal_sample\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Generic AJAX command that passes raw data to JavaScript.
 */
class GenericDataCommand implements CommandInterface {

  /**
   * The command name to invoke on the client side.
   *
   * @var string
   */
  protected string $command;

  /**
   * Arbitrary data payload.
   *
   * @var array
   */
  protected array $data;

  /**
   * Construct a new generic command.
   *
   * @param string $command
   *   The command name expected by JavaScript.
   * @param array $data
   *   The data payload to send.
   */
  public function __construct(string $command, array $data = []) {
    $this->command = $command;
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => $this->command,
      'data' => $this->data,
    ];
  }

}


