<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Image;
use Contao\Input;
use Contao\Model;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Contao\Widget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;

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
     * @var ContaoFramework
     */
    private $framework;
    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Utils
     */
    private $utils;
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        ContaoFramework $framework,
        Utils $utils,
        FieldPaletteModelManager $modelManager,
        DcaHandler $dcaHandler,
        RequestStack $requestStack,
        LoggerInterface $logger,
        Connection $connection
    ) {
        $this->modelManager = $modelManager;
        $this->dcaHandler = $dcaHandler;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->utils = $utils;
        $this->connection = $connection;
    }

    public function setTable(string $table, int $insertID, array $set): bool
    {
        $this->framework->getAdapter(Controller::class)->loadDataContainer($table);

        if (!isset($GLOBALS['TL_DCA'][$table]['config']['fieldpalette'])) {
            return false;
        }
        $fieldPalette = $this->dcaHandler->getPaletteFromRequest();

        if (!$model = $this->modelManager->createModelByTable($table)) {
            return false;
        }
        $parentModel = $model->findByPk($insertID);
        if (!$parentModel) {
            return false;
        }

        // if are within nested field palettes set parent item tstamp
        if (isset($set['ptable']) && 'tl_fieldpalette' === $set['ptable']) {
            $parent = $parentModel->findByPk($parentModel->pid);

            if (null !== $parent) {
                $parent->tstamp = time();
                $parent->save();
            }
        }

        // set fieldpalette field
        $parentModel->pfield = $fieldPalette;
        $parentModel->save();

        return true;
    }

    /**
     * Use this method as an oncopy_callback.
     * Support recursive copying fieldpalette records by copying their parent record.
     */
    public function copyFieldPaletteRecords(int $newId): void
    {
        if (!$this->utils->container()->isBackend()) {
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

            $requestPath = $this->requestStack->getCurrentRequest()->getBasePath();
            $strUrl = substr($this->framework->getAdapter(Environment::class)->get('requestUri'), \strlen($requestPath) + 1);

            $sessionData[$key][$referer]['current'] = $strUrl;
            $sessionData[$key][$referer]['last'] = $strUrl;

            $session->set($key, $sessionData[$key]);
        }
    }

    /**
     * @return string The "toggle visibility" button.
     */
    public function toggleIcon(array $row, string $href, string $label, string $title, string $icon, string $attributes, string $table): string
    {
        $tid = $this->framework->getAdapter(Input::class)->get('tid');
        if ($tid) {
            $this->toggleVisibility($tid, ('1' === $this->framework->getAdapter(Input::class)->get('state')), (@func_get_arg(12) ?: null));
            $this->framework->getAdapter(Controller::class)->redirect(
                $this->framework->getAdapter(System::class)->getReferer()
            );
        }

        /** @var BackendUser $user */
        $user = $this->framework->createInstance(BackendUser::class);

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$user->hasAccess($table.'::published', 'alexf')) {
            return '';
        }

        $href = $this->utils->url()->addQueryStringParameterToUrl('tid='.$row['id'], $href);
        $href = $this->utils->url()->addQueryStringParameterToUrl('state='.($row['published'] ? '' : 1), $href);

        if (!$row['published']) {
            $icon = 'invisible.gif';
        }

        $image = $this->framework->getAdapter(Image::class)->getHtml(
            $icon,
            $label,
            'data-state="'.($row['published'] ? 1 : 0).'"'
        );

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            $image
        );
    }

    /**
     * Disable/enable a user group.
     *
     * @param DataContainer $dc
     */
    public function toggleVisibility(int $id, bool $visible, DataContainer $dc = null): void
    {
        // Set the ID and action
        $this->framework->getAdapter(Input::class)->setGet('id', $id);
        $this->framework->getAdapter(Input::class)->setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $id; // see #8043
        }

        /** @var BackendUser $user */
        $user = $this->framework->createInstance(BackendUser::class);

        // Check the field access
        if (!$user->hasAccess($dc->table.'::published', 'alexf')) {
            $this->logger->log(
                LogLevel::ERROR,
                'Not enough permissions to publish/unpublish fieldpalette item ID "'.$id.'"',
                ['contao' => new ContaoContext(__METHOD__, 'ERROR')]
            );
            $beRoute = $this->utils->routing()->generateBackendRoute(['act' => 'error'], false, false);
            $this->framework->getAdapter(Controller::class)->redirect($beRoute);
        }

        $objVersions = $this->framework->createInstance(Versions::class, [$dc->table, $id]);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->framework->getAdapter(System::class)->importStatic($callback[0])->{$callback[1]}($visible, ($dc ?: $this));
                } elseif (\is_callable($callback)) {
                    $visible = $callback($visible, ($dc ?: $this));
                }
            }
        }

        // Update the database
        $this->framework->createInstance(Database::class)->prepare(
            'UPDATE '.$dc->table.' SET tstamp='.time().", published='".($visible ? '1' : '')."' WHERE id=?"
        )->execute($id);

        $objVersions->create();

        $parentEntries = '';
        if ($record = $dc->activeRecord) {
            $parentEntries = '(parent records: '.$record->ptable.'.id='.$record->pid.')';
        }

        $this->logger->log(
            LogLevel::INFO,
            'A new version of record "'.$dc->table.'.id='.$id.'" has been created'.$parentEntries,
            ['contao' => new ContaoContext(__METHOD__, 'GENERAL')]
        );
    }

    /**
     * @throws Exception
     */
    public function updateParentFieldOnSubmit(DataContainer $dc): bool
    {
        $currentRecord = $this->modelManager->createModel()->findByPk($dc->id);
        if (!$currentRecord) {
            return false;
        }
        return $this->updateParentField($currentRecord);
    }

    /**
     * @throws Exception
     */
    public function updateParentFieldOnCut(DataContainer $dc): bool
    {
        $currentRecord = $this->modelManager->createModel()->findByPk($dc->id);
        if (!$currentRecord) {
            return false;
        }
        return $this->updateParentField($currentRecord);
    }

    /**
     * @throws Exception
     */
    public function updateParentFieldOnDelete(DataContainer $dc, $undoID): bool
    {
        $currentRecord = $this->modelManager->createModel()->findByPk($dc->id);
        if (!$currentRecord) {
            return false;
        }
        return $this->updateParentField($currentRecord, $currentRecord->id);
    }

    /**
     * Update the parent field with its tl_fieldpalette item ids.
     *
     * @return bool
     * @throws Exception
     */
    public function updateParentField(FieldPaletteModel $currentRecord, int $deleteIds = 0): bool
    {
        $ptable = $currentRecord->ptable;

        if (!$ptable) {
            return false;
        }

        $modelClass = $this->framework->getAdapter(Model::class)->getClassFromTable($currentRecord->ptable);

        if (!class_exists($modelClass)) {
            return false;
        }

        /** @var class-string<Model> $modelClass */
        if (null === $modelClass::findByPk($currentRecord->pid)) {
            return false;
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

        $this->connection->update(
            $currentRecord->ptable,
            [$currentRecord->pfield => serialize($varValue)],
            ['id' => $currentRecord->pid]
        );

        return true;
    }
}
