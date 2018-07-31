# Changelog
All notable changes to this project will be documented in this file.

## [0.4.2] - 2018-07-31

#### Fixed
* deprecation warnings due private services

#### Changed
* updated documentation

## [0.4.1] - 2018-07-31

#### Fixed
* no entity encoding for item template label

## [0.4.0] - 2018-07-26

#### Added
- `FieldPaletteModelManager::getInstance()`
- `FieldPaletteModel::hasTable()`
- FieldPalette input type constant (`FieldPaletteWizard::TYPE`)

#### Changed
- updated UPGRADE.md

## [0.3.5] - 2018-07-25

#### Changed
- jquery now included static
- updated readme
- added UPGRADE.md for upgrading from module

## [0.3.4] - 2018-07-16

#### Fixed
- missing ajax action check in HookListener::executePostActionsHook()

## [0.3.3] - 2018-07-16

#### Fixed
- not catched undefinded return value in fieldpalette-be.js (#5)

## [0.3.2] - 2018-07-12

### Fixed
- Do not die() in `HookListener::executePostActionsHook` if not fieldpalette relevant

## [0.3.1] - 2018-07-12

### Fixed
- syntax-error in default list template (#2)

## [0.3.0] - 2018-07-12

### Fixed
- Do not die() in `HookListener::executePostActionsHook` if `$field['inputType']` is not `fieldpalette`, otherwise nested field `executePostActions` Hooks wont work anymore (e.g. multicolumneditor) 
- Invalid button html markup that prevent display of `header_new` icon for instance 

### Added
- Now supports `submitOnChange` in `eval` for input type `fieldpalette` and `autoSubmit` parent table form 

## [0.2.3] - 2018-07-11

### Fixed
- `Argument 2 passed to HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler::extractFieldPaletteFields() must be of the type array, null given`

## [0.2.2] - 2018-07-03

### Fixed
* ensure that `$GLOBALS['TL_JAVASCRIPT']` is an array before `array_merge()` backend styles

## [0.2.1] - 2018-07-03

### Fixed
* correctly size modal window responsive

## [0.2.0] - 2018-03-22

### Changed
* removed haste_plus dependencies

## [0.1.1] - 2018-03-22

### Fixed
* missing argument in callback service definition

## [0.1] - 2018-03-21 

Refactored fieldpalette module to bundle structure.
