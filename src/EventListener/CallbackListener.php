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
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\DcMultilingualBundle\Model\Multilingual;
use Terminal42\DcMultilingualBundle\Model\MultilingualTrait;

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
     * @var UrlUtil
     */
    private $urlUtil;
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
        UrlUtil $urlUtil,
        LoggerInterface $logger,
        Connection $connection
    ) {
        $this->modelManager = $modelManager;
        $this->dcaHandler = $dcaHandler;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->urlUtil = $urlUtil;
        $this->logger = $logger;
        $this->utils = $utils;
        $this->connection = $connection;
    }

    /**
     * Use this method as an oncopy_callback.
     * Support recursive copying fieldpalette records by copying their parent record.
     */
    public function copyFieldPaletteRecords(int $newId)
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

    public function setReferrerOnSaveAndClose(DataContainer $dc)
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

            $strUrl = substr($this->framework->getAdapter(Environment::class)->get('requestUri'), \strlen(TL_PATH) + 1);

            $sessionData[$key][$referer]['current'] = $strUrl;
            $sessionData[$key][$referer]['last'] = $strUrl;

            $session->set($key, $sessionData[$key]);
        }
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
     *
     * @return bool
     */
    public function updateParentField(FieldPaletteModel $currentRecord, int $deleteIds = 0)
    {
        $ptable = $currentRecord->ptable;
        if ($ptable) {
            $modelClass = $this->framework->getAdapter(Model::class)->getClassFromTable($currentRecord->ptable);
        }

        if (!class_exists($modelClass)) {
            return false;
        }

        /** @var Model $modelClass */
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

        $this->connection->update($currentRecord->ptable, [$currentRecord->pfield => serialize($varValue)], ['id' => $currentRecord->pid]);
    }
}
