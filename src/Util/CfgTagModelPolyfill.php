<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Util;

use Codefog\TagsBundle\Model\TagModel;
use Contao\Database;
use Contao\DataContainer;
use Contao\Model;
use Contao\Model\Collection;
use Contao\System;

if (class_exists(TagModel::class)) {
    class TagModelBase extends TagModel {}
} else {
    class TagModelBase extends Model {}
}

class CfgTagModelPolyfill extends TagModelBase
{
    protected static $strTable = 'tl_cfg_tag';

    public function findAllBySource($source, array $arrOptions = []): Collection|static|null
    {
        /** @var CfgTagModelPolyfill $adapter */
        $adapter = System::getContainer()->get('contao.framework')->getAdapter(self::class);
        return $adapter?->findBy('source', $source, $arrOptions);
    }

    public static function getSourcesAsOptions(DataContainer $dc): array
    {
        $options = [];
        $tags = Database::getInstance()->prepare('SELECT source FROM tl_cfg_tag GROUP BY source')->execute();

        if (null !== $tags && $tags->numRows) {
            $options = $tags->fetchEach('source');
            asort($options);
        }

        return $options;
    }
}