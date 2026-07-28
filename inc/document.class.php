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
 * Class PluginKyndrylclientDocument
 */
class PluginKyndrylclientDocument extends CommonDBTM
{
  private static $_instance = null;

  /**
   * PluginKyndrylclientDocument constructor.
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
   * @return PluginKyndrylclientDocument
   */
  static function getDocument($update = false)
  {
    $document = new self();

    if ($update) {
      $document->getFromDB(1);
    }
    return $document;
  }

  static function install(Migration $migration)
  {
    global $DB;

    $default_charset = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

    $table = self::getTable();
    if (!$DB->tableExists($table)) {
      $migration->displayMessage("Installing $table");

      $query = "CREATE TABLE IF NOT EXISTS $table (
              `id` int {$default_key_sign} NOT NULL auto_increment,
              `document_id` int DEFAULT NULL,
              `ticket_id` int DEFAULT NULL,
              PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC AUTO_INCREMENT=1;";

      $DB->query($query) or die($DB->error());
    } else {
      $migration->migrationOneTable($table);
    }
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
