<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\DcaHelper;

use Contao\BackendUser;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\System;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class DcaHandler
{
    /**
     * @var string
     */
    const TableRequestKey = 'table';
    /**
     * @var string
     */
    const ParentTableRequestKey = 'ptable';
    /**
     * @var string
     */
    const PaletteRequestKey = 'fieldpalette';
    /**
     * @var string
     */
    const FieldpaletteRefreshAction = 'refreshFieldPaletteField';
    /**
     * @var ContaoFramework
     */
    private $framework;
    /**
     * @var string
     */
    private $defaultTable;

    public function __construct(string $table, ContaoFramework $framework)
    {
        $this->framework = $framework;
        $this->defaultTable = $table;
    }

    /**
     * @param string $table
     * @param array  $fields
     * @param string $paletteTable
     *
     * @throws \Exception
     *
     * @return array
     */
    public static function extractFieldPaletteFields(string $table, $fields = [], $paletteTable = null)
    {
        $extract = [];

        if (null === $paletteTable) {
            $paletteTable = Config::get('fieldpalette_table');
        }

        if (!is_array($fields)) {
            return $extract;
        }

        foreach ($fields as $name => $field) {
            if ('fieldpalette' !== $field['inputType']) {
                continue;
            }

            if (!isset($field['fieldpalette']['config']['table'])) {
                $paletteTable = Config::get('fieldpalette_table');
            }

            if ($field['fieldpalette']['config']['table'] && $field['fieldpalette']['config']['table'] !== $paletteTable) {
                $paletteTable = $field['fieldpalette']['config']['table'];

                Controller::loadDataContainer($paletteTable);

                if (!isset($GLOBALS['TL_DCA'][$paletteTable])) {
                    throw new \Exception('Custom fieldpalette table '.$paletteTable.' does not exist.');
                    continue;
                }
            }

            $extract[$paletteTable] = array_merge(
                is_array($extract[$paletteTable]) ? $extract[$paletteTable] : [],
                is_array($field['fieldpalette']['fields']) ? $field['fieldpalette']['fields'] : []
            );

            $extract = array_merge_recursive($extract, static::extractFieldPaletteFields($table, $field['fieldpalette']['fields']));
        }

        return $extract;
    }

    public static function loadDynamicPaletteByParentTable($strAct, $strTable, $strParentTable)
    {
        if (!isset($GLOBALS['loadDataContainer'][$strTable])) {
            \Controller::loadDataContainer($strTable);
        }

        $strRootTable = '';
        $varPalette = [];

        switch ($strAct) {
            case 'create':
                $strParentTable = FieldPalette::getParentTableFromRequest();
                $strRootTable = $strParentTable;

                // determine root table from parent entity tree, if requested parent table = tl_fieldpalette -> nested fieldpalette
                if ($strParentTable === $strTable && $intPid = \Input::get('pid')) {
                    $helper = new FieldPaletteModel();
                    $objParent = $helper->setTable($strParentTable)->findByPk($intPid);

                    if (null !== $objParent) {
                        list($strRootTable, $varPalette) = static::getParentTable($objParent, $objParent->id);
                    }
                }

                $varPalette[] = FieldPalette::getPaletteFromRequest(); // append requested palette

                break;
            case 'cut':
            case 'edit':
            case 'show':
            case 'delete':
            case 'toggle':
                $id = strlen(\Input::get('id')) ? \Input::get('id') : CURRENT_ID;

                $helper = new FieldPaletteModel();
                $objModel = $helper->setTable($strTable)->findByPk($id);

                if (null === $objModel) {
                    break;
                }

                list($strRootTable, $varPalette) = FieldPalette::getParentTable($objModel, $objModel->id);
                $strParentTable = $objModel->ptable;

                // set back link from request
                if (\Input::get('popup') && \Input::get('popupReferer')) {
                    $arrSession = \Session::getInstance()->getData();

                    if (class_exists('\Contao\StringUtil')) {
                        $arrSession['popupReferer'][TL_REFERER_ID]['current'] = \StringUtil::decodeEntities(rawurldecode(\Input::get('popupReferer')));
                    } else {
                        $arrSession['popupReferer'][TL_REFERER_ID]['current'] = \StringUtil::decodeEntities(rawurldecode(\Input::get('popupReferer')));
                    }

                    \Session::getInstance()->setData($arrSession);
                }

                break;
        }

        if (!$strRootTable || !$varPalette) {
            return false;
        }

        return [$varPalette, $strRootTable, $strParentTable];
    }

    public static function registerFieldPalette($strTable)
    {
        $strParentTable = static::getParentTableFromRequest();

        list(
            $varPalette, $strRootTable, $strParentTable
            ) = FieldPalette::loadDynamicPaletteByParentTable(\Input::get('act'), $strTable, $strParentTable);

        if (!$GLOBALS['TL_DCA'][$strTable]['config']['fieldpalette'] || null === $strParentTable || null === $varPalette) {
            return false;
        }

        if ($strTable !== $strRootTable) {
            \Controller::loadDataContainer($strRootTable);
        }

        $arrDCA = $GLOBALS['TL_DCA'][$strRootTable];

        $arrFields = $arrDCA['fields'];

        if (!is_array($arrFields)) {
            return false;
        }

        if (!$varPalette) {
            return false;
        }

        // nested palette
        if (is_array($varPalette)) {
            $arrNestedPalette = static::findNestedFieldPaletteFields($varPalette, $arrFields);

            if (false !== $arrNestedPalette) {
                $arrFields = $arrNestedPalette;
            }
        } else {
            if (!isset($arrFields[$varPalette])) {
                return false;
            }

            $arrFields = [$varPalette => $arrFields[$varPalette]];
        }

        $blnFound = static::registerFieldPaletteFields($dc, $strTable, $strParentTable, $strRootTable, $varPalette, $arrFields);

        if (!$blnFound) {
            static::refuseFromBackendModuleByTable($strTable);
        }
    }

    public function findNestedFieldPaletteFields(array $arrPalettes, $arrFields)
    {
        if (1 === count($arrPalettes)) {
            $strPalette = $arrPalettes[0];

            // root level
            if (!isset($arrFields['fields']) && isset($arrFields[$strPalette])) {
                return [$strPalette => $arrFields[$strPalette]];
            }

            // nested palette
            if (isset($arrFields['fields'][$strPalette])) {
                return [$strPalette => $arrFields['fields'][$strPalette]];
            }

            return false;
        }

        foreach ($arrPalettes as $i => $strFieldPalette) {
            if (!isset($arrFields[$strFieldPalette])) {
                return false;
            }

            if ($arrFields[$strFieldPalette]['inputType'] !== 'fieldpalette') {
                return false;
            }

            if (!is_array($arrFields[$strFieldPalette]['fieldpalette'])) {
                return false;
            }

            $arrChildPalettes = array_slice($arrPalettes, $i + 1, count($arrPalettes));

            return $this->findNestedFieldPaletteFields($arrChildPalettes, $arrFields[$strFieldPalette]['fieldpalette']['fields']);
        }
    }

    public static function registerFieldPaletteFields(&$dc, $strTable, $strParentTable, $strRootTable, $varPalette, $arrFields, $blnFound = false)
    {
        if (!is_array($arrFields)) {
            return false;
        }

        foreach ($arrFields as $strField => $arrData) {
            if (!is_array($arrData) || !is_array($arrData['fieldpalette'])) {
                continue;
            }

            $dc['fields'] = array_merge(
                is_array($dc['fields']) ? $dc['fields'] : [],
                is_array($arrData['fieldpalette']['fields']) ? $arrData['fieldpalette']['fields'] : [],
                is_array($GLOBALS['TL_DCA'][$strTable]['fields']) ? $GLOBALS['TL_DCA'][$strTable]['fields'] : []);

            System::getContainer()->get('huh.fieldpalette.registry')->set($strRootTable, $strField, $dc);

            // set active ptable
            if (static::isActive($strRootTable, $strParentTable, $strTable, $strField)) {
                \Controller::loadLanguageFile($strRootTable); // allow translations within parent fieldpalette table
                $GLOBALS['TL_DCA'][$strTable] = static::getDca($strRootTable, $strParentTable, $strField, $varPalette);
            }

            $blnFound = true;
        }

        return $blnFound;
    }

    public static function isActive($strRootTable, $strParentTable, $strTable, $strField)
    {
        $arrRegistry = System::getContainer()->get('huh.fieldpalette.registry')->get($strRootTable);

        if (!isset($arrRegistry[$strField])) {
            return false;
        }

        // determine active state by current element
        if (Fieldpalette::getTableFromRequest() === $strTable) {
            $id = strlen(\Input::get('id')) ? \Input::get('id') : CURRENT_ID;

            switch (\Input::get('act')) {
                case 'create':
                    return true;
                case 'cut':
                case 'edit':
                case 'show':
                case 'delete':
                case 'toggle':
                    $helper = new FieldPaletteModel();
                    $objModel = $helper->setTable($strTable)->findByPk($id);

                    if (null === $objModel) {
                        return false;
                    }

                    return $strParentTable === $objModel->ptable && $strField === $objModel->pfield;
            }
        }

        return false;
    }

    public static function getTableFromRequest()
    {
        return Input::get(static::$strTableRequestKey);
    }

    public static function getParentTableFromRequest()
    {
        return \Input::get(static::$strParentTableRequestKey);
    }

    public static function getPaletteFromRequest()
    {
        return \Input::get(static::$strPaletteRequestKey);
    }

    public static function getParentTable($objModel, $intId, $arrPalette = [])
    {
        // always store current pfield
        if (empty($arrPalette)) {
            $arrPalette = [$objModel->pfield];
        }

        if ($objModel->ptable === \Config::get('fieldpalette_table')) {
            $objModel = Sys::findByPk($objModel->pid);

            if (null === $objModel) {
                throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['fieldPaletteNestedParentTableDoesNotExist'], $intId));
            }

            // save nested path
            if ($objModel->pfield) {
                $arrPalette[] = $objModel->pfield;
            }

            return static::getParentTable($objModel, $intId, $arrPalette);
        }

        return [$objModel->ptable, array_reverse($arrPalette)];
    }

    public function getDca(string $rootTable, string $parentTable, string $field, array $palette = [])
    {
        $controller = $this->framework->getAdapter(Controller::class);
        $controller->loadDataContainer($rootTable);

        // custom table support
        $paletteTable = $GLOBALS['TL_DCA'][$rootTable]['fields'][$field]['fieldpalette']['config']['table'] ?: $this->defaultTable;

        $controller->loadDataContainer($paletteTable);

        $data = [];

        $defauts = $GLOBALS['TL_DCA'][$paletteTable];
        $custom = $GLOBALS['TL_DCA'][$rootTable]['fields'][$field]['fieldpalette'];

        if (is_array($palette)) {
            $nestedPalette = $this->findNestedFieldPaletteFields($palette, $GLOBALS['TL_DCA'][$rootTable]['fields']);

            if ($nestedPalette) {
                $strNestedPalette = key($nestedPalette);
                $custom = $nestedPalette[$strNestedPalette]['fieldpalette'];
            }
        }

        if (!is_array($defauts) || !is_array($custom)) {
            return $data;
        }

        // replace tl_fieldpalette with custom config
        $data = @array_replace_recursive($defauts, $custom); // supress warning, as long as references may exist in both arrays
        $data['config']['ptable'] = $parentTable;

        if ($data['config']['hidePublished']) {
            $data['fields']['published']['inputType'] = 'hidden';
            $data['fields']['published']['default'] = true;
            unset($data['list']['operations']['toggle']);
            $data['palettes']['default'] .= ',published';
        } else {
            $data['palettes']['default'] .= ';{published_legend},published'; // always append published
        }

        $backendUser = $this->framework->getAdapter(BackendUser::class)->getInstance;

        // Include all excluded fields which are allowed for the current user
        if ($data['fields']) {
            foreach ($data['fields'] as $k => $v) {
                if ($v['exclude']) {
                    if ($backendUser->hasAccess($paletteTable.'::'.$k, 'alexf')) {
                        if ('tl_user_group' === $this->defaultTable) {
                            $data['fields'][$k]['orig_exclude'] = $data['fields'][$k]['exclude'];
                        }

                        $data['fields'][$k]['exclude'] = false;
                    }
                }
            }
        }

        return $data;
    }

    public static function refuseFromBackendModuleByTable($strTable)
    {
        foreach ($GLOBALS['BE_MOD'] as $strGroup => $arrGroup) {
            if (!is_array($arrGroup)) {
                continue;
            }

            foreach ($arrGroup as $strModule => $arrModule) {
                if (!is_array($arrModule) || !is_array($arrModule['tables'])) {
                    continue;
                }

                if (!in_array($strTable, $arrModule['tables'], true)
                    || false === ($idx = array_search(\Config::get('fieldpalette_table'), $arrModule['tables'], true))
                ) {
                    continue;
                }

                unset($GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][$idx]);
            }
        }
    }

    /**
     * @param $intPid       int The id of the former parent record
     * @param $intNewId     int the id of the new parent record just copied from the former record
     * @param $strTable     string The parent table
     * @param $arrDcaFields array A dca array of fields
     */
    public static function recursivelyCopyFieldPaletteRecords($intPid, $intNewId, $strTable, array $arrDcaFields)
    {
        foreach ($arrDcaFields as $strField => $arrData) {
            if ('fieldpalette' === $arrData['inputType']) {
                if (isset($arrData['fieldpalette']['fields']) && !$arrData['eval']['doNotCopy']) {
                    $objFieldPaletteRecords = System::getContainer()->get('contao.framework')->getAdapter(FieldPaletteModel::class)->findByPidAndTableAndField($intPid, $strTable, $strField);

                    if (null === $objFieldPaletteRecords) {
                        continue;
                    }

                    while ($objFieldPaletteRecords->next()) {
                        $objFieldpalette = new FieldPaletteModel();

                        // get existing data except id
                        $arrFieldData = $objFieldPaletteRecords->row();
                        unset($arrFieldData['id']);

                        $objFieldpalette->setRow($arrFieldData);

                        // set new data
                        $objFieldpalette->tstamp = time();
                        $objFieldpalette->pid = $intNewId;
                        $objFieldpalette->published = true;

                        if (isset($arrData['eval']['fieldpalette']['copy_callback']) && is_array($arrData['eval']['fieldpalette']['copy_callback'])) {
                            foreach ($arrData['eval']['fieldpalette']['copy_callback'] as $arrCallback) {
                                if (is_array($arrCallback)) {
                                    \System::importStatic($arrCallback[0]);
                                    $arrCallback[0]::$arrCallback[1]($objFieldpalette, $intPid, $intNewId, $strTable, $arrData);
                                } elseif (is_callable($arrCallback)) {
                                    $arrCallback($objFieldpalette, $intPid, $intNewId, $strTable, $arrData);
                                }
                            }
                        }

                        $objFieldpalette->save();

                        static::recursivelyCopyFieldPaletteRecords(
                            $objFieldPaletteRecords->id,
                            $objFieldpalette->id,
                            \Config::get('fieldpalette_table'),
                            $arrData['fieldpalette']['fields']
                        );
                    }
                }
            } else {
                if ($strTable === \Config::get('fieldpalette_table')) {
                    $objFieldPaletteRecords = System::getContainer()->get('contao.framework')->getAdapter(FieldPaletteModel::class)->findByPidAndTableAndField($intPid, $strTable, $strField);

                    if (null === $objFieldPaletteRecords) {
                        continue;
                    }

                    while ($objFieldPaletteRecords->next()) {
                        $objFieldpalette = new FieldPaletteModel();
                        $objFieldpalette->setRow($objFieldPaletteRecords->row());
                        // set new data
                        $objFieldpalette->tstamp = time();
                        $objFieldpalette->pid = $intNewId;
                        $objFieldpalette->published = true;

                        if (isset($arrData['eval']['fieldpalette']['copy_callback']) && is_array($arrData['eval']['fieldpalette']['copy_callback'])) {
                            foreach ($arrData['eval']['fieldpalette']['copy_callback'] as $arrCallback) {
                                if (is_array($arrCallback)) {
                                    \System::importStatic($arrCallback[0]);
                                    $arrCallback[0]::$arrCallback[1]($objFieldpalette, $intPid, $intNewId, $strTable, $arrData);
                                } elseif (is_callable($arrCallback)) {
                                    $arrCallback($objFieldpalette, $intPid, $intNewId, $strTable, $arrData);
                                }
                            }
                        }

                        $objFieldpalette->save();
                    }
                }
            }
        }
    }
}
