<?php

/*
 * Copyright (c) 2024 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Registry;

class FieldPaletteRegistry
{
    protected array $registry = [];

    public function set(string $table, string $field, array $dca): void
    {
        $this->registry[$table][$field] = $dca;
    }

    public function get(string $table)
    {
        if (!isset($this->registry[$table])) {
            return null;
        }

        return $this->registry[$table];
    }
}