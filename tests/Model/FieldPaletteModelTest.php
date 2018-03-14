<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\Model;

use Contao\Database;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class FieldPaletteModelTest extends ContaoTestCase
{
    protected function setUp()
    {
        parent::setUp();
        if (!isset($GLOBALS['TL_LANGUAGE'])) {
            $GLOBALS['TL_LANGUAGE'] = 'de';
        }
    }

    public function testSetTable()
    {
        $container = $this->mockContainer();

        $container->set('contao.framework', $this->mockContaoFramework([
            Database::class => $this->getDatabaseMock(),
            FieldPaletteModel::class => $this->getFieldPaletteModelMock(),
        ]));
        $container->setParameter('contao.resources_paths', [__DIR__.'/../vendor/contao/core-bundle/src/Resources/contao']);
        System::setContainer($container);

        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock()->setTable('notExistingTable');
        $this->assertInstanceOf(FieldPaletteModel::class, $model);
        $this->assertSame('tl_fieldpalette', $model->getTable());

        $GLOBALS['TL_DCA']['tl_existing']['config']['fieldpalette'] = [];
        $this->assertInstanceOf(FieldPaletteModel::class, $model->setTable('tl_existing'));
        $this->assertSame('tl_existing', $model->getTable());
        $this->assertSame('tl_existing', $model->getTable());
        $this->assertSame($GLOBALS['TL_MODELS']['tl_existing'], FieldPaletteModel::class);
    }

    public function testFindPublishedByIdsFrontend()
    {
        if (!defined('BE_USER_LOGGED_IN')) {
            define('BE_USER_LOGGED_IN', false);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();
        $this->assertNull($model->findPublishedByIds());

        $result = $model->findPublishedByIds([1, 2, 3]);
        $this->assertCount(3, $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testFindPublishedByIdsBackend()
    {
        if (!defined(BE_USER_LOGGED_IN)) {
            define(BE_USER_LOGGED_IN, true);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();
        $this->assertNull($model->findPublishedByIds());
        $result = $model->findPublishedByIds([1, 2, 3]);
        $this->assertCount(3, $result);
    }

    public function getDatabaseMock()
    {
        $databaseAdapter = $this->mockAdapter([
            'getInstance',
            'prepare',
            'execute',
            'fetchEach',
            'tableExists',
        ]);
        $databaseAdapter->method('getInstance')->willReturnSelf();
        $databaseAdapter->method('prepare')->withAnyParameters()->willReturnSelf();
        $databaseAdapter->method('fetchEach')->willReturn(['options', 'aoptions']);
        $databaseAdapter->method('execute')->willReturnSelf();
        $databaseAdapter->method('tableExists')->willReturnCallback(function ($table) {
            switch ($table) {
                case 'notExistingTable':
                    return false;
                default:
                    return true;
            }
        });

        return $databaseAdapter;
    }

    public function getFieldPaletteModelMock()
    {
        $mock = $this->getMockBuilder(FieldPaletteModel::class)->setMethods([
            'findBy',
            'dynamicFindBy',
        ])->disableOriginalConstructor()
            ->getMock();
        $mock->method('dynamicFindBy')->willReturnCallback(function ($col = [], $val = [], $opt = []) {
            return [$col, $val, $opt];
        });

        return $mock;
    }
}
