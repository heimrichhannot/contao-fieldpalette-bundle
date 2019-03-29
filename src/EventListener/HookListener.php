<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Contao\Widget;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaExtractor;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
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
    /**
     * @var DcaHandler
     */
    private $dcaHandler;
    /**
     * @var FieldPaletteModelManager
     */
    private $modelManager;

    public function __construct(DcaExtractor $dcaExtractor, ContainerInterface $container, ContaoFramework $framework, DcaHandler $dcaHandler, FieldPaletteModelManager $modelManager)
    {
        $this->dcaExtractor = $dcaExtractor;
        $this->container = $container;
        $this->framework = $framework;
        $this->dcaHandler = $dcaHandler;
        $this->modelManager = $modelManager;
    }

    /**
     * Adjust back end module to allow fieldpalette table access.
     *
     * Note: Do never execute Controller::loadDataContainer() inside this function as no BackendUser is available inside initializeSystem Hook.
     */
    public function initializeSystemHook()
    {
        $table = $this->framework->getAdapter(Input::class)->get(DcaHandler::TableRequestKey);

        if (empty($table)) {
            return;
        }

        foreach ($GLOBALS['BE_MOD'] as $strGroup => $arrGroup) {
            if (!\is_array($arrGroup)) {
                continue;
            }

            foreach ($arrGroup as $strModule => $arrModule) {
                if (!\is_array($arrModule) && !\is_array($arrModule['tables'])) {
                    continue;
                }

                $GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][] = $table;
            }
        }
    }

    /**
     * @param string        $action
     * @param DataContainer $dc
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
                    die('Bad Request');
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

                die(json_encode($data));
            }
        }
    }

    /**
     * Add fieldpalette fields to tl_fieldpalette.
     *
     * @param string $table
     *
     * @throws \Exception
     */
    public function loadDataContainerHook($table)
    {
        $environment = $this->framework->getAdapter(Environment::class);
        $input = $this->framework->getAdapter(Input::class);

        if (preg_match('/(contao\/install)/', $environment->get('request')) || 'group' === $input->get('do')) {
            $this->extractTableFields($table);
        }
        $this->dcaHandler->registerFieldPalette($table);
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

    /**
     * Extract table fields sql.
     *
     * @param string $tables The field palette table name
     *
     * @throws \Exception
     */
    protected function extractTableFields($tables)
    {
        $dcaFields = $GLOBALS['TL_DCA'][$tables]['fields'];
        $palettes = [];
        if (!empty($dcaFields)) {
            $palettes = $this->dcaHandler->extractFieldPaletteFields($tables, $dcaFields);
        }
        foreach ($palettes as $paletteTable => $fields) {
            if (!isset($GLOBALS['loadDataContainer'][$paletteTable])) {
                $this->framework->getAdapter(Controller::class)->loadDataContainer($paletteTable);
            }

            $GLOBALS['TL_DCA'][$paletteTable]['fields'] = array_merge(
                \is_array($GLOBALS['TL_DCA'][$paletteTable]['fields']) ? $GLOBALS['TL_DCA'][$paletteTable]['fields'] : [],
                \is_array($fields) ? $fields : []
            );
        }
    }
}
