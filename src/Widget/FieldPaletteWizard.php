<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Widget;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DC_Table;
use Contao\Image;
use Contao\Input;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use HeimrichHannot\FieldPalette\FieldPalette;
use HeimrichHannot\FieldPalette\FieldPaletteButton;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class FieldPaletteWizard extends Widget
{
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
     * @var Collection|FieldPaletteModel|null
     */
    protected $models = null;

    protected $buttonDefaults = [];

    protected $viewMode = 0;

    /**
     * Palette table (tl_fieldpalette or custom table).
     *
     * @var mixed|null
     */
    protected $paletteTable;
    /**
     * @var ContaoFramework|object
     */
    protected $framework;
    /**
     * @var object|\Twig\Environment
     */
    protected $twig;
    /**
     * @var \HeimrichHannot\UtilsBundle\Form\FormUtil|object
     */
    protected $formUtil;

    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        Controller::loadLanguageFile(Config::get('fieldpalette_table'));
        Controller::loadLanguageFile($this->strTable);

        $this->import('Database');

        $this->dca = FieldPalette::getDca($this->strTable, $this->strTable, $this->strName);
        $this->viewMode = $this->dca['list']['viewMode'] ?: 0;
        $this->paletteTable = $this->dca['config']['table'] ?: Config::get('fieldpalette_table');

        // load custom table labels
        Controller::loadLanguageFile($this->paletteTable);

        $this->framework = System::getContainer()->get('contao.framework');
        $this->twig = System::getContainer()->get('twig');
        $this->formUtil = System::getContainer()->get('huh.utils.form');
    }

    /**
     * Generate the widget and return it as string.
     *
     * @return string
     */
    public function generate()
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
            'fieldpaletteKey' => FieldPalette::$strPaletteRequestKey,
            'popup' => true,
            'syncId' => 'ctrl_'.$this->strId,
            'pfield' => $this->strId,
        ];

        $values = [];

        if ($this->models) {
            $values = $this->models->fetchEach('id');
        }

        return $this->twig->render($this->getViewTemplate('wizard'), [
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
     * @param string $table
     *
     * @return FieldPaletteModel
     *
     * @codeCoverageIgnore
     */
    public function getModelInstance(string $table = '')
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
     * @param string $table
     * @param array  $module
     *
     * @return DC_Table
     *
     * @codeCoverageIgnore
     */
    public function getDcTableInstance(string $table, array $module = [])
    {
        return new DC_Table($table, $module);
    }

    protected function getViewTemplate(string $type)
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

        return '@HeimrichHannotContaoFieldpalette/'.$type.'/fieldpalette_'.$type.'_'.$mode.'.html.twig';
    }

    protected function generateListView()
    {
        $items = [];
        $i = 0;

        if ($this->models) {
            while ($this->models->next()) {
                $current = $this->models->current();

                if (0 === $current->tstamp) {
                    continue;
                }
                $items[] = $this->generateListItem($current, ++$i);
            }
        }

        return $this->twig->render($this->getViewTemplate('list'), [
            'id' => $this->strId,
            'image' => $this->framework->getAdapter(Image::class)->getHtml('loading.gif', '', 'class="tl_fielpalette_indicator_icon"'),
            'labelIcon' => 'bundles/heimrichhannotcontaofieldpalette/img/fieldpalette.png',
            'label' => $this->strLabel,
            'mandatory' => $this->mandatory,
            'items' => $items,
            'sortable' => !$this->dca['config']['notSortable'],
        ]);
    }

    /**
     * @param FieldPaletteModel $model
     * @param int               $index
     *
     * @return string
     */
    protected function generateListItem($model, $index)
    {
        return $this->twig->render($this->getViewTemplate('item'), [
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
        $system = $this->framework->getAdapter(System::class);

        $protected = false;
        $showFields = $this->dca['list']['label']['fields'];

        $dc = $this->getDcTableInstance($this->paletteTable);
        $dc->id = $model->id;
        $dc->activeRecord = $model;

        foreach ($showFields as $k => $v) {
            $value = $model->{$v};

            // Call load_callback
            if (is_array($this->dca['fields'][$v]['load_callback'])) {
                foreach ($this->dca['fields'][$v]['load_callback'] as $callback) {
                    if (is_array($callback)) {
                        $value = $system::importStatic($callback[0])->{$callback[1]}($value, $dc);
                    } elseif (is_callable($callback)) {
                        $value = $callback($value, $dc);
                    }
                }
            }
            $args[$k] = $this->formUtil->prepareSpecialValueForOutput($v, $value, $dc);
        }

        $label = vsprintf(
            ((strlen($this->dca['list']['label']['format'])) ? $this->dca['list']['label']['format'] : '%s'),
            $args
        );

        // Shorten the label if it is too long
        if ($this->dca['list']['label']['maxCharacters'] > 0
            && $this->dca['list']['label']['maxCharacters'] < utf8_strlen(
                strip_tags($label)
            )
        ) {
            $label = trim(StringUtil::substrHtml($label, $this->dca['list']['label']['maxCharacters'])).' …';
        }

        // Call the label_callback ($row, $label, $this)
        if (is_array($this->dca['list']['label']['label_callback'])) {
            $strClass = $this->dca['list']['label']['label_callback'][0];
            $strMethod = $this->dca['list']['label']['label_callback'][1];

            $this->import($strClass);

            return $this->{$strClass}->{$strMethod}($model->row(), $label, $this, $folderAttribute, false, $protected);
        } elseif (is_callable($this->dca['list']['label']['label_callback'])) {
            return $this->dca['list']['label']['label_callback']($model->row(), $label, $this, $folderAttribute, false, $protected);
        }

        return $label;
    }

    /**
     * Compile buttons from the table configuration array and return them as HTML.
     *
     * @param array  $arrRow
     * @param string $strTable
     * @param array  $arrRootIds
     * @param bool   $blnCircularReference
     * @param array  $arrChildRecordIds
     * @param string $strPrevious
     * @param string $strNext
     *
     * @return string
     */
    protected function generateButtons(
        $objRow,
        $arrRootIds = [],
        $blnCircularReference = false,
        $arrChildRecordIds = null,
        $strPrevious = null,
        $strNext = null
    ) {
        if (empty($this->dca['list']['operations'])) {
            return '';
        }

        $return = '';

        $dc = new DC_Table($this->paletteTable);
        $dc->id = $this->currentRecord;
        $dc->activeRecord = $objRow;

        foreach ($this->dca['list']['operations'] as $k => $v) {
            $v = is_array($v) ? $v : [$v];
            $id = specialchars(rawurldecode($objRow->id));

            $label = $v['label'][0] ?: $k;
            $title = sprintf($v['label'][1] ?: $k, $id);
            $attributes = ('' !== $v['attributes']) ? ltrim(sprintf($v['attributes'], $id, $id)) : '';

            $objButton = FieldPaletteButton::getInstance();
            $objButton->addOptions($this->buttonDefaults);
            $objButton->setType($k);
            $objButton->setId($objRow->id);
            $objButton->setModalTitle(
                sprintf(
                    $GLOBALS['TL_LANG']['tl_fieldpalette']['modalTitle'],
                    $GLOBALS['TL_LANG'][$this->strTable][$this->strName][0] ?: $this->strName,
                    sprintf($title, $objRow->id)
                )
            );
            $objButton->setAttributes([$attributes]);
            $objButton->setLabel(\Image::getHtml($v['icon'], $label));
            $objButton->setTitle(specialchars($title));

            // Call a custom function instead of using the default button
            if (is_array($v['button_callback'])) {
                $this->import($v['button_callback'][0]);
                $return .= $this->{$v['button_callback'][0]}->{$v['button_callback'][1]}(
                    $objRow->row(),
                    $objButton->getHref(),
                    $label,
                    $title,
                    $v['icon'],
                    $attributes,
                    $this->paletteTable,
                    $arrRootIds,
                    $arrChildRecordIds,
                    $blnCircularReference,
                    $strPrevious,
                    $strNext,
                    $dc
                );
                continue;
            } elseif (is_callable($v['button_callback'])) {
                $return .= $v['button_callback'](
                    $objRow->row(),
                    $objButton->getHref(),
                    $label,
                    $title,
                    $v['icon'],
                    $attributes,
                    $this->paletteTable,
                    $arrRootIds,
                    $arrChildRecordIds,
                    $blnCircularReference,
                    $strPrevious,
                    $strNext,
                    $dc
                );
                continue;
            }

            // Generate all buttons except "move up" and "move down" buttons
            if ('move' !== $k && 'move' !== $v) {
                $return .= $objButton->generate();
                continue;
            }

            $arrDirections = ['up', 'down'];
            $arrRootIds = is_array($arrRootIds) ? $arrRootIds : [$arrRootIds];

            foreach ($arrDirections as $dir) {
                $label = $GLOBALS['TL_LANG'][\Config::get('fieldpalette_table')][$dir][0] ?: $dir;
                $title = $GLOBALS['TL_LANG'][\Config::get('fieldpalette_table')][$dir][1] ?: $dir;

                $label = \Image::getHtml($dir.'.gif', $label);
                $href = $v['href'] ?: '&amp;act=move';

                if ('up' === $dir) {
                    $return .= ((is_numeric($strPrevious)
                            && (!in_array($objRow->id, $arrRootIds, true)
                                || empty($this->dca['list']['sorting']['root'])))
                            ? '<a href="'.$this->addToUrl(
                                $href.'&amp;id='.$objRow->id
                            ).'&amp;sid='.(int) $strPrevious.'" title="'.specialchars($title).'"'.$attributes.'>'.$label.'</a> '
                            : \Image::getHtml(
                                'up_.gif'
                            )).' ';
                    continue;
                }

                $return .= ((is_numeric($strNext)
                        && (!in_array($objRow->id, $arrRootIds, true)
                            || empty($this->dca['list']['sorting']['root'])))
                        ? '<a href="'.$this->addToUrl(
                            $href.'&amp;id='.$objRow->id
                        ).'&amp;sid='.(int) $strNext.'" title="'.specialchars($title).'"'.$attributes.'>'.$label.'</a> '
                        : \Image::getHtml(
                            'down_.gif'
                        )).' ';
            }
        }

        // Sort elements
        if (!$this->dca['config']['notSortable']) {
            $href = version_compare(VERSION, '4.0', '<') ? 'contao/main.php' : 'contao';
            $href .= '?do='.\Input::get('do');
            $href .= '&amp;table='.$this->paletteTable;
            $href .= '&amp;id='.$objRow->id;
            $href .= '&amp;'.FieldPalette::$strParentTableRequestKey.'='.$this->strTable;
            $href .= '&amp;'.FieldPalette::$strPaletteRequestKey.'='.$this->strName;
            $href .= '&amp;rt='.\RequestToken::get();

            $return .= ' '.\Image::getHtml(
                    'drag.gif',
                    '',
                    'class="drag-handle" title="'.sprintf($GLOBALS['TL_LANG'][$this->strTable]['cut'][1], $objRow->id).'" data-href="'.$href
                    .'" data-id="'.$objRow->id.'" data-pid="'.$objRow->pid.'"'
                );
        }

        return trim($return);
    }

    protected function generateGlobalButtons()
    {
        $objCreateButton = FieldPaletteButton::getInstance();
        $objCreateButton->addOptions($this->buttonDefaults);
        $objCreateButton->setType('create');
        $objCreateButton->setModalTitle(
            sprintf(
                $GLOBALS['TL_LANG']['tl_fieldpalette']['modalTitle'],
                $GLOBALS['TL_LANG'][$this->strTable][$this->strName][0] ?: $this->strName,
                $GLOBALS['TL_LANG']['tl_fieldpalette']['new'][1]
            )
        );
        $objCreateButton->setLabel($GLOBALS['TL_LANG']['tl_fieldpalette']['new'][0]);
        $objCreateButton->setTitle($GLOBALS['TL_LANG']['tl_fieldpalette']['new'][0]);

        return $objCreateButton->generate();
    }

    /**
     * Delete all incomplete and unrelated records.
     */
    protected function reviseTable()
    {
        $reload = false;
        $ptable = $this->dca['config']['ptable'];
        $ctable = $this->dca['config']['ctable'];

        $new_records = $this->Session->get('new_records');

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['reviseTable']) && is_array($GLOBALS['TL_HOOKS']['reviseTable'])) {
            foreach ($GLOBALS['TL_HOOKS']['reviseTable'] as $callback) {
                $status = null;

                if (is_array($callback)) {
                    $this->import($callback[0]);
                    $status = $this->{$callback[0]}->{$callback[1]}($this->strTable, $new_records[$this->strTable], $ptable, $ctable);
                } elseif (is_callable($callback)) {
                    $status = $callback($this->strTable, $new_records[$this->strTable], $ptable, $ctable);
                }

                if (true === $status) {
                    $reload = true;
                }
            }
        }

        // Delete all new but incomplete fieldpalette records (tstamp=0)
        if (!empty($new_records[$this->paletteTable]) && is_array($new_records[$this->paletteTable])) {
            $objStmt = $this->Database->prepare(
                'DELETE FROM '.$this->paletteTable.' WHERE id IN('.implode(
                    ',',
                    array_map(
                        'intval',
                        $new_records[$this->paletteTable]
                    )
                ).') AND tstamp=0 AND (? IS NULL OR id != ?)'
            )->execute($this->activeRecord->id, $this->activeRecord->id);

            if ($objStmt->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete all fieldpalette records whose child record isn't existing
        if ('' !== $ptable) {
            if ($this->dca['config']['dynamicPtable']) {
                $objStmt = $this->Database->execute(
                    'DELETE FROM '.$this->paletteTable." WHERE ptable='".$ptable."' AND NOT EXISTS (SELECT * FROM (SELECT * FROM "
                    .$ptable.') AS fpp WHERE '.$this->paletteTable.'.pid = fpp.id)'
                );
            } else {
                $objStmt = $this->Database->execute(
                    'DELETE FROM '.$this->paletteTable.' WHERE NOT EXISTS '.'(SELECT * FROM (SELECT * FROM '.$ptable.') AS fpp WHERE '
                    .\Config::get(
                        'fieldpalette_table'
                    ).'.pid = fpp.id)'
                );
            }

            if ($objStmt->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete all records of the child table that are not related to the current table
        if (!empty($ctable) && is_array($ctable)) {
            foreach ($ctable as $v) {
                if ('' !== $v) {
                    // Load the DCA configuration so we can check for "dynamicPtable"
                    if (!isset($GLOBALS['loadDataContainer'][$v])) {
                        \Controller::loadDataContainer($v);
                    }

                    if ($GLOBALS['TL_DCA'][$v]['config']['dynamicPtable']) {
                        $objStmt = $this->Database->execute(
                            "DELETE FROM $v WHERE ptable='".$this->paletteTable."' AND NOT EXISTS (SELECT * FROM ".'(SELECT * FROM '
                            .$this->paletteTable.") AS fp WHERE $v.pid = fp.id)"
                        );
                    } else {
                        $objStmt = $this->Database->execute(
                            "DELETE FROM $v WHERE NOT EXISTS (SELECT * FROM (SELECT * FROM ".$this->paletteTable
                            .") AS fp WHERE $v.pid = fp.id)"
                        );
                    }

                    if ($objStmt->affectedRows > 0) {
                        $reload = true;
                    }
                }
            }
        }

        // Reload the page
        if ($reload) {
            if (\Environment::get('isAjaxRequest')) {
                return;
            }

            \Controller::reload();
        }
    }
}
