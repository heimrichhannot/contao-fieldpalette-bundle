<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Controller;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

#[AsHook('sqlGetFromDca')]
class SqlGetFromDcaListener
{
    protected FieldPaletteRegistry $registry;

    public function __construct(FieldPaletteRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(array $sql): array
    {
        foreach ($sql as $table => $value) {
            Controller::loadDataContainer($table);
        }

        $this->registry->storeResults();
        foreach ($this->registry->getFields() as $field) {
            $fieldData = $this->registry->getFieldData($field);
            if (
                !isset($sql[$field['targetTable']]['TABLE_FIELDS'][$field['fieldName']])
                && isset($fieldData['sql'])
            ) {
                $sql[$field['targetTable']]['TABLE_FIELDS'][$field['fieldName']] =
                    '`' . $field['fieldName'] . '` ' . $fieldData['sql'];
            }
        }

        return $sql;
    }
}
