<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Input;
use Contao\Widget;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaExtractor;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HookListener
{
    /**
     * @var DcaExtractor
     */
    private $dcaExtractor;
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(DcaExtractor $dcaExtractor, ContainerInterface $container, ContaoFramework $framework)
    {
        $this->dcaExtractor = $dcaExtractor;
        $this->container = $container;
        $this->framework = $framework;
    }

    /**
     * @param string $action
     */
    public function executePostActionsHook($action, DataContainer $dc)
    {
        if (DcaHandler::FieldpaletteRefreshAction === $action) {
            if ($this->framework->getAdapter(Input::class)->post('field')) {
                $this->framework->getAdapter(Controller::class)->loadDataContainer($dc->table);

                $name = $this->framework->getAdapter(Input::class)->post('field');
                $field = $GLOBALS['TL_DCA'][$dc->table]['fields'][$name];

                // Die if the field does not exist
                if (!\is_array($field)) {
                    header('HTTP/1.1 400 Bad Request');
                    exit('Bad Request');
                }

                /** @var Widget $class */
                $class = $GLOBALS['BE_FFL'][$field['inputType']];

                // Die if the class is not defined or inputType is not fieldpalette
                if ('fieldpalette' !== $field['inputType'] || !class_exists($class)) {
                    return;
                }

                $attributes = $this->framework->getAdapter(Widget::class)->getAttributesFromDca($field, $name, $dc->activeRecord->{$name}, $name, $dc->table, $dc);

                /** @var Widget $widget */
                $widget = new $class($attributes);
                $widget->currentRecord = $dc->id;

                $data = ['field' => $name, 'target' => '#ctrl_'.$name, 'content' => $widget->generate()];

                if ($widget->submitOnChange) {
                    $data['autoSubmit'] = $dc->table;
                }

                exit(json_encode($data));
            }
        }
    }

    /**
     * Modify the tl_fieldpalette dca sql, afterwards all loadDataContainer Hooks has been run
     * This is required, fields within all dca tables needs to be added to the database.
     *
     * @param array $dcaSqlExtract
     *
     * @throws \Exception
     *
     * @return array The entire extracted sql data from all tables
     */
    public function sqlGetFromDcaHook($dcaSqlExtract)
    {
        foreach ($dcaSqlExtract as $table => $sql) {
            $this->framework->getAdapter(Controller::class)->loadDataContainer($table);
        }

        $fieldpaletteTable = $this->container->getParameter('huh.fieldpalette.table');
        $extract = $this->dcaExtractor->getExtract($fieldpaletteTable);

        if ($extract->isDbTable()) {
            $dcaSqlExtract[$fieldpaletteTable] = $extract->getDbInstallerArray();
        }

        return $dcaSqlExtract;
    }
}
