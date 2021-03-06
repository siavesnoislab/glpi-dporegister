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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginDporegisterLawfulBasisModel extends CommonDropdown
{
    static $rightname = 'plugin_dporegister_lawfulbasismodel';

    // --------------------------------------------------------------------
    //  PLUGIN MANAGEMENT - DATABASE INITIALISATION
    // --------------------------------------------------------------------

    /**
     * Install or update PluginDporegisterLawfulbasis
     *
     * @param Migration $migration Migration instance
     * @param string    $version   Plugin current version
     *
     * @return boolean
     */
    public static function install(Migration $migration, $version)
    {
        global $DB;
        $table = self::getTable();
        $processingsTable = PluginDporegisterProcessing::getTable();

        if (!TableExists($table)) {

            $query = "CREATE TABLE `$table` (
                `id` int(11) NOT NULL auto_increment,
                `name` varchar(255) collate utf8_unicode_ci default NULL,
                `content` varchar(1024) collate utf8_unicode_ci default NULL,
                `comment` text collate utf8_unicode_ci,
                `is_gdpr` tinyint(1) NOT NULL default 0,
                `entities_id` int(11) NOT NULL default '0',
                `is_recursive` tinyint(1) NOT NULL default '1',
                `date_creation` datetime default NULL,
                `date_mod` datetime default NULL,
                
                PRIMARY KEY  (`id`),
                KEY `name` (`name`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

            $DB->query($query) or die("error creating $table " . $DB->error());
        }

        // Alter Processings table, adding the new field
        if (!FieldExists(
            $processingsTable,
            self::getForeignKeyField()
        )) {

            $query = "ALTER TABLE `$processingsTable` ADD `" . self::getForeignKeyField() . "` int(11) NOT NULL default '0' COMMENT 'RELATION to $table (id)';";
            $DB->query($query) or die("error altering $processingsTable to add the new lawfulbasis column " . $DB->error());

            // Insert GDPR Values in current table
            foreach (self::getGDPRLawfulBasises() as $key => $value) {

                $object = new self();

                // Check if shortname already exists
                $nb = countElementsInTable(

                    self::getTable(),
                    ['name' => $value]
                );

                if ($nb < 1) {

                    $object->add([
                        'name' => $value,
                        'content' => addslashes(self::showGDPRLawfulBasis($key)),
                        'is_gdpr' => true,
                    ]);
                }
            }
        }        

        // Alter Processings table, remove the old field
        if (FieldExists($processingsTable, 'lawfulbasis')) {

            // If there is processings from old versions
            $processings = (new PluginDporegisterProcessing())->find();

            foreach ($processings as $resultSet) {

                $lawfulbasis = new self();

                $name = self::getGDPRLawfulBasises()[$resultSet['lawfulbasis']];
                $lawfulbasis->getFromDBByQuery("WHERE `name` like '$name'");

                if($lawfulbasis) {

                    $processing = new PluginDporegisterProcessing();

                    $resultSet[self::getForeignKeyField()] = $lawfulbasis->fields['id'];                    
                    $processing->update($resultSet);
                }
            }

            $query = "ALTER TABLE `$processingsTable` DROP `lawfulbasis`";
            $DB->query($query) or die("error altering $processingsTable to remove the old lawfulbasis column " . $DB->error());
        }
    }

    /**
     * Uninstall PluginDporegisterLawfulbasis
     *
     * @return boolean
     */
    public static function uninstall()
    {
        global $DB;
        $table = self::getTable();

        if (TableExists($table)) {
            $query = "DROP TABLE `$table`";
            $DB->query($query) or die("error deleting $table");
        }

        $query = "DELETE FROM `glpi_logs` WHERE `itemtype` = '" . __class__ . "'";
        $DB->query($query) or die("error purge logs table");
    }

    // --------------------------------------------------------------------
    //  GLPI PLUGIN COMMON
    // --------------------------------------------------------------------

    //! @copydoc CommonDBTM::canUpdateItem()
    function canUpdateItem() {

        // If it's from GDPR, prevent update
        if($this->fields['is_gdpr']) return false;

        return parent::canUpdateItem();
    }

    //! @copydoc CommonDBTM::canDeleteItem()
    function canDeleteItem() {

        // If it's from GDPR, prevent delete
        if($this->fields['is_gdpr']) return false;

        return parent::canDeleteItem();
    }

    //! @copydoc CommonDBTM::canPurgeItem()
    function canPurgeItem() {

        // If it's from GDPR, prevent edit
        if($this->fields['is_gdpr']) return false;

        return parent::canPurgeItem();
    }

    //! @copydoc CommonGLPI::getTypeName($nb)
    public static function getTypeName($nb = 0)
    {
        return _n('LawfulBasis', 'LawfulBasises', $nb, 'dporegister');
    }

    //! @copydoc CommonDropdown::getAdditionalFields()
    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'content',
                'label' => __('Content'),
                'type' => 'textarea',
                'rows' => 6
            ]
        ];
    }

    // --------------------------------------------------------------------
    //  SPECIFICS FOR THE CURRENT OBJECT CLASS
    // --------------------------------------------------------------------

    /**
     * Get the lawful basis list from GDPR
     * 
     * @param Boolean $WithMetaForSearch
     * 
     * @return Array
     */
    protected static function getGDPRLawfulBasises($withmetaforsearch = false)
    {
        $options = [
            'undef' => __('Undefined', 'dporegister'),
            'art6a' => __('Article 6-a', 'dporegister'),
            'art6b' => __('Article 6-b', 'dporegister'),
            'art6c' => __('Article 6-c', 'dporegister'),
            'art6d' => __('Article 6-d', 'dporegister'),
            'art6e' => __('Article 6-e', 'dporegister'),
            'art6f' => __('Article 6-f', 'dporegister')
        ];

        if ($withmetaforsearch) {

            $options['all'] = __('All');
        }

        return $options;
    }

    /**
     * Get the full description of the lawful basis from GDPR
     * 
     * @param String $îndex
     * 
     * @return String
     */
    protected static function showGDPRLawfulBasis($index)
    {
        $options = [
            'undef' => __('Select a Lawful Basis for this processing.', 'dporegister'),
            'art6a' => __('The data subject has given consent to the processing of his or her personal data for one or more specific purposes.', 'dporegister'),
            'art6b' => __('Processing is necessary for the performance of a contract to which the data subject is party or in order to take steps at the request of the data subject prior to entering into a contract.', 'dporegister'),
            'art6c' => __('Processing is necessary for compliance with a legal obligation to which the controller is subject.', 'dporegister'),
            'art6d' => __('Processing is necessary in order to protect the vital interests of the data subject or of another natural person.', 'dporegister'),
            'art6e' => __('Processing is necessary for the performance of a task carried out in the public interest or in the exercise of official authority vested in the controller.', 'dporegister'),
            'art6f' => __('Processing is necessary for the purposes of the legitimate interests pursued by the controller or by a third party, except where such interests are overridden by the interests or fundamental rights and freedoms of the data subject which require protection of personal data, in particular where the data subject is a child.', 'dporegister'),
        ];

        if (array_key_exists($index, $options)) {
            return $options[$index];
        }

        return '';
    }
}