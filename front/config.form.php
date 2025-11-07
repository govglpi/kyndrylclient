<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Kyndryl Client Plugin
 * Copyright (C) 2025 by GLPI Kyndryl Client Plugin Team
 * -------------------------------------------------------------------------
 */

include '../../../inc/includes.php';

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isInstalled('kyndrylclient') || !$plugin->isActivated('kyndrylclient')) {
  Html::displayNotFoundError();
}

// Check rights
Session::checkRight('config', UPDATE);

$config = new PluginKyndrylclientConfig();

// Handle form submission
if (isset($_POST['update'])) {
  $config->check($_POST['id'], UPDATE);

  if ($config->update($_POST)) {
    Session::addMessageAfterRedirect(
      __('Configuration updated successfully', 'kyndrylclient'),
      true,
      INFO
    );
  }

  Html::back();
}

// Redirect to main config page with our tab
Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode('PluginKyndrylclientConfig$1'));
