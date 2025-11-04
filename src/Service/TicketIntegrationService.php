<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by GLPI Kyndryl Client Plugin Team
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\KyndrylClient\Service;

use GlpiPlugin\KyndrylClient\Enum\TicketStatus;
use GlpiPlugin\KyndrylClient\Enum\TicketType;
use GlpiPlugin\KyndrylClient\Exception\ApiException;
use GlpiPlugin\KyndrylClient\Repository\ConfigurationRepository;
use GlpiPlugin\KyndrylClient\Repository\RequesterRepository;

/**
 * Ticket Integration Service
 * Main service for handling ticket synchronization between GLPI and Kyndryl
 */
class TicketIntegrationService
{
  private static ?self $instance = null;

  private KyndrylApiClient $apiClient;
  private FileAttachmentService $fileService;
  private ConfigurationRepository $configRepo;
  private RequesterRepository $requesterRepo;

  /**
   * Private constructor (Singleton)
   */
  private function __construct()
  {
    $this->configRepo = new ConfigurationRepository();
    $this->requesterRepo = new RequesterRepository();
    $this->fileService = new FileAttachmentService();

    // Initialize API client with config
    $config = $this->configRepo->getConfig();
    $this->apiClient = new KyndrylApiClient(
      $config['base_url'],
      $config['login'],
      $config['pwd']
    );
  }

  /**
   * Get service instance (Singleton)
   *
   * @return self
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Handle ticket creation
   *
   * @param \Ticket $ticket Ticket object
   * @return void
   */
  public function handleTicketCreation(\Ticket $ticket): void
  {
    $config = $this->configRepo->getConfig();

    // Check if ticket is imported from Kyndryl
    if ($this->isKyndrylImportedTicket($ticket)) {
      $this->handleKyndrylImportedTicket($ticket);
      return;
    }

    // Check if ticket should be synced based on rules
    if (!$this->shouldSyncTicket($ticket, $config)) {
      return;
    }

    try {
      $this->createTicketInKyndryl($ticket, $config);
    } catch (ApiException $e) {
      $this->handleApiError($ticket, $e, 'creation');
    }
  }

  /**
   * Handle ticket update
   *
   * @param \Ticket $ticket Ticket object
   * @return void
   */
  public function handleTicketUpdate(\Ticket $ticket): void
  {
    $config = $this->configRepo->getConfig();

    // Check if ticket has Kyndryl requester ID
    $requester = $this->requesterRepo->getByTicketId($ticket->getID());

    if (!$requester) {
      return; // Not synced with Kyndryl
    }

    // Skip if update is from API user (prevent loops)
    if (\Session::getLoginUserID() == $config['request_user']) {
      return;
    }

    try {
      $this->updateTicketInKyndryl($ticket, $requester['requester_id'], $config);
    } catch (ApiException $e) {
      $this->handleApiError($ticket, $e, 'update');
    }
  }

  /**
   * Handle followup creation
   *
   * @param \ITILFollowup $followup Followup object
   * @return void
   */
  public function handleFollowupCreation(\ITILFollowup $followup): void
  {
    $config = $this->configRepo->getConfig();
    $ticketId = $followup->fields['items_id'];

    // Skip if followup is from API user or ticket is closed
    if (
      $followup->fields['users_id'] == $config['request_user']
      || $followup->fields['status'] == TicketStatus::CLOSED->value
    ) {
      return;
    }

    $requester = $this->requesterRepo->getByTicketId($ticketId);

    if (!$requester) {
      return; // Not synced with Kyndryl
    }

    try {
      $ticket = \Ticket::getById($ticketId);
      $this->syncFollowupToKyndryl($ticket, $followup, $requester['requester_id'], $config);
    } catch (ApiException $e) {
      $ticket = \Ticket::getById($ticketId);
      $this->handleApiError($ticket, $e, 'followup');
    }
  }

  /**
   * Check if ticket is imported from Kyndryl
   *
   * @param \Ticket $ticket
   * @return bool
   */
  private function isKyndrylImportedTicket(\Ticket $ticket): bool
  {
    $config = $this->configRepo->getConfig();

    if ($ticket->fields['requesttypes_id'] != $config['request_type']) {
      return false;
    }

    $titlePrefix = strtok($ticket->fields['name'], ' ');
    $prefix = substr($titlePrefix, 0, 2);

    return in_array($prefix, ['SR', 'IN']);
  }

  /**
   * Handle ticket imported from Kyndryl
   *
   * @param \Ticket $ticket
   * @return void
   */
  private function handleKyndrylImportedTicket(\Ticket $ticket): void
  {
    $requesterId = strtok($ticket->fields['name'], ' ');

    // Save requester mapping
    $this->requesterRepo->create([
      'requester_id' => $requesterId,
      'ticket_id' => $ticket->getID(),
    ]);

    // Add followup
    $this->addFollowup(
      $ticket,
      sprintf(__('Ticket originated from Kyndryl - RequesterID: %s', 'kyndrylclient'), $requesterId),
      $this->configRepo->getConfig()['request_user']
    );
  }

  /**
   * Check if ticket should be synced
   *
   * @param \Ticket $ticket
   * @param array $config
   * @return bool
   */
  private function shouldSyncTicket(\Ticket $ticket, array $config): bool
  {
    // Check if ticket type matches
    if ($ticket->fields['requesttypes_id'] != $config['request_type']) {
      return false;
    }

    // Check if assigned groups match rule
    $groups = $ticket->getGroups(\CommonITILActor::ASSIGN);
    $groupIds = array_column($groups, 'groups_id');

    $ruleGroups = getSonsOf('glpi_groups', $config['group']);
    $matchingGroups = array_intersect($ruleGroups, $groupIds);

    return !empty($matchingGroups);
  }

  /**
   * Create ticket in Kyndryl
   *
   * @param \Ticket $ticket
   * @param array $config
   * @return void
   * @throws ApiException
   */
  private function createTicketInKyndryl(\Ticket $ticket, array $config): void
  {
    $ticketType = TicketType::fromGlpiType($ticket->fields['type']);
    $attachments = $this->fileService->getTicketAttachments($ticket->getID());

    $groups = $ticket->getGroups(\CommonITILActor::ASSIGN);
    $assignedGroupId = !empty($groups) ? $groups[0]['groups_id'] : null;

    $data = [
      'ProviderID' => $ticket->getID(),
      'RequesterSeverity' => $ticket->fields['priority'],
      'WorkflowStatus' => $ticket->fields['status'],
      'Problem' => [
        'Abstract' => $ticket->fields['name'],
        'Description' => $this->stripHtmlTags($ticket->fields['content']),
        'OwnerGroup' => $assignedGroupId,
      ],
      'Attachment' => $attachments,
    ];

    $response = $this->apiClient->createTicket(
      $ticketType->getApiEndpoint(),
      $data
    );

    if (isset($response['RequesterID'])) {
      // Save requester mapping
      $this->requesterRepo->create([
        'requester_id' => $response['RequesterID'],
        'ticket_id' => $ticket->getID(),
      ]);

      // Add success followup
      $message = sprintf(
        __('Ticket sent to Kyndryl - RequesterID: %s', 'kyndrylclient'),
        $response['RequesterID']
      );
      $this->addFollowup($ticket, $message, $config['request_user']);

      \Session::addMessageAfterRedirect(
        __('Ticket sent to Kyndryl', 'kyndrylclient'),
        true,
        INFO
      );
    }
  }

  /**
   * Update ticket in Kyndryl
   *
   * @param \Ticket $ticket
   * @param string $requesterId
   * @param array $config
   * @return void
   * @throws ApiException
   */
  private function updateTicketInKyndryl(\Ticket $ticket, string $requesterId, array $config): void
  {
    global $DB;

    $ticketType = TicketType::fromGlpiType($ticket->fields['type']);
    $ticketStatus = TicketStatus::from($ticket->fields['status']);

    $groups = $ticket->getGroups(\CommonITILActor::ASSIGN);
    $assignedGroupId = !empty($groups) ? $groups[0]['groups_id'] : null;

    // Get latest followup
    $lastFollowup = $this->getLatestFollowup($ticket->getID(), $config['request_user']);
    $latestAttachment = $this->fileService->getLatestAttachment($ticket->getID());

    $data = [
      'ProviderID' => $ticket->getID(),
      'RequesterSeverity' => $ticket->fields['priority'],
      'WorkflowStatus' => $ticket->fields['status'],
      'Problem' => [
        'Abstract' => $ticket->fields['name'],
        'Description' => $this->stripHtmlTags($ticket->fields['content']),
        'OwnerGroup' => $assignedGroupId,
      ],
    ];

    // Add activity if there's a followup
    if ($lastFollowup) {
      $data['Activity'] = [[
        'Summary' => $ticketStatus->requiresSolutionSync()
          ? $ticketStatus->getSummary()
          : __('Worklog from GLPI', 'kyndrylclient'),
        'ActionLog' => $this->stripHtmlTags($lastFollowup['content']),
      ]];
    }

    // Add solution for solved/closed tickets
    if ($ticketStatus->requiresSolutionSync() && $lastFollowup) {
      $data['Solution'] = [[
        'Resolution' => $this->stripHtmlTags($lastFollowup['content']),
      ]];
    }

    // Add attachment if exists
    if ($latestAttachment) {
      $data['Attachment'] = $latestAttachment;
    }

    $this->apiClient->updateTicket(
      $ticketType->getApiEndpoint(),
      $requesterId,
      $data
    );

    // Add success followup
    $this->addFollowup(
      $ticket,
      __('Ticket updated in Kyndryl', 'kyndrylclient'),
      $config['request_user']
    );

    \Session::addMessageAfterRedirect(
      __('Ticket updated in Kyndryl', 'kyndrylclient'),
      true,
      INFO
    );
  }

  /**
   * Sync followup to Kyndryl
   *
   * @param \Ticket $ticket
   * @param \ITILFollowup $followup
   * @param string $requesterId
   * @param array $config
   * @return void
   * @throws ApiException
   */
  private function syncFollowupToKyndryl(\Ticket $ticket, \ITILFollowup $followup, string $requesterId, array $config): void
  {
    $ticketType = TicketType::fromGlpiType($ticket->fields['type']);
    $latestAttachment = $this->fileService->getLatestAttachment($ticket->getID());

    $groups = $ticket->getGroups(\CommonITILActor::ASSIGN);
    $assignedGroupId = !empty($groups) ? $groups[0]['groups_id'] : null;

    $data = [
      'ProviderID' => $ticket->getID(),
      'RequesterSeverity' => $ticket->fields['priority'],
      'WorkflowStatus' => $ticket->fields['status'],
      'Problem' => [
        'OwnerGroup' => $assignedGroupId,
      ],
      'Activity' => [[
        'ActionLog' => $this->stripHtmlTags($followup->fields['content']),
      ]],
    ];

    if ($latestAttachment) {
      $data['Attachment'] = $latestAttachment;
    }

    $this->apiClient->updateTicket(
      $ticketType->getApiEndpoint(),
      $requesterId,
      $data
    );

    // Add success followup
    $this->addFollowup(
      $ticket,
      __('Followup added to Kyndryl', 'kyndrylclient'),
      $config['request_user']
    );

    \Session::addMessageAfterRedirect(
      __('Followup added to Kyndryl', 'kyndrylclient'),
      true,
      INFO
    );
  }

  /**
   * Get latest followup (excluding API user)
   *
   * @param int $ticketId
   * @param int $excludeUserId
   * @return array|null
   */
  private function getLatestFollowup(int $ticketId, int $excludeUserId): ?array
  {
    global $DB;

    return $DB->request([
      'FROM' => 'glpi_itilfollowups',
      'WHERE' => [
        'itemtype' => 'Ticket',
        'items_id' => $ticketId,
        'users_id' => ['!=', $excludeUserId],
      ],
      'ORDER' => 'id DESC',
      'LIMIT' => 1,
    ])->current() ?: null;
  }

  /**
   * Add followup to ticket
   *
   * @param \Ticket $ticket
   * @param string $content
   * @param int $userId
   * @return void
   */
  private function addFollowup(\Ticket $ticket, string $content, int $userId): void
  {
    $followup = new \ITILFollowup();
    $followup->add([
      'itemtype' => 'Ticket',
      'items_id' => $ticket->getID(),
      'users_id' => $userId,
      'content' => $content,
    ]);
  }

  /**
   * Handle API errors
   *
   * @param \Ticket $ticket
   * @param ApiException $exception
   * @param string $operation
   * @return void
   */
  private function handleApiError(\Ticket $ticket, ApiException $exception, string $operation): void
  {
    $config = $this->configRepo->getConfig();

    $errorMessage = sprintf(
      __('Kyndryl API Error (%s): %s', 'kyndrylclient'),
      $operation,
      $exception->getMessage()
    );

    // Log error
    \Toolbox::logError($errorMessage);

    // Add error followup
    $this->addFollowup($ticket, $errorMessage, $config['request_user']);

    // Show error to user
    \Session::addMessageAfterRedirect($errorMessage, true, ERROR);
  }

  /**
   * Strip HTML tags from content
   *
   * @param string $content
   * @return string
   */
  private function stripHtmlTags(string $content): string
  {
    return strip_tags(html_entity_decode($content));
  }
}
