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

use GlpiPlugin\KyndrylClient\Service\TicketIntegrationService;

/**
 * Install plugin database tables
 *
 * @return bool True on success
 */
function plugin_kyndrylclient_install(): bool
{
  $migration = new Migration(PLUGIN_KYNDRYLCLIENT_VERSION);

  // Auto-install all classes that have install method
  $classFiles = glob(__DIR__ . '/inc/*.class.php');

  foreach ($classFiles as $filepath) {
    if (preg_match('/inc\/(.+)\.class\.php/', $filepath, $matches)) {
      $classname = 'PluginKyndrylclient' . ucfirst($matches[1]);
      include_once $filepath;

      if (class_exists($classname) && method_exists($classname, 'install')) {
        $classname::install($migration);
      }
    }
  }

  $migration->executeMigration();

  return true;
}

/**
 * Uninstall plugin database tables
 *
 * @return bool True on success
 */
function plugin_kyndrylclient_uninstall(): bool
{
  $migration = new Migration(PLUGIN_KYNDRYLCLIENT_VERSION);

  // Auto-uninstall all classes that have uninstall method
  $classFiles = glob(__DIR__ . '/inc/*.class.php');

  foreach ($classFiles as $filepath) {
    if (preg_match('/inc\/(.+)\.class\.php/', $filepath, $matches)) {
      $classname = 'PluginKyndrylclient' . ucfirst($matches[1]);
      include_once $filepath;

      if (class_exists($classname) && method_exists($classname, 'uninstall')) {
        $classname::uninstall($migration);
      }
    }
  }

  $migration->executeMigration();

  return true;
}

/**
 * Handle ticket creation
 *
 * @param Ticket $item Ticket object
 * @return void
 */
function plugin_kyndrylclient_item_add_ticket(Ticket $item): void
{
  try {
    $service = TicketIntegrationService::getInstance();
    $service->handleTicketCreation($item);
  } catch (\Throwable $e) {
    // Log error but don't break GLPI flow
    Toolbox::logError('Kyndryl Client - Ticket Add Error: ' . $e->getMessage());
  }
}

/**
 * Handle ticket update
 *
 * @param Ticket $item Ticket object
 * @return void
 */
function plugin_kyndrylclient_item_update_ticket(Ticket $item): void
{
  try {
    $service = TicketIntegrationService::getInstance();
    $service->handleTicketUpdate($item);
  } catch (\Throwable $e) {
    // Log error but don't break GLPI flow
    Toolbox::logError('Kyndryl Client - Ticket Update Error: ' . $e->getMessage());
  }
}

/**
 * Handle followup creation
 *
 * @param ITILFollowup $item Followup object
 * @return void
 */
function plugin_kyndrylclient_item_add_followup(ITILFollowup $item): void
{
  try {
    $service = TicketIntegrationService::getInstance();
    $service->handleFollowupCreation($item);
  } catch (\Throwable $e) {
    // Log error but don't break GLPI flow
    Toolbox::logError('Kyndryl Client - Followup Add Error: ' . $e->getMessage());
  }
}
