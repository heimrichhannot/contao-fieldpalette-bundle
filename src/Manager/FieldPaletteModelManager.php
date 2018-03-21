<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Manager;

use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class FieldPaletteModelManager
{
    /**
     * Return a new model instance.
     *
     * @return FieldPaletteModel
     *
     * @codeCoverageIgnore
     */
    public function createModel()
    {
        return new FieldPaletteModel();
    }

    /**
     * Returns a new model instance with given table set.
     *
     * @param string $table
     *
     * @return FieldPaletteModel
     * @codeCoverageIgnore
     */
    public function createModelByTable(string $table)
    {
        $model = new FieldPaletteModel();
        if (!empty($table)) {
            $model->setTable($table);
        }

        return $model;
    }
}
