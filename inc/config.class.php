<?php
/*
   ------------------------------------------------------------------------
   Plugin GLPI Kyndryl Client
   Copyright (C) 2025 by PLSS
   
   ------------------------------------------------------------------------
   LICENSE
   This file is part of Plugin Kyndryl Client project.
   Plugin Kyndryl Client is private software: you can't redistribute it and/or modify
   it.
   ------------------------------------------------------------------------
   @package   Plugin Kyndryl Client
   @author    PLSS
   @co-author
   @copyright Copyright (c) 2025 Kyndryl Client Plugin Development team
   @license   Software proprietário
   @since     2025
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginKyndrylclientConfig
 */
class PluginKyndrylclientConfig extends CommonDBTM
{
  public const INCIDENTE = 1;
  public const REQUISICAO = 2;
  public const HTTP_TIMEOUT = 120;
  public const HTTP_CONNECT_TIMEOUT = 10;
  private const PWD_PREFIX = 'enc:';
  private static $_instance = null;

  // Estado por requisição para coordenar os hooks de Ticket e Group_Ticket:
  // - $sentTicketsInRequest: guarda a fila (OwnerGroup) enviada no update — permite
  //   detectar quando o item_update enviou a fila antiga e a troca de fila precisa reenviar
  // - $creatingTicketsInRequest: evita disparar update por grupos atribuídos após a criação
  // - $ownerGroupOverride: permite que a troca de fila informe explicitamente a fila nova
  private static $sentTicketsInRequest = [];
  private static $creatingTicketsInRequest = [];
  private static $ownerGroupOverride = [];
  // true entre o pre_item_add e o item_add de um Ticket: os Group_Ticket criados
  // nessa janela pertencem ao chamado em criação (tratado por afterTicketAdd).
  private static $ticketCreationInProgress = false;

  /**
   * PluginKyndrylclientConfig constructor.
   */
  function __construct()
  {
    global $DB;

    if ($DB->tableExists($this->getTable())) {
      $this->getFromDB(1);
    }
  }

  static function canCreate(): bool
  {
    return Session::haveRight('config', UPDATE);
  }

  static function canView(): bool
  {
    return Session::haveRight('config', READ);
  }

  static function canUpdate(): bool
  {
    return Session::haveRight('config', UPDATE);
  }
  /**
   * @param int $nb
   *
   * @return translated
   */
  static function getTypeName($nb = 0)
  {
    return __('Kyndryl Client', 'kyndrylclient');
  }

  static function getInstance()
  {
    if (!isset(self::$_instance)) {
      self::$_instance = new self();
      if (!self::$_instance->getFromDB(1)) {
        self::$_instance->getEmpty();
      }
    }
    return self::$_instance;
  }

  /**
   * @param bool $update
   *
   * @return PluginKyndrylclientConfig
   */
  static function getConfig($update = false)
  {
    $config = new self();

    if ($update) {
      $config->getFromDB(1);
    }
    return $config;
  }

  static function showConfigForm()
  {
    $config = new self();
    $config->getFromDB(1);

    $config->showFormHeader(['colspan' => 4]);

    echo "<table class='tab_cadre_fixe'><thead>";
    echo "<th colspan='4'>" . self::getTypeName() . '</th></thead>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('URL Base da API: ', 'kyndrylclient') . '</td><td>';
    echo "<input style='width: 100%;' type='text' minlength='1' name='base_url' value='" . htmlspecialchars((string) $config->fields['base_url'], ENT_QUOTES) . "'>";
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Login: ', 'kyndrylclient') . '</td><td>';
    echo "<input style='width: 100%;' type='text' minlength='1' name='login' value='" . htmlspecialchars((string) $config->fields['login'], ENT_QUOTES) . "'>";
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Senha: ', 'kyndrylclient') . '</td><td>';
    echo "<input style='width: 100%;' type='password' name='pwd' value='' autocomplete='new-password' placeholder='" . __('Deixe em branco para manter a senha atual', 'kyndrylclient') . "'>";
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Usuário Kyndryl API: ', 'kyndrylclient') . '</td><td>';
    Dropdown::show('User', ['name' => 'request_user', 'value' => $config->fields['request_user']]);
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Origem da Requisição: ', 'kyndrylclient') . '</td><td>';
    RequestType::dropdown(['name' => 'request_type', 'value' => $config->fields['request_type']]);
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Grupo Regra: ', 'kyndrylclient') . '</td><td>';
    Group::dropdown(['name' => 'group', 'value' => $config->fields['group']]);
    echo '</td>';
    echo '</tr>';

    $config->showFormButtons(['candel' => false]);

    return false;
  }

  function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
  {
    if ($item->getType() == 'Config') {
      return PLUGIN_KYNDRYLCLIENT_NAME;
    }
    return '';
  }

  static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
  {
    if ($item->getType() == 'Config') {
      self::showConfigForm();
    }
    return true;
  }

  /**
   * Antes de gravar: a senha é criptografada (GLPIKey) e, se vier vazia, a atual é mantida.
   */
  function prepareInputForUpdate($input)
  {
    if (array_key_exists('pwd', $input)) {
      if ($input['pwd'] === '') {
        unset($input['pwd']);
      } else {
        $input['pwd'] = self::PWD_PREFIX . (new GLPIKey())->encrypt($input['pwd']);
      }
    }
    return $input;
  }

  /**
   * Return current API Base URL if exists
   */
  static function getCurrentBaseUrl(): ?string
  {
    return self::getInstance()->fields['base_url'] ?? null;
  }

  /**
   * Senha da API em texto puro. Valores antigos (sem prefixo) são tratados como texto puro.
   */
  static function getPassword($config = null): string
  {
    $config = $config ?? self::getInstance();
    $stored = (string) ($config->fields['pwd'] ?? '');
    if (strpos($stored, self::PWD_PREFIX) === 0) {
      return (string) (new GLPIKey())->decrypt(substr($stored, strlen(self::PWD_PREFIX)));
    }
    return $stored;
  }

  /**
   * Return auth data if exists
   *
   * @return array
   */
  static function getAuthData(): array
  {
    $config = self::getInstance();
    return [$config->fields['login'] ?? '', self::getPassword($config)];
  }

  static function getTicketType($item): string
  {
    return $item->fields['type'] == self::REQUISICAO ? 'sr' : 'incident';
  }

  /**
   * Chamada HTTP à Kyndryl com timeout. Lança exceção em erro de rede/HTTP.
   */
  static function request(string $method, string $url, array $data, $config = null)
  {
    $config = $config ?? new self();
    $client = new \GuzzleHttp\Client([
      'timeout' => self::HTTP_TIMEOUT,
      'connect_timeout' => self::HTTP_CONNECT_TIMEOUT,
    ]);

    return $client->request($method, $url, [
      'headers' => [
        'Content-type' => 'application/json',
        'Accept' => 'application/json',
        'Accept-Encoding' => 'gzip, deflate',
      ],
      'auth' => [$config->fields['login'], self::getPassword($config)],
      'body' => json_encode($data),
    ]);
  }

  /**
   * Monta os anexos pendentes. Arquivos ilegíveis são ignorados (e logados) para
   * não serem marcados como enviados.
   *
   * @return array [anexos para o payload, documentos efetivamente incluídos]
   */
  static function buildAttachments($item): array
  {
    $attachments = [];
    $included = [];

    $documents = self::getUnsendedDocuments($item);
    if (!$documents) {
      return [$attachments, $included];
    }

    foreach ($documents as $doc) {
      $path = GLPI_VAR_DIR . '/' . $doc['filepath'];
      $content = is_readable($path) ? file_get_contents($path) : false;
      if ($content === false) {
        Toolbox::logInFile('kyndrylclient', sprintf("Anexo ilegível ignorado: ticket=%d documento=%d path=%s\n", $item->getID(), $doc['id'], $path));
        continue;
      }
      $attachments[] = [
        'AttachmentName' => $doc['filename'],
        'Attachment' => base64_encode($content),
      ];
      $included[] = $doc;
    }

    return [$attachments, $included];
  }

  /**
   * Última solução do chamado não gerada pelo usuário da integração.
   */
  static function getLastSolution($item, $config): ?array
  {
    /** @var \DBmysql $DB */
    global $DB;
    $row = $DB->request([
      'FROM' => 'glpi_itilsolutions',
      'WHERE' => [
        'itemtype' => 'Ticket',
        'items_id' => $item->getID(),
        'NOT' => ['users_id' => $config->fields['request_user']],
      ],
      'ORDER' => 'id DESC',
      'LIMIT' => 1,
    ])->current();
    return $row ?: null;
  }

  /**
   * Payload comum a create e update.
   */
  static function buildBasePayload($item, $ownerGroup, array $attachment): array
  {
    return [
      'ProviderID' => $item->getID(),
      'RequesterSeverity' => $item->fields['priority'],
      'WorkflowStatus' => $item->fields['status'],
      'Problem' => [
        'Abstract' => $item->fields['name'],
        'Description' => self::strip_tags_content($item->fields['content'] ?? ''),
        'OwnerGroup' => $ownerGroup === null ? null : (int) $ownerGroup,
      ],
      'Attachment' => $attachment,
    ];
  }

  static function strip_tags_content(string $string): string
  {
    return strip_tags(html_entity_decode($string));
  }

  static function getSummary($status = null): ?string
  {
    if (!$status) {
      return null;
    }
    switch ($status) {
      case 5:
        return 'Solução providenciada no GLPI';
        break;
      case 6:
        return 'Chamado encerrado no GLPI';
        break;

      default:
        return null;
        break;
    }
  }

  static function getUnsendedDocuments($item)
  {
    /** @var \DBmysql $DB */
    global $DB;
    $fupsIds = [];
    $documentsItemsIds = [];
    $documentsSendedIds = [];
    $documentsUnsended = [];
    $documentItemTb = Document_Item::getTable();

    $iteratorFups = $DB->request([
      'SELECT' => ['id'],
      'FROM' => 'glpi_itilfollowups',
      'WHERE' => [
        'itemtype' => 'Ticket',
        'items_id' => $item->getID(),
      ],
    ]);

    foreach ($iteratorFups as $fId) {
      array_push($fupsIds, $fId['id']);
    }

    // Sem followups a cláusula IN ficaria vazia (SQL inválido) — só inclui se houver
    $orConditions = [
      [
        'itemtype' => 'Ticket',
        'items_id' => $item->getID(),
      ],
    ];
    if (!empty($fupsIds)) {
      $orConditions[] = [
        'itemtype' => 'ITILFollowup',
        'items_id' => $fupsIds,
      ];
    }

    $documentItems = $DB->request([
      'SELECT' => ['documents_id'],
      'FROM' => $documentItemTb,
      'WHERE' => ['OR' => $orConditions],
      'ORDER' => 'id DESC',
    ]);

    foreach ($documentItems as $key => $dId) {
      array_push($documentsItemsIds, $dId['documents_id']);
    }

    $documentsOnTicket = array_unique($documentsItemsIds);

    $iteratorSendedDocs = $DB->request([
      'SELECT' => ['document_id'],
      'FROM' => 'glpi_plugin_kyndrylclient_documents',
      'WHERE' => [
        'ticket_id' => $item->getID(),
      ],
    ]);

    foreach ($iteratorSendedDocs as $key => $dId) {
      array_push($documentsSendedIds, $dId['document_id']);
    }

    $documentsUnsendedIds = array_diff($documentsOnTicket, $documentsSendedIds);

    if (empty($documentsUnsendedIds)) {
      return false;
    }

    $documentsUnsendedIterator = $DB->request([
      'FROM' => Document::getTable(),
      'WHERE' => ['id' => array_values($documentsUnsendedIds)],
    ]);

    foreach ($documentsUnsendedIterator as $doc) {
      array_push($documentsUnsended, $doc);
    }

    return $documentsUnsended;
  }

  static function sendDocuments(int $ticket_id, array $documentsToSend)
  {
    $document = new PluginKyndrylclientDocument();
    foreach ($documentsToSend as $key => $doc) {
      $document->add([
        'document_id' => $doc['id'],
        'ticket_id' => $ticket_id,
      ]);
    }
  }

  static function beforeTicketAdd($item)
  {
    self::$ticketCreationInProgress = true;
  }

  static function afterTicketAdd($item)
  {
    self::$ticketCreationInProgress = false;

    // Marca o chamado como "em criação": grupos atribuídos ainda nesta requisição
    // não devem disparar update. Os grupos do próprio create nem chegam a acionar
    // afterGroupTicketChange (o hook de Group_Ticket dispara antes deste, quando o
    // requester ainda não existe); este flag cobre atribuições feitas depois, na
    // mesma requisição. O envio de criação é responsabilidade deste método (POST /create).
    self::$creatingTicketsInRequest[$item->getID()] = true;

    $config = new self();

    if ($item->fields['requesttypes_id'] == $config->fields['request_type']) {
      $stringCrop = strtok($item->fields['name'], ' ');
      $subStrName = substr($stringCrop, 0, 2);
      if ($subStrName == 'SR' || $subStrName == 'IN') {
        $requester = new PluginKyndrylclientRequester();
        $fup = new ITILFollowup();
        $fup->add([
          'itemtype' => $item::getType(),
          'items_id' => $item->getID(),
          'users_id' => $config->fields['request_user'],
          'content' => 'Chamado originado no GLPI Kyndryl - RequesterID: ' . $stringCrop,
        ]);
        $requester->add([
          'requester_id' => $stringCrop,
          'ticket_id' => $item->getID(),
        ]);
      }
      return false;
    }

    $groupsArray = [];
    $groups = $item->getGroups(CommonITILActor::ASSIGN);
    $ruleGroups = getSonsOf('glpi_groups', $config->fields['group']);

    foreach ($groups as $key => $group) {
      array_push($groupsArray, $group['groups_id']);
    }

    $atribuidos = array_intersect($ruleGroups, $groupsArray);

    if (!empty($atribuidos)) {
      self::createInKyndryl($item, reset($atribuidos), $config);
    }
    return false;
  }

  /**
   * Cria o chamado na Kyndryl (POST /create) e registra o RequesterID retornado.
   * Usado na criação do chamado e no escalonamento posterior para uma fila Kyndryl.
   */
  static function createInKyndryl($item, $ownerGroup, $config = null)
  {
    /** @var \DBmysql $DB */
    global $DB;
    $config = $config ?? new self();

    // Já integrado: nada a criar.
    if (PluginKyndrylclientRequester::getByTicket($item->getID())) {
      return false;
    }

    [$attachment, $sentDocuments] = self::buildAttachments($item);

    $url = self::getCurrentBaseUrl() . self::getTicketType($item) . '/create';
    $data = self::buildBasePayload($item, $ownerGroup, $attachment);

    // Histórico anterior ao envio (relevante no escalonamento): followups viram
    // Activity, em ordem cronológica, ignorando os gerados pelo usuário da integração.
    $activities = [];
    $followups = $DB->request([
      'FROM' => 'glpi_itilfollowups',
      'WHERE' => [
        'itemtype' => 'Ticket',
        'items_id' => $item->getID(),
        'NOT' => ['users_id' => $config->fields['request_user']],
      ],
      'ORDER' => 'id ASC',
    ]);
    foreach ($followups as $fup) {
      $activities[] = [
        'Summary' => 'Worklog originária do GLPI',
        'ActionLog' => self::strip_tags_content($fup['content'] ?? ''),
      ];
    }

    // Chamado já solucionado/fechado: envia a última solução, como no update.
    if (in_array($item->fields['status'], [5, 6])) {
      $solution = self::getLastSolution($item, $config);
      $activities[] = [
        'Summary' => self::getSummary($item->fields['status']),
        'ActionLog' => self::strip_tags_content($solution['content'] ?? 'Chamado ' . ($item->fields['status'] == 5 ? 'solucionado' : 'fechado')),
      ];
      $data['Solution'] = [
        ['Resolution' => self::strip_tags_content($solution['content'] ?? '')],
      ];
    }

    if (!empty($activities)) {
      $data['Activity'] = $activities;
    }

    try {
      $response = self::request('POST', $url, $data, $config);
      $body = json_decode($response->getBody());
      if (empty($body->RequesterID)) {
        throw new RuntimeException('Resposta da Kyndryl sem RequesterID: ' . $response->getBody());
      }
      // Só marca anexos como enviados após o sucesso da requisição.
      if ($sentDocuments) {
        self::sendDocuments($item->getID(), $sentDocuments);
      }
      $fup = new ITILFollowup();
      $requester = new PluginKyndrylclientRequester();
      $fup->add([
        'itemtype' => $item::getType(),
        'items_id' => $item->getID(),
        'users_id' => $config->fields['request_user'],
        'content' => 'Chamado integrado com o GLPI Kyndryl - RequesterID: ' . $body->RequesterID,
      ]);
      $requester->add([
        'requester_id' => $body->RequesterID,
        'ticket_id' => $item->getID(),
      ]);
      Session::addMessageAfterRedirect('Chamado integrado com o GLPI Kyndryl', true, INFO);
      return true;
    } catch (Exception $e) {
      $fup = new ITILFollowup();
      $fup->add([
        'itemtype' => $item::getType(),
        'items_id' => $item->getID(),
        'users_id' => $config->fields['request_user'],
        'content' => 'Erro Requisição Kyndryl: ' . $e->getMessage(),
      ]);
      Session::addMessageAfterRedirect($e->getMessage(), true, ERROR);
      return false;
    }
  }

  static function afterTicketUpdate($item)
  {
    /** @var \DBmysql $DB */
    global $DB;

    // Evita envio duplicado: se esta atualização já foi enviada nesta requisição
    // (por este hook ou pela troca de fila), não reenvia aqui.
    if (array_key_exists($item->getID(), self::$sentTicketsInRequest)) {
      return false;
    }

    $requester = PluginKyndrylclientRequester::getByTicket($item->getID());

    if (!$requester) {
      return false;
    }

    $config = new self();

    if (Session::getLoginUserID() == $config->fields['request_user']) {
      return false;
    }

    // Sempre consulta o banco para obter os grupos atuais (estado confiável)
    $groupsArray = [];
    $assignedGroupsIterator = $DB->request([
      'SELECT' => ['groups_id'],
      'FROM'   => 'glpi_groups_tickets',
      'WHERE'  => [
        'tickets_id' => $item->getID(),
        'type'       => CommonITILActor::ASSIGN,
      ],
    ]);
    foreach ($assignedGroupsIterator as $row) {
      $groupsArray[] = $row['groups_id'];
    }

    // Verifica se _actors indica uma mudança explícita de atores (grupos diferentes do banco).
    // O hook item_update pode disparar antes de updateActors() gravar no banco; nesse caso os
    // novos grupos só existem em _actors. Porém, em atualizações de followup sem mudança de
    // grupo, _actors pode trazer dados desatualizados do formulário — por isso só sobrescreve
    // o banco quando os grupos do input são realmente diferentes dos grupos atuais.
    if (isset($item->input['_actors']['assign'])) {
      $inputGroupsArray = [];
      foreach ($item->input['_actors']['assign'] as $actor) {
        if (isset($actor['itemtype']) && $actor['itemtype'] === 'Group' && isset($actor['items_id'])) {
          $inputGroupsArray[] = (int) $actor['items_id'];
        }
      }

      if (!empty($inputGroupsArray)) {
        $sortedInput = $inputGroupsArray;
        $sortedDb    = $groupsArray;
        sort($sortedInput);
        sort($sortedDb);

        if ($sortedInput !== $sortedDb) {
          // Mudança real de atores: usa o input (banco ainda não foi atualizado por updateActors())
          $groupsArray = $inputGroupsArray;
        }
        // Se os grupos do input forem iguais ao banco, é followup sem mudança de ator — mantém banco
      }
    }

    // Aplica o mesmo filtro de afterTicketAdd: só grupos filhos do grupo-regra
    $ruleGroups = getSonsOf('glpi_groups', $config->fields['group']);
    $atribuidos = array_intersect($ruleGroups, $groupsArray);
    // Fallback: se nenhum grupo Kyndryl estiver atribuído, usa o primeiro grupo disponível
    $ownerGroup = !empty($atribuidos) ? reset($atribuidos) : (!empty($groupsArray) ? reset($groupsArray) : null);

    // Se a troca de fila (afterGroupTicketChange) definiu explicitamente a fila
    // nova, ela tem prioridade sobre a leitura do banco/_actors.
    if (isset(self::$ownerGroupOverride[$item->getID()])) {
      $ownerGroup = self::$ownerGroupOverride[$item->getID()];
    }

    // Registra a fila enviada nesta requisição: se depois disso a fila mudar
    // (updateActors roda após este hook), afterGroupTicketChange detecta a
    // diferença e reenvia com a fila nova.
    self::$sentTicketsInRequest[$item->getID()] = (int) $ownerGroup;

    Toolbox::logInFile(
      'kyndrylclient',
      sprintf(
        "afterTicketUpdate: ticket=%d status=%s ownerGroup=%s\n",
        $item->getID(),
        $item->fields['status'] ?? '',
        $ownerGroup ?? 'null'
      )
    );

    [$attachment, $sentDocuments] = self::buildAttachments($item);

    $requesterId = preg_replace('/^(SR|IN)/', '', $requester['requester_id']);
    $url = self::getCurrentBaseUrl() . self::getTicketType($item) . '/' . $requesterId;

    $lastFollowup = $DB
      ->request([
        'FROM' => 'glpi_itilfollowups',
        'WHERE' => [
          'itemtype' => 'Ticket',
          'items_id' => $item->getID(),
          [
            'OR' => [
              'NOT' => [
                'users_id' => $config->fields['request_user'],
              ],
            ],
          ],
        ],
        'ORDER' => 'id DESC',
        'LIMIT' => 1,
      ])
      ->current();

    $solution = self::getLastSolution($item, $config);

    // Monta o payload base
    $data = self::buildBasePayload($item, $ownerGroup, $attachment);

    // Adiciona Activity e Solution conforme o status
    if (in_array($item->fields['status'], [5, 6])) {
      // Status 5 = Solucionado, 6 = Fechado
      $data['Activity'] = [
        [
          'Summary' => self::getSummary($item->fields['status']),
          'ActionLog' => self::strip_tags_content($solution['content'] ?? "Chamado " . ($item->fields['status'] == 5 ? 'solucionado' : 'fechado')),
        ],
      ];
      $data['Solution'] = [
        [
          'Resolution' => self::strip_tags_content($solution['content'] ?? ""),
        ],
      ];
    } else {
      // Para outros status (incluindo reabertura)
      $actionLog = '';
      $summary = 'Worklog originária do GLPI';
      
      if ($lastFollowup) {
        $actionLog = self::strip_tags_content($lastFollowup['content']);
      } else {
        // Se não há followup, pode ser uma reabertura ou mudança de status
        $actionLog = 'Chamado atualizado - Status: ' . Ticket::getStatus($item->fields['status']);
      }

      $data['Activity'] = [
        [
          'Summary' => $summary,
          'ActionLog' => $actionLog,
        ],
      ];
    }

    try {
      self::request('PUT', $url, $data, $config);
      if ($sentDocuments) {
        self::sendDocuments($item->getID(), $sentDocuments);
      }
      //$body = json_decode($response->getBody());
      // Não adiciona followup aqui para evitar loop de duplicação
      Session::addMessageAfterRedirect('Chamado atualizado no Kyndryl', true, INFO);
    } catch (Exception $e) {
      $fup = new ITILFollowup();
      $fup->add([
        'itemtype' => $item::getType(),
        'items_id' => $item->getID(),
        'users_id' => $config->fields['request_user'],
        'content' => 'Erro na requisição: ' . $e->getMessage(),
      ]);
      Session::addMessageAfterRedirect($e->getMessage(), true, ERROR);
    }

    return false;
  }

  /**
   * Disparado quando um grupo é atribuído a um chamado (glpi_groups_tickets).
   *
   * Roda depois que a fila nova já está gravada no banco, então resolve os dois
   * problemas do fluxo antigo:
   *  - o item_update do chamado não dispara quando SÓ a fila muda; e
   *  - a leitura do grupo podia trazer a fila antiga por ordem de gravação.
   * Usa o grupo recém-atribuído como OwnerGroup (sem ambiguidade de ordenação).
   */
  static function afterGroupTicketChange($groupTicket)
  {
    /** @var \DBmysql $DB */
    global $DB;

    // Só interessa a atribuição (grupo responsável), não requerente/observador.
    if ((int) ($groupTicket->fields['type'] ?? 0) !== CommonITILActor::ASSIGN) {
      return false;
    }

    $ticketId = (int) ($groupTicket->fields['tickets_id'] ?? 0);
    if (!$ticketId) {
      return false;
    }

    // Durante a criação do chamado o envio é feito por afterTicketAdd (POST /create).
    if (self::$ticketCreationInProgress || !empty(self::$creatingTicketsInRequest[$ticketId])) {
      return false;
    }

    $requester = PluginKyndrylclientRequester::getByTicket($ticketId);

    $config = new self();

    if (Session::getLoginUserID() == $config->fields['request_user']) {
      return false;
    }

    // A fila nova é exatamente o grupo recém-atribuído.
    $ownerGroup = (int) ($groupTicket->fields['groups_id'] ?? 0);

    // Só reporta se for um grupo gerenciado pela Kyndryl (filho do Grupo Regra).
    $ruleGroups = getSonsOf('glpi_groups', $config->fields['group']);
    if (!in_array($ownerGroup, $ruleGroups)) {
      return false;
    }

    // Chamado ainda não integrado: escalonamento para um time Kyndryl após a criação
    // => integra agora (POST /create), exceto chamados originados na própria Kyndryl.
    if (!$requester) {
      $ticket = new Ticket();
      if (!$ticket->getFromDB($ticketId)) {
        return false;
      }
      if ($ticket->fields['requesttypes_id'] == $config->fields['request_type']) {
        return false;
      }

      Toolbox::logInFile(
        'kyndrylclient',
        sprintf("afterGroupTicketChange: escalonamento ticket=%d fila=%d -> create\n", $ticketId, $ownerGroup)
      );

      if (self::createInKyndryl($ticket, $ownerGroup, $config)) {
        // O create já carrega o estado atual: evita PUT redundante nesta requisição.
        self::$sentTicketsInRequest[$ticketId] = $ownerGroup;
      }
      return false;
    }

    // Dedup sensível à fila: se o update já foi enviado nesta requisição com ESTA
    // fila, não reenvia. Se foi enviado com fila diferente — o item_update do
    // chamado dispara antes de updateActors() gravar a fila nova (ex.: regra de
    // negócio trocando o grupo junto com outros campos) — limpa o flag e reenvia
    // com a fila correta.
    if (array_key_exists($ticketId, self::$sentTicketsInRequest)) {
      if ((int) self::$sentTicketsInRequest[$ticketId] === $ownerGroup) {
        return false;
      }
      unset(self::$sentTicketsInRequest[$ticketId]);
    }

    $item = new Ticket();
    if (!$item->getFromDB($ticketId)) {
      return false;
    }

    Toolbox::logInFile(
      'kyndrylclient',
      sprintf("afterGroupTicketChange: ticket=%d novaFila(ownerGroup)=%d\n", $ticketId, $ownerGroup)
    );

    // Informa a fila nova e delega o envio ao fluxo de update (que aplica o override).
    self::$ownerGroupOverride[$ticketId] = $ownerGroup;
    self::afterTicketUpdate($item);

    return false;
  }

  static function install(Migration $migration)
  {
    global $DB;

    $default_charset = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

    $table = self::getTable();
    $config = new self();
    if (!$DB->tableExists($table)) {
      $migration->displayMessage("Installing $table");

      $query = "CREATE TABLE IF NOT EXISTS $table (
        `id` int {$default_key_sign} NOT NULL auto_increment,
        `group` int NOT NULL DEFAULT '0',
        `request_type` int NOT NULL DEFAULT '0',
        `request_user` int NOT NULL DEFAULT '0',
        `base_url` varchar(255) NOT NULL DEFAULT '',
        `login` varchar(50) NOT NULL DEFAULT '',
        `pwd` varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY (`id`))
        ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC
        AUTO_INCREMENT=1;";

      $DB->doQuery($query) or die($DB->error());
      $config->add([
        'id' => 1,
        'base_url' => 'https://integrationqa.ismbr.kyndryl.com/ISBR_DSP_GLPI_BRIDGE/inb/',
        'login' => 'dsp-integration',
      ]);
    } else {
      $migration->addField($table, 'request_type', 'int', ['after' => 'group']);
      $migration->addField($table, 'request_user', 'int', ['after' => 'request_type']);
    }

    $migration->executeMigration();
  }

  static function uninstall(Migration $migration)
  {
    global $DB;

    $table = self::getTable();
    if ($DB->TableExists($table)) {
      $migration->displayMessage("Uninstalling $table");
      $migration->dropTable($table);
    }
  }
}
