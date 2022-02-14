<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_fieldpalette'] = [
    'config' => [
        'dataContainer' => 'Table',
        'ptable' => '',
        'dynamicPtable' => true,
        'fieldpalette' => true, // required to grant access for back end modules
        'enableVersioning' => true,
        'notCopyable' => true,
        'onload_callback' => [
            'setDateAdded' => ['huh.utils.dca', 'setDateAdded', true],
            'setReferrerOnSaveAndClose' => ['huh.fieldpalette.listener.callback', 'setReferrerOnSaveAndClose'],
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid,ptable,pfield,published,sorting' => 'index',
            ],
        ],
        'oncreate_callback' => [
            ['huh.fieldpalette.listener.callback', 'setTable'],
        ],
        'onsubmit_callback' => [
            ['huh.fieldpalette.listener.callback', 'updateParentFieldOnSubmit'],
        ],
        'oncut_callback' => [
            ['huh.fieldpalette.listener.callback', 'updateParentFieldOnCut'],
        ],
        'ondelete_callback' => [
            ['huh.fieldpalette.listener.callback', 'updateParentFieldonDelete'],
        ],
    ],
    'list' => [
        'label' => [
            'fields' => ['pid', 'ptable', 'pfield'],
            'format' => '%s <span style="color:#b3b3b3;padding-left:3px">[%s:%s]</span>',
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null)
                    .'\'))return false;FieldPaletteBackend.deleteFieldPaletteEntry(this,%s);return false;"',
            ],
            'toggle' => [
                'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['toggle'],
                'icon' => 'visible.gif',
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => ['huh.fieldpalette.listener.callback', 'toggleIcon'],
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => ['published'],
    ],
    'subpalettes' => [
        'published' => 'start,stop',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'ptable' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'pfield' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'sorting' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'dateAdded' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['dateAdded'],
            'sorting' => true,
            'flag' => 6,
            'eval' => ['rgxp' => 'datim', 'doNotCopy' => true],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published' => [
            'exclude' => true,
            'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['published'],
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true, 'doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'start' => [
            'exclude' => true,
            'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['start'],
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
        'stop' => [
            'exclude' => true,
            'label' => &$GLOBALS['TL_LANG']['tl_fieldpalette']['stop'],
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
    ],
];
