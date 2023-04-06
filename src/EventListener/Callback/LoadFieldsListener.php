<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\DataContainer;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

class LoadFieldsListener
{
    private FieldPaletteRegistry $registry;

    public function __construct(FieldPaletteRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function onLoadCallback(DataContainer $dc = null): void
    {
        if (!$dc || !$dc->table || !$this->registry->hasTargetFields($dc->table)) {
            return;
        }

        $fields = $this->registry->getTargetFields($dc->table);
        foreach ($fields as $field) {
            $GLOBALS['TL_DCA'][$dc->table]['fields'][$field['fieldName']] = $field['fieldData'];
        }
    }
}
