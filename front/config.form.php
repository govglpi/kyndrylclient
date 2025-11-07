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

include '../../../inc/includes.php';

// Check if plugin is activated...
$plugin = new Plugin();
if (!$plugin->isInstalled('kyndrylclient') || !$plugin->isActivated('kyndrylclient')) {
  Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);

$config = new PluginKyndrylclientConfig();

if (isset($_POST['update'])) {
  $config->check($_POST['id'], UPDATE);
  $config->update([
    'id' => $_POST['id'],
    'group' => $_POST['group'],
    'request_type' => $_POST['request_type'],
    'request_user' => $_POST['request_user'],
    'base_url' => $_POST['base_url'],
    'login' => $_POST['login'],
    'pwd' => htmlspecialchars_decode($_POST['pwd']),
  ]);

  Html::back();
}

Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode('PluginKyndrylclientConfig$1'));
