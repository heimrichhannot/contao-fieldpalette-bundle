# Changelog
All notable changes to this project will be documented in this file.

## [0.7.11] - 2025-09-25
- Fixed: issue with contao 5 permission system

## [0.7.10] - 2025-09-22
- Fixed: issues with contao 5 permission system

## [0.7.9] - 2025-09-11
- Changed: do not show buttons other than "save" in modals

## [0.7.8] - 2025-09-09
- Fixed: permission issue with contao 5 when a target instance not exists anymore

## [0.7.6] - 2025-07-15
- Fixed: issue on cache warmup

## [0.7.5] - 2025-06-04
- Fixed: exception in frontend when using member modules

## [0.7.4] - 2025-05-21
- Added: support of doctrine schema representation sql

## [0.7.3] - 2025-03-11
- Changed: allow contao 5 ([#22](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/22))
- Changed: require at least php 8.1 ([#22](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/22))
- Fixed: various warnings and deprecations ([#22](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/22))

## [0.7.2] - 2025-03-11
- Fixed: warning

## [0.7.1] - 2025-02-04
- Changed: allow utils bundle v3
- Changed: code refactoring and modernization
- Fixed: issue with showing palette fields when edit entry
- Deprecated: CallbackListener::setReferrerOnSaveAndClose()
- Deprecated: CallbackListener::toggleIcon()
- Deprecated: CallbackListener::setReferrerOnSaveAndClose()
- Deprecated: 'huh.fieldpalette.element.button' service alias

## [0.7.0] - 2025-01-17
- Changed: refactored logic to load fieldpalette fields (**Please check if everything works as expected!**) ([#20](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/20))
- Changed: require contao 4.13 ([#20](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/20))
- Changed: require at least php 7.4 ([#20](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/20))
- Changed: a lot of refactoring and modernization ([#20](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/20))
- Removed: huh.fieldpalette.listener.callback service alias ([#20](https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/20))

## [0.6.18] - 2023-12-22
- Fixed: php8 syntax incompatible with php7

## [0.6.17] - 2023-12-14
- Fixed: issues with updating fieldpalette fields (DC_Multilingual detection removed)

## [0.6.16] - 2023-12-13
- Fixed: issues with DC_Multilingual detection

## [0.6.15] - 2023-12-01
- Fixed: warning

## [0.6.14] - 2023-11-29
- Fixed: warning

## [0.6.13] - 2022-11-30
- Changed: better error message
- Fixed: recursion (Issue: [#16], PR: [#17])
- Fixed: warnings with php 8
- Fixed: exception on ajax requests ([#19])

## [0.6.12] - 2022-11-30
- Added: DcaGenerator class (to avoid issues with [custom tables](docs/developers/custom_table.md))
- Changed: raised contao dependency to 4.9
- Changed: raised util bundle dependency
- Changed: some refactoring to CallbackListener
- Fixed: missing support for MultilingualTrait
- Fixed: exception in toggle callback

## [0.6.11] - 2022-09-14
- Fixed: backend widget not working

## [0.6.10] - 2022-09-14
- Fixed: fixed field not registered correct
- Fixed: array index issues with php 8
- Removed: sqlGetFromDcaHook listener

## [0.6.9] - 2022-07-27
- Changed: removed unnecassary and buggy saveNclose js code

## [0.6.8] - 2022-07-21
- Fixed: php 8 array index issues

## [0.6.7] - 2022-06-28
- Fixed: fields not correctly registered
- 
## [0.6.6] - 2022-06-27
- Fixed: exception if dca is empty

## [0.6.5] - 2022-06-27
- Changed: some refactoring to fix issues with custom fieldpalette dca's 

## [0.6.4] - 2022-02-14

- Added: handling Terminal42 Multilingual instance

## [0.6.3] - 2022-02-14

- Fixed: array index issues in php 8+

## [0.6.2] - 2022-02-14

- Fixed: array index issues in php 8+

## [0.6.1] - 2022-02-10

- Removed: usage of Utf8 bundle functions

## [0.6.0] - 2022-01-07
- Changed: this bundle now replaces the fieldpalette module

## [0.5.7] - 2022-01-06
- Fixed: fieldpalette bundle not always loaded after fieldpalette module 

## [0.5.6] - 2021-09-22

- Changed: skip replace insert tag for backend item labels (`FieldPaletteWizard::generateItemLabel()`) only in backend context

## [0.5.5] - 2021-09-22

- Changed: skip replace insert tag for backend item labels (`FieldPaletteWizard::generateItemLabel()`) -> see https://github.com/heimrichhannot/contao-utils-bundle/issues/34

## [0.5.4] - 2021-05-17
- allow php 8

## [0.5.3] - 2021-05-17
- fixed issue with contao picker

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

[#19]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/19
[#17]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/pull/17
[#16]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/16
[#12]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/12
[#10]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/10
[#9]: https://github.com/heimrichhannot/contao-fieldpalette-bundle/issues/9
