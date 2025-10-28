<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Repository;

/**
 * Configuration Repository
 * Handles configuration data access
 */
class ConfigurationRepository
{
  private const TABLE_NAME = 'glpi_plugin_kyndrylclient_configs';

  /**
   * Get plugin configuration
   * 
   * @return array Configuration data
   */
  public function getConfig(): array
  {
    global $DB;

    $result = $DB->request([
      'FROM' => self::TABLE_NAME,
      'WHERE' => ['id' => 1],
      'LIMIT' => 1,
    ])->current();

    if (!$result) {
      return $this->getDefaultConfig();
    }

    // Decrypt password if encrypted
    if (!empty($result['pwd'])) {
      $result['pwd'] = $this->decryptPassword($result['pwd']);
    }

    return $result;
  }

  /**
   * Update configuration
   * 
   * @param array $data Configuration data
   * @return bool Success
   */
  public function updateConfig(array $data): bool
  {
    global $DB;

    // Encrypt password if provided
    if (isset($data['pwd']) && !empty($data['pwd'])) {
      $data['pwd'] = $this->encryptPassword($data['pwd']);
    }

    return $DB->update(
      self::TABLE_NAME,
      $data,
      ['id' => 1]
    );
  }

  /**
   * Get default configuration
   * 
   * @return array
   */
  private function getDefaultConfig(): array
  {
    return [
      'id' => 1,
      'base_url' => '',
      'login' => '',
      'pwd' => '',
      'request_user' => 0,
      'request_type' => 0,
      'group' => 0,
    ];
  }

  /**
   * Encrypt password
   * 
   * @param string $password Plain password
   * @return string Encrypted password
   */
  private function encryptPassword(string $password): string
  {
    if (function_exists('\\Toolbox::sodiumEncrypt')) {
      return \Toolbox::sodiumEncrypt($password);
    }

    // Fallback to base64
    return base64_encode($password);
  }

  /**
   * Decrypt password
   * 
   * @param string $encrypted Encrypted password
   * @return string Plain password
   */
  private function decryptPassword(string $encrypted): string
  {
    if (function_exists('\\Toolbox::sodiumDecrypt')) {
      try {
        return \Toolbox::sodiumDecrypt($encrypted);
      } catch (\Throwable $e) {
        // If decryption fails, fallback to base64
      }
    }

    // Fallback to base64
    $decoded = base64_decode($encrypted, true);
    return $decoded !== false ? $decoded : $encrypted;
  }

  /**
   * Validate configuration
   * 
   * @param array $config Configuration data
   * @return array Validation errors
   */
  public function validateConfig(array $config): array
  {
    $errors = [];

    if (empty($config['base_url'])) {
      $errors['base_url'] = __('Base URL is required', 'kyndrylclient');
    } elseif (!filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
      $errors['base_url'] = __('Invalid URL format', 'kyndrylclient');
    }

    if (empty($config['login'])) {
      $errors['login'] = __('Login is required', 'kyndrylclient');
    }

    if (empty($config['pwd'])) {
      $errors['pwd'] = __('Password is required', 'kyndrylclient');
    }

    if (empty($config['request_user']) || $config['request_user'] <= 0) {
      $errors['request_user'] = __('API user is required', 'kyndrylclient');
    }

    if (empty($config['request_type']) || $config['request_type'] <= 0) {
      $errors['request_type'] = __('Request type is required', 'kyndrylclient');
    }

    if (empty($config['group']) || $config['group'] <= 0) {
      $errors['group'] = __('Group is required', 'kyndrylclient');
    }

    return $errors;
  }

  /**
   * Check if configuration is valid
   * 
   * @return bool
   */
  public function isConfigured(): bool
  {
    $config = $this->getConfig();
    $errors = $this->validateConfig($config);
    return empty($errors);
  }
}
