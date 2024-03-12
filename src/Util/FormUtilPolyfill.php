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
use Contao\System;
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
        $formatOptions = $config['formatOptions'] ?? 0;
        # todo: formatOptions needs to be an integer {@see \HeimrichHannot\UtilsBundle\Util\FormatterUtil::OPTION_*}

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
            $utils = System::getContainer()->get(Utils::class);

            foreach ($data as $field => $value) {
                if (empty($restrictFields) && in_array($field, $skipFields)) {
                    continue;
                }

                if (!empty($restrictFields) && !in_array($field, $restrictFields)) {
                    continue;
                }

                $key = $prefix . $formattedValuePrefix . $field;
                $result[$key] = $utils->formatter()->formatDcaFieldValue($dc, $field, $value, $formatOptions);
            }
        }

        return $result;
    }
}
