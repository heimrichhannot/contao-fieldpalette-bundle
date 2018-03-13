<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package fieldpalette
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\FieldPalette;


use Contao\Controller;

class FieldPaletteHooks extends \Controller
{

    protected static $arrSkipTables = ['tl_formdata'];
    protected static $intMaximumDepth = 10;
    protected static $intCurrentDepth = 0;

    public function initializeSystemHook()
    {
        FieldPalette::adjustBackendModules();
    }
}
