<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Model;

use Contao\Database;
use Contao\Date;
use Contao\Model;
use Contao\Model\Collection;
use Contao\System;

/**
 * @property int $id
 * @property int $pid
 * @property string $ptable
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
    const TABLE = 'tl_fieldpalette';

    protected static $strTable = self::TABLE;

    /**
     * Use this model with a custom fieldpalette table.
     *
     * @param string $table Fieldpalette
     *
     * @return FieldPaletteModel $this
     */
    public function setTable($table)
    {
        static::$strTable = $table;
        $framework = System::getContainer()->get('contao.framework');

        /** @var Database $database */
        $database = $framework->getAdapter(Database::class)->getInstance();

        if (!isset($GLOBALS['TL_DCA'][$table]['config']['fieldpalette']) || !$database->tableExists($table)) {
            static::$strTable = 'tl_fieldpalette';

            return $this;
        }

        // support custom fieldpalette entities without having its own model
        if (!isset($GLOBALS['TL_MODELS'][$table])) {
            $GLOBALS['TL_MODELS'][$table] = __CLASS__;
        }

        return $this;
    }

    /**
     * Check if instance has the correct table.
     *
     * @param string $table
     *
     * @return bool
     */
    public function hasTable(string $table = self::TABLE)
    {
        if (static::$strTable === $table) {
            return true;
        }

        return false;
    }

    /**
     * Find all published fieldpalette elements by their ids.
     *
     * @param array $fieldpaletteIds An array of fielpalette ids
     * @param array $options         An optional options array
     * @param array $columns         Additional clauses columns
     * @param array $values          Additional clauses values
     *
     * @return Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public function findPublishedByIds(array $fieldpaletteIds = [], array $options = [], array $columns = [], array $values = [])
    {
        $t = static::$strTable;

        if (!\is_array($fieldpaletteIds) || empty($fieldpaletteIds)) {
            return null;
        }

        $columns[] = "$t.id IN(".implode(',', array_map('intval', $fieldpaletteIds)).')';

        if (!isset($options['order'])) {
            $options['order'] = "$t.sorting";
        }

        return $this->findPublishedBy($columns, $values, $options);
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
    public function findPublishedByPidAndTableAndField(int $pid, string $parentTable, string $parentField, array $options = [], array $columns = [], array $values = [])
    {
        $t = static::$strTable;

        $columns[] = "$t.pid=? AND $t.ptable=? AND $t.pfield=?";
        $values = array_merge($values, [$pid, $parentTable, $parentField]);

        if (!isset($options['order'])) {
            $options['order'] = "$t.sorting";
        }

        return $this->findPublishedBy($columns, $values, $options);
    }

    /**
     * Find published fieldpalette elements by their parent IDs and parent table.
     *
     * @param array  $pids        The parent ids
     * @param string $parentTable The parent table name
     * @param string $parentField The parent field name
     * @param array  $options     An optional options array
     * @param array  $columns     Additional clauses columns
     * @param array  $values      Additional clauses values
     *
     * @return Collection|FieldPaletteModel|null A collection of models or null if there are no fieldpalette elements
     */
    public function findPublishedByPidsAndTableAndField(array $pids, string $parentTable,
        string $parentField, array $options = [], array $columns = [], array $values = [])
    {
        if (empty($pids)) {
            return null;
        }

        $t = static::$strTable;

        $columns[] = "$t.pid IN(".implode(',', array_map('intval', $pids)).')';

        $columns[] = "$t.ptable=? AND $t.pfield=?";
        $values = array_merge($values, [$parentTable, $parentField]);

        if (!isset($options['order'])) {
            $options['order'] = "FIELD($t.pid, ".implode(',', array_map('intval', $pids))."), $t.sorting";
        }

        return $this->findPublishedBy($columns, $values, $options);
    }

    /**
     * Find all fieldpalette elements by their parent ID and parent table.
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
    public function findByPidAndTableAndField(int $pid, string $parentTable, string $parentField,
        array $options = [], array $columns = [], array $values = [])
    {
        $t = static::$strTable;

        $querys = ["$t.pid=? AND $t.ptable=? AND $t.pfield=?"];

        if (!isset($options['order'])) {
            $options['order'] = "$t.sorting";
        }

        $querys = array_merge($querys, $columns);
        $values = array_merge([$pid, $parentTable, $parentField], $values);

        return $this->dynamicFindBy($querys, $values, $options);
    }

    /**
     * @param array $columns
     * @param array $values
     * @param array $options
     *
     * @return Collection|FieldPaletteModel|null
     */
    public function findPublishedBy(array $columns = [], array $values = [], array $options = [])
    {
        $t = static::$strTable;
        if (!BE_USER_LOGGED_IN) {
            $time = Date::floorToMinute();
            $columns[] = "($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'".($time + 60)."') AND $t.published='1'";
        }

        return $this->dynamicFindBy($columns, $values, $options);
    }

    /**
     * Helper method to make findBy testable.
     *
     * @param mixed $columns
     * @param mixed $values
     * @param array $options
     *
     * @return Collection|FieldPaletteModel|null
     *
     * @codeCoverageIgnore
     */
    public function dynamicFindBy($columns, $values, array $options = [])
    {
        return static::findBy($columns, $values, $options);
    }
}
