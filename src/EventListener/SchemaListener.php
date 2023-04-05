<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;

/**
 * @ServiceTag("doctrine.event_listener", event="postGenerateSchema", priority=-1)
 */
class SchemaListener
{
    private FieldPaletteRegistry $registry;

    public function __construct(FieldPaletteRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(GenerateSchemaEventArgs $event): void
    {
        $schema = $event->getSchema();

        $this->registry->storeResults();

        foreach ($this->registry->getFields() as $field) {
            if (!$schema->hasTable($field['targetTable'])) {
                continue;
            }

            $table = $schema->getTable($field['targetTable']);

            if ($table->hasColumn($field['fieldName'])) {
                continue;
            }
//
//            $table->addColumn()
//
//            if (
//                !isset($sql[$field['targetTable']]['TABLE_FIELDS'][$field['fieldName']])
//                && isset($field['fieldData']['sql'])
//            ) {
//                $sql[$field['targetTable']]['TABLE_FIELDS'][$field['fieldName']] =
//                    '`'.$field['fieldName'].'` '.$field['fieldData']['sql'];
//            }
        }

        // TODO: Implement __invoke() method.
    }
}
