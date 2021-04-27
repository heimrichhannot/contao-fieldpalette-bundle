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

class InitializeSystemListener
{
    /**
     * @var ContaoFramework
     */
    protected $contaoFramework;

    /**
     * InitializeSystemListener constructor.
     */
    public function __construct(ContaoFramework $contaoFramework)
    {
        $this->contaoFramework = $contaoFramework;
    }

    public function __invoke(): void
    {
        $this->adjustBackenModules();
    }

    /**
     * Adjust back end module to allow fieldpalette table access.
     *
     * Note: Do never execute Controller::loadDataContainer() inside this function as no BackendUser is available inside initializeSystem Hook.
     */
    protected function adjustBackenModules(): void
    {
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

                $GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][] = $table;
            }
        }
    }
}
