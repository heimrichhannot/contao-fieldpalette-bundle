<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Input;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;

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
    /**
     * @var ContainerUtil
     */
    private $containerUtil;

    public function __construct(ContaoFrameworkInterface $framework, FieldPaletteModelManager $modelManager, DcaHandler $dcaHandler, ContainerUtil $containerUtil)
    {
        $this->modelManager = $modelManager;
        $this->dcaHandler = $dcaHandler;
        $this->framework = $framework;
        $this->containerUtil = $containerUtil;
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

        $model = $this->modelManager->createModelByTable($table);
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

    /**
     * Use this method as an oncopy_callback.
     * Support recursive copying fieldpalette records by copying their parent record.
     *
     * @param int $newId
     */
    public function copyFieldPaletteRecords(int $newId)
    {
        if (!$this->containerUtil->isBackend()) {
            return;
        }

        $id = $this->framework->getAdapter(Input::class)->get('id') ?: CURRENT_ID;
        $do = $this->framework->getAdapter(Input::class)->get('do');

        $table = $do ? 'tl_'.$do : null;

        if (!$id || !$table) {
            return;
        }
        $this->framework->getAdapter(Controller::class)->loadDataContainer($table);
        $dcaFields = $GLOBALS['TL_DCA'][$table]['fields'];

        $this->dcaHandler->recursivelyCopyFieldPaletteRecords($id, $newId, $table, $dcaFields);
    }
}
