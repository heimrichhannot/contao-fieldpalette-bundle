<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\FieldPalette;

class FieldPaletteDcaExtractor extends \DcaExtractor
{

    /**
     * FieldPaletteDcaExtractor is required to disable usage of cached dca file
     * if internal contao cache is active, as fields get added dynamically
     *
     * @param string $strTable
     *
     * @throws \Exception
     */
    public function __construct($strTable)
    {
        if ($strTable == '')
        {
            throw new \Exception('The table name must not be empty');
        }

        if($strTable !== \Config::get('fieldpalette_table'))
        {
            parent::__construct($strTable);
        }

        $this->strTable = \Config::get('fieldpalette_table');

        // prevent caching for fieldpalette
        $this->createExtract();
    }
}