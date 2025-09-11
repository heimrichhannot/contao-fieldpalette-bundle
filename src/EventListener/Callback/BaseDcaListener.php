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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class BaseDcaListener
{
    public function __construct(
        private readonly FieldPaletteModelManager $modelManager,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly Utils $utils,
    ) {
    }

    public function onLoadCallback(?DataContainer $dc = null): void
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
        /* @phpstan-ignore property.notFound */
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

    public function onListOperationsToggleButtonCallback(
        array $row,
        ?string $href,
        string $label,
        string $title,
        ?string $icon,
        string $attributes,
        string $table,
        array $rootRecordIds,
        ?array $childRecordIds,
        bool $circularReference,
        ?string $previous,
        ?string $next,
        DataContainer $dc): string
    {
        $tid = Input::get('tid');

        if ($tid) {
            $this->toggleVisibility($tid, '1' === Input::get('state'), @func_get_arg(12) ?: null);
            Controller::redirect(Controller::getReferer());
        }

        if (!$this->auth->isGranted('contao_user.alexf', $table . '::published')) {
            return Image::getHtml(str_replace('.svg', '--disabled.svg', $icon)) . ' ';
        }

        $href = $this->utils->url()->addQueryStringParameterToUrl('tid=' . $row['id'], (string) $href);
        $href = $this->utils->url()->addQueryStringParameterToUrl('state=' . ($row['published'] ? '' : 1), (string) $href);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        $imgAttributes = $this->utils->html()->generateAttributeString([
            'data-icon' => Image::getPath('visible.svg'),
            'data-icon-disabled' => Image::getPath('invisible.svg'),
            'data-state' => (int) $row['published'],
            'data-alt' => StringUtil::specialchars($title),
            'data-alt-disabled' => StringUtil::specialchars($title),
        ]);

        $image = Image::getHtml($icon, $title, $imgAttributes);

        $attributes .= ' ' . $this->utils->html()->generateAttributeString([
            'data-action' => 'contao--scroll-offset#store',
            'onclick' => 'return AjaxRequest.toggleField(this,true)',
        ]);

        return sprintf('<a href="%s" title="%s" %s>%s</a> ',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            $image
        );
    }

    /**
     * @internal Only exposed for internal backwards compatibility. Will be private in next major version.
     */
    public function toggleVisibility(int $id, bool $visible, ?DataContainer $dc = null): void
    {
        Input::setGet('id', $id);
        Input::setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $id; // see #8043
        }

        if (!$this->auth->isGranted('contao_user.alexf', $dc->table . '::published')) {
            $this->utils->container()->log(
                'Not enough permissions to publish/unpublish fieldpalette item ID "' . $id . '"',
                __METHOD__,
                ContaoContext::ERROR
            );
            Controller::redirect($this->utils->routing()->generateBackendRoute([
                'act' => 'error',
            ], false, false));
        }

        $objVersions = new Versions($dc->table, $id);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'] ?? null)) {
            foreach ($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'] as $callback) {
                $visible = $this->utils->dca()->executeCallback($callback, $visible, $dc ?: $this);
            }
        }

        Database::getInstance()
            ->prepare('UPDATE ' . $dc->table . ' SET tstamp=' . time() . ", published='" . ($visible ? '1' : '') . "' WHERE id=?")
            ->execute($id);

        $objVersions->create();

        $parentEntries = '';
        /* @phpstan-ignore property.notFound */
        if ($record = $dc->activeRecord) {
            $parentEntries = '(parent records: ' . $record->ptable . '.id=' . $record->pid . ')';
        }

        $this->utils->container()->log(
            'A new version of record "' . $dc->table . '.id=' . $id . '" has been created' . $parentEntries,
            __METHOD__,
            ContaoContext::GENERAL
        );
    }

    public function onEditButtonsCallback(array $buttons, DataContainer $dc): array
    {
        return array_intersect_key($buttons, ['save' => '']);
    }
}
