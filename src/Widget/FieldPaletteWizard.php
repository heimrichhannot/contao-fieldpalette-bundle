<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Widget;

use Contao\Config;
use Contao\Controller;
use Contao\DC_Table;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Model\Collection;
use Contao\System;
use Contao\Widget;
use HeimrichHannot\FieldPalette\FieldPalette;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\Haste\Util\FormSubmission;

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
     * @var \Contao\CoreBundle\Framework\ContaoFramework|object
     */
    protected $framework;

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
    }

    /**
     * Generate the widget and return it as string.
     *
     * @return string
     */
    public function generate()
    {
        $this->reviseTable();
        /**
         * @var FieldPaletteModel
         */
        $model = $this->framework->getAdapter(FieldPaletteModel::class);
        $this->models = $model->setTable($this->paletteTable)->findByPidAndTableAndField($this->currentRecord, $this->strTable, $this->strName);

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

        $template = new FrontendTemplate($this->getViewTemplate('fieldpalette_wizard'));

        $template->buttons = $this->generateGlobalButtons();
        $template->listView = $this->generateListView();
        $template->strId = $this->strId;

        $value = [];

        if ($this->models) {
            $value = $this->models->fetchEach('id');
        }

        $template->value = $value;
        $template->strName = $this->strName;

        return $template->parse();
    }

    protected function getViewTemplate(string $prefix)
    {
        switch ($this->viewMode) {
            default:
            case 0:
                $suffix = 'table';
                break;
            case 1:
                $suffix = 'default';
                break;
        }

        return $prefix.'_'.$suffix;
    }

    protected function generateListView()
    {
        $template = new FrontendTemplate($this->getViewTemplate('fieldpalette_listview'));
        $template->label = $this->strLabel;
        $template->strId = $this->strId;
        $template->empty = $GLOBALS['TL_LANG']['tl_fieldpalette']['emptyList'];
        $template->sortable = !$this->dca['config']['notSortable'];
        $template->labelIcon = 'bundles/heimrichhannotcontaofieldpalette/img/fieldpalette.png';
        $template->mandatory = $this->mandatory;

        $items = [];
        $i = 0;

        if (null !== $this->models) {
            while ($this->models->next()) {
                $current = $this->models->current();

                if (0 === $current->tstamp) {
                    continue;
                }

                $items[] = $this->generateListItem($current, ++$i);
            }
        }

        $template->items = $items;

        return $template->parse();
    }

    protected function generateListItem($objRow, $rowIndex)
    {
        $template = new FrontendTemplate($this->getViewTemplate('fieldpalette_item'));
        $template->setData($objRow->row());

        $template->folderAttribute = '';
        $template->label = $this->generateItemLabel($objRow, $template->folderAttribute);
        $template->buttons = $this->generateButtons($objRow);
        $template->strId = sprintf('%s_%s_%s', $objRow->ptable, $objRow->pfield, $objRow->id);
        $template->rowIndex = $rowIndex;

        $twig = System::getContainer()->get('twig');

        return $template->parse();
    }

    protected function generateItemLabel($objRow, $folderAttribute)
    {
        $blnProtected = false;
        $showFields = $this->dca['list']['label']['fields'];

        $dc = new DC_Table($this->paletteTable);
        $dc->id = $objRow->id;
        $dc->activeRecord = $objRow;

        foreach ($showFields as $k => $v) {
            $varValue = $objRow->{$v};

            // Call load_callback
            if (is_array($this->dca['fields'][$v]['load_callback'])) {
                foreach ($this->dca['fields'][$v]['load_callback'] as $callback) {
                    if (is_array($callback)) {
                        $varValue = \System::importStatic($callback[0])->{$callback[1]}($varValue, $dc);
                    } elseif (is_callable($callback)) {
                        $varValue = $callback($varValue, $dc);
                    }
                }
            }

            $args[$k] = FormSubmission::prepareSpecialValueForPrint($varValue, $this->dca['fields'][$v], $this->strTable, $dc);
        }

        $label = vsprintf(((strlen($this->dca['list']['label']['format'])) ? $this->dca['list']['label']['format'] : '%s'), $args);

        // Shorten the label if it is too long
        if ($this->dca['list']['label']['maxCharacters'] > 0
            && $this->dca['list']['label']['maxCharacters'] < utf8_strlen(
                strip_tags($label)
            )
        ) {
            $label = trim(\StringUtil::substrHtml($label, $this->dca['list']['label']['maxCharacters'])).' …';
        }

        // Call the label_callback ($row, $label, $this)
        if (is_array($this->dca['list']['label']['label_callback'])) {
            $strClass = $this->dca['list']['label']['label_callback'][0];
            $strMethod = $this->dca['list']['label']['label_callback'][1];

            $this->import($strClass);

            return $this->{$strClass}->{$strMethod}($objRow->row(), $label, $this, $folderAttribute, false, $blnProtected);
        } elseif (is_callable($this->dca['list']['label']['label_callback'])) {
            return $this->dca['list']['label']['label_callback']($objRow->row(), $label, $this, $folderAttribute, false, $blnProtected);
        }

        return $label;

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
