<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;

class CallbackListener
{
    /**
     * @var FieldPaletteModelManager
     */
    private $modelManager;
    /**
     * @var DcaHandler
     */
    private $dcaHandler;
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    public function __construct(ContaoFrameworkInterface $framework, FieldPaletteModelManager $modelManager, DcaHandler $dcaHandler)
    {
        $this->modelManager = $modelManager;
        $this->dcaHandler = $dcaHandler;
        $this->framework = $framework;
    }

    /**
     * @param string $table
     * @param $insertID
     * @param $set
     *
     * @return bool
     */
    public function setTable(string $table, int $insertID, array $set)
    {
        $this->framework->getAdapter(Controller::class)->loadDataContainer($table);

        if (!isset($GLOBALS['TL_DCA'][$table]['config']['fieldpalette'])) {
            return false;
        }
        $fieldPalette = $this->dcaHandler->getPaletteFromRequest();

        $model = $this->modelManager->getModelByTable($table);
        $model = $model->findByPk($insertID);

        if (!$model) {
            return false;
        }

        // if are within nested fieldpalettes set parent item tstamp
        if (isset($set['ptable']) && 'tl_fieldpalette' === $set['ptable']) {
            $parent = $model->findByPk($model->pid);

            if (null !== $parent) {
                $parent->tstamp = time();
                $parent->save();
            }
        }

        // set fieldpalette field
        $model->pfield = $fieldPalette;
        $model->save();

        return true;
    }
}
