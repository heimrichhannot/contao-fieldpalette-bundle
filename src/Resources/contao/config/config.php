<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Config
 */

use HeimrichHannot\FieldpaletteBundle\EventListener\Contao\ExecutePostActionsListener;
use HeimrichHannot\FieldpaletteBundle\EventListener\Contao\InitializeSystemListener;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard;

$GLOBALS['TL_CONFIG']['fieldpalette_table'] = 'tl_fieldpalette';

/*
 * Back end form fields
 */
$GLOBALS['BE_FFL'][FieldPaletteWizard::TYPE] = FieldPaletteWizard::class;

/*
 * Hooks
 */
$GLOBALS['TL_HOOKS']['initializeSystem']['fieldPalette'] = [InitializeSystemListener::class, '__invoke'];
$GLOBALS['TL_HOOKS']['executePostActions']['fieldPalette'] = [ExecutePostActionsListener::class, '__invoke'];

/*
 * Models
 */
$GLOBALS['TL_MODELS']['tl_fieldpalette'] = FieldPaletteModel::class;
