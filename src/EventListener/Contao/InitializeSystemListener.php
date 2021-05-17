<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;

class InitializeSystemListener
{
    /**
     * @var ContaoFramework
     */
    protected $contaoFramework;
    /**
     * @var ContainerUtil
     */
    protected $containerUtil;

    /**
     * InitializeSystemListener constructor.
     */
    public function __construct(ContaoFramework $contaoFramework, ContainerUtil $containerUtil)
    {
        $this->contaoFramework = $contaoFramework;
        $this->containerUtil = $containerUtil;
    }

    public function __invoke(): void
    {
        $this->adjustBackenModules();
        $this->addBackendAssets();
    }

    public function addBackendAssets(): void
    {
        if (!$this->containerUtil->isBackend()) {
            return;
        }

        $jquery = 'assets/jquery/js/jquery.min.js';
        if (isset($GLOBALS['TL_JAVASCRIPT']['jquery'])) {
            $jquery = $GLOBALS['TL_JAVASCRIPT']['jquery'];
            unset($GLOBALS['TL_JAVASCRIPT']['jquery']);
        }
        $GLOBALS['TL_JAVASCRIPT'] = array_merge(
            ['jquery' => $jquery],
            \is_array($GLOBALS['TL_JAVASCRIPT']) ? $GLOBALS['TL_JAVASCRIPT'] : []
        );
        $GLOBALS['TL_JAVASCRIPT']['datatables-i18n'] = 'assets/datatables-additional/datatables-i18n/datatables-i18n.min.js';
        $GLOBALS['TL_JAVASCRIPT']['datatables-core'] = 'assets/datatables/datatables/media/js/jquery.dataTables.min.js';
        $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js';

        $GLOBALS['TL_CSS']['datatables-core'] = 'assets/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
        $GLOBALS['TL_CSS']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';

        $GLOBALS['TL_JAVASCRIPT']['fieldpalette-be.js'] = 'bundles/heimrichhannotcontaofieldpalette/js/fieldpalette-be.min.js';
        $GLOBALS['TL_CSS']['fieldpalette-wizard-be'] = 'bundles/heimrichhannotcontaofieldpalette/css/fieldpalette-wizard-be.css';
    }

    /**
     * Adjust back end module to allow fieldpalette table access.
     *
     * Note: Do never execute Controller::loadDataContainer() inside this function as no BackendUser is available inside initializeSystem Hook.
     */
    protected function adjustBackenModules(): void
    {
        if (Input::get('picker')) {
            return;
        }
        $table = $this->contaoFramework->getAdapter(Input::class)->get(DcaHandler::TableRequestKey);

        if (empty($table)) {
            return;
        }

        foreach ($GLOBALS['BE_MOD'] as $strGroup => $arrGroup) {
            if (!\is_array($arrGroup)) {
                continue;
            }

            foreach ($arrGroup as $strModule => $arrModule) {
                if (!\is_array($arrModule) && !\is_array($arrModule['tables'])) {
                    continue;
                }

                if (isset($GLOBALS['BE_MOD'][$strGroup][$strModule]['tables']) && !\is_array($GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'])) {
                    trigger_error(
                        'Invalid backend module configuration. $GLOBALS[\'BE_MOD\'][\''.$strGroup.'\'][\''.$strModule.'\'][\'tables\'] must be an array.',
                        E_USER_WARNING
                    );
                    if (\is_string($GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'])) {
                        $GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'] = [$GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'], $table];
                    }
                    continue;
                }

                $GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][] = $table;
            }
        }
    }
}
