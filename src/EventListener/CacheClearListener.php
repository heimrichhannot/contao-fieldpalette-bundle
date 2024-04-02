<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class CacheClearListener implements CacheClearerInterface
{
    /**
     * {@inheritdoc}
     */
    public function clear($cacheDir): void
    {
        $cache = new TagAwareAdapter(new FilesystemAdapter(LoadDataContainerListener::CACHE_NAMESPACE));
        $cache->clear();
    }
}
