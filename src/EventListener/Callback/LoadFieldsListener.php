<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\DataContainer;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

class LoadFieldsListener
{
    private FieldPaletteRegistry $registry;
    private DcaHandler           $dcaHandler;

    public function __construct(FieldPaletteRegistry $registry, DcaHandler $dcaHandler)
    {
        $this->registry = $registry;
        $this->dcaHandler = $dcaHandler;
    }

    public function onLoadCallback(DataContainer $dc = null): void
    {
        $table = $dc->table;
        if (!$dc || !$table || !$this->registry->hasTargetFields($table)) {
            return;
        }

        $fields = $this->registry->getTargetFields($table);
        foreach ($fields as $field) {
            $fieldData = $this->registry->getFieldData($field);
            if (!$fieldData) {
                continue;
            }
            $GLOBALS['TL_DCA'][$table]['fields'][$field['fieldName']] = $fieldData;

            $this->dcaHandler->registerFieldPalette($table);
        }
    }
}
