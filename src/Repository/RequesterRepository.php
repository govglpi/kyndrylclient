<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Repository;

/**
 * Requester Repository
 * Handles requester mapping data access
 */
class RequesterRepository
{
  private const TABLE_NAME = 'glpi_plugin_kyndrylclient_requesters';

  /**
   * Get requester by ticket ID
   * 
   * @param int $ticketId
   * @return array|null Requester data or null
   */
  public function getByTicketId(int $ticketId): ?array
  {
    global $DB;

    $result = $DB->request([
      'FROM' => self::TABLE_NAME,
      'WHERE' => ['ticket_id' => $ticketId],
      'LIMIT' => 1,
    ])->current();

    return $result ?: null;
  }

  /**
   * Get requester by Kyndryl requester ID
   * 
   * @param string $requesterId kyndryl requester ID
   * @return array|null Requester data or null
   */
  public function getByRequesterId(string $requesterId): ?array
  {
    global $DB;

    $result = $DB->request([
      'FROM' => self::TABLE_NAME,
      'WHERE' => ['requester_id' => $requesterId],
      'LIMIT' => 1,
    ])->current();

    return $result ?: null;
  }

  /**
   * Create requester mapping
   * 
   * @param array $data Requester data
   * @return int|false Inserted ID or false on failure
   */
  public function create(array $data)
  {
    global $DB;

    // Check if mapping already exists
    if (!empty($data['ticket_id'])) {
      $existing = $this->getByTicketId($data['ticket_id']);
      if ($existing) {
        return $existing['id'];
      }
    }

    return $DB->insert(self::TABLE_NAME, [
      'requester_id' => $data['requester_id'] ?? '',
      'ticket_id' => $data['ticket_id'] ?? 0,
    ]);
  }

  /**
   * Update requester mapping
   * 
   * @param int $id Requester ID
   * @param array $data Update data
   * @return bool Success
   */
  public function update(int $id, array $data): bool
  {
    global $DB;

    return $DB->update(
      self::TABLE_NAME,
      $data,
      ['id' => $id]
    );
  }

  /**
   * Delete requester mapping
   * 
   * @param int $id Requester ID
   * @return bool Success
   */
  public function delete(int $id): bool
  {
    global $DB;

    return $DB->delete(
      self::TABLE_NAME,
      ['id' => $id]
    );
  }

  /**
   * Delete requester mapping by ticket ID
   * 
   * @param int $ticketId Ticket ID
   * @return bool Success
   */
  public function deleteByTicketId(int $ticketId): bool
  {
    global $DB;

    return $DB->delete(
      self::TABLE_NAME,
      ['ticket_id' => $ticketId]
    );
  }

  /**
   * Get all requesters
   * 
   * @param int $limit Limit
   * @param int $offset Offset
   * @return array Requesters
   */
  public function getAll(int $limit = 100, int $offset = 0): array
  {
    global $DB;

    $requesters = [];

    $iterator = $DB->request([
      'FROM' => self::TABLE_NAME,
      'ORDER' => 'id DESC',
      'LIMIT' => $limit,
      'START' => $offset,
    ]);

    foreach ($iterator as $row) {
      $requesters[] = $row;
    }

    return $requesters;
  }

  /**
   * Count total requesters
   * 
   * @return int Total count
   */
  public function count(): int
  {
    global $DB;

    $result = $DB->request([
      'COUNT' => 'cpt',
      'FROM' => self::TABLE_NAME,
    ])->current();

    return (int) ($result['cpt'] ?? 0);
  }
}
