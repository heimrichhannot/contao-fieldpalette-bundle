# UPGRADE GUIDE

## To 0.7
Update your custom dca to use [DcaGenerator.php](src/Dca/DcaGenerator.php instead of defining the complete dca on your own.

## From module

### Namespaces
Namespaced now respect PSR-4. 

Example: `HeimrichHannot\FieldPalette\FieldPaletteModel` is now `HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel`

### oncopy_callback
`['HeimrichHannot\FieldPalette\FieldPalette', 'copyFieldPaletteRecords']` to `['huh.fieldpalette.listener.callback', 'copyFieldPaletteRecords']`

### Model methods call
`FieldPaletteModel` is now dynamic. You need to adapt your code to reflect this change. Use FieldPaletteManager to get FieldPaletteModel instance. Example:

```php
System::getContainer()->get('huh.fieldpalette.manager')->getInstance()->findByPidAndTableAndField($entity->id, 'tl_table', 'parentField');
```

With custom table: 

```php
System::getContainer()->get('huh.fieldpalette.manager')->createModel('tl_my_custom_fieldpalette')->findByPidAndTableAndField($entity->id, 'tl_table', 'parentField')
```



