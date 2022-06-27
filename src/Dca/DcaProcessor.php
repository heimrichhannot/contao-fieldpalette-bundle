<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Dca;

class DcaProcessor
{
    /** @var string */
    private $defaultTable = 'tl_fieldpalette';

    public function getFieldpaletteFields(array $dca): array
    {
        $fieldpaletteFields = [];

        if (!isset($dca['fields'])) {
            return $fieldpaletteFields;
        }

        foreach ($dca['fields'] as $fieldName => $field) {
            if (!isset($field['inputType']) || 'fieldpalette' !== $field['inputType']) {
                continue;
            }

            $fieldpaletteTable = $field['config']['table'] ?? $this->defaultTable;

            $fieldpaletteFields[$fieldpaletteTable][$fieldName] = $field;

            if (isset($field['fields'])) {
                $fieldpaletteFields = array_merge_recursive($fieldpaletteFields, $this->getFieldpaletteFields($field));
            }
        }

        return $fieldpaletteFields;
    }

    public function updateFieldpaletteTable(string $table, array $fields): void
    {
        if (!isset($GLOBALS['TL_DCA'][$table])) {
            throw new \Exception('Table must be loaded before applying fieldpalette fields!');
        }

        foreach ($fields as $field) {
            if (!isset($field['fields'])) {
                continue;
            }

            foreach ($field['fields'] as $fieldpaletteFieldName => $fieldpaletteField) {
                $GLOBALS['TL_DCA'][$table]['fields'][$fieldpaletteFieldName] = $fieldpaletteField;
            }
        }
    }
}
