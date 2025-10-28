<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Service;

use GlpiPlugin\KyndrylClient\Exception\ApiException;
use GlpiPlugin\KyndrylClient\Exception\ConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Kyndryl API Client
 * Handles all HTTP communications with Kyndryl API
 */
class KyndrylApiClient
{
  private const DEFAULT_TIMEOUT = 30;
  private const DEFAULT_RETRY_ATTEMPTS = 3;
  private const RETRY_DELAY_MS = 1000;

  private string $baseUrl;
  private array $credentials;
  private Client $httpClient;
  private int $timeout;
  private int $retryAttempts;

  /**
   * Constructor
   * 
   * @param string $baseUrl Base URL of the API
   * @param string $username API username
   * @param string $password API password
   * @param int $timeout Request timeout in seconds
   * @param int $retryAttempts Number of retry attempts
   * @throws ConfigurationException
   */
  public function __construct(
    string $baseUrl,
    string $username,
    string $password,
    int $timeout = self::DEFAULT_TIMEOUT,
    int $retryAttempts = self::DEFAULT_RETRY_ATTEMPTS
  ) {
    $this->validateConfig($baseUrl, $username, $password);

    $this->baseUrl = rtrim($baseUrl, '/') . '/';
    $this->credentials = [$username, $password];
    $this->timeout = $timeout;
    $this->retryAttempts = $retryAttempts;

    $this->httpClient = new Client([
      'timeout' => $this->timeout,
      'verify' => true,
      'http_errors' => false,
    ]);
  }

  /**
   * Validate configuration
   * 
   * @param string $baseUrl
   * @param string $username
   * @param string $password
   * @throws ConfigurationException
   */
  private function validateConfig(string $baseUrl, string $username, string $password): void
  {
    if (empty($baseUrl)) {
      throw ConfigurationException::missingConfig('base_url');
    }

    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
      throw ConfigurationException::invalidConfig('base_url', 'Invalid URL format');
    }

    if (empty($username)) {
      throw ConfigurationException::missingConfig('username');
    }

    if (empty($password)) {
      throw ConfigurationException::missingConfig('password');
    }
  }

  /**
   * Create a new ticket in Kyndryl
   * 
   * @param string $endpoint API endpoint (incident/sr)
   * @param array $data Ticket data
   * @return array Response data
   * @throws ApiException
   */
  public function createTicket(string $endpoint, array $data): array
  {
    return $this->request('POST', "{$endpoint}/create", $data);
  }

  /**
   * update an existing ticket in Kyndryl
   * 
   * @param string $endpoint API endpoint (incident/sr)
   * @param string $requesterId Kyndryl requester ID
   * @param array $data Update data
   * @return array Response data
   * @throws ApiException
   */
  public function updateTicket(string $endpoint, string $requesterId, array $data): array
  {
    return $this->request('PUT', "{$endpoint}/{$requesterId}", $data);
  }

  /**
   * Make HTTP request to API
   *
   * @param string $method HTTP method
   * @param string $endpoint API endpoint
   * @param array $data Request data
   * @return array Response data
   * @throws ApiException
   */
  private function request(string $method, string $endpoint, array $data): array
  {
    $url = $this->baseUrl . ltrim($endpoint, '/');
    $attempt = 0;

    while ($attempt < $this->retryAttempts) {
      try {
        $response = $this->httpClient->request($method, $url, [
          'auth' => $this->credentials,
          'json' => $data,
          'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate',
            'User-Agent' => 'GLPI-Kyndryl-Client' . PLUGIN_KYNDRYLCLIENT_VERSION,
          ],
        ]);

        return $this->handleResponse($response);
      } catch (GuzzleException $e) {
        $attempt++;

        if ($attempt >= $this->retryAttempts) {
          throw new ApiException(
            "API request failed after {$this->retryAttempts} attempts: " . $e->getMessage(),
            $e->getCode(),
            $e
          );
        }

        // Wait before retry
        usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
      }
    }

    throw new ApiException("Failed to complete API request");
  }

  /**
   * Handle API response
   *
   * @param ResponseInterface $response HTTP response
   * @return array Decoded response data
   * @throws ApiException
   */
  private function handleResponse(ResponseInterface $response): array
  {
    $statusCode = $response->getStatusCode();
    $body = (string) $response->getBody();

    // Try to decode JSON response
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new ApiException(
        "Invalid JSON response: " . json_last_error_msg(),
        0,
        null,
        $statusCode
      );
    }

    // Check for success status codes
    if ($statusCode >= 200 && $statusCode < 300) {
      return $data ?? [];
    }

    // Handle error responses
    $errorMessage = $this->extractErrorMessage($data, $statusCode);

    throw new ApiException(
      $errorMessage,
      0,
      null,
      $statusCode,
      $data
    );
  }

  /**
   * Extract error message from response
   *
   * @param array|null $data Response data
   * @param int $statusCode HTTP status code
   * @return string Error message
   */
  private function extractErrorMessage(?array $data, int $statusCode): string
  {
    if (isset($data['error'])) {
      return is_array($data['error'])
        ? ($data['error']['message'] ?? json_encode($data['error']))
        : (string) $data['error'];
    }

    if (isset($data['message'])) {
      return (string) $data['message'];
    }

    return "API request failed with status code: {$statusCode}";
  }

  /**
   * Get base URL
   *
   * @return string
   */
  public function getBaseUrl(): string
  {
    return $this->baseUrl;
  }

  /**
   * Test API connectivity
   *
   * @return bool True if connection successful
   */
  public function testConnection(): bool
  {
    try {
      // Attempt a simple request to validate credentials
      $response = $this->httpClient->request('GET', $this->baseUrl, [
        'auth' => $this->credentials,
        'timeout' => 10,
      ]);

      return $response->getStatusCode() < 500;
    } catch (\Throwable $e) {
      return false;
    }
  }
}
