<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\Registry;

use HeimrichHannot\FieldpaletteBundle\Registry\FieldPaletteRegistry;
use PHPUnit\Framework\TestCase;

class FieldPaletteRegistryTest extends TestCase
{
    public function testSetGet()
    {
        $registry = new FieldPaletteRegistry();

        $this->assertNull($registry->get('tl_news'));

        $registry->set('tl_news', 'title', ['hallo']);
        $result = $registry->get('tl_news');
        $this->assertArrayHasKey('title', $result);
        $this->assertArraySubset(['hallo'], $result['title']);
        $this->assertNull($registry->get('tl_content'));

        $registry->set('tl_news', 'text', ['lorem ipsum']);
        $result = $registry->get('tl_news');
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertArraySubset(['title' => ['hallo']], $result);
        $this->assertArraySubset(['text' => ['lorem ipsum']], $result);
        $this->assertNull($registry->get('tl_content'));

        $registry->set('tl_news', 'title', ['neu']);
        $result = $registry->get('tl_news');
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertArraySubset(['title' => ['neu']], $result);
        $this->assertArraySubset(['lorem ipsum'], $result['text']);
        $this->assertNull($registry->get('tl_content'));
    }
}
