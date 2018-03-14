<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package ${CARET}
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\FieldPalette;


use Contao\Config;
use Contao\Controller;
use Contao\Input;

class FieldPalette
{

    /**
     * Object instance (Singleton)
     *
     * @var \Session
     */
    protected static $objInstance;

    public static $strTableRequestKey = 'table';

    public static $strParentTableRequestKey = 'ptable';

    public static $strPaletteRequestKey = 'fieldpalette';

    public static $strFieldpaletteRefreshAction = 'refreshFieldPaletteField';


    /**
     * Prevent cloning of the object (Singleton)
     */
    final public function __clone()
    {
    }


    /**
     * Return the object instance (Singleton)
     *
     * @return \FieldPalette The object instance
     */
    public static function getInstance()
    {
        if (static::$objInstance === null)
        {
            static::$objInstance = new static();
        }

        return static::$objInstance;
    }

    /**
     * @param string $table
     * @param array $fields
     * @param string $paletteTable
     * @return array
     * @throws \Exception
     */
    public static function extractFieldPaletteFields(string $table, $fields = [], $paletteTable = null)
    {
        $extract = [];

        if ($paletteTable === null)
        {
            $paletteTable = Config::get('fieldpalette_table');
        }

        if (!is_array($fields))
        {
            return $extract;
        }

        foreach ($fields as $name => $field)
        {
            if ($field['inputType'] != 'fieldpalette')
            {
                continue;
            }

            if (!isset($field['fieldpalette']['config']['table']))
            {
                $paletteTable = Config::get('fieldpalette_table');
            }

            if ($field['fieldpalette']['config']['table'] && $field['fieldpalette']['config']['table'] !== $paletteTable)
            {
                $paletteTable = $field['fieldpalette']['config']['table'];

                Controller::loadDataContainer($paletteTable);


                if (!isset($GLOBALS['TL_DCA'][$paletteTable]))
                {
                    throw new \Exception('Custom fieldpalette table ' . $paletteTable . ' does not exist.');
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
        if (!isset($GLOBALS['loadDataContainer'][$strTable]))
        {
            \Controller::loadDataContainer($strTable);
        }

        $strRootTable = '';
        $varPalette   = [];

        switch ($strAct)
        {
            case 'create':
                $strParentTable = FieldPalette::getParentTableFromRequest();
                $strRootTable   = $strParentTable;

                // determine root table from parent entity tree, if requested parent table = tl_fieldpalette -> nested fieldpalette
                if ($strParentTable == $strTable && $intPid = \Input::get('pid'))
                {
                    $objParent = FieldPaletteModel::setTable($strParentTable)->findByPk($intPid);

                    if ($objParent !== null)
                    {
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

                $objModel = \HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel::setTable($strTable)->findByPk($id);

                if ($objModel === null)
                {
                    break;
                }

                list($strRootTable, $varPalette) = FieldPalette::getParentTable($objModel, $objModel->id);
                $strParentTable = $objModel->ptable;

                // set back link from request
                if (\Input::get('popup') && \Input::get('popupReferer'))
                {
                    $arrSession = \Session::getInstance()->getData();

                    if (class_exists('\Contao\StringUtil'))
                    {
                        $arrSession['popupReferer'][TL_REFERER_ID]['current'] = \StringUtil::decodeEntities(rawurldecode(\Input::get('popupReferer')));
                    } else
                    {
                        $arrSession['popupReferer'][TL_REFERER_ID]['current'] = \StringUtil::decodeEntities(rawurldecode(\Input::get('popupReferer')));
                    }

                    \Session::getInstance()->setData($arrSession);
                }

                break;
        }

        if (!$strRootTable || !$varPalette)
        {
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

        if (!$GLOBALS['TL_DCA'][$strTable]['config']['fieldpalette'] || $strParentTable === null || $varPalette === null)
        {
            return false;
        }

        if ($strTable != $strRootTable)
        {
            \Controller::loadDataContainer($strRootTable);
        }

        $arrDCA = $GLOBALS['TL_DCA'][$strRootTable];

        $arrFields = $arrDCA['fields'];

        if (!is_array($arrFields))
        {
            return false;
        }

        if (!$varPalette)
        {
            return false;
        }

        // nested palette
        if (is_array($varPalette))
        {
            $arrNestedPalette = static::findNestedFieldPaletteFields($varPalette, $arrFields);

            if ($arrNestedPalette !== false)
            {
                $arrFields = $arrNestedPalette;
            }
        } else
        {
            if (!isset($arrFields[$varPalette]))
            {
                return false;
            }

            $arrFields = [$varPalette => $arrFields[$varPalette]];
        }

        $blnFound = static::registerFieldPaletteFields($dc, $strTable, $strParentTable, $strRootTable, $varPalette, $arrFields);

        if (!$blnFound)
        {
            static::refuseFromBackendModuleByTable($strTable);
        }
    }

    public static function findNestedFieldPaletteFields(array $arrPalettes, $arrFields)
    {
        if (count($arrPalettes) == 1)
        {
            $strPalette = $arrPalettes[0];

            // root level
            if (!isset($arrFields['fields']) && isset($arrFields[$strPalette]))
            {
                return [$strPalette => $arrFields[$strPalette]];
            }

            // nested palette
            if (isset($arrFields['fields'][$strPalette]))
            {
                return [$strPalette => $arrFields['fields'][$strPalette]];
            }

            return false;
        }

        foreach ($arrPalettes as $i => $strFieldPalette)
        {
            if (!isset($arrFields[$strFieldPalette]))
            {
                return false;
            }

            if ($arrFields[$strFieldPalette]['inputType'] != 'fieldpalette')
            {
                return false;
            }

            if (!is_array($arrFields[$strFieldPalette]['fieldpalette']))
            {
                return false;
            }

            $arrChildPalettes = array_slice($arrPalettes, $i + 1, count($arrPalettes));

            return static::findNestedFieldPaletteFields($arrChildPalettes, $arrFields[$strFieldPalette]['fieldpalette']['fields']);
        }

    }

    public static function registerFieldPaletteFields(&$dc, $strTable, $strParentTable, $strRootTable, $varPalette, $arrFields, $blnFound = false)
    {
        if (!is_array($arrFields))
        {
            return false;
        }

        foreach ($arrFields as $strField => $arrData)
        {
            if (!is_array($arrData) || !is_array($arrData['fieldpalette']))
            {
                continue;
            }

            $dc['fields'] = array_merge(
                is_array($dc['fields']) ? $dc['fields'] : [],
                is_array($arrData['fieldpalette']['fields']) ? $arrData['fieldpalette']['fields'] : [],
                is_array($GLOBALS['TL_DCA'][$strTable]['fields']) ? $GLOBALS['TL_DCA'][$strTable]['fields'] : []);

            FieldPaletteRegistry::set($strRootTable, $strField, $dc);

            // set active ptable
            if (static::isActive($strRootTable, $strParentTable, $strTable, $strField))
            {
                \Controller::loadLanguageFile($strRootTable); // allow translations within parent fieldpalette table
                $GLOBALS['TL_DCA'][$strTable] = static::getDca($strRootTable, $strParentTable, $strField, $varPalette);
            }

            $blnFound = true;
        }

        return $blnFound;
    }

    public static function isActive($strRootTable, $strParentTable, $strTable, $strField)
    {
        $arrRegistry = FieldPaletteRegistry::get($strRootTable);

        if (!isset($arrRegistry[$strField]))
        {
            return false;
        }

        // determine active state by current element
        if (Fieldpalette::getTableFromRequest() == $strTable)
        {
            $id = strlen(\Input::get('id')) ? \Input::get('id') : CURRENT_ID;

            switch (\Input::get('act'))
            {
                case 'create':
                    return true;
                case 'cut':
                case 'edit':
                case 'show':
                case 'delete':
                case 'toggle':
                    $objModel = \HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel::setTable($strTable)->findByPk($id);

                    if ($objModel === null)
                    {
                        return false;
                    }

                    return ($strParentTable == $objModel->ptable && $strField == $objModel->pfield);
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
        if (empty($arrPalette))
        {
            $arrPalette = [$objModel->pfield];
        }

        if ($objModel->ptable == \Config::get('fieldpalette_table'))
        {
            $objModel = \HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel::findByPk($objModel->pid);

            if ($objModel === null)
            {
                throw new \Exception(sprintf($GLOBALS['TL_LANG']['ERR']['fieldPaletteNestedParentTableDoesNotExist'], $intId));
            }

            // save nested path
            if ($objModel->pfield)
            {
                $arrPalette[] = $objModel->pfield;
            }

            return static::getParentTable($objModel, $intId, $arrPalette);
        }

        return [$objModel->ptable, array_reverse($arrPalette)];
    }

    public static function getDca($strRootTable, $strParentTable, $strField, $varPalette = null)
    {
        \Controller::loadDataContainer($strRootTable);

        // custom table support
        $paletteTable = $GLOBALS['TL_DCA'][$strRootTable]['fields'][$strField]['fieldpalette']['config']['table'] ?: \Config::get('fieldpalette_table');

        \Controller::loadDataContainer($paletteTable);

        $arrData = [];

        $arrDefaults = $GLOBALS['TL_DCA'][$paletteTable];
        $arrCustom   = $GLOBALS['TL_DCA'][$strRootTable]['fields'][$strField]['fieldpalette'];

        if (is_array($varPalette))
        {
            $arrNestedPalette = static::findNestedFieldPaletteFields($varPalette, $GLOBALS['TL_DCA'][$strRootTable]['fields']);

            if ($arrNestedPalette !== false)
            {
                $strNestedPalette = key($arrNestedPalette);
                $arrCustom        = $arrNestedPalette[$strNestedPalette]['fieldpalette'];
            }
        }

        if (!is_array($arrDefaults) || !is_array($arrCustom))
        {
            return $arrData;
        }

        // replace tl_fieldpalette with custom config
        $arrData                     = @array_replace_recursive($arrDefaults, $arrCustom); // supress warning, as long as references may exist in both arrays
        $arrData['config']['ptable'] = $strParentTable;

        if ($arrData['config']['hidePublished'])
        {
            $arrData['fields']['published']['inputType'] = 'hidden';
            $arrData['fields']['published']['default']   = true;
            unset($arrData['list']['operations']['toggle']);
            $arrData['palettes']['default'] .= ',published';
        } else
        {
            $arrData['palettes']['default'] .= ';{published_legend},published'; // always append published
        }

        // Include all excluded fields which are allowed for the current user
        if ($arrData['fields'])
        {
            foreach ($arrData['fields'] as $k => $v)
            {
                if ($v['exclude'])
                {
                    if (\BackendUser::getInstance()->hasAccess($paletteTable . '::' . $k, 'alexf'))
                    {
                        if (\Config::get('fieldpalette_table') == 'tl_user_group')
                        {
                            $arrData['fields'][$k]['orig_exclude'] = $arrData['fields'][$k]['exclude'];
                        }

                        $arrData['fields'][$k]['exclude'] = false;
                    }
                }
            }
        }

        return $arrData;
    }

    public static function refuseFromBackendModuleByTable($strTable)
    {
        foreach ($GLOBALS['BE_MOD'] as $strGroup => $arrGroup)
        {
            if (!is_array($arrGroup))
            {
                continue;
            }

            foreach ($arrGroup as $strModule => $arrModule)
            {
                if (!is_array($arrModule) || !is_array($arrModule['tables']))
                {
                    continue;
                }

                if (!in_array($strTable, $arrModule['tables'])
                    || ($idx = array_search(\Config::get('fieldpalette_table'), $arrModule['tables'])) === false
                )
                {
                    continue;
                }

                unset($GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][$idx]);
            }
        }
    }

    /**
     * Use this method as an oncopy_callback in order to support recursive copying fieldpalette records by copying their parent record
     *
     * @param $intNewId
     */
    public function copyFieldPaletteRecords($intNewId)
    {
        if (TL_MODE != 'BE')
        {
            return;
        }

        $intId    = strlen(Input::get('id')) ? Input::get('id') : CURRENT_ID;
        $strTable = 'tl_' . \Input::get('do');

        if (!$intId || !$strTable)
        {
            return;
        }

        \Controller::loadDataContainer($strTable);
        $arrDcaFields = $GLOBALS['TL_DCA'][$strTable]['fields'];

        static::recursivelyCopyFieldPaletteRecords($intId, $intNewId, $strTable, $arrDcaFields);
    }

    /**
     * @param $intPid       int The id of the former parent record
     * @param $intNewId     int the id of the new parent record just copied from the former record
     * @param $strTable     string The parent table
     * @param $arrDcaFields array A dca array of fields
     */
    public static function recursivelyCopyFieldPaletteRecords($intPid, $intNewId, $strTable, array $arrDcaFields)
    {
        foreach ($arrDcaFields as $strField => $arrData)
        {
            if ($arrData['inputType'] == 'fieldpalette')
            {
                if (isset($arrData['fieldpalette']['fields']) && !$arrData['eval']['doNotCopy'])
                {
                    $objFieldPaletteRecords = FieldPaletteModel::findByPidAndTableAndField($intPid, $strTable, $strField);

                    if ($objFieldPaletteRecords === null)
                    {
                        continue;
                    }

                    while ($objFieldPaletteRecords->next())
                    {
                        $objFieldpalette = new FieldPaletteModel();

                        // get existing data except id
                        $arrFieldData = $objFieldPaletteRecords->row();
                        unset($arrFieldData['id']);

                        $objFieldpalette->setRow($arrFieldData);

                        // set new data
                        $objFieldpalette->tstamp    = time();
                        $objFieldpalette->pid       = $intNewId;
                        $objFieldpalette->published = true;

                        if (isset($arrData['eval']['fieldpalette']['copy_callback']) && is_array($arrData['eval']['fieldpalette']['copy_callback']))
                        {
                            foreach ($arrData['eval']['fieldpalette']['copy_callback'] as $arrCallback)
                            {
                                if (is_array($arrCallback))
                                {
                                    \System::importStatic($arrCallback[0]);
                                    $arrCallback[0]::$arrCallback[1]($objFieldpalette, $intPid, $intNewId, $strTable, $arrData);
                                } elseif (is_callable($arrCallback))
                                {
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
            } else
            {
                if ($strTable == \Config::get('fieldpalette_table'))
                {
                    $objFieldPaletteRecords = FieldPaletteModel::findByPidAndTableAndField($intPid, $strTable, $strField);

                    if ($objFieldPaletteRecords === null)
                    {
                        continue;
                    }

                    while ($objFieldPaletteRecords->next())
                    {
                        $objFieldpalette = new FieldPaletteModel();
                        $objFieldpalette->setRow($objFieldPaletteRecords->row());
                        // set new data
                        $objFieldpalette->tstamp    = time();
                        $objFieldpalette->pid       = $intNewId;
                        $objFieldpalette->published = true;

                        if (isset($arrData['eval']['fieldpalette']['copy_callback']) && is_array($arrData['eval']['fieldpalette']['copy_callback']))
                        {
                            foreach ($arrData['eval']['fieldpalette']['copy_callback'] as $arrCallback)
                            {
                                if (is_array($arrCallback))
                                {
                                    \System::importStatic($arrCallback[0]);
                                    $arrCallback[0]::$arrCallback[1]($objFieldpalette, $intPid, $intNewId, $strTable, $arrData);
                                } elseif (is_callable($arrCallback))
                                {
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
