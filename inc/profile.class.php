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

/**
 * Profile Class
 * Manages plugin permissions
 */
class PluginKyndrylclientProfile extends Profile
{
  public static $rightname = 'profile';

  /**
   * Get type name
   *
   * @param int $nb Number of items
   * @return string
   */
  public static function getTypeName($nb = 0): string
  {
    return __('Kyndryl Client Rights', 'kyndrylclient');
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
    if ($item->getType() === 'Profile') {
      return self::getTypeName();
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
    if ($item->getType() === 'Profile') {
      self::showForProfile($item->getID());
    }
    return true;
  }

  /**
   * Show profile form
   *
   * @param int $profiles_id Profile ID
   * @return void
   */
  public static function showForProfile(int $profiles_id): void
  {
    $profile = new Profile();
    $profile->getFromDB($profiles_id);

    echo "<div class='spaced'>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<thead>";
    echo "<tr><th colspan='4'>" . __('Kyndryl Client Rights', 'kyndrylclient') . "</th></tr>";
    echo "</thead>";
    echo "<tbody>";

    // Configuration rights (using existing GLPI config right)
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('Configuration', 'kyndrylclient') . "</td>";
    echo "<td colspan='3'>";

    if (Session::haveRight('profile', UPDATE)) {
      echo __('Uses standard GLPI configuration rights', 'kyndrylclient');
    } else {
      echo __('No permission to modify', 'kyndrylclient');
    }

    echo "</td>";
    echo "</tr>";

    echo "</tbody>";
    echo "</table>";
    echo "</div>";
  }

  /**
   * Install profile rights
   *
   * @param Migration $migration Migration instance
   * @return void
   */
  public static function install(Migration $migration): void
  {
    // Plugin uses existing GLPI config rights
    // No additional rights table needed
    $migration->displayMessage("Profile rights use existing GLPI config permissions");
  }

  /**
   * Uninstall profile rights
   *
   * @param Migration $migration Migration instance
   * @return void
   */
  public static function uninstall(Migration $migration): void
  {
    // Nothing to uninstall
    $migration->displayMessage("No profile-specific data to remove");
  }
}
