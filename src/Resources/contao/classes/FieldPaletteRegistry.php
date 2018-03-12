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


abstract class FieldPaletteRegistry
{
	private static $arrRegistry = [];

	public static function set($strTable, $strField, $arrDca)
	{
		self::$arrRegistry[$strTable][$strField] = $arrDca;
	}

	public static function get($strTable)
	{
		if(!isset(self::$arrRegistry[$strTable]))
		{
			return null;
		}

		return self::$arrRegistry[$strTable];
	}
}