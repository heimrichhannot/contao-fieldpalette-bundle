# Changelog
All notable changes to this project will be documented in this file.

## [0.5.2] - 2021-04-27
- refactored initializeSystem hook
- catch invalid backend module configuration in initializeSystem hook (see [#12])
- backend assets now added from initializeSystem hook
- removed saveNclose button from modals (see [#9])
- fixed titles on table actions (see [#10])

## [0.5.1] - 2020-03-27
- fixed an issue where field registration was not called

## [0.5.0] - 2020-03-27
- changed dca field update implementation to better work with contao 4.9
- updated composer.json

## [0.4.9] - 2019-03-29

#### Fixed
* drop async static flags for all js files in backend mode in order to maintain js order (this fixes jquery not defined error)

## [0.4.8] - 2019-01-23

#### Fixed
* error due changes in utils bundle js

#### Changed
* increased utils bundle version dependency

## [0.4.7] - 2018-12-06

#### Fixed
* do not throw Exception fieldPaletteNestedParentTableDoesNotExist if model is null, because nested fieldpalette model might have already been removed

## [0.4.6] - 2018-11-19

#### Fixed
* symfony 4.x compatibility 
* request_token not getting verified correctly #8

## [0.4.5] - 2018-11-12

#### Fixed
* #7 `services.yml / huh.fieldpalette.element.button service not public` 

## [0.4.4] - 2018-10-10

#### Fixed
* add 100% width to nesting .tl_fieldpalette_wrapper > div

## [0.4.3] - 2018-07-31

#### Fixed
* dependency of `RoutingUtil` in `ButtonElement` and `CallbackListener`

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

[#12]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/12
[#10]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/10
[#9]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/9