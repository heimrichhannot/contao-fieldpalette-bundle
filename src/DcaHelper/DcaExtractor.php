<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\DcaHelper;

use Contao\DcaExtractor as ContaoDcaExtractor;
use Exception;
use Symfony\Component\DependencyInjection\Container;

/**
 * @fixme please: Parent constructor call is missing. This class might not be functional.
 * @experimental This class is missing the parent constructor call and might therefore not be functional.
 */
class DcaExtractor extends ContaoDcaExtractor
{
    protected Container $container;

    /**
     * FieldPaletteDcaExtractor is required to disable usage of cached dca file
     * if internal contao cache is active, as fields get added dynamically.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param $table
     *
     * @return ContaoDcaExtractor
     *@throws Exception
     *
     */
    public function getExtract($table)
    {
        if (empty($table)) {
            throw new Exception('The table name must not be empty');
        }

        if ($table !== $this->container->getParameter('huh.fieldpalette.table')) {
            return new parent($table);
        }

        $this->strTable = $this->container->getParameter('huh.fieldpalette.table');

        // prevent caching for fieldpalette
        $this->createExtract();

        return $this;
    }
}
