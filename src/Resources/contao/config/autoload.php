<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(
    [
	'HeimrichHannot',]
);


/**
 * Register the classes
 */
ClassLoader::addClasses(
    [
	// Models
	'HeimrichHannot\FieldPalette\FieldPaletteModel'        => 'system/modules/fieldpalette/models/FieldPaletteModel.php',

	// Widgets
	'HeimrichHannot\FieldPalette\FieldPaletteWizard'       => 'system/modules/fieldpalette/widgets/FieldPaletteWizard.php',

	// Classes
	'HeimrichHannot\FieldPalette\FieldPalette'             => 'system/modules/fieldpalette/classes/FieldPalette.php',
	'HeimrichHannot\FieldPalette\FieldPaletteHooks'        => 'system/modules/fieldpalette/classes/FieldPaletteHooks.php',
	'HeimrichHannot\FieldPalette\FieldPaletteDcaExtractor' => 'system/modules/fieldpalette/classes/FieldPaletteDcaExtractor.php',
	'HeimrichHannot\FieldPalette\FieldPaletteRegistry'     => 'system/modules/fieldpalette/classes/FieldPaletteRegistry.php',
	'HeimrichHannot\FieldPalette\FieldPaletteButton'       => 'system/modules/fieldpalette/classes/FieldPaletteButton.php',]
);


/**
 * Register the templates
 */
TemplateLoader::addFiles(
    [
	'fieldpalette_item_default'     => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_item_table'       => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_button_default'   => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_listview_default' => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_wizard_table'     => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_wizard_default'   => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_listview_table'   => 'system/modules/fieldpalette/templates/fieldpalette',
	'fieldpalette_buttons_default'  => 'system/modules/fieldpalette/templates/fieldpalette',
	'be_fieldpalette'               => 'system/modules/fieldpalette/templates/backend',]
);
