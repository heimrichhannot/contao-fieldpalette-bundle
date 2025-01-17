<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Registry;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database\Installer;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class FieldPaletteRegistry
{
    public const CACHE_REGISTRY = 'huh.fieldpalette.registry';

    protected ContaoFramework $framework;

    protected array $fields = [];
    protected array $targetFields = [];
    protected array $sourceFields = [];

    protected $registriy;
    private CacheInterface $cache;
    /**
     * @var true
     */
    private bool           $fullyLoaded = false;

    public function __construct(ContaoFramework $framework, CacheInterface $cache)
    {
        $this->framework = $framework;
        $this->cache = $cache;
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

    public function addField(string $fieldName, string $parentFieldName, string $sourceTable, string $targetTable = 'tl_fieldpalette'): void
    {
        $internalName = $this->getInternalName($sourceTable, $parentFieldName, $fieldName);

        $this->fields[$internalName] = [
            'fieldName' => $fieldName,
            'parentFieldName' => $parentFieldName,
            'sourceTable' => $sourceTable,
            'targetTable' => $targetTable,
        ];

        $this->indexField($targetTable, $sourceTable, $internalName);
    }

    public function hasField(string $fieldName, string $parentFieldName, string $sourceTable): bool
    {
        return isset($this->fields[$this->getInternalName($sourceTable, $parentFieldName, $fieldName)]);
    }

    /**
     * @return array{fieldName: string, parentFieldName:string}
     */
    public function getFields(): array
    {
        if (!$this->isFullyLoaded()) {
            $this->restoreResults();
        }

        return $this->fields;
    }

    public function getFieldData(array $field): ?array
    {
        Controller::loadDataContainer($field['sourceTable']);

        return $GLOBALS['TL_DCA'][$field['sourceTable']]['fields'][$field['parentFieldName']]['fieldpalette']['fields'][$field['fieldName']] ?? null;
    }

    public function storeResults(): void
    {
        $cachePool = new FilesystemAdapter(static::CACHE_REGISTRY);
        $item = $cachePool->getItem(static::CACHE_REGISTRY);
        $item->set($this->fields);
        $cachePool->save($item);
        $this->fullyLoaded = true;
    }

    public function isFullyLoaded(): bool
    {
        return $this->fullyLoaded;
    }

    public function hasTargetFields(string $table): bool
    {
        if (!$this->isFullyLoaded()) {
            $this->restoreResults();
        }

        return !empty($this->targetFields[$table]);
    }

    public function getTargetFields(string $table, ): array
    {
        if (!$this->isFullyLoaded()) {
            $this->restoreResults();
        }

        return $this->getTableFields($table, $this->targetFields);
    }

    public function hasSourceFields(string $table): bool
    {
        if (!$this->isFullyLoaded()) {
            $this->restoreResults();
        }

        return !empty($this->sourceFields[$table]);
    }

    public function getSourceFields(string $table): array
    {
        return $this->getTableFields($table, $this->sourceFields);
    }

    public function refresh(): void
    {
        /** @var Installer $installer */
        $installer = $this->framework->createInstance(Installer::class);
        $installer->getFromDca();
    }

    protected function getTableFields(string $table, array $fields): array
    {
        $targetFields = $fields[$table] ?? [];
        if (empty($targetFields)) {
            return [];
        }

        $return = [];
        foreach ($targetFields as $field) {
            $return[$field] = $this->fields[$field];
        }

        return $return;
    }

    protected function restoreResults(): void
    {
        $cachePool = new FilesystemAdapter(static::CACHE_REGISTRY);
        $item = $cachePool->getItem(static::CACHE_REGISTRY);
        if ($item->isHit() && \is_array($item->get())) {
            $this->fields = $item->get();
            foreach ($this->fields as $internalName => $field) {
                $this->indexField($field['targetTable'], $field['sourceTable'], $internalName);
            }
            $this->fullyLoaded = true;
        } else {
            $this->refresh();
        }
    }

    protected function indexField(string $targetTable, string $sourceTable, string $internalName): void
    {
        if (!isset($this->targetFields[$targetTable])) {
            $this->targetFields[$targetTable] = [];
        }
        if (!isset($this->sourceFields[$sourceTable])) {
            $this->sourceFields[$sourceTable] = [];
        }

        $this->targetFields[$targetTable][] = $internalName;
        $this->sourceFields[$sourceTable][] = $internalName;
    }

    protected function getInternalName(string $sourceTable, string $parentFieldName, string $fieldName): string
    {
        return implode('_', [$sourceTable, $parentFieldName, $fieldName]);
    }
}
