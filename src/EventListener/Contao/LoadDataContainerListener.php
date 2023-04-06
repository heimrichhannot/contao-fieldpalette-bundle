<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use HeimrichHannot\FieldpaletteBundle\EventListener\Callback\LoadFieldsListener;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

/**
 * @Hook("loadDataContainer", priority=-250)
 */
class LoadDataContainerListener
{
    private FieldPaletteRegistry $registry;

    public function __construct(FieldPaletteRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(string $table): void
    {
        $dca = $GLOBALS['TL_DCA'][$table];

        $parentFields = array_filter(
            array_filter($dca['fields'] ?? []),
            static fn (array $definition): bool => 'fieldpalette' === ($definition['inputType'] ?? null)
        );

        $dca['config']['onload_callback'] = [LoadFieldsListener::class, 'onLoadCallback'];

        if (empty($parentFields)) {
            return;
        }

        if ($this->registry->isFullyLoaded()) {
            trigger_error('Fieldpalette fields can only be added from dca files.');
        }

        foreach ($parentFields as $parentFieldName => $parentFieldData) {
            if (!isset($parentFieldData['fieldpalette']['fields'])) {
                continue;
            }

            $targetTable = $parentFieldData['config']['table'] ?? 'tl_fieldpalette';

            foreach (array_filter($parentFieldData['fieldpalette']['fields']) as $fieldName => $fieldData) {
                $this->registry->addField(
                    $fieldName,
                    $parentFieldName,
                    $fieldData,
                    $table,
                    $targetTable
                );
            }
        }
    }
}
