<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Callback;

use Contao\DataContainer;
use HeimrichHannot\FieldpaletteBundle\Dca\DcaProcessor;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

class LoadFieldsListener
{
    private FieldPaletteRegistry $registry;
    private DcaHandler           $dcaHandler;
    private DcaProcessor         $dcaProcessor;

    public function __construct(FieldPaletteRegistry $registry, DcaHandler $dcaHandler, DcaProcessor $dcaProcessor)
    {
        $this->registry = $registry;
        $this->dcaHandler = $dcaHandler;
        $this->dcaProcessor = $dcaProcessor;
    }

    public function onLoadCallback(DataContainer $dc = null): void
    {
        if (!$dc || !$dc->table || !$this->registry->hasTargetFields($dc->table)) {
            return;
        }

        $fields = $this->registry->getTargetFields($dc->table);
        foreach ($fields as $field) {
            $fieldData = $this->registry->getFieldData($field);
            if (!$fieldData) {
                continue;
            }
            $GLOBALS['TL_DCA'][$dc->table]['fields'][$field['fieldName']] = $field['fieldData'];

            $this->dcaProcessor->prepareTargetDca($field);

            $this->dcaHandler->registerFieldPalette($dc->table);
        }
    }
}
