<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by GLPI Kyndryl Client Plugin Team
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\KyndrylClient\Repository\ConfigurationRepository;
use Glpi\Toolbox\Sanitizer;

/**
 * Configuration Class
 */
class PluginKyndrylclientConfig extends CommonDBTM
{
  public static $rightname = 'config';

  private static ?self $instance = null;
  private ConfigurationRepository $repository;

  /**
   * Constructor
   */
  public function __construct()
  {
    global $DB;

    parent::__construct();
    $this->repository = new ConfigurationRepository();

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
    return __('Kyndryl Client', 'kyndrylclient');
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
   * Display configuration form
   *
   * @return void
   */
  public static function showConfigForm(): void
  {
    $config = self::getInstance();
    $config->showFormHeader(['colspan' => 4]);

    echo "<table class='tab_cadre_fixe'>";
    echo "<thead>";
    echo "<tr><th colspan='4'>" . self::getTypeName() . "</th></tr>";
    echo "</thead>";
    echo "<tbody>";

    // Base URL
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('API Base URL', 'kyndrylclient') . "</td>";
    echo "<td>";
    echo "<input type='text' name='base_url' class='form-control' ";
    echo "value='" . htmlspecialchars($config->fields['base_url']) . "' ";
    echo "placeholder='https://api.kyndryl.com/' required>";
    echo "</td>";
    echo "</tr>";

    // Login
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('Login', 'kyndrylclient') . "</td>";
    echo "<td>";
    echo "<input type='text' name='login' class='form-control' ";
    echo "value='" . htmlspecialchars($config->fields['login']) . "' required>";
    echo "</td>";
    echo "</tr>";

    // Password
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('Password', 'kyndrylclient') . "</td>";
    echo "<td>";
    echo "<input type='password' name='pwd' class='form-control' ";
    echo "value='" . htmlspecialchars($config->fields['pwd']) . "' required>";
    echo "</td>";
    echo "</tr>";

    // API User
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('API User (GLPI)', 'kyndrylclient') . "</td>";
    echo "<td>";
    User::dropdown([
      'name' => 'request_user',
      'value' => $config->fields['request_user'],
      'right' => 'all',
    ]);
    echo "</td>";
    echo "</tr>";

    // Request Type
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('Request Source', 'kyndrylclient') . "</td>";
    echo "<td>";
    RequestType::dropdown([
      'name' => 'request_type',
      'value' => $config->fields['request_type'],
    ]);
    echo "</td>";
    echo "</tr>";

    // Group Rule
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('Group Rule', 'kyndrylclient') . "</td>";
    echo "<td>";
    Group::dropdown([
      'name' => 'group',
      'value' => $config->fields['group'],
    ]);
    echo "</td>";
    echo "</tr>";

    // Test Connection Button
    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' class='center'>";
    echo "<button type='button' class='btn btn-secondary' ";
    echo "onclick='testKyndrylConnection()'>";
    echo __('Test Connection', 'kyndrylclient');
    echo "</button>";
    echo "</td>";
    echo "</tr>";

    echo "</tbody>";
    echo "</table>";

    $config->showFormButtons(['candel' => false]);
  }

  /**
   * Get tab name for item
   *
   * @param CommonGLPI $item Item
   * @param int $withtemplate Template flag
   * @return string
   */
  public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
  {
    if ($item->getType() === 'Config') {
      return PLUGIN_KYNDRYLCLIENT_NAME;
    }
    return '';
  }

  /**
   * Display tab content for item
   *
   * @param CommonGLPI $item Item
   * @param int $tabnum Tab number
   * @param int $withtemplate Template flag
   * @return bool
   */
  public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
  {
    if ($item->getType() === 'Config') {
      self::showConfigForm();
    }
    return true;
  }

  /**
   * Prepare input for update
   *
   * @param array $input Input data
   * @return array|false
   */
  public function prepareInputForUpdate($input)
  {
    // Sanitize inputs
    if (class_exists('Glpi\Toolbox\Sanitizer')) {
      $input = Sanitizer::sanitize($input);
    }

    // Validate configuration
    $errors = $this->repository->validateConfig($input);

    if (!empty($errors)) {
      foreach ($errors as $field => $error) {
        Session::addMessageAfterRedirect($error, false, ERROR);
      }
      return false;
    }

    return $input;
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
                `base_url` varchar(255) NOT NULL DEFAULT '',
                `login` varchar(100) NOT NULL DEFAULT '',
                `pwd` varchar(255) NOT NULL DEFAULT '',
                `request_user` int NOT NULL DEFAULT '0',
                `request_type` int NOT NULL DEFAULT '0',
                `group` int NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `request_user` (`request_user`),
                KEY `request_type` (`request_type`),
                KEY `group` (`group`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

      $DB->query($query) or die($DB->error());

      // Insert default config
      $DB->insert($table, [
        'id' => 1,
        'base_url' => 'https://integrationqa.ismbr.kyndryl.com/ISBR_DSP_GLPI_BRIDGE/inb/',
        'login' => '',
        'pwd' => '',
        'request_user' => 0,
        'request_type' => 0,
        'group' => 0,
      ]);
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
}
