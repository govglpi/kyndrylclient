<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by PLSS Soluções
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI Kyndryl Client Plugin.
 *
 * GLPI Kyndryl Client is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GLPI Kyndryl Client is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI Kyndryl Client. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 */

define('PLUGIN_KYNDRYLCLIENT_VERSION', '1.0.0');
define('PLUGIN_KYNDRYLCLIENT_MIN_GLPI', '10.0.0');
define('PLUGIN_KYNDRYLCLIENT_MAX_GLPI', '10.0.20');
define('PLUGIN_KYNDRYLCLIENT_NAME', 'Kyndryl Client');

// Autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Get plugin version information
 *
 * @return array Plugin information
 */
function plugin_version_kyndrylclient(): array
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
      'php' => [
        'min' => '8.0',
      ],
    ],
  ];
}

/**
 * Check plugin prerequisites
 *
 * @return bool True if prerequisites are met
 */
function plugin_kyndrylclient_check_prerequisites(): bool
{
  // Check PHP version
  if (version_compare(PHP_VERSION, '8.0', '<')) {
    echo "This plugin requires PHP 8.0 or higher.";
    return false;
  }

  // Check required PHP extensions
  $required_extensions = ['curl', 'json', 'mbstring'];
  foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
      echo "This plugin requires PHP extension: {$ext}";
      return false;
    }
  }

  // Check if Composer dependencies are installed
  if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "Please run 'composer install' in the plugin directory.";
    return false;
  }

  return true;
}

/**
 * Check plugin configuration
 *
 * @param bool $verbose Display messages if true
 * @return bool True if configuration is valid
 */
function plugin_kyndrylclient_check_config($verbose = false): bool
{
  if ($verbose) {
    echo 'Installed / not configured';
  }
  return true;
}

/**
 * Initialize plugin
 */
function plugin_init_kyndrylclient(): void
{
  global $PLUGIN_HOOKS, $CFG_GLPI;

  $PLUGIN_HOOKS['csrf_compliant']['kyndrylclient'] = true;

  $plugin = new Plugin();

  if ($plugin->isActivated('kyndrylclient')) {
    // Configuration page
    $PLUGIN_HOOKS['config_page']['kyndrylclient'] = 'front/config.form.php';

    // Register classes
    Plugin::registerClass('PluginKyndrylclientConfig', [
      'addtabon' => 'Config'
    ]);

    Plugin::registerClass('PluginKyndrylclientProfile', [
      'addtabon' => 'Profile'
    ]);

    // Item hooks - Ticket creation
    $PLUGIN_HOOKS['item_add']['kyndrylclient'] = [
      'Ticket' => 'plugin_kyndrylclient_item_add_ticket',
    ];

    // Item hooks - Ticket update
    $PLUGIN_HOOKS['item_update']['kyndrylclient'] = [
      'Ticket' => 'plugin_kyndrylclient_item_update_ticket',
    ];

    // Item hooks - Followup
    $PLUGIN_HOOKS['item_add']['kyndrylclient'] += [
      'ITILFollowup' => 'plugin_kyndrylclient_item_add_followup',
    ];

    // Add menu entry
    if (Session::haveRight('config', UPDATE)) {
      $PLUGIN_HOOKS['menu_toadd']['kyndrylclient'] = [
        'config' => 'PluginKyndrylclientConfig'
      ];
    }
  }
}
