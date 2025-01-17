<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\Controller;
use Contao\DataContainer;
use Contao\Input;
use Contao\Model;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;

class BaseDcaListener
{
    private FieldPaletteModelManager $modelManager;

    public function __construct(
        FieldPaletteModelManager $modelManager
    )
    {
        $this->modelManager = $modelManager;
    }

    public function onLoadCallback(DataContainer $dc = null): void
    {
        Controller::loadLanguageFile('tl_fieldpalette');
        $this->setDateAdded($dc);
    }

    private function setDateAdded(?DataContainer $dc): void
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

    public function onConfigCreateCallback(string $table, int $insertID, array $set): void
    {
        if (!isset($GLOBALS['TL_DCA'][$table]['config']['fieldpalette'])) {
            return;
        }

        $fieldPalette = Input::get('fieldpalette');

        $model = $this->modelManager->getInstance()->findByPk($insertID);

        if (!$model) {
            return;
        }

        // evaluate the parent table
        $ptable = $model->ptable ?: $set['ptable'] ?: Input::get('ptable');

        // if are within nested fieldpalettes set parent item tstamp
        if ($ptable && 'tl_fieldpalette' === $set['ptable']) {
            $parent = $this->modelManager->getInstance()->findByPk($model->pid);

            if (null !== $parent) {
                $parent->tstamp = time();
                $parent->save();
            }
        }

        // set parent table if not already set
        if (!$model->ptable) {
            $model->ptable = $ptable;
        }
        // set fieldpalette field
        $model->pfield = $fieldPalette;
        $model->save();
    }
}
