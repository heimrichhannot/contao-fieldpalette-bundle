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


/**
 *
 * @property integer $id
 * @property integer $pid
 * @property integer $tstamp
 *
 *
 * @method static \NewsletterModel|null findById($id, $opt = [])
 * @method static \NewsletterModel|null findByPk($id, $opt = [])
 * @method static \NewsletterModel|null findByIdOrAlias($val, $opt = [])
 * @method static \NewsletterModel|null findOneBy($col, $val, $opt = [])
 * @method static \NewsletterModel|null findOneByPid($val, $opt = [])
 * @method static \NewsletterModel|null findOneByTstamp($val, $opt = [])
 *
 * @method static \Model\Collection|\NewsletterModel|null findByPid($val, $opt = [])
 * @method static \Model\Collection|\NewsletterModel|null findByTstamp($val, $opt = [])
 * @method static \Model\Collection|\NewsletterModel|null findMultipleByIds($val, $opt = [])
 * @method static \Model\Collection|\NewsletterModel|null findBy($col, $val, $opt = [])
 * @method static \Model\Collection|\NewsletterModel|null findAll($opt = [])
 *
 * @method static integer countById($id, $opt = [])
 * @method static integer countByPid($val, $opt = [])
 * @method static integer countByTstamp($val, $opt = [])
 */
class FieldPaletteModel extends \Model
{
    protected static $strTable = 'tl_fieldpalette';


    /**
     * Use this model with a custom fieldpalette table
     * @param $strTable Fieldpalette
     * @return static Current instance
     */
    public static function setTable($strTable)
    {
        static::$strTable = $strTable;

        if (!$GLOBALS['TL_DCA'][$strTable]['config']['fieldpalette'] || !\Contao\Database::getInstance()->tableExists($strTable)) {
            static::$strTable = 'tl_fieldpalette';
            return new static();
        }

        // support custom fieldpalette entities without having its own model
        if (!isset($GLOBALS['TL_MODELS'][$strTable])) {
            $GLOBALS['TL_MODELS'][$strTable] = __CLASS__;
        }

        return new static();
    }

    /**
     * Find all published fieldpalette elements by their ids
     *
     * @param array $arrIds An array of fielpalette ids
     * @param array $arrOptions An optional options array
     * @param array $columns Additional clauses columns
     * @param array $values Additional clauses values
     *
     * @return \Model\Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findPublishedByIds(array $arrIds = [], array $arrOptions = [], array $columns = [], array $values = null)
    {
        $t = static::$strTable;

        if (!is_array($arrIds) || empty($arrIds)) {
            return null;
        }

        $arrColumns = ["$t.id IN(" . implode(',', array_map('intval', $arrIds)) . ")"];

        if (!BE_USER_LOGGED_IN) {
            $time         = \Date::floorToMinute();
            $arrColumns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'" . ($time + 60) . "') AND $t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.sorting";
        }

        $arrColumns = array_merge($arrColumns, $columns);

        return static::findBy($arrColumns, $values, $arrOptions);
    }

    /**
     * Find all published fieldpalette elements by their parent ID and parent table
     *
     * @param integer $intPid The article ID
     * @param string $strParentTable The parent table name
     * @param string $strParentField The parent field name
     * @param array $arrOptions An optional options array
     * @param array $columns Additional clauses columns
     * @param array $values Additional clauses values
     *
     * @return \Model\Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findPublishedByPidAndTableAndField($intPid, $strParentTable, $strParentField, array $arrOptions = [], array $columns = [], array $values = [])
    {
        $t = static::$strTable;

        $arrColumns = ["$t.pid=? AND $t.ptable=? AND $t.pfield=?"];

        if (!BE_USER_LOGGED_IN) {
            $time         = \Date::floorToMinute();
            $arrColumns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'" . ($time + 60) . "') AND $t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.sorting";
        }

        $arrColumns = array_merge($arrColumns, $columns);
        $values     = array_merge([$intPid, $strParentTable, $strParentField], $values);

        return static::findBy($arrColumns, $values, $arrOptions);
    }

    /**
     * Find all published fieldpalette elements by their parent ID and parent table
     *
     * @param array $arrPids The parent ids
     * @param string $strParentTable The parent table name
     * @param string $strParentField The parent field name
     * @param array $arrOptions An optional options array
     * @param array $columns Additional clauses columns
     * @param array $values Additional clauses values
     *
     * @return \Model\Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findPublishedByPidsAndTableAndField($arrPids, $strParentTable, $strParentField, array $arrOptions = [], array $columns = [], array $values = [])
    {
        if (!is_array($arrPids) || empty($arrPids)) {
            return null;
        }

        $t = static::$strTable;

        $arrColumns = ["$t.pid IN(" . implode(',', array_map('intval', $arrPids)) . ")"];

        $arrColumns[] = "$t.ptable=? AND $t.pfield=?";

        if (!BE_USER_LOGGED_IN) {
            $time         = \Date::floorToMinute();
            $arrColumns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'" . ($time + 60) . "') AND $t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "FIELD($t.pid, " . implode(',', array_map('intval', $arrPids)) . "), $t.sorting";
        }

        $arrColumns = array_merge($arrColumns, $columns);
        $values     = array_merge([$strParentTable, $strParentField], $values);

        return static::findBy($arrColumns, $values, $arrOptions);
    }


    /**
     * Find all fieldpalette elements by their parent ID and parent table
     *
     * @param integer $intPid The article ID
     * @param string $strParentTable The parent table name
     * @param string $strParentField The parent field name
     * @param array $arrOptions An optional options array
     * @param array $columns Additional clauses columns
     * @param array $values Additional clauses values
     *
     * @return \Model\Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findByPidAndTableAndField($intPid, $strParentTable, $strParentField, array $arrOptions = [], array $columns = [], array $values = [])
    {
        $t = static::$strTable;

        $arrColumns = ["$t.pid=? AND $t.ptable=? AND $t.pfield=?"];

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.sorting";
        }

        $arrColumns = array_merge($arrColumns, $columns);
        $values     = array_merge([$intPid, $strParentTable, $strParentField], $values);

        return static::findBy($arrColumns, $values, $arrOptions);
    }
}