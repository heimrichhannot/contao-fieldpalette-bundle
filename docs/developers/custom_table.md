# Set up a custom table

In order to use Fieldpalette with your own table, create a Data Container Array that extends from `$GLOBALS['TL_DCA']['tl_fieldpalette']`, as the following example with `tl_member_address` describes.

```
// src/Ressources/contao/dca/tl_member_address.php 

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
// src/Ressources/contao/dca/tl_member.php

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