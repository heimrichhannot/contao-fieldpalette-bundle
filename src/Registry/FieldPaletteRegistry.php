<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Registry;

class FieldPaletteRegistry
{
    protected $registriy;

    public function set(string $table, string $field, array $dca)
    {
        $this->registriy[$table][$field] = $dca;
    }

    public function get(string $table)
    {
        if (!isset($this->registriy[$table])) {
            return null;
        }

        return $this->registriy[$table];
    }
}
