<?php

/*
 * Copyright (c) 2024 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Util;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\DataContainer;
use Contao\Date;
use Contao\Environment;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Contao\Widget;
use HeimrichHannot\UtilsBundle\Util\Utils;

/**
 * Class FormUtil.
 *
 * @see https://heimrichhannot.github.io/contao-utils-bundle/HeimrichHannot/UtilsBundle/Form/FormUtil.html
 */
class FormUtilPolyfill
{
    protected ContaoFramework $framework;
    protected ?array $optionsCache;
    private Utils $utils;
    private array $kernelBundles;
    private InsertTagParser $insertTagParser;

    public function __construct(
        ContaoFramework $framework,
        Utils $utils,
        array $kernelBundles,
        InsertTagParser $insertTagParser
    ) {
        $this->framework = $framework;
        $this->utils = $utils;
        $this->kernelBundles = $kernelBundles;
        $this->insertTagParser = $insertTagParser;
    }

    /**
     * Get a new widget instance based on given attributes from a Data Container array.
     *
     * @param string             $name   The field name in the form
     * @param array              $data   The field configuration array
     * @param mixed|null $value  The field value
     * @param string             $dbName The field name in the database
     * @param string             $table  The table name in the database
     * @param DataContainer|null $dc     An optional DataContainer object
     * @param string             $mode   The contao mode, use FE or BE to get proper widget/form type
     *
     * @return Widget|null The new widget based on given attributes
     */
    public function getWidgetFromAttributes(string $name, array $data, mixed $value = null, string $dbName = '', string $table = '', DataContainer $dc = null, string $mode = ''): ?Widget
    {
        if ('' === $mode) {
            $mode = $this->utils->container()->isFrontend() ? 'FE' : 'BE';
        }

        if ('hidden' === $data['inputType']) {
            $mode = 'FE';
        }

        $mode = strtoupper($mode);
        $mode = in_array($mode, ['FE', 'BE']) ? $mode : 'FE';
        $class = 'FE' === $mode ? $GLOBALS['TL_FFL'][$data['inputType']] : $GLOBALS['BE_FFL'][$data['inputType']];
        /** @var $widget Widget */
        $widget = $this->framework->getAdapter(Widget::class);

        if (empty($class) || !class_exists($class)) {
            return null;
        }

        return new $class($widget->getAttributesFromDca($data, $name, $value, $dbName, $table, $dc));
    }

    /**
     * Prepares a special field's value. If an array is inserted, the function will call itself recursively.
     *
     * Possible config options:
     *
     * - `preserveEmptyArrayValues` -> preserves array values even if they're empty
     * - `skipLocalization` -> skips usage of "reference" array defined in the field's dca
     * - `skipDcaLoading`: boolean -> skip calling Controller::loadDataContainer on $dc->table
     * - `skipOptionCaching` -> skip caching options if $value is an array
     * - `_dcaOverride`: Array Set a custom dca from outside, which will be used instead of global dca value.
     *
     */
    public function prepareSpecialValueForOutput(string $field, $value, DataContainer $dc, array $config = [], bool $isRecursiveCall = false): ?string
    {
        $value = StringUtil::deserialize($value);

        /** @var Controller $controller */
        $controller = $this->framework->getAdapter(Controller::class);

        /** @var System $system */
        $system = $this->framework->getAdapter(System::class);

        /** @var CfgTagModelPolyfill $cfgTagModel */
        $cfgTagModel = $this->framework->getAdapter(CfgTagModelPolyfill::class);

        $system->loadLanguageFile('default');

        // prepare data
        $table = $dc->table;

        if (!isset($config['skipDcaLoading']) || !$config['skipDcaLoading']) {
            $controller->loadDataContainer($table);
            $system->loadLanguageFile($table);
        }

        $arraySeparator = $config['arraySeparator'] ?? ', ';
        $skipReplaceInsertTags = $config['skipReplaceInsertTags'] ?? false;

        // dca can be overridden from outside
        if (isset($config['_dcaOverride']) && is_array($config['_dcaOverride'])) {
            $data = $config['_dcaOverride'];
        } elseif (!isset($GLOBALS['TL_DCA'][$table]['fields'][$field]) || !is_array($GLOBALS['TL_DCA'][$table]['fields'][$field])) {
            return $value;
        } else {
            $data = $GLOBALS['TL_DCA'][$table]['fields'][$field];
        }

        $inputType = $data['inputType'] ?? null;

        // multi column editor
        $mceFieldSeparator = $config['mceFieldSeparator'] ?? "\t";
        $mceRowSeparator = $config['mceRowSeparator'] ?? "\t\n";
        $skipMceFieldLabels = $config['skipMceFieldLabels'] ?? false;
        $skipMceFieldLabelFormatting = $config['skipMceFieldLabelFormatting'] ?? false;
        $skipMceFields = isset($config['skipMceFields']) && is_array($config['skipMceFields']) ? $config['skipMceFields'] : [];
        $mceFields = isset($config['mceFields']) && is_array($config['mceFields']) ? $config['mceFields'] : [];

        if ('multiColumnEditor' == $inputType
            && in_array('HeimrichHannot\MultiColumnEditorBundle\HeimrichHannotContaoMultiColumnEditorBundle',
                array_merge(array_values($this->kernelBundles), array_keys($this->kernelBundles)))
            && is_array($value))
        {
            $formatted = '';

            foreach ($value as $row) {
                // new line - add "\t\n" after each line and not only "\n" to prevent outlook line break remover
                $formatted .= $mceRowSeparator;

                foreach ($row as $fieldName => $fieldValue) {
                    if (in_array($fieldName, $skipMceFields) || (!empty($mceFields) && !in_array($fieldName, $mceFields))) {
                        continue;
                    }

                    $dca = $data['eval']['multiColumnEditor']['fields'][$fieldName];

                    $label = '';

                    if (!$skipMceFieldLabels) {
                        $label = ($dca['label'][0] ?: $fieldName).': ';

                        if ($skipMceFieldLabelFormatting) {
                            $label = $fieldName.': ';
                        }
                    }

                    // indent new line
                    $formatted .= $mceFieldSeparator.$label.$this->prepareSpecialValueForOutput($fieldName, $fieldValue, $dc, array_merge($config, [
                            '_dcaOverride' => $dca,
                        ]));
                }
            }

            // new line - add "\t\n" after each line and not only "\n" to prevent outlook line break remover
            $formatted .= $mceRowSeparator;

            return $formatted;
        }

        // inputUnit
        if ('inputUnit' == $inputType)
        {
            $data = StringUtil::deserialize($value, true);

            if (!isset($data['value'])) {
                $data['value'] = '';
            }

            if (!isset($data['unit'])) {
                $data['unit'] = '';
            }

            return $data['value'].$arraySeparator.$data['unit'];
        }

        // Recursively apply logic to array
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $result = $this->prepareSpecialValueForOutput($field, $v, $dc, $config, true);

                if (isset($config['preserveEmptyArrayValues']) && $config['preserveEmptyArrayValues']) {
                    $value[$k] = $result;
                } else {
                    if (!empty($result)) {
                        $value[$k] = $result;
                    } else {
                        unset($value[$k]);
                    }
                }
            }

            // reset caches
            $this->optionsCache = null;

            return implode($arraySeparator, $value);
        }

        $reference = null;

        if (isset($data['reference']) && (!isset($config['skipLocalization']) || !$config['skipLocalization'])) {
            $reference = $data['reference'];
        }

        $rgxp = null;

        if (isset($data['eval']['rgxp'])) {
            $rgxp = $data['eval']['rgxp'];
        }

        if ((!isset($config['skipOptionCaching']) || !$config['skipOptionCaching']) && null !== $this->optionsCache) {
            $options = $this->optionsCache;
        } else {
            $options = Polyfill::getConfigByArrayOrCallbackOrFunction($data, 'options', [$dc]);
            $this->optionsCache = !is_array($options) ? [] : $options;
        }

        // foreignKey
        if (isset($data['foreignKey'])) {
            [$foreignTable, $foreignField] = explode('.', $data['foreignKey']);

            if (null !== ($instance = $this->utils->model()->findModelInstanceByPk($foreignTable, $value))) {
                $value = $instance->{$foreignField};
            }
        }

        if ('explanation' == $inputType) {
            if (isset($data['eval']['text'])) {
                return $data['eval']['text'];
            }
        } elseif ('cfgTags' == $inputType) {
            $collection = $cfgTagModel->findBy(['source=?', 'id = ?'], [$data['eval']['tagsManager'], $value]);
            $value = null;

            if (null !== $collection) {
                $result = $collection->fetchEach('name');
                $value = implode($arraySeparator, $result);
            }
        } elseif ('date' == $rgxp) {
            $value = Date::parse(Config::get('dateFormat'), $value);
        } elseif ('time' == $rgxp) {
            $value = Date::parse(Config::get('timeFormat'), $value);
        } elseif ('datim' == $rgxp) {
            $value = Date::parse(Config::get('datimFormat'), $value);
        } elseif (Validator::isBinaryUuid($value)) {
            $strPath = $this->utils->file()->getPathFromUuid($value);
            $value = $strPath ? Environment::get('url').'/'.$strPath : StringUtil::binToUuid($value);
        } // Replace boolean checkbox value with "yes" and "no"
        else {
            if ((isset($data['eval']['isBoolean']) && $data['eval']['isBoolean']) || ('checkbox' == $inputType && !($data['eval']['multiple'] ?? false))) {
                $value = ('' != $value) ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
            } elseif (is_array($options) && !array_is_list($options)) {
                $value = $options[$value] ?? $value;
            }
        }

        if (is_array($reference)) {
            $value = isset($reference[$value]) ? ((is_array($reference[$value])) ? $reference[$value][0] : $reference[$value]) : $value;
        }

        if (isset($data['eval']['encrypt']) && $data['eval']['encrypt']) {
            [$encrypted, $iv] = explode('.', $value);
            $secret = System::getContainer()->getParameter('secret');
            $value = openssl_decrypt($encrypted, 'aes-256-ctr', $secret, 0, base64_decode($iv, true));
        }

        // reset caches
        if (!$isRecursiveCall) {
            $this->optionsCache = null;
        }

        if (!$skipReplaceInsertTags) {
            $value = $this->insertTagParser->replace($value);
        }

        // Convert special characters (see #1890)
        return StringUtil::specialchars($value);
    }

    public function escapeAllHtmlEntities($table, $field, $value)
    {
        if (!$value) {
            return $value;
        }

        Controller::loadDataContainer($table);

        $data = $GLOBALS['TL_DCA'][$table]['fields'][$field];

        $preservedTags = $data['eval']['allowedTags'] ?? Config::get('allowedTags');

        $requestCleaner = new RequestCleanerPolyfill();

        if (
            isset($data['eval'])
            && (
                ($data['eval']['allowHtml'] ?? false)
                || strlen($data['eval']['rte'] ?? '')
                || ($data['eval']['preserveTags'] ?? false)
            )
        ) {
            // always decode entities if HTML is allowed
            $value = $requestCleaner->cleanHtml($value, true, true, $preservedTags);
        } elseif (is_array($data['options'] ?? false) || isset($data['options_callback']) || isset($data['foreignKey'])) {
            // options should not be strictly cleaned, as they might contain html tags like <strong>
            $value = $requestCleaner->cleanHtml($value, true, true, $preservedTags);
        } else {
            $value = $requestCleaner->clean($value, $data['eval']['decodeEntities'] ?? false);
        }

        return $value;
    }

    /**
     * Get an instance of Widget by passing field name and dca data.
     *
     * @param string     $fieldName     The field name
     * @param array      $dca           The DCA
     * @param array|null $value
     * @param string     $dbField       The database field name
     * @param string     $table         The table
     * @param null       $dataContainer object The data container
     *
     * @return Widget|null
     */
    public function getBackendFormField(string $fieldName, array $dca, ?array $value = null, string $dbField = '', string $table = '', $dataContainer = null): ?Widget
    {
        $strClass = $GLOBALS['BE_FFL'][$dca['inputType']];
        if (!$strClass) {
            return null;
        }
        return new $strClass(Widget::getAttributesFromDca($dca, $fieldName, $value, $dbField, $table, $dataContainer));
    }

    public function getModelDataAsNotificationTokens(array $data, ?string $prefix, DataContainer $dc, array $config = []): array
    {
        $prefix = $prefix ?: 'form_';
        $skipRawValues = $config['skipRawValues'] ?? false;
        $rawValuePrefix = $config['rawValuePrefix'] ?? 'raw_';
        $skipFormattedValues = $config['skipFormattedValues'] ?? false;
        $formattedValuePrefix = $config['formattedValuePrefix'] ?? '';
        $skipFields = $config['skipFields'] ?? [];
        $restrictFields = $config['restrictFields'] ?? [];
        $formatOptions = $config['formatOptions'] ?? [];

        $result = [];

        // raw values
        if (!$skipRawValues) {
            foreach ($data as $field => $value) {
                if (empty($restrictFields) && in_array($field, $skipFields)) {
                    continue;
                }

                if (!empty($restrictFields) && !in_array($field, $restrictFields)) {
                    continue;
                }

                $result[$prefix.$rawValuePrefix.$field] = $value;
            }
        }

        // formatted values
        if (!$skipFormattedValues) {
            foreach ($data as $field => $value) {
                if (empty($restrictFields) && in_array($field, $skipFields)) {
                    continue;
                }

                if (!empty($restrictFields) && !in_array($field, $restrictFields)) {
                    continue;
                }

                $key = $prefix.$formattedValuePrefix.$field;
                $result[$key] = $this->prepareSpecialValueForOutput($field, $value, $dc, $formatOptions);
            }
        }

        return $result;
    }
}
