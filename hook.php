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

function plugin_kyndrylclient_install()
{
  $migration = new Migration(PLUGIN_KYNDRYLCLIENT_VERSION);

  foreach (glob(__DIR__ . '/inc/*') as $filepath) {
    if (preg_match('/inc.(.+)\.class.php/', $filepath, $matches)) {
      $classname = 'PluginKyndrylclient' . ucfirst($matches[1]);
      include_once $filepath;
      if (method_exists($classname, 'install')) {
        $classname::install($migration);
      }
    }
  }

  $migration->executeMigration();

  return true;
}

function plugin_kyndrylclient_uninstall()
{
  $migration = new Migration(PLUGIN_KYNDRYLCLIENT_VERSION);

  foreach (glob(__DIR__ . '/inc/*') as $filepath) {
    if (preg_match('/inc.(.+)\.class.php/', $filepath, $matches)) {
      $classname = 'PluginKyndrylclient' . ucfirst($matches[1]);
      include_once $filepath;
      if (method_exists($classname, 'uninstall')) {
        $classname::uninstall($migration);
      }
    }
  }

  $migration->executeMigration();

  return true;

  global $DB;
}

function plugin_kyndrylclient_item_add($item)
{
  if ($item::getType() === Ticket::getType()) {
    PluginKyndrylclientConfig::afterTicketAdd($item);
  }

  if ($item::getType() === ITILFollowup::getType()) {
    PluginKyndrylclientConfig::afterTicketFollowUp($item);
  }
}

function plugin_kyndrylclient_item_update($item)
{
  if ($item::getType() === Ticket::getType()) {
    PluginKyndrylclientConfig::afterTicketUpdate($item);
  }

  if ($item::getType() === ITILFollowup::getType()) {
    PluginKyndrylclientConfig::afterTicketFollowUp($item);
  }
}
