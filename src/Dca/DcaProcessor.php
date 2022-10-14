<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Dca;

use HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard;

class DcaProcessor
{
    /** @var string */
    private $defaultTable = 'tl_fieldpalette';

    /**
     * Scan the dca for fieldpalette fields and return them ordered by their fieldpalette table.
     */
    public function getFieldpaletteFields(array $dca): array
    {
        $fieldpaletteFields = [];

        foreach (($dca['fields'] ?? []) as $fieldName => $field) {
            if (FieldPaletteWizard::TYPE !== ($field['inputType'] ?? '') || empty($field['fieldpalette']['fields'])) {
                continue;
            }

            $fieldpaletteTable = $field['fieldpalette']['config']['table'] ?? $this->defaultTable;

            $fieldpaletteFields[$fieldpaletteTable][$fieldName] = $field;

            // Check for nested fieldpalette fields
            $fieldpaletteFields = array_merge_recursive($fieldpaletteFields, $this->getFieldpaletteFields($field['fieldpalette']));
        }

        return $fieldpaletteFields;
    }

    /**
     * Apply field to the fieldpalette table.
     */
    public function updateFieldpaletteTable(string $table, array $fields): void
    {
        if (!isset($GLOBALS['TL_DCA'][$table])) {
            throw new \Exception('Table must be loaded before applying fieldpalette fields!');
        }

        $dca = &$GLOBALS['TL_DCA'][$table];

        foreach ($fields as $field) {
            if (!isset($field['fieldpalette']['fields'])) {
                continue;
            }

            foreach ($field['fieldpalette']['fields'] as $fieldpaletteFieldName => $fieldpaletteField) {
                $dca['fields'][$fieldpaletteFieldName] = $fieldpaletteField;
            }
        }
    }
}
