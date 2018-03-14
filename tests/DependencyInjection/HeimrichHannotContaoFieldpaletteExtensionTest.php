<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\DependencyInjection;

use HeimrichHannot\FieldpaletteBundle\DependencyInjection\HeimrichHannotContaoFieldpaletteExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class HeimrichHannotContaoFieldpaletteExtensionTest extends TestCase
{
    private $container;

    protected function setUp()
    {
        parent::setUp();
        $this->container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));

        $extension = new HeimrichHannotContaoFieldpaletteExtension();
        $extension->load([], $this->container);
    }

    public function testCanBeInstantiated()
    {
        $this->assertInstanceOf(
            HeimrichHannotContaoFieldpaletteExtension::class,
            new HeimrichHannotContaoFieldpaletteExtension()
        );
    }
}
