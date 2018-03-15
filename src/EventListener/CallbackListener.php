<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use Contao\DataContainer;
use HeimrichHannot\FieldPalette\FieldPalette;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class CallbackListener
{
    /**
     * Returns a new FieldPaletteModel instance.
     *
     * @param string $table
     *
     * @return FieldPaletteModel
     */
    public function getModelInstance(string $table = '')
    {
        $model = new FieldPaletteModel();
        if (!empty($table)) {
            $model->setTable($table);
        }

        return $model;
    }

    /**
     * @param string $table
     * @param $insertID
     * @param $set
     * @param DataContainer $dc
     */
    public function setTable(string $table, $insertID, $set, DataContainer $dc)
    {
        Controller::loadDataContainer($table);

        if (!$GLOBALS['TL_DCA'][$table]['config']['fieldpalette']) {
            return;
        }

        $strFieldPalette = FieldPalette::getPaletteFromRequest();

        $model = $this->getModelInstance($table)->findByPk($insertID);

        // if are within nested fieldpalettes set parent item tstamp
        if ('tl_fieldpalette' === $set['ptable']) {
            $parent = FieldPaletteModel::findByPk($model->pid);

            if (null !== $parent) {
                $parent->tstamp = time();
                $parent->save();
            }
        }

        // set fieldpalette field
        $model->pfield = $strFieldPalette;
        $model->save();
    }
}
