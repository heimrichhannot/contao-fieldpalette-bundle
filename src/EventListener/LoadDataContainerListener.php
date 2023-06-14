<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use HeimrichHannot\FieldpaletteBundle\Dca\DcaProcessor;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;

class LoadDataContainerListener
{
    const CACHE_NAMESPACE = 'huh.fieldpalette';

    /**
     * @var DcaHandler
     */
    private $dcaHandler;

    /** @var array */
    private $fieldCache = [];

    /** @var array */
    private $processedTables = [];

    /**
     * @var DcaProcessor
     */
    private $dcaProcessor;

    /**
     * LoadDataContainerListener constructor.
     */
    public function __construct(DcaHandler $dcaHandler, DcaProcessor $dcaProcessor)
    {
        $this->dcaHandler = $dcaHandler;
        $this->dcaProcessor = $dcaProcessor;
    }

    /**
     * Hook("loadDataContainer").
     */
    public function onLoadDataContainer(string $table): void
    {
//        $this->dcaHandler->registerFieldPalette($table);
    }
}
