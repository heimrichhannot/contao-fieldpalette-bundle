# Contao FieldPalette bundle

[![](https://img.shields.io/packagist/v/heimrichhannot/contao-fieldpalette-bundle.svg)](https://packagist.org/packages/heimrichhannot/contao-fieldpalette-bundle)
[![](https://img.shields.io/packagist/dt/heimrichhannot/contao-fieldpalette-bundle.svg)](https://packagist.org/packages/heimrichhannot/contao-fieldpalette-bundle)
[![](https://img.shields.io/travis/heimrichhannot/contao-fieldpalette-bundle/master.svg)](https://travis-ci.org/heimrichhannot/contao-fieldpalette-bundle/)
[![](https://img.shields.io/coveralls/heimrichhannot/contao-fieldpalette-bundle/master.svg)](https://coveralls.io/github/heimrichhannot/contao-fieldpalette-bundle)

FieldPalette is a contao widget similar to [MultiColumnWizard](https://github.com/menatwork/MultiColumnWizard).
Unlike MultiColumnWizard, fields are stored flatly into `tl_fieldpalette` table and synced with its parent field.

The fieldpalette configuration is based on Contao's [Data Container Arrays](https://docs.contao.org/books/api/dca/index.html).

![alt fieldpalette wizard](./docs/img/fieldpalette_wizard.jpg)
*FieldPalette Wizard - ListView*

![alt fieldpalette edit](./docs/img/fieldpalette_edit.jpg)
*FieldPalette Wizard - Edit item*

## Technical instructions

### Default Setup (`tl_fieldpalette` table)

This example shows the setup of an fieldpalette field within tl_news by using it within an subpalette. That example is available within the module [Contao News Leisure Bundle](https://github.com/heimrichhannot/contao-news-leisure-bundle).

```php
<?php
# /src/Ressource/contao/dca/tl_news.php

$dc = &$GLOBALS['TL_DCA']['tl_news'];

/**
 * Selectors
 */
$dc['palettes']['__selector__'][] = 'addVenues';

/**
 * Subpalettes
 */
$dc['subpalettes']['addVenues'] = 'venues';

/**
 * Fields
 */
$arrFields = array
(
	// venue
	'addVenues'           => array
	(
		'label'     => &$GLOBALS['TL_LANG']['tl_news']['addVenues'],
		'exclude'   => true,
		'inputType' => 'checkbox',
		'eval'      => array('submitOnChange' => true),
		'sql'       => "char(1) NOT NULL default ''",
	),
	'venues'              => array
	(
		'label'        => &$GLOBALS['TL_LANG']['tl_news']['venues'],
		'inputType'    => 'fieldpalette',
		'foreignKey'   => 'tl_fieldpalette.id',
		'relation'     => array('type' => 'hasMany', 'load' => 'eager'),
		'sql'          => "blob NULL",
		'fieldpalette' => array
		(
			'config' => array(
				'hidePublished' => false
			),
			'list'     => array
			(
				'label' => array
				(
					'fields' => array('venueName', 'venueStreet', 'venuePostal', 'venueCity'),
					'format' => '%s <span style="color:#b3b3b3;padding-left:3px">[%s, %s %s]</span>',
				),
			),
			'palettes' => array
			(
				'default' => 'venueName,venueStreet,venuePostal,venueCity,venueCountry,venueSingleCoords,venuePhone,venueFax,venueEmail,venueWebsite,venueText',
			),
			'fields'   => array
			(
				'venueName'         => array
				(
					'label'     => &$GLOBALS['TL_LANG']['tl_news']['venueName'],
					'exclude'   => true,
					'search'    => true,
					'inputType' => 'text',
					'eval'      => array('maxlength' => 255, 'tl_class' => 'long'),
					'sql'       => "varchar(255) NOT NULL default ''",
				),
				'venueStreet'       => array
				(
					'label'     => &$GLOBALS['TL_LANG']['tl_news']['venueStreet'],
					'exclude'   => true,
					'search'    => true,
					'inputType' => 'text',
					'eval'      => array('maxlength' => 255, 'tl_class' => 'w50'),
					'sql'       => "varchar(255) NOT NULL default ''",
				),
				'venuePostal'       => array
				(
					'label'     => &$GLOBALS['TL_LANG']['tl_news']['venuePostal'],
					'exclude'   => true,
					'search'    => true,
					'inputType' => 'text',
					'eval'      => array('maxlength' => 32, 'tl_class' => 'w50'),
					'sql'       => "varchar(32) NOT NULL default ''",
				),
				'venueCity'         => array
				(
					'label'     => &$GLOBALS['TL_LANG']['tl_news']['venueCity'],
					'exclude'   => true,
					'filter'    => true,
					'search'    => true,
					'sorting'   => true,
					'inputType' => 'text',
					'eval'      => array('maxlength' => 255, 'tl_class' => 'w50'),
					'sql'       => "varchar(255) NOT NULL default ''",
				),
				'venueCountry'      => array
				(
					'label'     => &$GLOBALS['TL_LANG']['tl_news']['venueCountry'],
					'exclude'   => true,
					'filter'    => true,
					'sorting'   => true,
					'inputType' => 'select',
					'options'   => System::getCountries(),
					'eval'      => array('includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'),
					'sql'       => "varchar(2) NOT NULL default ''",
				),
				'venueSingleCoords' => array
				(
					'label'         => &$GLOBALS['TL_LANG']['tl_news']['venueSingleCoords'],
					'exclude'       => true,
					'search'        => true,
					'inputType'     => 'text',
					'eval'          => array('maxlength' => 64),
					'sql'           => "varchar(64) NOT NULL default ''",
					'save_callback' => array
					(
						array('tl_news_plus', 'generateVenueCoords'),
					),
				),
				'venueText'         => array
				(
					'label'     => &$GLOBALS['TL_LANG']['tl_news']['venueText'],
					'exclude'   => true,
					'search'    => true,
					'inputType' => 'textarea',
					'eval'      => array('rte' => 'tinyMCE', 'tl_class' => 'clr'),
					'sql'       => "text NULL",
				),
			),
		),
	),
);

$dc['fields'] = array_merge($dc['fields'], $arrFields);
```

### Custom table setup (e.g. `tl_member_address`)

In order to use Fieldpalette with your own table, create a Data Container Array that extends from `$GLOBALS['TL_DCA']['tl_fieldpalette']`, as the following example describes.

```
<?php
# src/Ressources/contao/dca/tl_member_address.php 

\Contao\Controller::loadLanguageFile('tl_fieldpalette');
\Contao\Controller::loadDataContainer('tl_fieldpalette');
\Contao\Controller::loadDataContainer('tl_member');

$GLOBALS['TL_DCA']['tl_member_address'] = $GLOBALS['TL_DCA']['tl_fieldpalette'];
$dca                                    = &$GLOBALS['TL_DCA']['tl_member_address'];

$fields = [
    'company'     => $GLOBALS['TL_DCA']['tl_member']['fields']['company'],
    'phone'       => $GLOBALS['TL_DCA']['tl_member']['fields']['phone'],
    'fax'         => $GLOBALS['TL_DCA']['tl_member']['fields']['fax'],
    'street'      => $GLOBALS['TL_DCA']['tl_member']['fields']['street'],
    'street2'     => $GLOBALS['TL_DCA']['tl_member']['fields']['street2'],
    'postal'      => $GLOBALS['TL_DCA']['tl_member']['fields']['postal'],
    'city'        => $GLOBALS['TL_DCA']['tl_member']['fields']['city'],
    'state'       => $GLOBALS['TL_DCA']['tl_member']['fields']['state'],
    'country'     => $GLOBALS['TL_DCA']['tl_member']['fields']['country'],
    'addressText' => $GLOBALS['TL_DCA']['tl_member']['fields']['addressText'],
];

$dca['fields'] = array_merge($dca['fields'], $fields);
```

Than add the following fieldpalette input to your parent table (e.g. `tl_member`).

```
/* /dca/tl_member.php */

$dca = &$GLOBALS['TL_DCA']['tl_member'];

/**
* Adjust palettes
*/
$dca['palettes']['default'] = str_replace('country', 'country,additionalAddresses', $dca['palettes']['default']);

/**
* Adjust fields
*/
$dca['fields']['additionalAddresses'] = [
    'label'        => &$GLOBALS['TL_LANG']['tl_member']['additionalAddresses'],
    'inputType'    => 'fieldpalette',
    'foreignKey'   => 'tl_member_address.id',
    'relation'     => ['type' => 'hasMany', 'load' => 'eager'],
    'sql'          => "blob NULL",
    'fieldpalette' => [
        'config'   => [
            'hidePublished' => false,
            'table'         => 'tl_member_address',
        ],
        'list'     => [
            'label' => [
                'fields' => ['city'],
                'format' => '%s',
            ],
        ],
        'palettes' => [
            'default' => '{contact_legend},phone,fax;{address_legend},company,street,street2,postal,city,state,country,addressText',
        ],
    ],
];

```

### Additional dca reference

The most attributes listed in the [DCA Reference](https://docs.contao.org/books/api/dca/reference.html) are supported. Additional attributes will be listed here.

#### Listing records

##### Sorting

  Key    | Value               | Description
---------| ------------------  | ---
viewMode | View mode (integer) | **0** Table (default) <br /> **1** List 

### Support recursive copying of fieldpalette records by copying their parent record

Simply add a `oncopy_callback` to the dca containing fields of type `fieldpalette`:

```php
$GLOBALS['TL_DCA']['tl_*'] = [
    'config'   => [
        // ...
        'oncopy_callback' => [
            ['huh.fieldpalette.listener.callback', 'copyFieldPaletteRecords']
        ],
    ]
]
```

#### Manipulate fieldpalette records about to be copied on the fly

Sometimes your fieldpalette records contain references to other fieldpalette records. When copying them, reference ids don't match the new (copied) ids anymore.
You can adjust that by using the copy_callback definable in your field's dca (the field of type "fieldpalette"):

```
'inputType' => 'fieldpalette',
'eval'       => array(
    'fieldpalette' => array(
        'copy_callback' => array(
            array('tl_selection_model', 'updateOptionValuesOnCopy')
        )
    ),
    // ...
)
    
```

Example for such a callback:

```php

public function updateOptionValuesOnCopy($fieldPalette, int $pid, int $newId, string $table, array $data)
{
    $filter = FieldPaletteModel::findByPk($fieldPalette->selectionModel_questionData_options_filter);

    if ($filter === null)
        return;

    $filterNew = FieldPaletteModel::findBy(
        array('selectionModel_questionData_filters_title=?', 'pid=?'),
        array($filter->selectionModel_questionData_filters_title, $newId)
    );

    if ($filterNew !== null)
    {
        $fieldPalette->selectionModel_questionData_options_filter = $filterNew->id;
    }
}
```

## Features

### Widgets

Name         | Description
------------ | -----------
fieldpalette | The FieldPaletteWizard renders the tl_fieldpalette items and provide crud functionality within its parent record (e.g. tl_news).

### Fields

tl_fieldpalette:

Name      | Description
--------- | -----------
id        | autoincrement unique identifiere
pid       | id of the parent entry
ptable    | parent table name (e.g. tl_news)
pfield    | parent field name (e.g. tl_news.venues)
sorting   | the sorting value
published | the published state (1 = published) 
start     | timestamp from where the element is published 
stop      | timestamp until the element is published

### Form Callbacks

tl_fieldpalette:

Type              | Description
----------------- | -----------
oncreate_callback | Get fieldpalette key from request, check if the parent table is active within Fieldpalette Registry and set the pfield to tl_fieldpalette item. 
onsubmit_callback | Update/Sync parent fieldpalette item value (for example tl_news.venues) when tl_fieldpalette entries were updated.
oncut_callback    | Update/Sync parent fieldpalette item value (for example tl_news.venues) when tl_fieldpalette entries were sorted.
ondelete_callback | Update/Sync parent fieldpalette item value (for example tl_news.venues) when tl_fieldpalette entries were deleted.


### Hooks

Name               | Arguments                     | Description
-----------------  | ----------------------------- | -----------
loadDataContainer  | $strTable                     | Register fields from parent datacontainer (like tl_news) to tl_fieldpalette and disable fieldpalette support from back end modules where no fieldpalette fields exists (see: initializeSystem Hook). 
initializeSystem   | -                             | Enable tl_fieldpalette table within all back end modules.	
executePostActions | $strAction, DataContainer $dc | Add refreshFieldPaletteField ajax action that return the updated FieldPaletteWizard content.

## Restrictions

* only supports DC_Table DataContainers
