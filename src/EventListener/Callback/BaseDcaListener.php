<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\Model;
use Contao\StringUtil;
use Contao\Versions;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\Security\Core\Security;

class BaseDcaListener
{
    private FieldPaletteModelManager $modelManager;
    private Security $security;
    private Utils $utils;

    public function __construct(
        FieldPaletteModelManager $modelManager,
        Security $security,
        Utils $utils
    )
    {
        $this->modelManager = $modelManager;
        $this->security = $security;
        $this->utils = $utils;
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

    public function onListOperationsToggleButtonCallback(array $row, string $href, string $label, string $title, string $icon, string $attributes, string $table): string
    {
        $tid = Input::get('tid');

        if ($tid) {
            $this->toggleVisibility($tid, ('1' === Input::get('state')), (@func_get_arg(12) ?: null));
            Controller::redirect(Controller::getReferer());
        }

        if (!$this->security->isGranted('contao_user.alexf', $table.'::published')) {
            return '';
        }

        $this->utils->url()->addQueryStringParameterToUrl('tid='.$row['id'], $href);
        $this->utils->url()->addQueryStringParameterToUrl('state='.($row['published'] ? '' : 1), $href);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        $image = Image::getHtml($icon, $label, 'data-state="'.($row['published'] ? 1 : 0).'"');

        return '<a href="'.$href.'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.$image.'</a> ';
    }

    private function toggleVisibility(int $id, bool $visible, DataContainer $dc = null): void
    {
        Input::setGet('id', $id);
        Input::setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $id; // see #8043
        }

        if (!$this->security->isGranted('contao_user.alexf', $dc->table.'::published')) {
            $this->utils->container()->log(
                'Not enough permissions to publish/unpublish fieldpalette item ID "'.$id.'"',
                __METHOD__,
                ContaoContext::ERROR
            );
            Controller::redirect($this->utils->routing()->generateBackendRoute(['act' => 'error'], false, false));
        }

        $objVersions = new Versions($dc->table, $id);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'] as $callback) {
                $visible = $this->utils->dca()->executeCallback($callback, $visible, ($dc ?: $this));
            }
        }

        Database::getInstance()
            ->prepare('UPDATE '.$dc->table.' SET tstamp='.time().", published='".($visible ? '1' : '')."' WHERE id=?")
            ->execute($id);

        $objVersions->create();

        $parentEntries = '';
        if ($record = $dc->activeRecord) {
            $parentEntries = '(parent records: '.$record->ptable.'.id='.$record->pid.')';
        }

        $this->utils->container()->log(
            'A new version of record "'.$dc->table.'.id='.$id.'" has been created'.$parentEntries,
            __METHOD__,
            ContaoContext::GENERAL
        );
    }
}
