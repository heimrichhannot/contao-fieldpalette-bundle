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


use Contao\Controller;

class FieldPaletteHooks extends \Controller
{

    protected static $arrSkipTables = ['tl_formdata'];
    protected static $intMaximumDepth = 10;
    protected static $intCurrentDepth = 0;

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
     * @param string $table The field palette table name
     */
    protected function extractTableFields($table)
    {
        $palettes = FieldPalette::extractFieldPaletteFields($table, $GLOBALS['TL_DCA'][$table]['fields']);

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
