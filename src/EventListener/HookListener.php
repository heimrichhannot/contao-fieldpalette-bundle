<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @author  Thomas Körner <t.koerner@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */


namespace HeimrichHannot\FieldpaletteBundle\EventListener;


use Contao\Controller;
use Contao\Environment;
use Contao\Input;
use Contao\Widget;
use HeimrichHannot\FieldPalette\FieldPalette;
use Contao\DataContainer;

class HookListener
{
    public function __construct()
    {
    }

    /**
     * @param string $action
     * @param DataContainer $dc
     */
    public function executePostActionsHook($action, DataContainer $dc)
    {
        if ($action === FieldPalette::$strFieldpaletteRefreshAction)
        {
            if (Input::post('field'))
            {
                Controller::loadDataContainer($dc->table);

                $name  = Input::post('field');
                $field = $GLOBALS['TL_DCA'][$dc->table]['fields'][$name];

                // Die if the field does not exist
                if (!is_array($field))
                {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                /** @var Widget $class */
                $class = $GLOBALS['BE_FFL'][$field['inputType']];

                // Die if the class is not defined or inputType is not fieldpalette
                if ($field['inputType'] != 'fieldpalette' || !class_exists($class))
                {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $attributes = Widget::getAttributesFromDca($field, $name, $dc->activeRecord->{$name}, $name, $dc->table, $dc);

                /** @var Widget $widget */
                $widget                = new $class($attributes);
                $widget->currentRecord = $dc->id;

                die(json_encode(['field' => $name, 'target' => '#ctrl_' . $name, 'content' => $widget->generate()]));
            }

            header('HTTP/1.1 400 Bad Request');
            die('Bad Request');
        }
    }

    /**
     * Add fieldpalette fields to tl_fieldpalette
     *
     * @param string $table
     *
     */
    public function loadDataContainerHook($table)
    {
        // dca extractor does not provide any entity context, show all fieldpalette fields within tl_user_group
        if (version_compare(VERSION, '4.0', '<')) {
            if ((Input::get('update') == 'database' || Input::get('do') == 'group')) {
                $this->extractTableFields($table);
            }
        }

        if (preg_match('/(contao\/install|install\.php)/', Environment::get('request')) || Input::get('do') == 'group') {
            $this->extractTableFields($table);
        }

        FieldPalette::registerFieldPalette($table);
    }

    /**
     * Extract table fields sql
     * @param string $strTable The field palette table name
     */
    protected function extractTableFields($strTable)
    {
        $palettes = FieldPalette::extractFieldPaletteFields($strTable, $GLOBALS['TL_DCA'][$strTable]['fields']);

        foreach ($palettes as $paletteTable => $fields) {

            if (!isset($GLOBALS['loadDataContainer'][$paletteTable])) {
                \Controller::loadDataContainer($paletteTable);
            }

            $GLOBALS['TL_DCA'][$paletteTable]['fields'] = array_merge(
                is_array($GLOBALS['TL_DCA'][$paletteTable]['fields']) ? $GLOBALS['TL_DCA'][$paletteTable]['fields'] : [],
                is_array($fields) ? $fields : []
            );
        }
    }


    /**
     * Modify the tl_fieldpalette dca sql, afterwards all loadDataContainer Hooks has been run
     * This is required, fields within all dca tables needs to be added to the database
     *
     * @param $arrDCASqlExtract
     *
     * @return $array The entire extracted sql data from all tables
     */
    public function sqlGetFromDcaHook($arrDCASqlExtract)
    {
        // in contao 4 we have to load the DCA for all table before we extract tl_fieldpalette fields
        if (version_compare(VERSION, '4.0', '>=')) {
            foreach ($arrDCASqlExtract as $strTable => $extract) {
                \Controller::loadDataContainer($strTable);
            }
        }

        $objExtract = new FieldPaletteDcaExtractor(\Config::get('fieldpalette_table'));

        if ($objExtract->isDbTable()) {
            $arrDCASqlExtract[\Config::get('fieldpalette_table')] = $objExtract->getDbInstallerArray();
        }

        return $arrDCASqlExtract;
    }


}