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
  private static $_instance = null;

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

  static function canCreate()
  {
    return Session::haveRight('config', UPDATE);
  }

  static function canView()
  {
    return Session::haveRight('config', READ);
  }

  static function canUpdate()
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
    echo "<input style='width: 100%;' type='text' minlength='1' name='base_url' value=" . $config->fields['base_url'] . '>';
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Login: ', 'kyndrylclient') . '</td><td>';
    echo "<input style='width: 100%;' type='text' minlength='1' name='login' value=" . $config->fields['login'] . '>';
    echo '</td>';
    echo '</tr>';

    echo "<tr class='tab_bg_1'>";
    echo '<td>' . __('Senha: ', 'kyndrylclient') . '</td><td>';
    echo "<input style='width: 100%;' type='password' minlength='1' name='pwd' value=" . $config->fields['pwd'] . '>';
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
   * Return current API Base URL if exists
   *
   * @return boolean
   */
  static function getCurrentBaseUrl()
  {
    $config = new self();
    return $config->fields['base_url'] ?? null;
  }

  /**
   * Return auth data if exists
   *
   * @return array
   */
  static function getAuthData()
  {
    $config = new self();
    return [$config->fields['login'], $config->fields['pwd']] ?? null;
  }

  static function strip_tags_content(string $string): string
  {
    return strip_tags(html_entity_decode($string));
  }

  static function getSummary($status = null): string
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

    foreach ($iteratorFups as $key => $fId) {
      array_push($fupsIds, $key);
    }
    $inFup = join(',', $fupsIds);

    $documentItems = $DB->request([
      'SELECT' => ['documents_id'],
      'FROM' => $documentItemTb,
      'WHERE' => [
        'OR' => [
          [
            'itemtype' => 'Ticket',
            'items_id' => $item->getID(),
          ],
          [
            'itemtype' => 'ITILFollowup',
            'items_id IN (' . $inFup . ')',
          ],
        ],
      ],
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

    $inUnsend = join(',', $documentsUnsendedIds);

    if ($inUnsend !== "") {
      $documentsUnsendedIterator = $DB->request([
        'FROM' => Document::getTable(),
        'WHERE' => ['id IN (' . $inUnsend . ')'],
      ]);

      foreach ($documentsUnsendedIterator as $key => $doc) {
        array_push($documentsUnsended, $doc);
      }

      return $documentsUnsended;
    } else {
      return false;
    }
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

  static function afterDocumentAdd($item)
  {
    return false;
  }

  static function afterTicketAdd($item)
  {
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
      /** @var \DBmysql $DB */
      global $DB;
      $client = new \GuzzleHttp\Client();

      $unsendedDocuments = self::getUnsendedDocuments($item);

      $attachment = [];

      if ($unsendedDocuments) {
        foreach ($unsendedDocuments as $key => $fileDocument) {
          array_push($attachment, [
            'AttachmentName' => $fileDocument['filename'],
            'Attachment' => base64_encode(file_get_contents(GLPI_VAR_DIR . '/' . $fileDocument['filepath'])),
          ]);
        }
        self::sendDocuments($item->getID(), $unsendedDocuments);
      }

      $ticketType = $item->fields['type'] == self::INCIDENTE ? 'incident' : ($item->fields['type'] == self::REQUISICAO ? 'sr' : 'incident');
      $url = self::getCurrentBaseUrl() . $ticketType . '/create';
      $data = [
        'ProviderID' => $item->getID(),
        'RequesterSeverity' => $item->fields['priority'],
        'WorkflowStatus' => $item->fields['status'],
        'Problem' => [
          'Abstract' => $item->fields['name'],
          'Description' => self::strip_tags_content($item->fields['content'] ?? ""),
          'OwnerGroup' => reset($atribuidos),
        ],
        'Attachment' => $attachment,
      ];

      try {
        $response = $client->request('POST', $url, [
          'headers' => [
            'Content-type' => 'application/json',
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate',
          ],
          'auth' => [$config->fields['login'], $config->fields['pwd']],
          'body' => json_encode($data),
        ]);
        $body = json_decode($response->getBody());
        $fup = new ITILFollowup();
        $requester = new PluginKyndrylclientRequester();
        $fup->add([
          'itemtype' => $item::getType(),
          'items_id' => $item->getID(),
          'users_id' => $config->fields['request_user'], //Session::getLoginUserID(),
          'content' => 'Chamado integrado com o GLPI Kyndryl - RequesterID: ' . $body->RequesterID,
        ]);
        $requester->add([
          'requester_id' => $body->RequesterID,
          'ticket_id' => $item->getID(),
        ]);
        Session::addMessageAfterRedirect('Chamado integrado com o GLPI Kyndryl', true, INFO);
      } catch (Exception $e) {
        $fup = new ITILFollowup();
        $fup->add([
          'itemtype' => $item::getType(),
          'items_id' => $item->getID(),
          'users_id' => $config->fields['request_user'],
          'content' => 'Erro Requisição Kyndryl: ' . $e->getMessage(),
        ]);
        Session::addMessageAfterRedirect($e->getMessage(), true, ERROR);
      }
    }
    return false;
  }

  static function afterTicketUpdate($item)
  {
    /** @var \DBmysql $DB */
    global $DB;

    $requester = $DB
      ->request([
        'FROM' => 'glpi_plugin_kyndrylclient_requesters',
        'WHERE' => [
          'ticket_id' => $item->getID(),
        ],
        'LIMIT' => 1,
      ])
      ->current();

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

    $client = new \GuzzleHttp\Client();

    $unsendedDocuments = self::getUnsendedDocuments($item);

    $attachment = [];

    if ($unsendedDocuments) {
      foreach ($unsendedDocuments as $key => $fileDocument) {
        array_push($attachment, [
          'AttachmentName' => $fileDocument['filename'],
          'Attachment' => base64_encode(file_get_contents(GLPI_VAR_DIR . '/' . $fileDocument['filepath'])),
        ]);
      }
      self::sendDocuments($item->getID(), $unsendedDocuments);
    }

    $ticketType = $item->fields['type'] == self::INCIDENTE ? 'incident' : ($item->fields['type'] == self::REQUISICAO ? 'sr' : 'incident');
    $requesterId = preg_replace('/^(SR|IN)/', '', $requester['requester_id']);
    $url = self::getCurrentBaseUrl() . $ticketType . '/' . $requesterId;

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

    $solution = $DB
      ->request([
        'FROM' => 'glpi_itilsolutions',
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

    // Monta o payload base
    $data = [
      'ProviderID' => $item->getID(),
      'RequesterSeverity' => $item->fields['priority'],
      'WorkflowStatus' => $item->fields['status'],
      'Problem' => [
        'Abstract' => $item->fields['name'],
        'Description' => self::strip_tags_content($item->fields['content'] ?? ""),
        'OwnerGroup' => $ownerGroup,
      ],
      'Attachment' => $attachment,
    ];

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
      $response = $client->request('PUT', $url, [
        'headers' => [
          'Content-type' => 'application/json',
          'Accept' => 'application/json',
          'Accept-Encoding' => 'gzip, deflate',
        ],
        'auth' => [$config->fields['login'], $config->fields['pwd']],
        'body' => json_encode($data),
      ]);
      //$body = json_decode($response->getBody());
      // Não adiciona followup aqui para evitar loop de duplicação
      Session::addMessageAfterRedirect('Chamado atualizado no IBM Maximo', true, INFO);
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

      $DB->query($query) or die($DB->error());
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
