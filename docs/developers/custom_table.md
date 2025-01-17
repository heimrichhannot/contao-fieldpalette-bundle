# Set up a custom table

To setup a custom fieldpalette table, use the `DcaGenerator` class to create the base setup:

```php
// contao/dca/tl_member_address.php 

use Contao\Controller;
use HeimrichHannot\FieldpaletteBundle\Dca\DcaGenerator;

$GLOBALS['TL_DCA']['tl_member_address'] = DcaGenerator::generateFieldpaletteBaseDca();

Controller::loadDataContainer('tl_member');

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


Then add the following fieldpalette input to your parent table (e.g. `tl_member`).

```php
# contao/dca/tl_member.php

$dca = &$GLOBALS['TL_DCA']['tl_member'];

/**
* Adjust palettes
*/
$dca['palettes']['default'] = str_replace('country', 'country,additionalAddresses', $dca['palettes']['default']);

/**
* Adjust fields
*/
$dca['fields']['additionalAddresses'] = [
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