<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;

/**
 * @Hook("sqlGetFromDca")
 */
class SqlGetFromDcaListener
{
    protected FieldPaletteRegistry $registry;

    public function __construct(FieldPaletteRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(array $sql): array
    {
        return $sql;

        $this->registry->storeResults();
        foreach ($this->registry->getFields() as $field) {
            if (
                !isset($sql[$field['targetTable']]['TABLE_FIELDS'][$field['fieldName']])
                && isset($field['fieldData']['sql'])
            ) {
                $sql[$field['targetTable']]['TABLE_FIELDS'][$field['fieldName']] =
                    '`'.$field['fieldName'].'` '.$field['fieldData']['sql'];
            }
        }

        return $sql;
    }
}
