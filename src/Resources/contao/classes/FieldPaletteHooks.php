<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package fieldpalette
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\FieldPalette;


class FieldPaletteHooks extends \Controller
{

    protected static $arrSkipTables = ['tl_formdata'];
    protected static $intMaximumDepth = 10;
    protected static $intCurrentDepth = 0;

    public function executePostActionsHook($strAction, \DataContainer $dc)
    {
        if ($strAction == FieldPalette::$strFieldpaletteRefreshAction) {
            if (\Input::post('field')) {
                \Controller::loadDataContainer($dc->table);

                $strName  = \Input::post('field');
                $arrField = $GLOBALS['TL_DCA'][$dc->table]['fields'][$strName];

                // Die if the field does not exist
                if (!is_array($arrField)) {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                /** @var \Widget $strClass */
                $strClass = $GLOBALS['BE_FFL'][$arrField['inputType']];

                // Die if the class is not defined or inputType is not fieldpalette
                if ($arrField['inputType'] != 'fieldpalette' || !class_exists($strClass)) {
                    header('HTTP/1.1 400 Bad Request');
                    die('Bad Request');
                }

                $arrData = \Widget::getAttributesFromDca($arrField, $strName, $dc->activeRecord->{$strName}, $strName, $dc->table, $dc);

                /** @var \Widget $objWidget */
                $objWidget                = new $strClass($arrData);
                $objWidget->currentRecord = $dc->id;

                die(json_encode(['field' => $strName, 'target' => '#ctrl_' . $strName, 'content' => $objWidget->generate()]));
            }

            header('HTTP/1.1 400 Bad Request');
            die('Bad Request');
        }
    }

    public function initializeSystemHook()
    {
        FieldPalette::adjustBackendModules();
    }

    /**
     * Add fieldpalette fields to tl_fieldpalette
     *
     * @param string $strTable
     *
     */
    public function loadDataContainerHook($strTable)
    {
        // dca extractor does not provide any entity context, show all fieldpalette fields within tl_user_group
        if (version_compare(VERSION, '4.0', '<')) {
            if ((\Input::get('update') == 'database' || \Input::get('do') == 'group')) {
                $this->extractTableFields($strTable);
            }
        }

        if (preg_match('/(contao\/install|install\.php)/', \Environment::get('request')) || \Input::get('do') == 'group') {
            $this->extractTableFields($strTable);
        }

        FieldPalette::registerFieldPalette($strTable);
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
