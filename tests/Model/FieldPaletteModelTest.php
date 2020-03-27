<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
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

        $container = $this->mockContainer();

        $modelAdapter = $this->mockAdapter(['findBy']);
        $modelAdapter->method('findBy')->willReturnCallback(function ($col = [], $val = [], $opt = []) {
            return [$col, $val, $opt];
        });

        $container->set('contao.framework', $this->mockContaoFramework([
            Database::class => $this->getDatabaseMock(),
            FieldPaletteModel::class => $modelAdapter,
        ]));
        $container->setParameter('contao.resources_paths', [__DIR__.'/../vendor/contao/core-bundle/src/Resources/contao']);
        System::setContainer($container);
    }

    public function testSetTable()
    {
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
        if (!\defined('BE_USER_LOGGED_IN')) {
            \define('BE_USER_LOGGED_IN', false);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();
        $this->assertNull($model->findPublishedByIds());

        $result = $model->findPublishedByIds([1, 2, 3]);
        $this->assertCount(3, $result);
        $this->assertCount(2, $result[0]);
        $this->assertSame($model->getTable().'.id IN('.implode(',', [1, 2, 3]).')', $result[0][0]);
        $this->assertCount(1, $result[2]);

        $result = $model->findPublishedByIds([1, 2, 3], ['order' => $model->getTable().'.id']);
        $this->assertCount(1, $result[2]);
        $this->assertSame($model->getTable().'.id', $result[2]['order']);
    }

    public function testFindPublishedByPidAndTableAndField()
    {
        if (!\defined('BE_USER_LOGGED_IN')) {
            \define('BE_USER_LOGGED_IN', false);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();
        $result = $model->findPublishedByPidAndTableAndField(2, 'parentTable', 'parentField');
        $this->assertCount(3, $result);
        $this->assertCount(2, $result[0]);
        $this->assertCount(3, $result[1]);
        $this->assertCount(1, $result[2]);
        $this->assertTrue($this->checkIsStringArray($result[0]));

        $result = $model->findPublishedByPidAndTableAndField(3, 'parentTable', 'parentField', ['order' => 'ABC']);
        $this->assertCount(1, $result[2]);
    }

    public function testFindPublishedByPidsAndTableAndField()
    {
        if (!\defined('BE_USER_LOGGED_IN')) {
            \define('BE_USER_LOGGED_IN', false);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();

        $this->assertNull($model->findPublishedByPidsAndTableAndField([], 'parentTable', 'parentField'));

        $result = $model->findPublishedByPidsAndTableAndField([2, 3], 'parentTable', 'parentField');
        $this->assertCount(3, $result);
        $this->assertCount(3, $result[0]);
        $this->assertCount(2, $result[1]);
        $this->assertCount(1, $result[2]);
        $this->assertTrue($this->checkIsStringArray($result[0]));

        $result = $model->findPublishedByPidsAndTableAndField([2, 3], 'parentTable', 'parentField', ['order' => 'ABC']);
        $this->assertCount(1, $result[2]);
    }

    public function testFindByPidAndTableAndField()
    {
        if (!\defined('BE_USER_LOGGED_IN')) {
            \define('BE_USER_LOGGED_IN', false);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();

        $result = $model->findByPidAndTableAndField(2, 'parentTable', 'parentField');
        $this->assertCount(3, $result);
        $this->assertCount(1, $result[0]);
        $this->assertCount(3, $result[1]);
        $this->assertCount(1, $result[2]);
        $this->assertTrue($this->checkIsStringArray($result[0]));
        $this->assertTrue($this->checkIsStringArray($result[2]));

        $result = $model->findByPidAndTableAndField(3, 'parentTable', 'parentField', ['order' => 'ABC']);
        $this->assertCount(1, $result[2]);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFindPublishedByBackend()
    {
        if (!\defined('BE_USER_LOGGED_IN')) {
            \define('BE_USER_LOGGED_IN', true);
        }
        /**
         * @var FieldPaletteModel
         */
        $model = $this->getFieldPaletteModelMock();
        $result = $model->findPublishedBy(['column1', 'column2', 'column3']);
        $this->assertCount(3, $result);
        $this->assertCount(3, $result[0]);
        $this->assertCount(0, $result[1]);
        $this->assertCount(0, $result[2]);
        $this->assertTrue($this->checkIsStringArray($result[0]));
    }

    public function checkIsStringArray($array)
    {
        if (!empty($array)) {
            foreach ($array as $entry) {
                if (!\is_string($entry)) {
                    return false;
                }
            }
        }

        return true;
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
        $mock->method('findBy')->willReturnCallback(function ($col = [], $val = [], $opt = []) {
            return [$col, $val, $opt];
        });

        return $mock;
    }
}
