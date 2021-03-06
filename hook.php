<?php
/*
 -------------------------------------------------------------------------
 DPO Register plugin for GLPI
 Copyright (C) 2018 by the DPO Register Development Team.

 https://github.com/karhel/glpi-dporegister
 -------------------------------------------------------------------------

 LICENSE

 This file is part of DPO Register.

 DPO Register is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 DPO Register is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with DPO Register. If not, see <http://www.gnu.org/licenses/>.

 --------------------------------------------------------------------------

  @package   dporegister
  @author    Karhel Tmarr
  @copyright Copyright (c) 2010-2013 Uninstall plugin team
  @license   GPLv3+
             http://www.gnu.org/licenses/gpl.txt
  @link      https://github.com/karhel/glpi-dporegister
  @since     2018
 --------------------------------------------------------------------------
 */

function plugin_dporegister_classesToInstall()
{
    return [
        'Profile',
        'Processing',
        'Processing_Software',
        'PersonalDataCategory',
        'Processing_PersonalDataCategory',
        'IndividualsCategory',
        'Processing_IndividualsCategory',
        'SecurityMesure',
        'Processing_SecurityMesure',
        'Representative',
        'LawfulBasisModel',
    ];
}

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_dporegister_install()
{
    $plugin = new Plugin;
    $plugin->getFromDBbyDir('dporegister');

    $version = $plugin->fields['version'];

    $migration = new Migration($version);
    $classesToInstall = plugin_dporegister_classesToInstall();

    foreach ($classesToInstall as $className) {

        require_once('inc/' . strtolower($className) . '.class.php');

        $fullclassname = 'PluginDporegister' . $className;
        $fullclassname::install($migration, $version);
    }

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_dporegister_uninstall()
{
    $classesToInstall = plugin_dporegister_classesToInstall();
    
    foreach ($classesToInstall as $className) {

        require_once('inc/' . strtolower($className) . '.class.php');

        $fullclassname = 'PluginDporegister' . $className;
        $fullclassname::uninstall();
    }

    return true;
}

/**
 * Define Dropdown tables to be manage in GLPI
 * 
 * @return array
 */
function plugin_dporegister_getDropdown()
{
    // Table => Name
    return [
        PluginDporegisterLawfulBasisModel::class => PluginDporegisterLawfulBasisModel::getTypeName(2),
        PluginDporegisterPersonalDataCategory::class => PluginDporegisterPersonalDataCategory::getTypeName(2),
        PluginDporegisterIndividualsCategory::class => PluginDporegisterIndividualsCategory::getTypeName(2),
        PluginDporegisterSecurityMesure::class => PluginDporegisterSecurityMesure::getTypeName(2)
    ];
}