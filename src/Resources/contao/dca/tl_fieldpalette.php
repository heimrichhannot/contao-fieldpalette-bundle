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

$GLOBALS['TL_DCA']['tl_fieldpalette'] = [
    'config'      => [
        'dataContainer'     => 'Table',
        'ptable'            => '',
        'dynamicPtable'     => true,
        'fieldpalette'      => true, // required to grant access for back end modules
        'enableVersioning'  => true,
        'notCopyable'       => true,
        'onload_callback'   => [
            'setDateAdded'              => ['HeimrichHannot\HastePlus\Utilities', 'setDateAdded', true],
            'setReferrerOnSaveAndClose' => ['tl_fieldpalette', 'setReferrerOnSaveAndClose'],
        ],
        'sql'               => [
            'keys' => [
                'id'                                  => 'primary',
                'pid,ptable,pfield,published,sorting' => 'index',
            ],
        ],
        'oncreate_callback' => [
            ['tl_fieldpalette', 'setTable'],
        ],
        'onsubmit_callback' => [
            ['tl_fieldpalette', 'updateParentFieldOnSubmit'],
        ],
        'oncut_callback'    => [
            ['tl_fieldpalette', 'updateParentFieldOnCut'],
        ],
        'ondelete_callback' => [
            ['tl_fieldpalette', 'updateParentFieldonDelete'],
        ],
    ],
    'list'        => [
        'label'      => [
            'fields' => ['pid', 'ptable', 'pfield'],
            'format' => '%s <span style="color:#b3b3b3;padding-left:3px">[%s:%s]</span>',
        ],
        'operations' => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_fieldpalette']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm']
                    . '\'))return false;FieldPaletteBackend.deleteFieldPaletteEntry(this,%s);return false;"',
            ],
            'toggle' => [
                'label'           => &$GLOBALS['TL_LANG']['tl_fieldpalette']['toggle'],
                'icon'            => 'visible.gif',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => ['tl_fieldpalette', 'toggleIcon'],
            ],
            'show'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif',
            ],
        ],
    ],
    'palettes'    => [
        '__selector__' => ['published'],
    ],
    'subpalettes' => [
        'published' => 'start,stop',
    ],
    'fields'      => [
        'id'        => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'pid'       => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'ptable'    => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'pfield'    => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'sorting'   => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'    => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'dateAdded' => [
            'label'   => &$GLOBALS['TL_LANG']['MSC']['dateAdded'],
            'sorting' => true,
            'flag'    => 6,
            'eval'    => ['rgxp' => 'datim', 'doNotCopy' => true],
            'sql'     => "int(10) unsigned NOT NULL default '0'",
        ],
        'published' => [
            'exclude'   => true,
            'label'     => &$GLOBALS['TL_LANG']['tl_fieldpalette']['published'],
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'doNotCopy' => true],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'start'     => [
            'exclude'   => true,
            'label'     => &$GLOBALS['TL_LANG']['tl_fieldpalette']['start'],
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
        'stop'      => [
            'exclude'   => true,
            'label'     => &$GLOBALS['TL_LANG']['tl_fieldpalette']['stop'],
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
    ],
];

class tl_fieldpalette extends Backend
{
    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * Check permissions to edit table tl_fieldpalette
     */
    public function checkPermission()
    {
        // TODO
    }

    public function setReferrerOnSaveAndClose(\DataContainer $dc)
    {
        if (!isset($_POST['saveNclose'])) {
            return;
        }

        if (preg_match('/^contao/', TL_SCRIPT)) {
            $key = \Input::get('popup') ? 'popupReferer' : 'referer';
        }

        if ($key !== null) {
            $session = \Session::getInstance()->getData();

            if (!is_array($session[$key]) || !is_array($session[$key][TL_REFERER_ID])) {
                $session[$key][TL_REFERER_ID]['last'] = '';
            }

            while (count($session[$key]) >= 25) {
                array_shift($session[$key]);
            }

            $ref = \Input::get('ref');

            if ($ref != '' && isset($session[$key][$ref])) {
                if (!isset($session[$key][TL_REFERER_ID])) {
                    $session[$key][TL_REFERER_ID] = [];
                }

                $session[$key][TL_REFERER_ID]         = array_merge($session[$key][TL_REFERER_ID], $session[$key][$ref]);
                $session[$key][TL_REFERER_ID]['last'] = $session[$key][$ref]['current'];
            } elseif (count($session[$key]) > 1) {
                $session[$key][TL_REFERER_ID] = end($session[$key]);
            }

            $strUrl = substr(\Environment::get('requestUri'), strlen(TL_PATH) + 1);

            $session[$key][TL_REFERER_ID]['current'] = $strUrl;
            $session[$key][TL_REFERER_ID]['last']    = $strUrl;

            \Session::getInstance()->setData($session);
        }
    }

    public function setTable($strTable, $insertID, $arrSet, DataContainer $dc)
    {
        \Controller::loadDataContainer($strTable);

        if (!$GLOBALS['TL_DCA'][$strTable]['config']['fieldpalette']) {
            return;
        }


        $strFieldPalette = \HeimrichHannot\FieldPalette\FieldPalette::getPaletteFromRequest();

        $objModel        = \HeimrichHannot\FieldPalette\FieldPaletteModel::setTable($strTable)->findByPk($insertID);

        // if are within nested fieldpalettes set parent item tstamp
        if ($arrSet['ptable'] == 'tl_fieldpalette') {
            $objParent = \HeimrichHannot\FieldPalette\FieldPaletteModel::findByPk($objModel->pid);

            if ($objParent !== null) {
                $objParent->tstamp = time();
                $objParent->save();
            }
        }

        // set fieldpalette field
        $objModel->pfield = $strFieldPalette;
        $objModel->save();
    }

    /**
     * Return the "toggle visibility" button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     * @param string $table
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes, $table)
    {
        if (strlen(Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') === '1'), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->hasAccess($table . '::published', 'alexf')) {
            return '';
        }

        $href = \Haste\Util\Url::addQueryString('tid=' . $row['id'], $href);
        $href = \Haste\Util\Url::addQueryString('state=' . ($row['published'] ? '' : 1), $href);

        if (!$row['published']) {
            $icon = 'invisible.gif';
        }


        return '<a href="' . $href . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml(
                $icon,
                $label,
                'data-state="' . ($row['published'] ? 1 : 0) . '"'
            ) . '</a> ';
    }


    /**
     * Disable/enable a user group
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param DataContainer $dc
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null)
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $intId; // see #8043
        }

        $this->checkPermission();

        // Check the field access
        if (!$this->User->hasAccess($dc->table . '::published', 'alexf')) {
            $this->log('Not enough permissions to publish/unpublish fieldpalette item ID "' . $intId . '"', __METHOD__, TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        $objVersions = new Versions($dc->table, $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA'][$dc->table]['fields']['published']['save_callback'] as $callback) {
                if (is_array($callback)) {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                } elseif (is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        // Update the database
        $this->Database->prepare(
            "UPDATE " . $dc->table . " SET tstamp=" . time() . ", published='" . ($blnVisible ? '1' : '') . "' WHERE id=?"
        )->execute($intId);

        $objVersions->create();
        $this->log(
            'A new version of record "' . $dc->table . '.id=' . $intId . '" has been created' . $this->getParentEntries(
                $dc->table,
                $intId
            ),
            __METHOD__,
            TL_GENERAL
        );
    }

    public function updateParentFieldOnSubmit(DataContainer $dc)
    {
        $objCurrentRecord = \HeimrichHannot\FieldPalette\FieldPaletteModel::findByPk($dc->id);

        if ($objCurrentRecord === null) {
            return false;
        }

        $this->updateParentField($objCurrentRecord);
    }

    public function updateParentFieldOnCut(DataContainer $dc)
    {
        $objCurrentRecord = \HeimrichHannot\FieldPalette\FieldPaletteModel::findByPk($dc->id);

        if ($objCurrentRecord === null) {
            return false;
        }

        $this->updateParentField($objCurrentRecord);
    }

    public function updateParentFieldOnDelete(DataContainer $dc, $undoID)
    {
        $objCurrentRecord = \HeimrichHannot\FieldPalette\FieldPaletteModel::findByPk($dc->id);

        if ($objCurrentRecord === null) {
            return false;
        }

        $this->updateParentField($objCurrentRecord, $objCurrentRecord->id);
    }

    /**
     * Update the parent field with its tl_fieldpalette item ids
     *
     * @param DataContainer $dc
     *
     * @return bool
     */
    public function updateParentField($objCurrentRecord, $intDelete = 0)
    {
        $strClass = \Model::getClassFromTable($objCurrentRecord->ptable);

        if (!class_exists($strClass)) {
            return false;
        }

        /** @var \Model $strClass */
        $objParent = $strClass::findByPk($objCurrentRecord->pid);

        if ($objParent === null) {
            return false;
        }

        $objItems = \HeimrichHannot\FieldPalette\FieldPaletteModel::findByPidAndTableAndField(
            $objCurrentRecord->pid,
            $objCurrentRecord->ptable,
            $objCurrentRecord->pfield
        );

        $varValue = [];

        if ($objItems !== null) {
            $varValue = $objItems->fetchEach('id');

            // ondelete_callback support
            if ($intDelete > 0 && ($key = array_search($intDelete, $varValue)) !== false) {
                unset($varValue[$key]);
            }
        }

        if (empty($varValue)) {
            \Controller::loadDataContainer($objCurrentRecord->ptable);

            $arrData = $GLOBALS['TL_DCA'][$objCurrentRecord->ptable]['fields'][$objCurrentRecord->pfield];

            if (isset($arrData['sql'])) {
                $varValue = \Widget::getEmptyValueByFieldType($arrData['sql']);
            }
        }

        $objParent->{$objCurrentRecord->pfield} = $varValue;
        $objParent->save();
    }

}
