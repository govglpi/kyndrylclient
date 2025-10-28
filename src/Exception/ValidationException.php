<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Exception;

use Exception;

/**
 * Validation Exception - thrown when validation fails.
 */
class ValidationException extends Exception
{
  private array $errors = [];

  /**
   * Constructor
   * 
   * @param string $message Error message
   * @param array $errors Validation errors
   * @param int $code Error code
   * @param \Throwable|null $previous Previous exception
   */
  public function __construct(
    string $message = "Validation failed",
    array $errors = [],
    int $code = 0,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, $code, $previous);
    $this->errors = $errors;
  }

  /**
   * Get validation errors
   * 
   * @return array
   */
  public function getErrors(): array
  {
    return $this->errors;
  }

  /**
   * Check if has specific error
   * 
   * @param string $field Field name
   * @return bool
   */
  public function hasError(string $field): bool
  {
    return isset($this->errors[$field]);
  }

  /**
   * Get error for specific field
   * @param string $field Field name
   * @return string|null
   */
  public function getError(string $field): ?string
  {
    return $this->errors[$field] ?? null;
  }
}
