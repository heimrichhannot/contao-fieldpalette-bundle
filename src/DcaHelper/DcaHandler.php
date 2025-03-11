<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\DcaHelper;

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

class DcaHandler
{
    /**
     * @var string
     */
    public const TableRequestKey = 'table';
    /**
     * @var string
     */
    public const ParentTableRequestKey = 'ptable';
    /**
     * @var string
     */
    public const PaletteRequestKey = 'fieldpalette';
    /**
     * @var string
     */
    public const FieldpaletteRefreshAction = 'refreshFieldPaletteField';

    public function __construct(
        private readonly string $fieldPaletteTable,
        private readonly ContaoFramework $framework,
        private readonly FieldPaletteModelManager $modelManager,
        private readonly RequestStack $requestStack,
        private readonly FieldPaletteRegistry $registry,
    ) {
    }

    /**
     * @param string|null $act
     *
     * @return array|bool
     *
     * @throws \Exception
     */
    public function loadDynamicPaletteByParentTable($act, string $table, ?string $parentTable, DataContainer $dc)
    {
        $input = $this->framework->getAdapter(Input::class);

        if (!isset($GLOBALS['loadDataContainer'][$table])) {
            $this->framework->getAdapter(Controller::class)->loadDataContainer($table);
        }

        $strRootTable = '';
        $varPalette = [];

        switch ($act) {
            case 'create':
                $strRootTable = $parentTable;

                // determine root table from parent entity tree, if requested parent table = tl_fieldpalette -> nested fieldpalette
                if ($parentTable === $table && $intPid = $input->get('pid')) {
                    $objParent = $this->modelManager->createModelByTable($parentTable)->findByPk($intPid);

                    if (null !== $objParent) {
                        [$strRootTable, $varPalette] = $this->getParentTable($objParent, $objParent->id);
                    }
                }

                $varPalette[] = $this->getPaletteFromRequest(); // append requested palette

                break;
            case 'cut':
            case 'edit':
            case 'show':
            case 'delete':
            case 'toggle':
                $id = \strlen($input->get('id')) ? $input->get('id') : $dc->currentPid;

                $objModel = $this->modelManager->createModelByTable($table)->findByPk($id);

                if (!$objModel) {
                    break;
                }

                [$strRootTable, $varPalette] = $this->getParentTable($objModel, $objModel->id);
                if (empty($objModel->ptable) && 0 === $objModel->tstamp) {
                    $objModel->ptable = $parentTable;
                }
                $parentTable = $objModel->ptable;

                $request = $this->requestStack->getCurrentRequest();

                // set back link from request
                if ($input->get('popup') && $input->get('popupReferer')) {
                    $objSession = $request->getSession();
                    $strRefererId = $request->attributes->get('_contao_referer_id');
                    $session = $objSession->get('popupReferer');
                    $session[$strRefererId]['current'] = StringUtil::decodeEntities(rawurldecode($input->get('popupReferer')));
                    $objSession->set('popupReferer', $session);
                }

                break;
        }

        if (!$strRootTable || !$varPalette) {
            return false;
        }

        return [$varPalette, $strRootTable, $parentTable];
    }

    /**
     * @throws \Exception
     */
    public function registerFieldPalette(string $table, DataContainer $dc): void
    {
        $parentTable = $this->getParentTableFromRequest();

        [$palette, $rootTable, $parentTable] = $this->loadDynamicPaletteByParentTable(
            $this->framework->getAdapter(Input::class)->get('act'),
            $table,
            $parentTable,
            $dc
        );

        if (!isset($GLOBALS['TL_DCA'][$table]['config']['fieldpalette']) || !$parentTable || !$palette) {
            return;
        }

        if ($table !== $rootTable) {
            $this->framework->getAdapter(Controller::class)->loadDataContainer($rootTable);
        }

        $arrDCA = $GLOBALS['TL_DCA'][$rootTable];

        $fields = $arrDCA['fields'];

        if (!\is_array($fields)) {
            return;
        }

        if (!$palette) {
            return;
        }

        // nested palette
        if (\is_array($palette)) {
            $arrNestedPalette = $this->findNestedFieldPaletteFields($palette, $fields);

            if (false !== $arrNestedPalette) {
                $fields = $arrNestedPalette;
            }
        } else {
            if (!isset($fields[$palette])) {
                return;
            }

            $fields = [
                $palette => $fields[$palette],
            ];
        }

        $blnFound = $this->registerFieldPaletteFields(
            $arrDCA,
            $table,
            $parentTable,
            $rootTable,
            $palette,
            $fields,
            $dc
        );

        if (!$blnFound) {
            $this->refuseFromBackendModuleByTable($table);
        }
    }

    /**
     * @return array|bool
     */
    public function findNestedFieldPaletteFields(array $palettes, array $fields)
    {
        if (1 === \count($palettes)) {
            $palette = $palettes[0];

            // root level
            if (!isset($fields['fields']) && isset($fields[$palette])) {
                return [
                    $palette => $fields[$palette],
                ];
            }

            // nested palette
            if (isset($fields['fields'][$palette])) {
                return [
                    $palette => $fields['fields'][$palette],
                ];
            }

            return false;
        }

        foreach ($palettes as $i => $fieldPalette) {
            if (!isset($fields[$fieldPalette])) {
                return false;
            }

            if ('fieldpalette' !== $fields[$fieldPalette]['inputType']) {
                return false;
            }

            if (!\is_array($fields[$fieldPalette]['fieldpalette'])) {
                return false;
            }

            $childPalettes = \array_slice($palettes, $i + 1, \count($palettes));

            return $this->findNestedFieldPaletteFields($childPalettes, $fields[$fieldPalette]['fieldpalette']['fields']);
        }

        return false;
    }

    /**
     * @return bool
     */
    public function registerFieldPaletteFields(
        array &$dca,
        string $table,
        string $parentTable,
        string $rootTable,
        array $palette,
        array $fields,
        DataContainer $dc,
    ) {
        if (!\is_array($fields)) {
            return false;
        }

        $blnFound = false;

        foreach ($fields as $field => $fieldData) {
            if (!\is_array($fieldData) || !\is_array($fieldData['fieldpalette'] ?? null)) {
                continue;
            }

            $dca['fields'] = array_merge(
                $dca['fields'] ?? [],
                $fieldData['fieldpalette']['fields'] ?? [],
                $GLOBALS['TL_DCA'][$table]['fields'] ?? []
            );

            $this->registry->set($rootTable, $field, $dca);

            // set active ptable
            if ($this->isActive($rootTable, $parentTable, $table, $field, $dc)) {
                $this->framework->getAdapter(Controller::class)->loadLanguageFile($rootTable); // allow translations within parent fieldpalette table
                $GLOBALS['TL_DCA'][$table] = $this->getDca($rootTable, $parentTable, $field, $palette);
            }

            $blnFound = true;
        }

        return $blnFound;
    }

    public function isActive(
        string $rootTable,
        string $parentTable,
        string $table,
        string $field,
        DataContainer $dc,
    ) {
        $registry = $this->registry->get($rootTable);

        if (!isset($registry[$field])) {
            return false;
        }

        // determine active state by current element
        if ($this->getTableFromRequest() === $table) {
            $id = $this->framework->getAdapter(Input::class)->get('id') ?: $dc->currentPid;
            $act = $this->framework->getAdapter(Input::class)->get('act');

            switch ($act) {
                case 'create':
                    return true;
                case 'cut':
                case 'edit':
                case 'show':
                case 'delete':
                case 'toggle':
                    $model = $this->modelManager->createModelByTable($table)->findByPk($id);
                    if (!$model) {
                        return false;
                    }

                    return $parentTable === $model->ptable && $field === $model->pfield;
            }
        }

        return false;
    }

    public function getTableFromRequest()
    {
        return $this->framework->getAdapter(Input::class)->get(static::TableRequestKey);
    }

    public function getParentTableFromRequest()
    {
        return $this->framework->getAdapter(Input::class)->get(static::ParentTableRequestKey);
    }

    public function getPaletteFromRequest()
    {
        return $this->framework->getAdapter(Input::class)->get(static::PaletteRequestKey);
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public function getParentTable(FieldPaletteModel $model, int $id, array $palette = [])
    {
        // always store current pfield
        if (empty($palette)) {
            $palette = [$model->pfield];
        }

        if ($model->ptable === $this->fieldPaletteTable) {
            $parent = $this->framework->getAdapter(FieldPaletteModel::class)->findByPk($model->pid);

            if (null === $parent) {
                return [$model->ptable, null];
            }

            // save nested path
            if ($parent->pfield) {
                $palette[] = $parent->pfield;
            }

            return $this->getParentTable($parent, $id, $palette);
        }

        return [$model->ptable, array_reverse($palette)];
    }

    public function getDca(string $rootTable, string $parentTable, string $field, array $palette = []): array
    {
        $controller = $this->framework->getAdapter(Controller::class);
        $controller->loadDataContainer($rootTable);

        // custom table support
        $paletteTable = $GLOBALS['TL_DCA'][$rootTable]['fields'][$field]['fieldpalette']['config']['table'] ?? $this->fieldPaletteTable;

        $controller->loadDataContainer($paletteTable);

        $data = [];

        $defauts = $GLOBALS['TL_DCA'][$paletteTable];
        $custom = $GLOBALS['TL_DCA'][$rootTable]['fields'][$field]['fieldpalette'];

        if (\is_array($palette)) {
            $nestedPalette = $this->findNestedFieldPaletteFields($palette, $GLOBALS['TL_DCA'][$rootTable]['fields']);

            if ($nestedPalette) {
                $strNestedPalette = key($nestedPalette);
                $custom = $nestedPalette[$strNestedPalette]['fieldpalette'];
            }
        }

        if (!\is_array($defauts) || !\is_array($custom)) {
            return $data;
        }

        foreach (['config', 'list', 'palettes', 'subpalettes'] as $key) {
            $data[$key] = array_replace_recursive($defauts[$key] ?? [], $custom[$key] ?? []);
        }

        $data['fields'] = array_merge($defauts['fields'] ?? [], $custom['fields'] ?? []);

        // replace tl_fieldpalette with custom config
        //        $data = @array_replace_recursive($defauts, $custom); // supress warning, as long as references may exist in both arrays
        $data['config']['ptable'] = $parentTable;

        if ($data['config']['hidePublished']) {
            $data['fields']['published']['inputType'] = 'hidden';
            $data['fields']['published']['default'] = true;
            unset($data['list']['operations']['toggle']);
            $data['palettes']['default'] .= ',published';
        } else {
            $data['palettes']['default'] .= ';{published_legend},published'; // always append published
        }

        $backendUser = $this->framework->createInstance(BackendUser::class);

        // Include all excluded fields which are allowed for the current user
        if ($data['fields'] ?? false) {
            foreach ($data['fields'] as $k => $v) {
                if ($v['exclude'] ?? false) {
                    if ($backendUser->hasAccess($paletteTable . '::' . $k, 'alexf')) {
                        if ('tl_user_group' === $this->fieldPaletteTable) {
                            $data['fields'][$k]['orig_exclude'] = $data['fields'][$k]['exclude'];
                        }

                        $data['fields'][$k]['exclude'] = false;
                    }
                }
            }
        }

        return $data;
    }

    public function refuseFromBackendModuleByTable(string $table): void
    {
        foreach ($GLOBALS['BE_MOD'] as $strGroup => $arrGroup) {
            if (!\is_array($arrGroup)) {
                continue;
            }

            foreach ($arrGroup as $strModule => $arrModule) {
                if (!\is_array($arrModule) || !\is_array($arrModule['tables'] ?? null)) {
                    continue;
                }

                if (!\in_array($table, $arrModule['tables'], true)
                    || false === ($idx = array_search($this->fieldPaletteTable, $arrModule['tables'], true))
                ) {
                    continue;
                }

                unset($GLOBALS['BE_MOD'][$strGroup][$strModule]['tables'][$idx]);
            }
        }
    }

    /**
     * @param int    $pid       The id of the former parent record
     * @param int    $newId     the id of the new parent record just copied from the former record
     * @param string $table     The parent table
     * @param array  $dcaFields A dca array of fields
     */
    public function recursivelyCopyFieldPaletteRecords(int $pid, int $newId, string $table, array $dcaFields): void
    {
        foreach ($dcaFields as $field => $fieldData) {
            if ('fieldpalette' === ($fieldData['inputType'] ?? '')) {
                if (isset($fieldData['fieldpalette']['fields']) && !($fieldData['eval']['doNotCopy'] ?? false)) {
                    $fieldPaletteRecords = $this->modelManager->createModel()->findByPidAndTableAndField($pid, $table, $field);

                    if (!$fieldPaletteRecords) {
                        continue;
                    }

                    while ($fieldPaletteRecords->next()) {
                        $fieldPaletteModel = $this->modelManager->createModel();

                        // get existing data except id
                        $arrFieldData = $fieldPaletteRecords->row();
                        unset($arrFieldData['id']);

                        $fieldPaletteModel->setRow($arrFieldData);

                        // set new data
                        $fieldPaletteModel->tstamp = time();
                        $fieldPaletteModel->pid = $newId;
                        $fieldPaletteModel->published = true;

                        if (isset($fieldData['eval']['fieldpalette']['copy_callback']) && \is_array($fieldData['eval']['fieldpalette']['copy_callback'])) {
                            foreach ($fieldData['eval']['fieldpalette']['copy_callback'] as $callback) {
                                if (\is_array($callback)) {
                                    $this->framework->getAdapter(System::class)
                                        ->importStatic($callback[0]);
                                    $callback[0]::$callback[1]($fieldPaletteModel, $pid, $newId, $table, $fieldData);
                                } elseif (\is_callable($callback)) {
                                    $callback($fieldPaletteModel, $pid, $newId, $table, $fieldData);
                                }
                            }
                        }

                        $fieldPaletteModel->save();

                        $this->recursivelyCopyFieldPaletteRecords(
                            $fieldPaletteRecords->id,
                            $fieldPaletteModel->id,
                            $this->fieldPaletteTable,
                            $fieldData['fieldpalette']['fields']
                        );
                    }
                }
            } else {
                if ($table === $this->fieldPaletteTable) {
                    $fieldPaletteRecords = $this->modelManager->createModel()
                        ->findByPidAndTableAndField($pid, $table, $field);

                    if (!$fieldPaletteRecords) {
                        continue;
                    }

                    while ($fieldPaletteRecords->next()) {
                        $fieldPaletteModel = $this->modelManager->createModel();
                        $fieldPaletteModel->setRow($fieldPaletteRecords->row());
                        // set new data
                        $fieldPaletteModel->tstamp = time();
                        $fieldPaletteModel->pid = $newId;
                        $fieldPaletteModel->published = true;

                        if (isset($fieldData['eval']['fieldpalette']['copy_callback']) && \is_array($fieldData['eval']['fieldpalette']['copy_callback'])) {
                            foreach ($fieldData['eval']['fieldpalette']['copy_callback'] as $callback) {
                                if (\is_array($callback)) {
                                    $this->framework->getAdapter(System::class)->importStatic($callback[0]);
                                    $callback[0]->$callback[1]($fieldPaletteModel, $pid, $newId, $table, $fieldData);
                                } elseif (\is_callable($callback)) {
                                    $callback($fieldPaletteModel, $pid, $newId, $table, $fieldData);
                                }
                            }
                        }
                        $fieldPaletteModel->save();
                    }
                }
            }
        }
    }
}
