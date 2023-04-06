<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Registry;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database\Installer;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class FieldPaletteRegistry
{
    public const CACHE_REGISTRY = 'huh.fieldpalette.registry';

    protected ContaoFramework $framework;
    protected TagAwareAdapter $cache;

    protected array $fields = [];
    protected array $targetFields = [];

    protected $registriy;
    /**
     * @var true
     */
    private bool $fullyLoaded = false;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

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

    public function addField(string $fieldName, string $parentFieldName, array $fieldData, string $sourceTable, string $targetTable = 'tl_fieldpalette'): void
    {
        $internalName = implode('_', [$sourceTable, $parentFieldName, $fieldName]);

        $this->fields[$internalName] = [
            'fieldName' => $fieldName,
            'parentFieldName' => $parentFieldName,
            'fieldData' => $fieldData,
            'sourceTable' => $sourceTable,
            'targetTable' => $targetTable,
        ];

        $this->targetFields[$fieldName] = $internalName;
    }

    /**
     * @return array{fieldName: string, parentFieldName:string}
     */
    public function getFields(): array
    {
        if (!$this->fullyLoaded) {
            $this->restoreResults();
        }

        return $this->fields;
    }

    public function storeResults(): void
    {
        $cache = $this->getCache();

        $item = $cache->getItem('fields');
        $item->set($this->fields);
        $cache->save($item);

        $this->fullyLoaded = true;
    }

    public function isFullyLoaded(): bool
    {
        return $this->fullyLoaded;
    }

    public function hasTargetFields(string $table): bool
    {
        if (!$this->fullyLoaded) {
            $this->restoreResults();
        }

        return !empty($this->targetFields[$table]);
    }

    public function getTargetFields(string $table): array
    {
        if (!$this->fullyLoaded) {
            $this->restoreResults();
        }
        $targetFields = $this->targetFields[$table] ?? [];
        if (empty($targetFields)) {
            return [];
        }

        $return = [];
        foreach ($targetFields as $field) {
            $return[$field] = $this->fields[$field];
        }

        return $return;
    }

    protected function getCache(): TagAwareAdapter
    {
        if (!isset($this->cache)) {
            $this->cache = new TagAwareAdapter(new FilesystemAdapter(static::CACHE_REGISTRY));
        }

        return $this->cache;
    }

    protected function restoreResults(): void
    {
        $cache = $this->getCache();

        $item = $cache->getItem('fields');

        if (!$item->isHit()) {
            $this->createResults();
        } else {
            $fields = $item->get();
            $this->fields = $fields;
        }

        $this->fullyLoaded = true;
    }

    protected function createResults(): void
    {
        /** @var Installer $installer */
        $installer = $this->framework->createInstance(Installer::class);
        $installer->getFromDca();
    }
}
