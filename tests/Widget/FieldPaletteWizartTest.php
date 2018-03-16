<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\Widget;

use Contao\DC_Table;
use Contao\Image;
use Contao\Model\Collection;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FieldpaletteBundle\EventListener\CallbackListener;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard;
use HeimrichHannot\UtilsBundle\Form\FormUtil;

class FieldPaletteWizartTest extends ContaoTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $container = $this->mockContainer();
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $twig->method('render')->willReturnArgument(1);
        $container->set('twig', $twig);
        $container->set('contao.framework', $this->getFramework());
        System::setContainer($container);

        if (!\interface_exists('\listable')) {
            include_once __DIR__.'/../../vendor/contao/core-bundle/src/Resources/contao/helper/interface.php';
        }
    }

    /**
     * @param mixed $value
     *
     * @return FieldPaletteWizard|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getFieldPaletteWizardMock($methods = [], $value = null)
    {
        $dc = $this->createMock(DC_Table::class);
        /**
         * @var FieldPaletteWizard|\PHPUnit_Framework_MockObject_MockObject
         */
        $widget = $this->getMockBuilder(FieldPaletteWizard::class)->disableOriginalConstructor()->setMethods(array_merge(['getModelInstance', 'getDcTableInstance', 'import'], $methods))->getMock();
        $widget->method('getModelInstance')->willReturn($this->getFieldPaletteModelMock($value));
        $widget->method('getDcTableInstance')->willReturn($dc);
        $widget->setPaletteTable('tl_news');
        $widget->setStrName('paletteField');
        $widget->currentRecord = 7;
        $widget->strTable = 'tl_fieldpalette';

        return $widget;
    }

    public function getFieldPaletteModelMock($value = [])
    {
        $mock = $this->getMockBuilder(FieldPaletteModel::class)->setMethods([
            'findByPidAndTableAndField',
        ])->disableOriginalConstructor()
            ->getMock();
        $mock->method('findByPidAndTableAndField')->willReturn($this->getCollectionMock($value));

        return $mock;
    }

    public function testGenerate()
    {
        $result = $this->getFieldPaletteWizardMock(
            ['reviseTable', 'generateGlobalButtons', 'generateListView'],
            ['0', '1']
        )->generate();
        $this->assertCount(2, $result['values']);

        $result = $this->getFieldPaletteWizardMock(
            ['reviseTable', 'generateGlobalButtons', 'generateListView'], [])->generate();
        $this->assertEmpty($result['values']);
    }

    public function testGetViewTemplate()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $method = $reflectionClass->getMethod('getViewTemplate');
        $method->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock();
        $widget->setViewMode(0);
        $this->assertSame(
            '@HeimrichHannotContaoFieldpalette/list/fieldpalette_list_table.html.twig',
            $method->invokeArgs($widget, ['list'])
        );
        $this->assertSame(
            '@HeimrichHannotContaoFieldpalette/wizard/fieldpalette_wizard_table.html.twig',
            $method->invokeArgs($widget, ['wizard'])
        );

        $widget->setViewMode(1);
        $this->assertSame(
            '@HeimrichHannotContaoFieldpalette/list/fieldpalette_list_default.html.twig',
            $method->invokeArgs($widget, ['list'])
        );
        $this->assertSame(
            '@HeimrichHannotContaoFieldpalette/item/fieldpalette_item_default.html.twig',
            $method->invokeArgs($widget, ['item'])
        );
    }

    public function testGenerateListView()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $testMethod = $reflectionClass->getMethod('generateListView');
        $testMethod->setAccessible(true);

        $reflectionPropertyFramework = $reflectionClass->getProperty('framework');
        $reflectionPropertyFramework->setAccessible(true);
        $reflectionPropertyDca = $reflectionClass->getProperty('dca');
        $reflectionPropertyDca->setAccessible(true);
        $reflectionPropertyModels = $reflectionClass->getProperty('models');
        $reflectionPropertyModels->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock(['generateListItem']);
        $widget->method('generateListItem')->willReturnCallback(function ($model, $i) {
            return 'Item_'.$i;
        });
        $reflectionPropertyFramework->setValue($widget, System::getContainer()->get('contao.framework'));
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertCount(0, $result['items']);
        $this->assertTrue($result['sortable']);

        $reflectionPropertyDca->setValue($widget, ['config' => ['notSortable' => false]]);
        $reflectionPropertyModels->setValue($widget, $this->getCollectionMock([]));
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertCount(2, $result['items']);
        $this->assertTrue($result['sortable']);

        $reflectionPropertyDca->setValue($widget, ['config' => ['notSortable' => true]]);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result['sortable']);
    }

    public function testGenerateListItem()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $testMethod = $reflectionClass->getMethod('generateListItem');
        $testMethod->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock([
            'getViewTemplate',
            'generateButtons',
            'generateItemLabel',
        ]);

        $itemModel = $this->mockClassWithProperties(FieldPaletteModel::class, [
            'ptable' => 'tl_news',
            'pfield' => 'questions',
            'id' => 5,
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, 3]);
        $this->assertSame(3, $result['index']);
        $this->assertSame('tl_news_questions_5', $result['id']);
    }

    public function testGenerateItemLabel()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $testMethod = $reflectionClass->getMethod('generateItemLabel');
        $testMethod->setAccessible(true);

        $reflectionPropertyForm = $reflectionClass->getProperty('formUtil');
        $reflectionPropertyForm->setAccessible(true);
        $reflectionPropertyFramework = $reflectionClass->getProperty('framework');
        $reflectionPropertyFramework->setAccessible(true);
        $reflectionPropertyDca = $reflectionClass->getProperty('dca');
        $reflectionPropertyDca->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock([
            'getViewTemplate',
            'generateButtons',
            'generateItemLabel',
        ]);

        $formUtilMock = $this->createMock(FormUtil::class);
        $formUtilMock->method('prepareSpecialValueForOutput')->withAnyParameters()->willReturnArgument(1);
        $reflectionPropertyForm->setValue($widget, $formUtilMock);
        $reflectionPropertyFramework->setValue($widget, System::getContainer()->get('contao.framework'));

        $itemModel = $this->mockClassWithProperties(FieldPaletteModel::class, [
            'ptable' => 'tl_news',
            'pfield' => 'title',
            'title' => 'Hallo',
            'id' => 5,
        ]);

        $reflectionPropertyDca->setValue($widget, []);
        $this->assertSame(5, $testMethod->invokeArgs($widget, [$itemModel, '']));

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('Hallo', $result);

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                ],
            ],
            'fields' => [
                'title' => [
                    'load_callback' => [[CallbackListener::class, 'callbackMethod']],
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('Hallo', $result);

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                ],
            ],
            'fields' => [
                'title' => [
                    'load_callback' => [function ($value, $dca) {
                        return $value.'A';
                    }],
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('HalloA', $result);

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                    'format' => '%s %s',
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('Hallo tl_news', $result);

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                    'maxCharacters' => 2,
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('Ha …', $result);

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                    'maxCharacters' => 30,
                    'label_callback' => [CallbackListener::class, 'labelCallback'],
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('Hallo', $result);

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'label' => [
                    'fields' => ['title', 'ptable', 'pfield'],
                    'label_callback' => function ($row, $label) {
                        return str_replace('allo', 'olla', $label);
                    },
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel, '']);
        $this->assertTrue(is_string($result));
        $this->assertSame('Holla', $result);
    }

    public function testGenerateButtons()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $testMethod = $reflectionClass->getMethod('generateButtons');
        $testMethod->setAccessible(true);

        $reflectionPropertyDca = $reflectionClass->getProperty('dca');
        $reflectionPropertyDca->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock([
            'getViewTemplate',
            'generateButtons',
            'generateItemLabel',
        ]);

        $itemModel = $this->mockClassWithProperties(FieldPaletteModel::class, [
            'ptable' => 'tl_news',
            'pfield' => 'title',
            'title' => 'Hallo',
            'id' => 5,
        ]);

        $operations = [
            'edit' => [
                    'label' => [0 => 'Element bearbeiten', 1 => 'Element ID %s bearbeiten'],
                    'href' => 'act=edit',
                    'icon' => 'edit.gif',
                ],
            'delete' => [
                    'label' => [0 => 'Element löschen', 1 => 'Element ID %s löschen'],
                    'href' => 'act=delete',
                    'icon' => 'delete.gif',
                    'attributes' => 'onclick="if(!confirm(\'Soll der Eintrag ID %s wirklich gelöscht werden?\'))return false;FieldPaletteBackend.deleteFieldPaletteEntry(this,%s);return false;"',
                ],
            'toggle' => [
                    'label' => [0 => 'Element veröffentlichen/unveröffentlichen', 1 => 'Element ID %s veröffentlichen/unveröffentlichen'],
                    'icon' => 'visible.gif',
                    'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                    'button_callback' => [0 => 'tl_fieldpalette', 1 => 'toggleIcon'],
                ],
            'show' => [
                    'label' => [0 => 'Element anzeigen', 1 => 'Details des Elements ID %s anzeigen'],
                    'href' => 'act=show',
                    'icon' => 'show.gif',
                ],
        ];

        $reflectionPropertyDca->setValue($widget, []);
        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

//        $reflectionPropertyDca->setValue($widget, [
//            'list' => [
//                'operations' => $operations
//            ]
//        ]);
//        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));
    }

    protected function getCollectionMock($value)
    {
        $model1 = $this->mockClassWithProperties(FieldPaletteModel::class, [
            'tstamp' => 1000,
        ]);
        $model2 = $this->mockClassWithProperties(FieldPaletteModel::class, [
            'tstamp' => 2000,
        ]);
        $model3 = $this->mockClassWithProperties(FieldPaletteModel::class, [
            'tstamp' => 0,
        ]);

        $collection = $this->createMock(Collection::class);
        $collection->method('fetchEach')->willReturn($value);
        $collection->method('next')->will($this->onConsecutiveCalls($model1, $model2, $model3));
        $collection->method('current')->will($this->onConsecutiveCalls($model1, $model2, $model3));
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$model1, $model2, $model3]));

        return $collection;
    }

    protected function getFramework()
    {
        $imageAdapter = $this->mockAdapter(['getHtml']);
        $imageAdapter->method('getHtml')->willReturn('<img src="">');

        $systemAdapter = $this->mockAdapter(['importStatic']);
        $systemAdapter->method('importStatic')->willReturnCallback(function ($className) {
            $class = $this->getMockBuilder('AReallyNonExistingClass')->setMethods([
                'callbackMethod',
                'labelCallback',
            ])->getMock();
            $class->method('callbackMethod')->willReturnArgument(0);
            $class->method('labelCallback')->willReturnArgument(1);

            return $class;
        });

        return $this->mockContaoFramework([
            Image::class => $imageAdapter,
            System::class => $systemAdapter,
        ]);
    }
}
