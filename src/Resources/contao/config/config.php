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
$GLOBALS['BE_FFL'][\HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard::TYPE] = \HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard::class;

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['loadDataContainer']['fieldPalette']  = [\HeimrichHannot\FieldpaletteBundle\EventListener\LoadDataContainerListener::class, 'onLoadDataContainer'];
$GLOBALS['TL_HOOKS']['initializeSystem']['fieldPalette']   = ['huh.fieldpalette.listener.hook', 'initializeSystemHook'];
$GLOBALS['TL_HOOKS']['executePostActions']['fieldPalette'] = ['huh.fieldpalette.listener.hook', 'executePostActionsHook'];
$GLOBALS['TL_HOOKS']['sqlGetFromDca']['fieldPalette']      = ['huh.fieldpalette.listener.hook', 'sqlGetFromDcaHook'];

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_fieldpalette'] = HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel::class;


/**
 * Assets
 */

if (\Contao\System::getContainer()->get('huh.utils.container')->isBackend())
{
    if (isset($GLOBALS['TL_JAVASCRIPT']['jquery'])) {
        unset($GLOBALS['TL_JAVASCRIPT']['jquery']);
    }
    $GLOBALS['TL_JAVASCRIPT'] = array_merge(
        ['jquery' => 'assets/jquery/js/jquery.min.js'],
        is_array($GLOBALS['TL_JAVASCRIPT']) ? $GLOBALS['TL_JAVASCRIPT'] : []
    );
    $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       = 'assets/datatables-additional/datatables-i18n/datatables-i18n.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = 'assets/datatables/datatables/media/js/jquery.dataTables.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js';

    $GLOBALS['TL_CSS']['datatables-core']       = 'assets/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
    $GLOBALS['TL_CSS']['datatables-rowReorder'] = 'assets/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';

    $GLOBALS['TL_JAVASCRIPT']['fieldpalette-be.js'] = 'bundles/heimrichhannotcontaofieldpalette/js/fieldpalette-be.min.js';
    $GLOBALS['TL_CSS']['fieldpalette-wizard-be']    = 'bundles/heimrichhannotcontaofieldpalette/css/fieldpalette-wizard-be.css';
}
