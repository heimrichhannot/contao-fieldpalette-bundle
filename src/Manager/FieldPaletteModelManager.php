<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Manager;

use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class FieldPaletteModelManager
{
    /**
     * @var FieldPaletteModel
     */
    protected $modelInstance = null;

    /**
     * Return a new model instance.
     *
     * @return FieldPaletteModel
     *
     * @codeCoverageIgnore
     */
    public function createModel(): FieldPaletteModel
    {
        return new FieldPaletteModel();
    }

    /**
     * Returns an FieldPaletteModel instance for model calls without creating a new one (only if no already instantiated or has modified table).
     *
     * @return FieldPaletteModel
     */
    public function getInstance()
    {
        if (!$this->modelInstance) {
            $this->modelInstance = $this->createModel();
        } elseif (!$this->modelInstance->hasTable()) {
            unset($this->modelInstance);
            $this->modelInstance = $this->createModel();
        }

        return $this->modelInstance;
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
        $model = $this->createModel();
        if (!empty($table)) {
            $model->setTable($table);
        }

        return $model;
    }
}
