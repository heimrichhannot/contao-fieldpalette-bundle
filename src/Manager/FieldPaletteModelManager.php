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
     * @param string $table
     *
     * @return FieldPaletteModel
     * @codeCoverageIgnore
     */
    public function getModelByTable(string $table)
    {
        $model = new FieldPaletteModel();
        if (!empty($table)) {
            $model->setTable($table);
        }

        return $model;
    }
}
