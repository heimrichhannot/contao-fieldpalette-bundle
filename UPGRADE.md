# UPGRADE GUIDE

## From module to 1.0

### Namespaces
Namespaced now respect PSR-4. 

Example: `HeimrichHannot\FieldPalette\FieldPaletteModel` is now `HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel`

### oncopy_callback
`['HeimrichHannot\FieldPalette\FieldPalette', 'copyFieldPaletteRecords']` to `['huh.fieldpalette.listener.callback', 'copyFieldPaletteRecords']`



