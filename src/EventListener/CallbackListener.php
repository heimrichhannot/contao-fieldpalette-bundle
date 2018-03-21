<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Image;
use Contao\Input;
use Contao\Model;
use Contao\System;
use Contao\Versions;
use Contao\Widget;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Request\RoutingUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
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
     * @var ContaoFrameworkInterface
     */
    private $framework;
    /**
     * @var ContainerUtil
     */
    private $containerUtil;
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
     * @var RoutingUtil
     */
    private $routingUtil;

    public function __construct(
        ContaoFrameworkInterface $framework,
        FieldPaletteModelManager $modelManager,
        DcaHandler $dcaHandler,
        RequestStack $requestStack,
        ContainerUtil $containerUtil,
        UrlUtil $urlUtil,
        RoutingUtil $routingUtil,
        LoggerInterface $logger
    ) {
        $this->modelManager = $modelManager;
        $this->dcaHandler = $dcaHandler;
        $this->framework = $framework;
        $this->containerUtil = $containerUtil;
        $this->requestStack = $requestStack;
        $this->urlUtil = $urlUtil;
        $this->logger = $logger;
        $this->routingUtil = $routingUtil;
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

    public function setReferrerOnSaveAndClose(DataContainer $dc)
    {
        if (!isset($_POST['saveNclose'])) {
            return;
        }
        $key = null;
        if ($this->containerUtil->isBackend()) {
            $key = $this->framework->getAdapter(Input::class)->get('popup') ? 'popupReferer' : 'referer';
        }

        if ($key) {
            $session = $this->requestStack->getCurrentRequest()->getSession();
            $sessionData = $session->all();
            $referer = $this->requestStack->getCurrentRequest()->get('_contao_referer_id');

            if (!is_array($sessionData[$key]) || !is_array($sessionData[$key][$referer])) {
                $sessionData[$key][$referer]['last'] = '';
            }

            while (count($sessionData[$key]) >= 25) {
                array_shift($sessionData[$key]);
            }

            $ref = $this->framework->getAdapter(Input::class)->get('ref');

            if ('' !== $ref && isset($sessionData[$key][$ref])) {
                if (!isset($sessionData[$key][$referer])) {
                    $sessionData[$key][$referer] = [];
                }

                $sessionData[$key][$referer] = array_merge($sessionData[$key][$referer], $sessionData[$key][$ref]);
                $sessionData[$key][$referer]['last'] = $sessionData[$key][$ref]['current'];
            } elseif (count($sessionData[$key]) > 1) {
                $sessionData[$key][$referer] = end($sessionData[$key]);
            }

            $strUrl = substr($this->framework->getAdapter(Environment::class)->get('requestUri'), strlen(TL_PATH) + 1);

            $sessionData[$key][$referer]['current'] = $strUrl;
            $sessionData[$key][$referer]['last'] = $strUrl;

            $session->set($key, $sessionData[$key]);
        }
    }

    /**
     * Return the "toggle visibility" button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     * @param string $table
     *
     * @return string
     */
    public function toggleIcon(array $row, string $href, string $label, string $title, string $icon, string $attributes, string $table)
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

        $href = $this->urlUtil->addQueryString('tid='.$row['id'], $href);
        $href = $this->urlUtil->addQueryString('state='.($row['published'] ? '' : 1), $href);

        if (!$row['published']) {
            $icon = 'invisible.gif';
        }

        $image = $this->framework->getAdapter(Image::class)->getHtml(
            $icon,
            $label,
            'data-state="'.($row['published'] ? 1 : 0).'"'
        );

        return '<a href="'.$href.'" title="'.specialchars($title).'"'.$attributes.'>'.$image.'</a> ';
    }

    /**
     * Disable/enable a user group.
     *
     * @param int           $id
     * @param bool          $visible
     * @param DataContainer $dc
     */
    public function toggleVisibility(int $id, bool $visible, DataContainer $dc = null)
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
                ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]
            );
            $this->framework->getAdapter(Controller::class)
                ->redirect($this->routingUtil->generateBackendRoute(['act' => 'error'], false, false));
        }

        $objVersions = $this->framework->createInstance(Versions::class, [$dc->table, $id]);
        $objVersions->initialize();

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'] as $callback) {
                if (is_array($callback)) {
                    $this->framework->getAdapter(System::class)->importStatic($callback[0])->{$callback[1]}($visible, ($dc ?: $this));
                } elseif (is_callable($callback)) {
                    $visible = $callback($visible, ($dc ?: $this));
                }
            }
        }

        // Update the database
        $this->framework->createInstance(Database::class)->prepare(
            'UPDATE '.$dc->table.' SET tstamp='.time().", published='".($visible ? '1' : '')."' WHERE id=?"
        )->execute($id);

        $objVersions->create();

        $parentEntries = $this->framework->getAdapter(Controller::class)->getParentEntries(
            $dc->table,
            $id
        );
        $this->logger->log(
            LogLevel::INFO,
            'A new version of record "'.$dc->table.'.id='.$id.'" has been created'.$parentEntries,
            ['contao' => new ContaoContext(__METHOD__, TL_GENERAL)]
        );
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
     * @param FieldPaletteModel $currentRecord
     * @param int               $deleteIds
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
        $parentModel = $modelClass::findByPk($currentRecord->pid);

        if (null === $parentModel) {
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

        $parentModel->{$currentRecord->pfield} = $varValue;
        $parentModel->save();
    }
}
