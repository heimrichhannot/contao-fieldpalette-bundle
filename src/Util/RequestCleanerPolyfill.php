<?php

/*
 * Copyright (c) 2024 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Util;

use Contao\Input;
use Contao\StringUtil;
use Contao\Validator;

/**
 * @internal https://github.com/heimrichhannot/contao-utils-bundle/blob/ee122d2e267a60aa3200ce0f40d92c22028988e8/src/Request/RequestCleaner.php#L20
 */
class RequestCleanerPolyfill
{
    /**
     * XSS clean, decodeEntities, tidy/strip tags, encode special characters and encode inserttags and return save, cleaned value(s).
     *
     * @param mixed $value            The input value
     * @param bool  $decodeEntities   If true, all entities will be decoded
     * @param bool  $encodeInsertTags If true, encode the opening and closing delimiters of insert tags
     * @param bool  $tidy             If true, varValue is tidied up
     * @param bool  $strictMode       If true, the xss cleaner removes also JavaScript event handlers
     */
    public function clean(mixed $value, bool $decodeEntities = false, bool $encodeInsertTags = true, bool $tidy = true, bool $strictMode = true): mixed
    {
        // do not clean, otherwise empty string will be returned, not null
        if (null === $value) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as $i => $childValue) {
                $value[$i] = $this->clean($childValue, $decodeEntities, $encodeInsertTags, $tidy, $strictMode);
            }

            return $value;
        }

        // do not handle binary uuid
        if (Validator::isUuid($value)) {
            return $value;
        }

        $value = $this->xssClean($value, $strictMode);

        // decodeEntities for tidy is more complex, because non allowed tags should be displayed as readable text, not as html entity
        $value = Input::decodeEntities($value);

        // do not encodeSpecialChars when tidy did run, otherwise non allowed tags will be encoded twice
        if (!$decodeEntities && !$tidy) {
            $value = Input::encodeSpecialChars($value);
        }

        if ($encodeInsertTags) {
            $value = Input::encodeInsertTags($value);
        }

        return $value;
    }

    /**
     * XSS clean, decodeEntities, tidy/strip tags, encode special characters and encode inserttags and return save, cleaned value(s).
     *
     * @param mixed  $value            The input value
     * @param bool   $decodeEntities   If true, all entities will be decoded
     * @param bool   $encodeInsertTags If true, encode the opening and closing delimiters of insert tags
     * @param string $allowedTags      List of allowed html tags
     * @param bool   $tidy             If true, varValue is tidied up
     * @param bool   $strictMode       If true, the xss cleaner removes also JavaScript event handlers
     */
    public function cleanHtml(mixed $value, bool $decodeEntities = false, bool $encodeInsertTags = true, string $allowedTags = '', bool $tidy = true, bool $strictMode = true): mixed
    {
        // do not clean, otherwise empty string will be returned, not null
        if (null === $value) {
            return null;
        }

        if (is_array($value)) {
            foreach ($value as $i => $childValue) {
                $value[$i] = $this->cleanHtml($childValue, $decodeEntities, $encodeInsertTags, $allowedTags, $tidy, $strictMode);
            }

            return $value;
        }

        // do not handle binary uuid
        if (Validator::isUuid($value)) {
            return $value;
        }

        $value = $this->xssClean($value, $strictMode);

        // decodeEntities for tidy is more complex, because non allowed tags should be displayed as readable text, not as html entity
        $value = Input::decodeEntities($value);

        // do not encodeSpecialChars when tidy did run, otherwise non allowed tags will be encoded twice
        if (!$decodeEntities && !$tidy) {
            $value = Input::encodeSpecialChars($value);
        }

        if ($encodeInsertTags) {
            $value = Input::encodeInsertTags($value);
        }

        return $value;
    }

    /**
     * Clean a value and try to prevent XSS attacks.
     *
     * @param mixed $varValue   A string or array
     * @param bool  $strictMode If true, the function removes also JavaScript event handlers
     *
     * @return mixed The cleaned string or array
     */
    public function xssClean(mixed $varValue, bool $strictMode = false): mixed
    {
        if (\is_array($varValue)) {
            foreach ($varValue as $key => $value) {
                $varValue[$key] = $this->xssClean($value, $strictMode);
            }

            return $varValue;
        }

        // do not xss clean binary uuids
        if (Validator::isBinaryUuid($varValue)) {
            return $varValue;
        }

        // Fix issue StringUtils::decodeEntites() returning empty string when value is 0 in some contao 4.9 versions
        if ('0' !== $varValue && 0 !== $varValue) {
            $varValue = StringUtil::decodeEntities($varValue);
        }

        $varValue = preg_replace('/(&#[A-Za-z0-9]+);?/i', '$1;', $varValue);

        // fix: "><script>alert('xss')</script> or '></SCRIPT>">'><SCRIPT>alert(String.fromCharCode(88,83,83))</SCRIPT>
        $varValue = preg_replace('/(?<!\w)(?>["|\']>)+(<[^\/^>]+>.*)/', '$1', $varValue);

        return Input::xssClean($varValue, $strictMode);
    }
}