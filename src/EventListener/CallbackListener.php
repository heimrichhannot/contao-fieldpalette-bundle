<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Input;
use Contao\Model;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\EventListener\Callback\BaseDcaListener;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\HttpFoundation\RequestStack;

class CallbackListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Utils $utils,
        private readonly FieldPaletteModelManager $modelManager,
        private readonly DcaHandler $dcaHandler,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly BaseDcaListener $baseDcaListener,
    ) {
    }

    /**
     * Use this method as an oncopy_callback.
     * Support recursive copying fieldpalette records by copying their parent record.
     */
    public function copyFieldPaletteRecords(int $newId, DataContainer $dc): void
    {
        if (!$this->utils->container()->isBackend()) {
            return;
        }

        $id = $this->framework->getAdapter(Input::class)->get('id') ?: $dc->currentPid;
        $do = $this->framework->getAdapter(Input::class)->get('do');

        $table = $do ? 'tl_' . $do : null;

        if (!$id || !$table) {
            return;
        }
        $this->framework->getAdapter(Controller::class)->loadDataContainer($table);
        $dcaFields = $GLOBALS['TL_DCA'][$table]['fields'];

        $this->dcaHandler->recursivelyCopyFieldPaletteRecords($id, $newId, $table, $dcaFields);
    }

    /**
     * @deprecated No support for saveNclose anymore
     */
    public function setReferrerOnSaveAndClose(DataContainer $dc): void
    {
        if (!isset($_POST['saveNclose'])) {
            return;
        }
        $key = null;
        if ($this->utils->container()->isBackend()) {
            $key = $this->framework->getAdapter(Input::class)->get('popup') ? 'popupReferer' : 'referer';
        }

        if ($key) {
            $session = $this->requestStack->getCurrentRequest()->getSession();
            $sessionData = $session->all();
            $referer = $this->requestStack->getCurrentRequest()->get('_contao_referer_id');

            if (!\is_array($sessionData[$key]) || !\is_array($sessionData[$key][$referer])) {
                $sessionData[$key][$referer]['last'] = '';
            }

            while (\count($sessionData[$key]) >= 25) {
                array_shift($sessionData[$key]);
            }

            $ref = $this->framework->getAdapter(Input::class)->get('ref');

            if ('' !== $ref && isset($sessionData[$key][$ref])) {
                if (!isset($sessionData[$key][$referer])) {
                    $sessionData[$key][$referer] = [];
                }

                $sessionData[$key][$referer] = array_merge($sessionData[$key][$referer], $sessionData[$key][$ref]);
                $sessionData[$key][$referer]['last'] = $sessionData[$key][$ref]['current'];
            } elseif (\count($sessionData[$key]) > 1) {
                $sessionData[$key][$referer] = end($sessionData[$key]);
            }

            $strUrl = $this->requestStack->getCurrentRequest()->getBaseUrl();

            $sessionData[$key][$referer]['current'] = $strUrl;
            $sessionData[$key][$referer]['last'] = $strUrl;

            $session->set($key, $sessionData[$key]);
        }
    }

    /**
     * Return the "toggle visibility" button.
     *
     * @deprecated Use BaseDcaListener::onListOperationsToggleButtonCallback instead
     */
    public function toggleIcon(
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
        DataContainer $dc,
    ): string {
        return $this->baseDcaListener->onListOperationsToggleButtonCallback(
            $row,
            $href,
            $label,
            $title,
            $icon,
            $attributes,
            $table,
            $rootRecordIds,
            $childRecordIds,
            $circularReference,
            $previous,
            $next,
            $dc
        );
    }

    /**
     * Disable/enable a user group.
     *
     * @deprecated
     */
    public function toggleVisibility(int $id, bool $visible, ?DataContainer $dc = null): void
    {
        $this->baseDcaListener->toggleVisibility($id, $visible, $dc);
    }

    public function updateParentFieldOnSubmit(DataContainer $dc)
    {
        $currentRecord = $this->modelManager->createModel()->findByPk($dc->id);
        if (!$currentRecord) {
            return false;
        }
        $this->updateParentField($currentRecord);
    }

    public function updateParentFieldOnCut(DataContainer $dc)
    {
        $currentRecord = $this->modelManager->createModel()->findByPk($dc->id);
        if (!$currentRecord) {
            return false;
        }
        $this->updateParentField($currentRecord);
    }

    public function updateParentFieldOnDelete(DataContainer $dc, $undoID)
    {
        $currentRecord = $this->modelManager->createModel()->findByPk($dc->id);
        if (!$currentRecord) {
            return false;
        }
        $this->updateParentField($currentRecord, $currentRecord->id);
    }

    /**
     * Update the parent field with its tl_fieldpalette item ids.
     */
    public function updateParentField(FieldPaletteModel $currentRecord, int $deleteIds = 0): void
    {
        $ptable = $currentRecord->ptable;
        if (!$ptable) {
            return;
        }

        $modelClass = Model::getClassFromTable($ptable);
        if (!class_exists($modelClass)) {
            return;
        }

        if (null === $modelClass::findByPk($currentRecord->pid)) {
            return;
        }

        $objItems = $this->modelManager->createModel()->findByPidAndTableAndField(
            $currentRecord->pid,
            $currentRecord->ptable,
            $currentRecord->pfield
        );

        $varValue = [];

        if (null !== $objItems) {
            $varValue = $objItems->fetchEach('id');

            // ondelete_callback support
            if ($deleteIds > 0 && false !== ($key = array_search($deleteIds, $varValue, true))) {
                unset($varValue[$key]);
            }
        }

        if (empty($varValue)) {
            $this->framework->getAdapter(Controller::class)->loadDataContainer($currentRecord->ptable);

            $arrData = $GLOBALS['TL_DCA'][$currentRecord->ptable]['fields'][$currentRecord->pfield];

            if (isset($arrData['sql'])) {
                $varValue = $this->framework->getAdapter(Widget::class)->getEmptyValueByFieldType($arrData['sql']);
            }
        }

        $this->connection->update($currentRecord->ptable, [
            $currentRecord->pfield => serialize($varValue),
        ], [
            'id' => $currentRecord->pid,
        ]);
    }
}
