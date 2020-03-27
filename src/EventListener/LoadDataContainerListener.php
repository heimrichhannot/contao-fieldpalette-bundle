<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
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

    /**
     * LoadDataContainerListener constructor.
     */
    public function __construct(DcaHandler $dcaHandler)
    {
        $this->dcaHandler = $dcaHandler;
    }

    /**
     * Hook("loadDataContainer").
     */
    public function onLoadDataContainer(string $table): void
    {
        $cache = new TagAwareAdapter(new FilesystemAdapter(static::CACHE_NAMESPACE));
        /** @var CacheItem $item */
        $item = $cache->getItem('extract_'.$table);
        if (!$item->isHit()) {
            $item->set($this->extractTableFields($table));
            $item->tag('dca');
            $cache->save($item);
        }
        $palettes = $item->get();
        if (empty($palettes)) {
            return;
        }
        foreach ($palettes as $paletteTable => $fields) {
            if (!isset($GLOBALS['loadDataContainer'][$paletteTable])) {
                Controller::loadDataContainer($paletteTable);
            }

            $GLOBALS['TL_DCA'][$paletteTable]['fields'] = array_merge(
                \is_array($GLOBALS['TL_DCA'][$paletteTable]['fields']) ? $GLOBALS['TL_DCA'][$paletteTable]['fields'] : [],
                \is_array($fields) ? $fields : []
            );
        }
        $this->dcaHandler->registerFieldPalette($table);
    }

    /**
     * Extract table fields sql.
     *
     * @param string $tables The field palette table name
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function extractTableFields($tables): array
    {
        $dcaFields = $GLOBALS['TL_DCA'][$tables]['fields'];
        $palettes = [];
        if (!empty($dcaFields)) {
            $palettes = $this->dcaHandler->extractFieldPaletteFields($tables, $dcaFields);
        }

        return $palettes;
    }
}
