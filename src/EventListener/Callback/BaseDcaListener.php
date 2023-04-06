<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\DataContainer;
use Contao\Model;

class BaseDcaListener
{
    public function __construct()
    {
    }

    public function onLoadCallback(DataContainer $dc = null): void
    {
        $this->setDataAdded($dc);
    }

    protected function setDataAdded(?DataContainer $dc): void
    {
        if (!$dc || !$dc->id) {
            return;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = Model::getClassFromTable($dc->table);
        if (class_exists($modelClass) && ($model = $modelClass::findByPk($dc->id)) && 0 === $model->dateAdded) {
            $model->dateAdded = time();
            $model->save();
        }
    }
}
