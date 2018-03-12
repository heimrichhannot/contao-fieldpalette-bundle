<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package fieldpalette
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\FieldPalette;


use Contao\DC_Table;
use HeimrichHannot\Haste\Util\FormSubmission;

class FieldPaletteWizard extends \Widget
{
    /**
     * Submit user input
     *
     * @var boolean
     */
    protected $blnSubmitInput = true;

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'be_fieldpalette';

    protected $arrDca = [];

    protected $objModels;

    protected $arrButtonDefaults = [];

    protected $viewMode = 0;

    /**
     * Palette table (tl_fieldpalette or custom table)
     * @var mixed|null
     */
    protected $paletteTable;

    public function __construct($arrAttributes = null)
    {
        parent::__construct($arrAttributes);

        \Controller::loadLanguageFile(\Config::get('fieldpalette_table'));
        \Controller::loadLanguageFile($this->strTable);

        $this->import('Database');

        $this->arrDca       = \HeimrichHannot\FieldPalette\FieldPalette::getDca($this->strTable, $this->strTable, $this->strName);
        $this->viewMode     = $this->arrDca['list']['viewMode'] ?: 0;
        $this->paletteTable = $this->arrDca['config']['table'] ?: \Config::get('fieldpalette_table');

        // load custom table labels
        \Controller::loadLanguageFile($this->paletteTable);
    }


    /**
     * Generate the widget and return it as string
     *
     * @return string
     */
    public function generate()
    {
        $this->reviseTable();

        $this->objModels = FieldPaletteModel::setTable($this->paletteTable)->findByPidAndTableAndField($this->currentRecord, $this->strTable, $this->strName);

        $this->arrButtonDefaults = [
            'do'              => \Input::get('do'),
            'ptable'          => $this->strTable,
            'table'           => $this->paletteTable,
            'pid'             => $this->currentRecord,
            'fieldpalette'    => $this->strName,
            'fieldpaletteKey' => FieldPalette::$strPaletteRequestKey,
            'popup'           => true,
            'syncId'          => 'ctrl_' . $this->strId,
            'pfield'          => $this->strId,
        ];

        $objT = new \FrontendTemplate($this->getViewTemplate('fieldpalette_wizard'));

        $objT->buttons  = $this->generateGlobalButtons();
        $objT->listView = $this->generateListView();
        $objT->strId    = $this->strId;

        $varValue = [];

        if ($this->objModels !== null) {
            $varValue = $this->objModels->fetchEach('id');
        }

        $objT->value   = $varValue;
        $objT->strName = $this->strName;

        return $objT->parse();
    }

    protected function getViewTemplate($strPrefix)
    {
        $strSuffix = '';

        switch ($this->viewMode) {
            default:
            case 0:
                $strSuffix = 'table';
                break;
            case 1:
                $strSuffix = 'default';
                break;
        }

        return $strPrefix . '_' . $strSuffix;
    }

    protected function generateListView()
    {
        $objT            = new \FrontendTemplate($this->getViewTemplate('fieldpalette_listview'));
        $objT->label     = $this->strLabel;
        $objT->strId     = $this->strId;
        $objT->empty     = $GLOBALS['TL_LANG']['tl_fieldpalette']['emptyList'];
        $objT->sortable  = !$this->arrDca['config']['notSortable'];
        $objT->labelIcon = '<img src="system/modules/fieldpalette/assets/img/fieldpalette.png" width="16" height="16" alt="">';
        $objT->mandatory = $this->mandatory;

        $arrItems = [];
        $i        = 0;

        if ($this->objModels !== null) {
            while ($this->objModels->next()) {
                $objModel = $this->objModels->current();

                if ($objModel->tstamp == 0) {
                    continue;
                }

                $arrItems[] = $this->generateListItem($objModel, ++$i);
            }
        }

        $objT->items = $arrItems;

        return $objT->parse();
    }

    protected function generateItemLabel($objRow, $folderAttribute)
    {
        $blnProtected = false;
        $showFields   = $this->arrDca['list']['label']['fields'];

        $dc               = new DC_Table($this->paletteTable);
        $dc->id           = $objRow->id;
        $dc->activeRecord = $objRow;

        foreach ($showFields as $k => $v) {
            $varValue = $objRow->{$v};

            // Call load_callback
            if (is_array($this->arrDca['fields'][$v]['load_callback'])) {
                foreach ($this->arrDca['fields'][$v]['load_callback'] as $callback) {
                    if (is_array($callback)) {
                        $varValue = \System::importStatic($callback[0])->{$callback[1]}($varValue, $dc);
                    } elseif (is_callable($callback)) {
                        $varValue = $callback($varValue, $dc);
                    }
                }
            }

            $args[$k] = FormSubmission::prepareSpecialValueForPrint($varValue, $this->arrDca['fields'][$v], $this->strTable, $dc);
        }

        $label = vsprintf(((strlen($this->arrDca['list']['label']['format'])) ? $this->arrDca['list']['label']['format'] : '%s'), $args);

        // Shorten the label if it is too long
        if ($this->arrDca['list']['label']['maxCharacters'] > 0
            && $this->arrDca['list']['label']['maxCharacters'] < utf8_strlen(
                strip_tags($label)
            )
        ) {
            $label = trim(\StringUtil::substrHtml($label, $this->arrDca['list']['label']['maxCharacters'])) . ' …';
        }

        // Call the label_callback ($row, $label, $this)
        if (is_array($this->arrDca['list']['label']['label_callback'])) {
            $strClass  = $this->arrDca['list']['label']['label_callback'][0];
            $strMethod = $this->arrDca['list']['label']['label_callback'][1];

            $this->import($strClass);

            return $this->{$strClass}->{$strMethod}($objRow->row(), $label, $this, $folderAttribute, false, $blnProtected);
        } elseif (is_callable($this->arrDca['list']['label']['label_callback'])) {
            return $this->arrDca['list']['label']['label_callback']($objRow->row(), $label, $this, $folderAttribute, false, $blnProtected);
        } else {
            return $label;
        }

        return $label;

    }

    /**
     * Compile buttons from the table configuration array and return them as HTML
     *
     * @param array $arrRow
     * @param string $strTable
     * @param array $arrRootIds
     * @param boolean $blnCircularReference
     * @param array $arrChildRecordIds
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
        if (empty($this->arrDca['list']['operations'])) {
            return '';
        }

        $return = '';

        $dc               = new DC_Table($this->paletteTable);
        $dc->id           = $this->currentRecord;
        $dc->activeRecord = $objRow;

        foreach ($this->arrDca['list']['operations'] as $k => $v) {
            $v  = is_array($v) ? $v : [$v];
            $id = specialchars(rawurldecode($objRow->id));

            $label      = $v['label'][0] ?: $k;
            $title      = sprintf($v['label'][1] ?: $k, $id);
            $attributes = ($v['attributes'] != '') ? ltrim(sprintf($v['attributes'], $id, $id)) : '';

            $objButton = FieldPaletteButton::getInstance();
            $objButton->addOptions($this->arrButtonDefaults);
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
            if ($k != 'move' && $v != 'move') {
                $return .= $objButton->generate();
                continue;
            }

            $arrDirections = ['up', 'down'];
            $arrRootIds    = is_array($arrRootIds) ? $arrRootIds : [$arrRootIds];

            foreach ($arrDirections as $dir) {
                $label = $GLOBALS['TL_LANG'][\Config::get('fieldpalette_table')][$dir][0] ?: $dir;
                $title = $GLOBALS['TL_LANG'][\Config::get('fieldpalette_table')][$dir][1] ?: $dir;

                $label = \Image::getHtml($dir . '.gif', $label);
                $href  = $v['href'] ?: '&amp;act=move';

                if ($dir == 'up') {
                    $return .= ((is_numeric($strPrevious)
                            && (!in_array($objRow->id, $arrRootIds)
                                || empty($this->arrDca['list']['sorting']['root'])))
                            ? '<a href="' . $this->addToUrl(
                                $href . '&amp;id=' . $objRow->id
                            ) . '&amp;sid=' . intval($strPrevious) . '" title="' . specialchars($title) . '"' . $attributes . '>' . $label . '</a> '
                            : \Image::getHtml(
                                'up_.gif'
                            )) . ' ';
                    continue;
                }

                $return .= ((is_numeric($strNext)
                        && (!in_array($objRow->id, $arrRootIds)
                            || empty($this->arrDca['list']['sorting']['root'])))
                        ? '<a href="' . $this->addToUrl(
                            $href . '&amp;id=' . $objRow->id
                        ) . '&amp;sid=' . intval($strNext) . '" title="' . specialchars($title) . '"' . $attributes . '>' . $label . '</a> '
                        : \Image::getHtml(
                            'down_.gif'
                        )) . ' ';
            }

        }

        // Sort elements
        if (!$this->arrDca['config']['notSortable']) {
            $href = version_compare(VERSION, '4.0', '<') ? 'contao/main.php' : 'contao';;
            $href .= '?do=' . \Input::get('do');
            $href .= '&amp;table=' . $this->paletteTable;
            $href .= '&amp;id=' . $objRow->id;
            $href .= '&amp;' . FieldPalette::$strParentTableRequestKey . '=' . $this->strTable;
            $href .= '&amp;' . FieldPalette::$strPaletteRequestKey . '=' . $this->strName;
            $href .= '&amp;rt=' . \RequestToken::get();

            $return .= ' ' . \Image::getHtml(
                    'drag.gif',
                    '',
                    'class="drag-handle" title="' . sprintf($GLOBALS['TL_LANG'][$this->strTable]['cut'][1], $objRow->id) . '" data-href="' . $href
                    . '" data-id="' . $objRow->id . '" data-pid="' . $objRow->pid . '"'
                );
        }

        return trim($return);
    }

    protected function generateListItem($objRow, $rowIndex)
    {
        $objT = new \FrontendTemplate($this->getViewTemplate('fieldpalette_item'));
        $objT->setData($objRow->row());

        $objT->folderAttribute = '';
        $objT->label           = $this->generateItemLabel($objRow, $objT->folderAttribute);
        $objT->buttons         = $this->generateButtons($objRow);
        $objT->strId           = sprintf('%s_%s_%s', $objRow->ptable, $objRow->pfield, $objRow->id);
        $objT->rowIndex        = $rowIndex;

        return $objT->parse();
    }

    protected function generateGlobalButtons()
    {
        $objCreateButton = FieldPaletteButton::getInstance();
        $objCreateButton->addOptions($this->arrButtonDefaults);
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
     * Delete all incomplete and unrelated records
     */
    protected function reviseTable()
    {
        $reload = false;
        $ptable = $this->arrDca['config']['ptable'];
        $ctable = $this->arrDca['config']['ctable'];

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

                if ($status === true) {
                    $reload = true;
                }
            }
        }

        // Delete all new but incomplete fieldpalette records (tstamp=0)
        if (!empty($new_records[$this->paletteTable]) && is_array($new_records[$this->paletteTable])) {
            $objStmt = $this->Database->prepare(
                "DELETE FROM " . $this->paletteTable . " WHERE id IN(" . implode(
                    ',',
                    array_map(
                        'intval',
                        $new_records[$this->paletteTable]
                    )
                ) . ") AND tstamp=0 AND (? IS NULL OR id != ?)"
            )->execute($this->activeRecord->id, $this->activeRecord->id);

            if ($objStmt->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete all fieldpalette records whose child record isn't existing
        if ($ptable != '') {
            if ($this->arrDca['config']['dynamicPtable']) {
                $objStmt = $this->Database->execute(
                    "DELETE FROM " . $this->paletteTable . " WHERE ptable='" . $ptable . "' AND NOT EXISTS (SELECT * FROM (SELECT * FROM "
                    . $ptable . ") AS fpp WHERE " . $this->paletteTable . ".pid = fpp.id)"
                );
            } else {
                $objStmt = $this->Database->execute(
                    "DELETE FROM " . $this->paletteTable . " WHERE NOT EXISTS " . "(SELECT * FROM (SELECT * FROM " . $ptable . ") AS fpp WHERE "
                    . \Config::get(
                        'fieldpalette_table'
                    ) . ".pid = fpp.id)"
                );
            }

            if ($objStmt->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete all records of the child table that are not related to the current table
        if (!empty($ctable) && is_array($ctable)) {
            foreach ($ctable as $v) {
                if ($v != '') {
                    // Load the DCA configuration so we can check for "dynamicPtable"
                    if (!isset($GLOBALS['loadDataContainer'][$v])) {
                        \Controller::loadDataContainer($v);
                    }

                    if ($GLOBALS['TL_DCA'][$v]['config']['dynamicPtable']) {
                        $objStmt = $this->Database->execute(
                            "DELETE FROM $v WHERE ptable='" . $this->paletteTable . "' AND NOT EXISTS (SELECT * FROM " . "(SELECT * FROM "
                            . $this->paletteTable . ") AS fp WHERE $v.pid = fp.id)"
                        );
                    } else {
                        $objStmt = $this->Database->execute(
                            "DELETE FROM $v WHERE NOT EXISTS (SELECT * FROM (SELECT * FROM " . $this->paletteTable
                            . ") AS fp WHERE $v.pid = fp.id)"
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


