<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\CoreBundle\ServiceAnnotation\Hook;

/**
 * @Hook("sqlCompileCommands")
 */
class SqlCompileCommandsListener
{
    public function __invoke(array $sql): array
    {
        // Modify the array of SQL statements

        return $sql;
    }
}
