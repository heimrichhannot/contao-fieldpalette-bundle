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

/**
 * Config
 */
$GLOBALS['TL_CONFIG']['fieldpalette_table'] = 'tl_fieldpalette';

/**
 * Back end form fields
 */
$GLOBALS['BE_FFL']['fieldpalette'] = 'HeimrichHannot\FieldPalette\FieldPaletteWizard';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['loadDataContainer']['fieldPalette']  = ['HeimrichHannot\FieldPalette\FieldPaletteHooks', 'loadDataContainerHook'];
$GLOBALS['TL_HOOKS']['initializeSystem']['fieldPalette']   = ['HeimrichHannot\FieldPalette\FieldPaletteHooks', 'initializeSystemHook'];
$GLOBALS['TL_HOOKS']['executePostActions']['fieldPalette'] = ['HeimrichHannot\FieldPalette\FieldPaletteHooks', 'executePostActionsHook'];
$GLOBALS['TL_HOOKS']['sqlGetFromDca']['fieldPalette']      = ['HeimrichHannot\FieldPalette\FieldPaletteHooks', 'sqlGetFromDcaHook'];

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_fieldpalette'] = 'HeimrichHannot\FieldPalette\FieldPaletteModel';


/**
 * Assets
 */
if (\HeimrichHannot\Haste\Util\Container::isBackend()) {
    $strBasePath = version_compare(VERSION, '4.0', '<') ? 'assets/components' : 'assets';

    $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       =
        $strBasePath . '/datatables-additional/datatables-i18n/datatables-i18n.min.js|static';
    $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = $strBasePath . '/datatables/datatables/media/js/jquery.dataTables.min.js|static';
    $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] =
        $strBasePath . '/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js|static';

    $GLOBALS['TL_CSS']['datatables-core']       =
        $strBasePath . '/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
    $GLOBALS['TL_CSS']['datatables-rowReorder'] =
        $strBasePath . '/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';

    $GLOBALS['TL_JAVASCRIPT']['fieldpalette-be.js'] = 'system/modules/fieldpalette/assets/js/fieldpalette-be.min.js|static';
    $GLOBALS['TL_CSS']['fieldpalette-wizard-be']    = 'system/modules/fieldpalette/assets/css/fieldpalette-wizard-be.css';
}
