<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Service;

/**
 * File Attachment Service
 * Handles file attachment operations for ticket synchronization
 */
class FileAttachmentService
{
  private const MAX_FILE_SIZE = 10485760;
  private const CHUNK_SIZE = 8192;

  /**
   * Get all attachments for a ticket
   * 
   * @param int $ticketId Ticket ID
   * @return array Array of attachment data
   */
  public function getTicketAttachments(int $ticketId): array
  {

    global $DB;

    $attachments = [];
    $documentItemTable = \Document_Item::getTable();

    // Get all document IDs linked to this ticket
    $iterator = $DB->request([
      'SELECT' => ['documents_id'],
      'FROM' => $documentItemTable,
      'WHERE' => [
        'itemtype' => 'Ticket',
        'items_id' => $ticketId,
      ],
    ]);

    $documentIds = [];
    foreach ($iterator as $row) {
      $documentIds[] = $row['documents_id'];
    }

    if (empty($documentIds)) {
      return [];
    }

    // Get document details
    $docIterator = $DB->request([
      'FROM' => \Document::getTable(),
      'WHERE' => [
        'id' => $documentIds
      ]
    ]);

    foreach ($docIterator as $doc) {
      $attachment = $this->prepareAttachment($doc);
      if ($attachment !== null) {
        $attachments[] = $attachment;
      }
    }

    return $attachments;
  }

  /**
   * Get latest attachment for a ticket (including followups)
   * 
   * @param int $ticketId Ticket ID
   * @return array|null Attachment data or null
   */
  public function getLatestAttachment(int $ticketId): ?array
  {
    global $DB;

    $documentItemTable = \Document_Item::getTable();

    // Get followup IDs
    $followupIds = [];
    $fupIterator = $DB->request([
      'SELECT' => ['id'],
      'FROM' => 'glpi_itilfollowups',
      'WHERE' => [
        'itemtype' => 'Ticket',
        'items_id' => $ticketId,
      ],
    ]);

    foreach ($fupIterator as $row) {
      $followupIds[] = $row['id'];
    }

    //Build WHERE clause
    $where = [
      'OR' => [
        [
          'itemtype' => 'Ticket',
          'items_id' => $ticketId,
        ],
      ],
    ];

    if (!empty($followupIds)) {
      $where['OR'][] = [
        'itemtype' => 'ITILFollowup',
        'items_id' => $followupIds
      ];
    }

    // Get latest document
    $docItem = $DB->request([
      'SELECT' => ['documents_id'],
      'FROM' => $documentItemTable,
      'WHERE' => $where,
      'ORDER BY' => 'id DESC',
      'LIMIT' => 1,
    ])->current();

    if (!$docItem) {
      return null;
    }

    // Get document details
    $doc = $DB->request([
      'FROM' => \Document::getTable(),
      'WHERE' => ['id' => $docItem['documents_id']],
    ])->current();

    return $doc ? $this->prepareAttachment($doc) : null;
  }

  /**
   * Prepare attachment data for API
   * 
   * @param array $document Document data from DB
   * @return array|null Prepared attachment or null if file doesn't exist
   */
  private function prepareAttachment(array $document): ?array
  {
    $filePath = GLPI_DOC_DIR . '/' . $document['filepath'];

    if (!file_exists($filePath) || !is_readable($filePath)) {
      \Toolbox::logWarning("File not found or not readable: {$filePath}");
      return null;
    }

    $fileSize = filesize($filePath);

    if ($fileSize > self::MAX_FILE_SIZE) {
      \Toolbox::logWarning("File too large ({$fileSize} bytes): {$filePath}");
      return null;
    }

    try {
      $content = $this->readFileInChunks($filePath);

      return [
        'AttachmentName' => $document['filename'],
        'Attachment' => base64_encode($content),
      ];
    } catch (\Throwable $e) {
      \Toolbox::logError("Error reading file {$filePath}: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Read file in chunks to avoid memory issues
   * 
   * @param string $filePath File path
   * @return string File content
   * @throws \RuntimeException
   */
  private function readFileInChunks(string $filePath): string
  {
    $handle = fopen($filePath, 'rb');

    if ($handle === false) {
      throw new \RuntimeException("Cannot open file: {$filePath}");
    }

    $content = '';

    try {
      while (!feof($handle)) {
        $chunk = fread($handle, self::CHUNK_SIZE);
        if ($chunk === false) {
          throw new \RuntimeException("Error reading file: {$filePath}");
        }
        $content .= $chunk;
      }
    } finally {
      fclose($handle);
    }

    return $content;
  }

  /**
   * Validate file extension
   * 
   * @param string $filename Filename
   * @param array $allowedExtensions Allowed extensions
   * @return bool
   */
  public function validateFileExtension(string $filename, array $allowedExtensions = []): bool
  {
    if (empty($allowedExtensions)) {
      return true;
    }

    $extensions = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extensions, $allowedExtensions);
  }

  /**
   * Get file size in human readable format
   * 
   * @param int $bytes File size in bytes
   * @return string Human readable size
   */
  public function formatFileSize(int $bytes): string
  {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
      $bytes /= 1024;
      $i++;
    }

    return round($bytes, 2) . ' ' . $units[$i];
  }
}
