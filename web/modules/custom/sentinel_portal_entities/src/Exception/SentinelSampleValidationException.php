<?php

namespace Drupal\sentinel_portal_entities\Exception;

/**
 * Exception thrown when sample validation fails (matches D7 behaviour).
 */
class SentinelSampleValidationException extends \Exception {

  /**
   * The list of validation errors.
   *
   * @var array
   */
  protected array $errors = [];

  /**
   * SentinelSampleValidationException constructor.
   *
   * @param array $errors
   *   List of validation errors keyed by field name.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Exception code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(array $errors, string $message = '', int $code = 0, \Throwable $previous = NULL) {
    $this->errors = $errors;
    parent::__construct($message, $code, $previous);
  }

  /**
   * Get validation errors.
   *
   * @return array
   *   Validation errors keyed by field name.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Set validation errors.
   *
   * @param array $errors
   *   Validation errors keyed by field name.
   */
  public function setErrors(array $errors): void {
    $this->errors = $errors;
  }

}


