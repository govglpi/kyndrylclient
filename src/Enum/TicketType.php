<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Enum;

/** 
 * Ticket type enum
 */
enum TicketType: int
{
  case INCIDENT = 1;
  case REQUEST = 2;

  /**
   * Get API endpoint for ticket type
   * 
   * @return string API endpoint
   */
  public function getApiEndpoint(): string
  {
    return match ($this) {
      self::INCIDENT => 'incident',
      self::REQUEST => 'sr'
    };
  }

  /**
   * Get translated label
   * 
   * @return string Translated label
   */
  public function getLabel(): string
  {
    return match ($this) {
      self::INCIDENT => __('Incident', 'kyndrylclient'),
      self::REQUEST => __('Service Request', 'kyndrylclient'),
    };
  }

  /**
   * Create from GLPI ticket type
   * 
   * @param int $glpiType GLPI ticket type
   * @return self
   */
  public static function fromGlpiType(int $glpiType): self
  {
    return match ($glpiType) {
      1 => self::INCIDENT,
      2 => self::REQUEST,
      default => self::INCIDENT,
    };
  }
}
