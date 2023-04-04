<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use HeimrichHannot\FieldpaletteBundle\Dca\DcaProcessor;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;

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
        return;

        $fieldpaletteTables = $this->extractTableFields($table);
        $this->updateTable($table);

        foreach ($fieldpaletteTables as $table) {
            if (!\in_array($table, $this->processedTables, true)) {
                Controller::loadDataContainer($table);
            }
        }

        $this->processedTables[] = $table;
        unset($this->fieldCache[$table]);

        $this->dcaHandler->registerFieldPalette($table);
    }

    public function extractTableFields(string $table): array
    {
        if (!isset($GLOBALS['TL_DCA'][$table])) {
            return [];
        }
        $cache = new TagAwareAdapter(new FilesystemAdapter(static::CACHE_NAMESPACE));
        /** @var CacheItem $item */
        $item = $cache->getItem('extract_'.$table);
        if (!$item->isHit()) {
            $dca = &$GLOBALS['TL_DCA'][$table];
            $item->set($this->dcaProcessor->getFieldpaletteFields($dca));
            $item->tag(['dca', 'contao.db.'.$table]);
            $cache->save($item);
        }
        $extract = $item->get();

        if (empty($extract)) {
            return [];
        }

        foreach ($extract as $fieldpaletteTable => $fields) {
            if (\in_array($fieldpaletteTable, $this->processedTables, true)) {
                $this->dcaProcessor->updateFieldpaletteTable($fieldpaletteTable, $fields);
            } else {
                if (!isset($this->fieldCache[$fieldpaletteTable])) {
                    $this->fieldCache[$fieldpaletteTable] = [];
                }
                $this->fieldCache[$fieldpaletteTable] = array_merge(
                    $this->fieldCache[$fieldpaletteTable],
                    $fields
                );
            }
        }

        return array_keys($extract);
    }

    private function updateTable(string $table): void
    {
        if (!isset($this->fieldCache[$table])) {
            return;
        }

        $this->dcaProcessor->updateFieldpaletteTable($table, $this->fieldCache[$table]);
    }
}
