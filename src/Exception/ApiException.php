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
 * API Exception - throw when API communication fails
 */
class ApiException extends Exception
{
  private ?array $response;
  private ?int $statusCode;

  /**
   * Constructor
   * 
   * @param string $message Error message
   * @param int $code Error code
   * @param \Throwable|null $previous Previous exception
   * @param int|null $statusCode HTTP status code
   * @param array|null $response API response data
   */
  public function __construct(
    string $message = "",
    int $code = 0,
    ?\Throwable $previous = null,
    ?int $statusCode = null,
    ?array $response = null
  ) {
    parent::__construct($message, $code, $previous);
    $this->statusCode = $statusCode;
    $this->response = $response;
  }

  /**
   * Get HTTP status code
   * 
   * @return int|null
   */
  public function getStatusCode(): ?int
  {
    return $this->statusCode;
  }

  /**
   * Get API response data
   * 
   * @return array|null
   */
  public function getResponse(): ?array
  {
    return $this->response;
  }

  /**
   * Check if error is retryable
   * 
   * @return bool
   */
  public function isRetryable(): bool
  {
    return in_array($this->statusCode, [408, 429, 500, 502, 503, 504]);
  }
}
