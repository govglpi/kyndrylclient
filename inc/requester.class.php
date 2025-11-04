<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access directly to this file");
}

class PluginKyndrylclientRequester extends CommonDBTM
{
  public static $rightname = 'config';

  private static ?self $instance = null;

  /**
   * Constructor
   */
  public function __construct()
  {
    global $DB;

    parent::__construct();

    if ($DB->tableExists($this->getTable())) {
      $this->getFromDB(1);
    }
  }

  /**
   * Get instance (Singleton)
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
   * Get type name
   *
   * @param int $nb Number of items
   * @return string
   */
  public static function getTypeName($nb = 0): string
  {
    return _n('Kyndryl Requester', 'Kyndryl Requesters', $nb, 'kyndrylclient');
  }

  /**
   * Check create right
   *
   * @return bool
   */
  public static function canCreate(): bool
  {
    return Session::haveRight('config', UPDATE);
  }

  /**
   * Check view right
   *
   * @return bool
   */
  public static function canView(): bool
  {
    return Session::haveRight('config', READ);
  }

  /**
   * Check update right
   *
   * @return bool
   */
  public static function canUpdate(): bool
  {
    return Session::haveRight('config', UPDATE);
  }

  /**
   * Install database table
   *
   * @param Migration $migration Migration instance
   * @return void
   */
  public static function install(Migration $migration): void
  {
    global $DB;

    $default_charset = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

    $table = self::getTable();

    if (!$DB->tableExists($table)) {
      $migration->displayMessage("Installing {$table}");

      $query = "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `requester_id` varchar(50) NOT NULL DEFAULT '',
                `ticket_id` int NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `requester_id` (`requester_id`),
                UNIQUE KEY `ticket_id` (`ticket_id`),
                KEY `date_creation` (`date_creation`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

      $DB->query($query) or die($DB->error());
    }
  }

  /**
   * Uninstall database table
   *
   * @param Migration $migration Migration instance
   * @return void
   */
  public static function uninstall(Migration $migration): void
  {
    global $DB;

    $table = self::getTable();

    if ($DB->tableExists($table)) {
      $migration->displayMessage("Uninstalling {$table}");
      $migration->dropTable($table);
    }
  }

  /**
   * Get raw name (for compatibility)
   *
   * @return string
   */
  public function getRawName(): string
  {
    return $this->fields['requester_id'] ?? '';
  }
}
