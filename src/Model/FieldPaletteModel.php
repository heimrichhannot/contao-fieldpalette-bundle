<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Model;

use Contao\Database;
use Contao\Date;
use Contao\Model;
use Contao\Model\Collection;

/**
 * @property int $id
 * @property int $pid
 * @property int $tstamp
 *
 * @method static FieldPaletteModel|null findById($id, $opt = [])
 * @method static FieldPaletteModel|null findByPk($id, $opt = [])
 * @method static FieldPaletteModel|null findByIdOrAlias($val, $opt = [])
 * @method static FieldPaletteModel|null findOneBy($col, $val, $opt = [])
 * @method static FieldPaletteModel|null findOneByPid($val, $opt = [])
 * @method static FieldPaletteModel|null findOneByTstamp($val, $opt = [])
 * @method static Collection|FieldPaletteModel|null findByPid($val, $opt = [])
 * @method static Collection|FieldPaletteModel|null findByTstamp($val, $opt = [])
 * @method static Collection|FieldPaletteModel|null findMultipleByIds($val, $opt = [])
 * @method static Collection|FieldPaletteModel|null findBy($col, $val, $opt = [])
 * @method static Collection|FieldPaletteModel|null findAll($opt = [])
 * @method static integer countById($id, $opt = [])
 * @method static integer countByPid($val, $opt = [])
 * @method static integer countByTstamp($val, $opt = [])
 */
class FieldPaletteModel extends Model
{
    protected static $strTable = 'tl_fieldpalette';

    /**
     * Use this model with a custom fieldpalette table.
     *
     * @param string $table Fieldpalette
     *
     * @return static Current instance
     */
    public static function setTable($table)
    {
        static::$strTable = $table;

        if (!$GLOBALS['TL_DCA'][$table]['config']['fieldpalette'] || !Database::getInstance()->tableExists($table)) {
            static::$strTable = 'tl_fieldpalette';

            return new static();
        }

        // support custom fieldpalette entities without having its own model
        if (!isset($GLOBALS['TL_MODELS'][$table])) {
            $GLOBALS['TL_MODELS'][$table] = __CLASS__;
        }

        return new static();
    }

    /**
     * Find all published fieldpalette elements by their ids.
     *
     * @param array $fieldpaletteIds An array of fielpalette ids
     * @param array $options         An optional options array
     * @param array $columns         Additional clauses columns
     * @param array $values          Additional clauses values
     *
     * @return \Model\Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findPublishedByIds(array $fieldpaletteIds = [], array $options = [], array $columns = [], array $values = null)
    {
        $t = static::$strTable;

        if (!is_array($fieldpaletteIds) || empty($fieldpaletteIds)) {
            return null;
        }

        $columns[] = "$t.id IN(".implode(',', array_map('intval', $fieldpaletteIds)).')';

        if (!BE_USER_LOGGED_IN) {
            $time = \Date::floorToMinute();
            $columns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'".($time + 60)."') AND $t.published='1'";
        }

        if (!isset($options['order'])) {
            $options['order'] = "$t.sorting";
        }

        return static::findBy($columns, $values, $options);
    }

    /**
     * Find all published fieldpalette elements by their parent ID and parent table.
     *
     * @param int    $pid         The article ID
     * @param string $parentTable The parent table name
     * @param string $parentField The parent field name
     * @param array  $options     An optional options array
     * @param array  $columns     Additional clauses columns
     * @param array  $values      Additional clauses values
     *
     * @return Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findPublishedByPidAndTableAndField(int $pid, string $parentTable, string $parentField, array $options = [], array $columns = [], array $values = [])
    {
        $t = static::$strTable;

        $columns[] = "$t.pid=? AND $t.ptable=? AND $t.pfield=?";
        $values = array_merge($values, [$pid, $parentTable, $parentField]);

        if (!BE_USER_LOGGED_IN) {
            $time = Date::floorToMinute();
            $columns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'".($time + 60)."') AND $t.published='1'";
        }

        if (!isset($options['order'])) {
            $options['order'] = "$t.sorting";
        }

        return static::findBy($columns, $values, $options);
    }

    /**
     * Find all published fieldpalette elements by their parent ID and parent table.
     *
     * @param array  $arrPids        The parent ids
     * @param string $strParentTable The parent table name
     * @param string $strParentField The parent field name
     * @param array  $arrOptions     An optional options array
     * @param array  $columns        Additional clauses columns
     * @param array  $values         Additional clauses values
     *
     * @return \Model\Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public static function findPublishedByPidsAndTableAndField($arrPids, $strParentTable, $strParentField, array $arrOptions = [], array $columns = [], array $values = [])
    {
        if (!is_array($arrPids) || empty($arrPids)) {
            return null;
        }

        $t = static::$strTable;

        $arrColumns = ["$t.pid IN(".implode(',', array_map('intval', $arrPids)).')'];

        $arrColumns[] = "$t.ptable=? AND $t.pfield=?";

        if (!BE_USER_LOGGED_IN) {
            $time = \Date::floorToMinute();
            $arrColumns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'".($time + 60)."') AND $t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "FIELD($t.pid, ".implode(',', array_map('intval', $arrPids))."), $t.sorting";
        }

        $arrColumns = array_merge($arrColumns, $columns);
        $values = array_merge([$strParentTable, $strParentField], $values);

        return static::findBy($arrColumns, $values, $arrOptions);
    }

    /**
     * Find all fieldpalette elements by their parent ID and parent table.
     *
     * @param int    $intPid         The article ID
     * @param string $strParentTable The parent table name
     * @param string $strParentField The parent field name
     * @param array  $arrOptions     An optional options array
     * @param array  $columns        Additional clauses columns
     * @param array  $values         Additional clauses values
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
        $values = array_merge([$intPid, $strParentTable, $strParentField], $values);

        return static::findBy($arrColumns, $values, $arrOptions);
    }
}
