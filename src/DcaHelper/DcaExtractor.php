<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\DcaHelper;

use Symfony\Component\DependencyInjection\ContainerInterface;

class DcaExtractor extends \Contao\DcaExtractor
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * FieldPaletteDcaExtractor is required to disable usage of cached dca file
     * if internal contao cache is active, as fields get added dynamically.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param $table
     *
     * @throws \Exception
     *
     * @return \Contao\DcaExtractor
     */
    public function getExtract($table)
    {
        if (empty($table)) {
            throw new \Exception('The table name must not be empty');
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
