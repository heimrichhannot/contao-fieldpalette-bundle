<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\DcaHelper;

use Contao\DcaExtractor as ContaoDcaExtractor;
use Exception;

class DcaExtractor extends ContaoDcaExtractor
{
    private string $fieldPaletteTable;

    /**
     * FieldPaletteDcaExtractor is required to disable usage of cached dca file
     * if internal contao cache is active, as fields get added dynamically.
     */
    public function __construct(string $fieldPaletteTable)
    {
        $this->fieldPaletteTable = $fieldPaletteTable;
    }

    /**
     * @param $table
     *
     * @return ContaoDcaExtractor
     *@throws Exception
     *
     */
    public function getExtract(string $table): ContaoDcaExtractor
    {
        if (empty($table)) {
            throw new Exception('The table name must not be empty');
        }

        if ($table !== $this->fieldPaletteTable) {
            return new parent($table);
        }

        $this->strTable = $this->fieldPaletteTable;

        // prevent caching for fieldpalette
        $this->createExtract();

        return $this;
    }
}
