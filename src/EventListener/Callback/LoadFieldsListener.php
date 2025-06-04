<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\DataContainer;
use Contao\Input;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

class LoadFieldsListener
{
    public function __construct(
        private readonly FieldPaletteRegistry $registry,
        private readonly DcaHandler $dcaHandler,
    ) {
    }

    public function onLoadCallback(?DataContainer $dc = null): void
    {
        if (!$dc || !$dc->table) {
            return;
        }

        $table = $dc->table;
        if (!$this->registry->hasTargetFields($table)) {
            return;
        }

        $fields = $this->registry->getTargetFields($table);
        $ptable = Input::get('ptable');

        if (!$ptable) {
            return;
        }

        $fields = array_filter($fields, fn ($field): bool => $field['sourceTable'] === $ptable);

        foreach ($fields as $field) {
            $fieldData = $this->registry->getFieldData($field);
            if (!$fieldData) {
                continue;
            }
            $GLOBALS['TL_DCA'][$table]['fields'][$field['fieldName']] = $fieldData;

            $this->dcaHandler->registerFieldPalette($table, $dc);
        }
    }
}
