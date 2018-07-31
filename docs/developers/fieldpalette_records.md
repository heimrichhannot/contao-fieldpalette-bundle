# Working with fieldpalette records

## Support recursive copying of fieldpalette records by copying their parent record

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

## Manipulate fieldpalette records about to be copied on the fly

Sometimes your fieldpalette records contain references to other fieldpalette records. When copying them, reference ids don't match the new (copied) ids anymore.
You can adjust that by using the copy_callback definable in your field's dca (the field of type "fieldpalette"):

```php
'inputType' => 'fieldpalette',
'eval'      => [
    'fieldpalette' => [
        'copy_callback' => [
            ['appbundle.listener.tl_selection_model', 'updateOptionValuesOnCopy']
        ]
    ],
    // ...
]
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