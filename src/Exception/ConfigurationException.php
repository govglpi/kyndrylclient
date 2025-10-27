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
 * Configuration Exception - throw when configuration is invalid
 */
class ConfigurationException extends Exception
{
  /**
   * Create exception for missing configuration
   * 
   * @param string $configKey Missing configuration key
   * @return self
   */
  public static function missingConfig(string $configKey): self
  {
    return new self("Missing required configuration: {$configKey}");
  }

  /**
   * Create exception for invalid configuration
   * 
   * @param string $configKey Invalid configuration key
   * @param string $reason Reason why it's invalid
   * @return self
   */
  public static function invalidConfig(string $configKey, string $reason): self
  {
    return new self("Invalid configuration '{$configKey}': {$reason}");
  }

  /**
   * Create exception for missing dependencies
   * 
   * @param string $dependency Missing dependency name
   * @return self
   */
  public static function missingDependency(string $dependency): self
  {
    return new self("Missing required dependency: {$dependency}");
  }
}
