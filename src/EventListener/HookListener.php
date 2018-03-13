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


use Contao\Config;
use Contao\Controller;
use Contao\Environment;
use Contao\Input;
use Contao\Widget;
use HeimrichHannot\FieldPalette\FieldPalette;
use Contao\DataContainer;
use HeimrichHannot\FieldPalette\FieldPaletteDcaExtractor;

class HookListener
{
    public function __construct()
    {
    }

    /**
     * Adjust back end module to allow fieldpalette table access
     * Note: Do never execute Controller::loadDataContainer() inside this function as no BackendUser is available inside initializeSystem Hook
     */
    public function initializeSystemHook()
    {
        $table = FieldPalette::getTableFromRequest();

        if (empty($table))
        {
            return;
        }

        foreach ($GLOBALS['BE_MOD'] as $strGroup => $arrGroup)
        {
            if (!is_array($arrGroup))
            {
                continue;
            }

            foreach ($arrGroup as $strModule => $arrModule)
            {
                if (!is_array($arrModule) && !is_array($arrModule['tables']))
                {
                    continue;
                }

                $GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][] = $table;
            }
        }
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
     * @throws \Exception
     */
    public function loadDataContainerHook($table)
    {
        if (preg_match('/(contao\/install)/', Environment::get('request')) || Input::get('do') == 'group') {
            $this->extractTableFields($table);
        }

        FieldPalette::registerFieldPalette($table);
    }

    /**
     * Extract table fields sql
     * @param string $tables The field palette table name
     * @throws \Exception
     */
    protected function extractTableFields($tables)
    {
        $palettes = FieldPalette::extractFieldPaletteFields($tables, $GLOBALS['TL_DCA'][$tables]['fields']);

        foreach ($palettes as $paletteTable => $fields) {

            if (!isset($GLOBALS['loadDataContainer'][$paletteTable])) {
                Controller::loadDataContainer($paletteTable);
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
     * @param array $dcaSqlExtract
     *
     * @return array The entire extracted sql data from all tables
     *
     * @throws \Exception
     */
    public function sqlGetFromDcaHook($dcaSqlExtract)
    {
        foreach ($dcaSqlExtract as $table => $sql) {
            Controller::loadDataContainer($table);
        }

        $extract = new FieldPaletteDcaExtractor(Config::get('fieldpalette_table'));

        if ($extract->isDbTable()) {
            $dcaSqlExtract[Config::get('fieldpalette_table')] = $extract->getDbInstallerArray();
        }

        return $dcaSqlExtract;
    }


}