<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Config
 */

use HeimrichHannot\FieldpaletteBundle\EventListener\Contao\InitializeSystemListener;

$GLOBALS['TL_CONFIG']['fieldpalette_table'] = 'tl_fieldpalette';

/*
 * Back end form fields
 */
$GLOBALS['BE_FFL'][\HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard::TYPE] = \HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard::class;

/*
 * Hooks
 */
$GLOBALS['TL_HOOKS']['loadDataContainer']['fieldPalette'] = [\HeimrichHannot\FieldpaletteBundle\EventListener\LoadDataContainerListener::class, 'onLoadDataContainer'];
$GLOBALS['TL_HOOKS']['initializeSystem']['fieldPalette'] = [InitializeSystemListener::class, '__invoke'];
$GLOBALS['TL_HOOKS']['executePostActions']['fieldPalette'] = ['huh.fieldpalette.listener.hook', 'executePostActionsHook'];
$GLOBALS['TL_HOOKS']['sqlGetFromDca']['fieldPalette'] = ['huh.fieldpalette.listener.hook', 'sqlGetFromDcaHook'];

/*
 * Models
 */
$GLOBALS['TL_MODELS']['tl_fieldpalette'] = HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel::class;
