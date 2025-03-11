<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use HeimrichHannot\FieldpaletteBundle\EventListener\Callback\LoadFieldsListener;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

#[AsHook('loadDataContainer', priority: -250)]
class LoadDataContainerListener
{
    public function __construct(
        private readonly FieldPaletteRegistry $registry,
    ) {
    }

    public function __invoke(string $table): void
    {
        $dca = &$GLOBALS['TL_DCA'][$table];

        $this->setDynamicParentTable($table);

        $parentFields = array_filter(
            array_filter($dca['fields'] ?? []),
            static fn (array $definition): bool => 'fieldpalette' === ($definition['inputType'] ?? null)
        );

        $dca['config']['onload_callback'][] = [LoadFieldsListener::class, 'onLoadCallback'];

        if (empty($parentFields)) {
            return;
        }

        $hasChanges = false;
        foreach ($parentFields as $parentFieldName => $parentFieldData) {
            if (!isset($parentFieldData['fieldpalette']['fields'])) {
                continue;
            }

            $targetTable = $parentFieldData['config']['table'] ?? 'tl_fieldpalette';

            foreach (array_filter($parentFieldData['fieldpalette']['fields']) as $fieldName => $fieldData) {
                if ($this->registry->hasField($fieldName, $parentFieldName, $table)) {
                    continue;
                }

                $this->registry->addField(
                    $fieldName,
                    $parentFieldName,
                    $table,
                    $targetTable
                );
                $hasChanges = true;
            }
        }

        if ($this->registry->isFullyLoaded() && $hasChanges) {
            $this->registry->refresh();
            $this->registry->storeResults();
        }
    }

    /**
     * Check if the table is a fieldpalette enabled table and set the dynamic parent table.
     */
    private function setDynamicParentTable(string $table): void
    {
        if (!isset($GLOBALS['TL_DCA'][$table]['config']['fieldpalette'])) {
            return;
        }

        $ptable = Input::get('ptable');
        if (!$ptable) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['config']['ptable'] = $ptable;
    }
}
