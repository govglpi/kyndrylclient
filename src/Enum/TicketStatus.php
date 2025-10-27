<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Enum;

/**
 * Ticket status enum
 */
enum TicketStatus: int
{
  case NEW = 1;
  case PROCESSING_ASSIGNED = 2;
  case PROCESSING_PLANNED = 3;
  case PENDING = 4;
  case SOLVED = 5;
  case CLOSED = 6;

  /**
   * Get summary message for status
   * 
   * @return string Summary message or null
   */
  public function getSummary(): ?string
  {
    return match ($this) {
      self::SOLVED => __('Solution provided in GLPI', 'kyndrylclient'),
      self::CLOSED => __('Ticket closed in GLPI', 'kyndrylclient'),
      default => null,
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
      self::NEW => __('New', 'kyndrylclient'),
      self::PROCESSING_ASSIGNED => __('Processing (assigned)', 'kyndrylclient'),
      self::PROCESSING_PLANNED => __('Processing (planned)', 'kyndrylclient'),
      self::PENDING => __('Pending', 'kyndrylclient'),
      self::SOLVED => __('Solved', 'kyndrylclient'),
      self::CLOSED => __('Closed', 'kyndrylclient'),
    };
  }

  /**
   * Check if status requires solution sync
   * 
   * @return bool True if solution should be synced
   */
  public function requiresSolutionSync(): bool
  {
    return match ($this) {
      self::SOLVED, self::CLOSED => true,
      default => false,
    };
  }

  /**
   * Check if status is final
   * 
   * @return bool True if status is final
   */
  public function isFinal(): bool
  {
    return match ($this) {
      self::SOLVED, self::CLOSED => true,
      default => false,
    };
  }
}
