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
$GLOBALS['BE_FFL']['fieldpalette'] = \HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard::class;

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['loadDataContainer']['fieldPalette']  = ['huh.fieldpalette.listener.hook', 'loadDataContainerHook'];
$GLOBALS['TL_HOOKS']['initializeSystem']['fieldPalette']   = ['huh.fieldpalette.listener.hook', 'initializeSystemHook'];
$GLOBALS['TL_HOOKS']['executePostActions']['fieldPalette'] = ['huh.fieldpalette.listener.hook', 'executePostActionsHook'];
$GLOBALS['TL_HOOKS']['sqlGetFromDca']['fieldPalette']      = ['huh.fieldpalette.listener.hook', 'sqlGetFromDcaHook'];

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_fieldpalette'] = 'HeimrichHannot\FieldPalette\FieldPaletteModel';


/**
 * Assets
 */

if (\HeimrichHannot\Haste\Util\Container::isBackend())
{


    $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       = 'assets/datatables-additional/datatables-i18n/datatables-i18n.min.js|static';
    $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = 'assets/datatables/datatables/media/js/jquery.dataTables.min.js|static';
    $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js|static';

    $GLOBALS['TL_CSS']['datatables-core']       = 'assets/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
    $GLOBALS['TL_CSS']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';

    $GLOBALS['TL_JAVASCRIPT']['fieldpalette-be.js'] = 'bundles/heimrichhannotcontaofieldpalette/js/fieldpalette-be.min.js|static';
    $GLOBALS['TL_CSS']['fieldpalette-wizard-be']    = 'bundles/heimrichhannotcontaofieldpalette/css/fieldpalette-wizard-be.css';
}
