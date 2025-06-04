<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;

/**
 * @ServiceTag("kernel.cache_clearer")
 * @ServiceTag("kernel.cache_warmer")
 */
class CacheListener implements CacheClearerInterface, CacheWarmerInterface
{
    public function __construct(
        private readonly FieldPaletteRegistry $registry,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp($cacheDir): array
    {
        $this->registry->refresh();

        return [];
    }

    public function clear($cacheDir): void
    {
        $cache = new TagAwareAdapter(new FilesystemAdapter($this->registry::CACHE_REGISTRY));
        $cache->clear();
    }
}
