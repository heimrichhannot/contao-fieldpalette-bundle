<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Widget;

use Contao\Controller;
use Contao\Database;
use Contao\DC_Table;
use Contao\Environment;
use Contao\Image;
use Contao\Input;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Element\ButtonElement;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Form\FormUtil;
use HeimrichHannot\UtilsBundle\Util\FormatterUtil\FormatDcaFieldValueOptions;
use HeimrichHannot\UtilsBundle\Util\Utils;

class FieldPaletteWizard extends Widget
{
    public const TYPE = 'fieldpalette';

    /**
     * Submit user input.
     *
     * @var bool
     */
    protected $submitInput = true;

    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'be_fieldpalette';

    protected $dca = [];

    /**
     * @var Collection|FieldPaletteModel[]|null
     */
    protected $models;

    protected $buttonDefaults = [];

    protected $viewMode = 0;

    /**
     * Palette table (tl_fieldpalette or custom table).
     *
     * @var mixed|null
     */
    protected $paletteTable;
    /**
     * @var ButtonElement|object
     */
    protected $buttonGenerator;

    /**
     * @param null $attributes
     *
     * @codeCoverageIgnore
     */
    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        $container = System::getContainer();
        $framework = $container->get('contao.framework');
        $dcaHandler = $container->get('huh.fieldpalette.dca.handler');
        $controller = $framework->getAdapter(Controller::class);

        $controller->loadLanguageFile($container->getParameter('huh.fieldpalette.table'));
        $controller->loadLanguageFile($this->strTable);

        $this->dca = $dcaHandler->getDca($this->strTable, $this->strTable, $this->strName);
        $this->viewMode = $this->dca['list']['viewMode'] ?? 0;
        $this->paletteTable = $this->dca['config']['table'] ?? $container->getParameter('huh.fieldpalette.table');

        // load custom table labels
        $controller->loadLanguageFile($this->paletteTable);
    }

    /**
     * Generate the widget and return it as string.
     */
    public function generate(): string
    {
        $this->reviseTable();

        $this->models = $this->getModelInstance($this->paletteTable)
            ->findByPidAndTableAndField($this->currentRecord, $this->strTable, $this->strName);

        $this->buttonDefaults = [
            'do' => Input::get('do'),
            'ptable' => $this->strTable,
            'table' => $this->paletteTable,
            'pid' => $this->currentRecord,
            'fieldpalette' => $this->strName,
            'fieldpaletteKey' => DcaHandler::PaletteRequestKey,
            'popup' => true,
            'syncId' => 'ctrl_' . $this->strId,
            'pfield' => $this->strId,
        ];

        $values = [];

        if ($this->models) {
            $values = $this->models->fetchEach('id');
        }

        return System::getContainer()->get('twig')->render($this->getViewTemplate('wizard'), [
            'id' => $this->strId,
            'buttons' => $this->generateGlobalButtons(),
            'listview' => $this->generateListView(),
            'values' => $values,
            'name' => $this->strName,
        ]);
    }

    /**
     * Returns a new FieldPaletteModel instance.
     *
     * @codeCoverageIgnore
     */
    public function getModelInstance(string $table = ''): FieldPaletteModel
    {
        $model = new FieldPaletteModel();
        if (!empty($table)) {
            $model->setTable($table);
        }

        return $model;
    }

    /**
     * Create a new DC_Table instance.
     *
     * @codeCoverageIgnore
     */
    public function getDcTableInstance(string $table, int $id): DC_Table
    {
        return new class($table, $id) extends DC_Table {
            /**
             * @noinspection MagicMethodsValidityInspection
             * @noinspection PhpMissingParentConstructorInspection
             */
            public function __construct(string $table, int $id) {
                $this->intId = $id;
                $this->strTable = $table;
            }
        };
    }

    protected function getViewTemplate(string $type): string
    {
        switch ($this->viewMode) {
            default:
            case 0:
                $mode = 'table';
                break;
            case 1:
                $mode = 'default';
                break;
        }

        return '@HeimrichHannotContaoFieldpalette/' . $type . '/fieldpalette_' . $type . '_' . $mode . '.html.twig';
    }

    /**
     * @return string
     */
    protected function generateListView(): string
    {
        $image = System::getContainer()->get('contao.framework')->getAdapter(Image::class);
        $items = [];
        $i = 0;
        if ($this->models) {
            foreach ($this->models as $current) {
                if (0 === $current->tstamp) {
                    continue;
                }
                $items[] = $this->generateListItem($current, ++$i);
            }
        }
        $sortable = true;
        if (isset($this->dca['config']['notSortable']) && \is_bool($this->dca['config']['notSortable'])) {
            $sortable = !$this->dca['config']['notSortable'];
        }

        return System::getContainer()->get('twig')->render($this->getViewTemplate('list'), [
            'id' => $this->strId,
            'image' => $image->getHtml('loading.gif', '', 'class="tl_fielpalette_indicator_icon"'),
            'labelIcon' => 'bundles/heimrichhannotcontaofieldpalette/img/fieldpalette.png',
            'label' => $this->strLabel,
            'mandatory' => $this->mandatory,
            'items' => $items,
            'sortable' => $sortable,
        ]);
    }

    /**
     * @param int $index
     *
     * @return string
     */
    protected function generateListItem(FieldPaletteModel $model, $index)
    {
        return System::getContainer()->get('twig')->render($this->getViewTemplate('item'), [
            'id' => sprintf('%s_%s_%s', $model->ptable, $model->pfield, $model->id),
            'index' => $index,
            'attribute' => '',
            'buttons' => $this->generateButtons($model),
            'label' => $this->generateItemLabel($model, ''),
        ]);
    }

    /**
     * @param FieldPaletteModel $model
     * @param string            $folderAttribute
     *
     * @return string
     */
    protected function generateItemLabel($model, $folderAttribute)
    {
        /**
         * @var System
         */
        $system = System::getContainer()->get('contao.framework')->getAdapter(System::class);

        $utils = System::getContainer()->get(Utils::class);
        $protected = false;

        if (!isset($this->dca['list']['label']['fields'])) {
            return $model->id;
        }
        $showFields = $this->dca['list']['label']['fields'];

        $dc = $this->getDcTableInstance($this->paletteTable, $model->id);
        /* @phpstan-ignore property.notFound */
        $dc->activeRecord = $model;

        $args = [];

        foreach ($showFields as $key => $fieldName) {
            $value = $model->{$fieldName};

            // Call load_callback
            if (isset($this->dca['fields'][$fieldName]['load_callback'])) {
                foreach ($this->dca['fields'][$fieldName]['load_callback'] as $callback) {
                    if (\is_array($callback)) {
                        $value = $system->importStatic($callback[0])->{$callback[1]}($value, $dc);
                    } elseif (\is_callable($callback)) {
                        $value = $callback($value, $dc);
                    }
                }
            }

            if (method_exists($utils, 'formatter')) {
                $args[$key] = $utils->formatter()->formatDcaFieldValue(
                    $dc,
                    $fieldName,
                    $value,
                    (new FormatDcaFieldValueOptions())
                        ->setDcaOverride($this->dca['fields'][$fieldName])
                        ->setReplaceInsertTags(!$utils->container()->isBackend())
                );
            // utils bundle v2 fallback
            /* @phpstan-ignore class.notFound */
            } elseif (System::getContainer()->has(FormUtil::class)) {
                /** @phpstan-ignore class.notFound */
                $formUtil = System::getContainer()->get(FormUtil::class);
                $args[$key] = $formUtil->prepareSpecialValueForOutput($fieldName, $value, $dc, [
                    '_dcaOverride' => $this->dca['fields'][$fieldName],
                    'skipReplaceInsertTags' => $utils->container()->isBackend(),
                ]);
            } else {
                $args[$key] = $value;
            }
        }

        $label = vsprintf(
            $this->dca['list']['label']['format'] ?? '%s',
            $args
        );

        // Shorten the label if it is too long
        if (isset($this->dca['list']['label']['maxCharacters'])
            && $this->dca['list']['label']['maxCharacters'] > 0
            && $this->dca['list']['label']['maxCharacters'] < mb_strlen(
                strip_tags($label)
            )
        ) {
            $label = trim(StringUtil::substrHtml($label, $this->dca['list']['label']['maxCharacters'])) . ' …';
        }

        // Call the label_callback ($row, $label, $this)
        if (isset($this->dca['list']['label']['label_callback'])) {
            $callback = &$this->dca['list']['label']['label_callback'];
            if (\is_array($callback)) {
                $strClass = $callback[0];
                $strMethod = $callback[1];

                $label = $system->importStatic($strClass)->{$strMethod}($model->row(), $label, $this, $folderAttribute, false, $protected);
            } elseif (\is_callable($callback)) {
                $label = $callback($model->row(), $label, $this, $folderAttribute, false, $protected);
            }
        }

        return $label;
    }

    /**
     * Compile buttons from the table configuration array and return them as HTML.
     *
     * @param array|null  $childRecordIds
     * @param string|null $previous
     * @param string|null $next
     */
    protected function generateButtons(
        FieldPaletteModel $rowModel,
        array $rootIds = [],
        bool $circularReference = false,
        $childRecordIds = null,
        $previous = null,
        $next = null,
    ): string {
        if (empty($this->dca['list']['operations'])) {
            return '';
        }

        $operations = $this->dca['list']['operations'];
        if (!\is_array($operations)) {
            return '';
        }

        $container = System::getContainer();
        $framework = $container->get('contao.framework');
        $buttonGenerator = $container->get(ButtonElement::class);
        /**
         * @var Image
         */
        $image = $framework->getAdapter(Image::class);
        /**
         * @var System
         */
        $system = $framework->getAdapter(System::class);

        $return = '';

        $dc = $this->getDcTableInstance($this->paletteTable, $this->currentRecord);
        /* @phpstan-ignore property.notFound */
        $dc->activeRecord = $rowModel;

        foreach ($operations as $key => $value) {
            $value = \is_array($value) ? $value : [$value];
            $id = StringUtil::specialchars(rawurldecode($rowModel->id));

            $label = isset($value['label']) ? (\is_string($value['label']) ? $value['label'] : ($value['label'][0] ?? $key)) : $key;

            $title = sprintf($label, $id);

            $attributes = (isset($value['attributes']) && !empty($value['attributes'])) ? ltrim(sprintf($value['attributes'], $id, $id)) : '';

            $button = $buttonGenerator;
            $button->addOptions($this->buttonDefaults);
            $button->setType($key);
            $button->setId($rowModel->id);
            $button->setModalTitle(
                sprintf(
                    $GLOBALS['TL_LANG']['tl_fieldpalette']['modalTitle'] ?? '',
                    $GLOBALS['TL_LANG'][$this->strTable][$this->strName][0] ?? $this->strName,
                    sprintf($title, $rowModel->id)
                )
            );
            $button->setAttributes([$attributes]);
            $button->setLabel($image->getHtml($value['icon'], $label));
            $button->setTitle(StringUtil::specialchars($title));

            if (isset($value['button_callback'])) {
                // Call a custom function instead of using the default button
                if (\is_array($value['button_callback'])) {
                    $return .= $system->importStatic($value['button_callback'][0])->{$value['button_callback'][1]}(
                        $rowModel->row(),
                        $button->getHref(),
                        $label,
                        $title,
                        $value['icon'],
                        $attributes,
                        $this->paletteTable,
                        $rootIds,
                        $childRecordIds,
                        $circularReference,
                        $previous,
                        $next,
                        $dc
                    );
                    continue;
                } elseif (\is_callable($value['button_callback'])) {
                    $return .= $value['button_callback'](
                        $rowModel->row(),
                        $button->getHref(),
                        $label,
                        $title,
                        $value['icon'],
                        $attributes,
                        $this->paletteTable,
                        $rootIds,
                        $childRecordIds,
                        $circularReference,
                        $previous,
                        $next,
                        $dc
                    );
                    continue;
                }
            }

            // Generate all buttons except "move up" and "move down" buttons
            if ('move' !== $key && 'move' !== $value) {
                $return .= $button->generate();
                continue;
            }

            $arrDirections = ['up', 'down'];
            $rootIds = \is_array($rootIds) ? $rootIds : [$rootIds];
            $defaultTable = $container->getParameter('huh.fieldpalette.table');
            $controller = $framework->getAdapter(Controller::class);

            foreach ($arrDirections as $dir) {
                $label = $GLOBALS['TL_LANG'][$defaultTable][$dir][0] ?? $dir;
                $title = $GLOBALS['TL_LANG'][$defaultTable][$dir][1] ?? $dir;

                $label = $image->getHtml($dir . '.gif', $label);
                $href = $value['href'] ?: '&amp;act=move';

                if ('up' === $dir) {
                    if (is_numeric($previous) && (!\in_array($rowModel->id, $rootIds, true)
                            || empty($this->dca['list']['sorting']['root']))) {
                        $return .= '<a href="' . $controller->addToUrl($href . '&amp;id=' . $rowModel->id)
                            . '&amp;sid=' . (int) $previous . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . $label . '</a> ';
                    } else {
                        $return .= $image->getHtml('up_.gif') . ' ';
                    }
                    continue;
                }

                if (is_numeric($next)
                    && (!\in_array($rowModel->id, $rootIds, true)
                        || empty($this->dca['list']['sorting']['root']))) {
                    $return .= '<a href="' . $controller->addToUrl($href . '&amp;id=' . $rowModel->id)
                        . '&amp;sid=' . (int) $next . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . $label . '</a> ';
                } else {
                    $return .= $image->getHtml('down_.gif');
                }
            }
        }

        $do = $framework->getAdapter(Input::class)->get('do');

        // Sort elements
        if (!isset($this->dca['config']['notSortable']) || !$this->dca['config']['notSortable']) {
            $href = $container->get(Utils::class)->routing()->generateBackendRoute([
                'do' => $do,
                'table' => $this->paletteTable,
                'id' => $rowModel->id,
                DcaHandler::ParentTableRequestKey => $this->strTable,
                DcaHandler::PaletteRequestKey => $this->strName,
            ], true, false);

            $title = sprintf($GLOBALS['TL_LANG'][$this->strTable]['cut'][1] ?? 'Cut', $rowModel->id);
            $return .= ' ' . $image->getHtml(
                'drag.gif',
                '',
                'class="drag-handle" title="' . $title . '" data-href="' . $href

                . '" data-id="' . $rowModel->id . '" data-pid="' . $rowModel->pid . '"'
            );
        }

        return trim($return);
    }

    protected function generateGlobalButtons()
    {
        $button = System::getContainer()->get(ButtonElement::class);

        $button->addOptions($this->buttonDefaults);
        $button->setType('create');
        $button->setModalTitle(
            sprintf(
                $GLOBALS['TL_LANG']['tl_fieldpalette']['modalTitle'],
                !empty($GLOBALS['TL_LANG'][$this->strTable][$this->strName][0]) ? $GLOBALS['TL_LANG'][$this->strTable][$this->strName][0] : $this->strName,
                $GLOBALS['TL_LANG']['tl_fieldpalette']['new'][1]
            )
        );
        $button->setLabel($GLOBALS['TL_LANG']['tl_fieldpalette']['new'][0]);
        $button->setTitle($GLOBALS['TL_LANG']['tl_fieldpalette']['new'][0]);

        return $button->generate();
    }

    /**
     * Delete all incomplete and unrelated records.
     */
    protected function reviseTable()
    {
        $container = System::getContainer();
        $framework = $container->get('contao.framework');
        $defaultTable = $container->getParameter('huh.fieldpalette.table');
        /** @var Controller $controller */
        $controller = $framework->getAdapter(Controller::class);
        /** @var Environment $environment */
        $environment = $framework->getAdapter(Environment::class);
        /**
         * @var System
         */
        $system = $framework->getAdapter(System::class);

        $reload = false;
        $ptable = $this->dca['config']['ptable'] ?? null;
        $ctable = $this->dca['config']['ctable'] ?? null;

        $new_records = System::getContainer()->get('request_stack')->getSession()->get('new_records') ?: null;

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['reviseTable']) && \is_array($GLOBALS['TL_HOOKS']['reviseTable'])) {
            foreach ($GLOBALS['TL_HOOKS']['reviseTable'] as $callback) {
                $status = false;

                if (\is_array($callback) && \count($callback) >= 2) {
                    $status = $system->importStatic($callback[0])->{$callback[1]}($this->strTable, $new_records[$this->strTable] ?? null, $ptable, $ctable);
                } elseif (\is_callable($callback)) {
                    $status = $callback($this->strTable, $new_records[$this->strTable], $ptable, $ctable);
                }

                if (true === $status) {
                    $reload = true;
                }
            }
        }

        // Delete all new but incomplete fieldpalette records (tstamp=0)
        if (!empty($new_records[$this->paletteTable]) && \is_array($new_records[$this->paletteTable])) {
            $result = $framework->createInstance(Database::class)->prepare(
                'DELETE FROM ' . $this->paletteTable . ' WHERE id IN(' . implode(
                    ',',
                    array_map(
                        'intval',
                        $new_records[$this->paletteTable]
                    )
                ) . ') AND tstamp=0 AND (? IS NULL OR id != ?)'
            )->execute($this->activeRecord->id, $this->activeRecord->id);

            if ($result->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete all fieldpalette records whose child record isn't existing
        if (!empty($ptable)) {
            if (isset($this->dca['config']['dynamicPtable']) && $this->dca['config']['dynamicPtable']) {
                $result = $framework->createInstance(Database::class)->execute(
                    'DELETE FROM ' . $this->paletteTable . " WHERE ptable='" . $ptable . "' AND NOT EXISTS (SELECT * FROM (SELECT * FROM "
                    . $ptable . ') AS fpp WHERE ' . $this->paletteTable . '.pid = fpp.id)'
                );
            } else {
                $result = $framework->createInstance(Database::class)->execute(
                    'DELETE FROM ' . $this->paletteTable . ' WHERE NOT EXISTS (SELECT * FROM (SELECT * FROM ' . $ptable . ') AS fpp WHERE '
                    . $defaultTable . '.pid = fpp.id)'
                );
            }

            if ($result->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete all records of the child table that are not related to the current table
        if (!empty($ctable) && \is_array($ctable)) {
            foreach ($ctable as $v) {
                if (\is_string($v) && !empty($v)) {
                    // Load the DCA configuration so we can check for "dynamicPtable"
                    if (!isset($GLOBALS['loadDataContainer'][$v])) {
                        $controller->loadDataContainer($v);
                    }

                    if (isset($GLOBALS['TL_DCA'][$v]['config']['dynamicPtable'])
                        && $GLOBALS['TL_DCA'][$v]['config']['dynamicPtable']) {
                        $result = $framework->createInstance(Database::class)->execute(
                            "DELETE FROM $v WHERE ptable='" . $this->paletteTable . "' AND NOT EXISTS (SELECT * FROM " . '(SELECT * FROM '
                            . $this->paletteTable . ") AS fp WHERE $v.pid = fp.id)"
                        );
                    } else {
                        $result = $framework->createInstance(Database::class)->execute(
                            "DELETE FROM $v WHERE NOT EXISTS (SELECT * FROM (SELECT * FROM " . $this->paletteTable
                            . ") AS fp WHERE $v.pid = fp.id)"
                        );
                    }

                    if ($result->affectedRows > 0) {
                        $reload = true;
                    }
                }
            }
        }

        // Reload the page
        if ($reload) {
            if (Environment::get('isAjaxRequest')) {
                return true;
            }

            return $controller->reload();
        }

        return false;
    }
}
