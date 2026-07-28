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

//plugin version
define('PLUGIN_KYNDRYLCLIENT_VERSION', '1.0.0');
// Minimal GLPI version
define('PLUGIN_KYNDRYLCLIENT_MIN_GLPI', '10.0.0');
// Maximum GLPI version
define('PLUGIN_KYNDRYLCLIENT_MAX_GLPI', '12.0.0');

define('PLUGIN_KYNDRYLCLIENT_NAME', 'Kyndryl Client');

function plugin_version_kyndrylclient()
{
  return [
    'name' => PLUGIN_KYNDRYLCLIENT_NAME,
    'version' => PLUGIN_KYNDRYLCLIENT_VERSION,
    'author' => 'Matheus Schmidt',
    'license' => 'GPL-3.0-or-later',
    'homepage' => 'https://github.com/govglpi/glpi-kyndryl-client',
    'requirements' => [
      'glpi' => [
        'min' => PLUGIN_KYNDRYLCLIENT_MIN_GLPI,
        'max' => PLUGIN_KYNDRYLCLIENT_MAX_GLPI,
      ],
    ],
  ];
}

function plugin_init_kyndrylclient()
{
  global $PLUGIN_HOOKS, $CFG_GLPI;

  $PLUGIN_HOOKS['csrf_compliant']['kyndrylclient'] = true;

  $plugin = new Plugin();

  if ($plugin->isActivated('kyndrylclient')) {
    $PLUGIN_HOOKS['config_page']['kyndrylclient'] = 'front/config.form.php';

    Plugin::registerClass('PluginKyndrylclientProfile', ['addtabon' => 'Profile']);

    Plugin::registerClass('PluginKyndrylclientConfig', ['addtabon' => 'Config']);

    // Followups não são tratados diretamente: a adição de um followup atualiza o
    // chamado-pai (date_mod), o que dispara o item_update de Ticket e envia o worklog.
    $PLUGIN_HOOKS['item_add']['kyndrylclient'] = [
      'Ticket' => 'plugin_kyndrylclient_item_add',
      'Document' => 'plugin_kyndrylclient_item_add',
      'Group_Ticket' => 'plugin_kyndrylclient_item_add',
    ];

    $PLUGIN_HOOKS['item_update']['kyndrylclient'] = [
      'Ticket' => 'plugin_kyndrylclient_item_update',
    ];
  }
}
