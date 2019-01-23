<?php

/*
 * Copyright (c) 2019 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test;

use HeimrichHannot\FieldpaletteBundle\HeimrichHannotContaoFieldpaletteBundle;
use PHPUnit\Framework\TestCase;

class HeimrichHannotContaoFieldpaletteBundleTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $bundle = new HeimrichHannotContaoFieldpaletteBundle();
        $this->assertInstanceOf(HeimrichHannotContaoFieldpaletteBundle::class, $bundle);
    }
}
