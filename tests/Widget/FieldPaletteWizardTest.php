<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\Widget;

use Contao\Controller;
use Contao\Database;
use Contao\DC_Table;
use Contao\Environment;
use Contao\Image;
use Contao\Input;
use Contao\Model\Collection;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FieldpaletteBundle\Element\ButtonElement;
use HeimrichHannot\FieldpaletteBundle\EventListener\CallbackListener;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\FieldpaletteBundle\Widget\FieldPaletteWizard;
use HeimrichHannot\UtilsBundle\Form\FormUtil;
use HeimrichHannot\UtilsBundle\Request\RoutingUtil;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class FieldPaletteWizardTest extends ContaoTestCase
{
    /**
     * @var int
     */
    protected $affectedRows = 0;
    protected $table = [];

    protected function setUp()
    {
        parent::setUp();
        $container = $this->mockContainer();
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $twig->method('render')->willReturnArgument(1);
        $formUtilMock = $this->createMock(FormUtil::class);
        $formUtilMock->method('prepareSpecialValueForOutput')->withAnyParameters()->willReturnArgument(1);
        $buttonElementMock = $this->getMockBuilder(ButtonElement::class)->disableOriginalConstructor()->getMock();
        $tokenManager = $this->createMock(CsrfTokenManager::class);
        $tokenManager->method('getToken')->with('dummy_token')->willReturn(new CsrfToken('dummy_token', 'abcd'));
        $routingUtilMock = $this->createMock(RoutingUtil::class);
        $routingUtilMock->method('generateBackendRoute')->willReturn('contao?action=test');

        $container->set('twig', $twig);
        $container->set('contao.framework', $this->getFramework());
        $container->set('huh.utils.form', $formUtilMock);
        $container->set('huh.utils.routing', $routingUtilMock);
        $container->set('huh.fieldpalette.element.button', $buttonElementMock);
        $container->setParameter('contao.csrf_token_name', 'dummy_token');
        $container->set('security.csrf.token_manager', $tokenManager);
        $container->set('session', new Session(new MockArraySessionStorage()));
        System::setContainer($container);

        if (!\interface_exists('\listable')) {
            include_once __DIR__.'/../../vendor/contao/core-bundle/src/Resources/contao/helper/interface.php';
        }
    }

    /**
     * @param array $methods
     * @param mixed $value
     *
     * @throws \ReflectionException
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

        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $reflectionProperty = $reflectionClass->getProperty('paletteTable');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($widget, 'tl_news');
        $reflectionProperty = $reflectionClass->getProperty('strName');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($widget, 'paletteField');

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

    public function getCollectionMock($value)
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

    public function getFramework()
    {
        $imageAdapter = $this->mockAdapter(['getHtml']);
        $imageAdapter->method('getHtml')->willReturnCallback(function ($src, $alt = '', $attributes = '') {
            return '<img src="'.$src.'" '.$attributes.'>';
        });

        $systemAdapter = $this->mockAdapter(['importStatic']);
        $systemAdapter->method('importStatic')->willReturnCallback(function ($className) {
            $class = $this->getMockBuilder('AReallyNonExistingClass')->setMethods([
                'callbackMethod',
                'labelCallback',
                'argumentThree',
                'setTableAndTrue',
                'setTableAndFalse',
            ])->getMock();
            $class->method('callbackMethod')->willReturnArgument(0);
            $class->method('labelCallback')->willReturnArgument(1);
            $class->method('argumentThree')->willReturnArgument(2);
            $class->method('setTableAndTrue')->willReturnCallback(function ($table) {
                $this->table[] = $table;

                return true;
            });
            $class->method('setTableAndFalse')->willReturnCallback(function ($table) {
                $this->table[] = $table;

                return false;
            });

            return $class;
        });

        $inputAdapter = $this->mockAdapter(['get']);
        $inputAdapter->method('get')->willReturnArgument(0);

        $environmentAdapter = $this->mockAdapter(['get']);
        $environmentAdapter->method('get')->willReturn(false);

        $controllerAdapter = $this->mockAdapter(['reload', 'loadDataContainer', 'addToUrl']);
        $controllerAdapter->method('reload')->willReturn('redirect');
        $controllerAdapter->method('loadDataContainer')->willReturn('redirect');
        $controllerAdapter->method('addToUrl')->willReturnArgument(0);

        $framework = $this->mockContaoFramework([
            Image::class => $imageAdapter,
            System::class => $systemAdapter,
            Input::class => $inputAdapter,
            Environment::class => $environmentAdapter,
            Controller::class => $controllerAdapter,
        ]);

        $this->mockFrameworkMethods($framework);

        return $framework;
    }

    public function mockFrameworkMethods(&$framework)
    {
        $framework->method('createInstance')->willReturnCallback(function ($class, $arg) {
            switch ($class) {
                case Database::class:
                    $dbresult = $this->mockAdapter(['execute']);
                    $dbresult->method('execute')->willReturnCallback(function (int $a, $b) {
                        $class = new \stdClass();
                        $class->affectedRows = $a;

                        return $class;
                    });
                    $database = $this->mockAdapter(['prepare', 'execute']);
                    $database->method('prepare')->willReturnCallback(function ($sql) use ($dbresult) {
                        $dbresult->sql = $sql;

                        return $dbresult;
                    });
                    $database->method('execute')->willReturnCallback(function ($sql) {
                        $strEnde = (strpos($sql, 'FROM') - 13);
                        $this->table[] = substr($sql, 12, $strEnde);
                        $class = new \stdClass();
                        $class->affectedRows = $this->affectedRows;

                        return $class;
                    });

                    return $database;
                default:
                    return null;
            }
        });
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

        $reflectionPropertyMode = $reflectionClass->getProperty('viewMode');
        $reflectionPropertyMode->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock();
        $reflectionPropertyMode->setValue($widget, 0);
        $this->assertSame(
            '@HeimrichHannotContaoFieldpalette/list/fieldpalette_list_table.html.twig',
            $method->invokeArgs($widget, ['list'])
        );
        $this->assertSame(
            '@HeimrichHannotContaoFieldpalette/wizard/fieldpalette_wizard_table.html.twig',
            $method->invokeArgs($widget, ['wizard'])
        );

        $reflectionPropertyMode->setValue($widget, 1);
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

        $reflectionPropertyDca = $reflectionClass->getProperty('dca');
        $reflectionPropertyDca->setAccessible(true);
        $reflectionPropertyModels = $reflectionClass->getProperty('models');
        $reflectionPropertyModels->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock(['generateListItem']);
        $widget->method('generateListItem')->willReturnCallback(function ($model, $i) {
            return 'Item_'.$i;
        });

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
        $reflectionPropertyName = $reflectionClass->getProperty('strName');
        $reflectionPropertyName->setAccessible(true);

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

        $GLOBALS['TL_LANG']['tl_fieldpalette']['modalTitle'] = '%s : %s';
        $GLOBALS['TL_LANG']['tl_fieldpalette']['paletteField'][0] = 'Testfeld';
        $GLOBALS['TL_LANG']['tl_fieldpalette']['cut'][1] = 'Beitrag ID %s verschieben';

        $reflectionPropertyDca->setValue($widget, []);
        $reflectionPropertyDca->setValue($widget, 'tl_news');
        $reflectionPropertyName->setValue($widget, 'paletteField');

        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

        $reflectionPropertyDca->setValue($widget, '');
        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

        $reflectionPropertyDca->setValue($widget, [
            'list' => ['operations' => []],
        ]);
        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

        $reflectionPropertyDca->setValue($widget, [
            'list' => ['operations' => ''],
        ]);
        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

        $reflectionPropertyDca->setValue($widget, [
            'list' => ['operations' => null],
        ]);
        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

        $reflectionPropertyDca->setValue($widget, [
            'list' => ['operations' => 'Testoperationen'],
        ]);
        $this->assertSame('', $testMethod->invokeArgs($widget, [$itemModel]));

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'operations' => [
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
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel]);
        $this->assertTrue(is_string($result));

        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'operations' => [
                    'edit' => [
                        'label' => [0 => 'LabelTestCallback', 1 => 'Element ID %s bearbeiten'],
                        'href' => 'act=edit',
                        'icon' => 'edit.gif',
                        'button_callback' => [0 => CallbackListener::class, 1 => 'argumentThree'],
                    ],
                    'delete' => [
                        'label' => [0 => 'LabelTestCallable', 1 => 'Element ID %s löschen'],
                        'href' => 'act=delete',
                        'icon' => 'delete.gif',
                        'button_callback' => function ($row, $url, $label) {
                            return $label;
                        },
                    ],
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel]);
        $this->assertTrue(is_string($result));
        $this->assertNotFalse(strpos($result, 'LabelTestCallback'));
        $this->assertNotFalse(strpos($result, 'LabelTestCallable'));

        System::getContainer()->setParameter('huh.fieldpalette.table', 'tl_fieldpalette');
        $reflectionPropertyDca->setValue($widget, [
            'list' => [
                'operations' => [
                    'move' => [
                        'label' => [0 => 'LabelTestCallback', 1 => 'Element ID %s bearbeiten'],
                        'href' => 'act=move',
                        'icon' => 'move.gif',
                    ],
                ],
            ],
        ]);
        $result = $testMethod->invokeArgs($widget, [$itemModel]);
        $this->assertTrue(is_string($result));

        $result = $testMethod->invokeArgs($widget, [$itemModel]);
        $this->assertTrue(is_string($result));

        $result = $testMethod->invokeArgs($widget, [$itemModel, [], false, null, '5']);
        $this->assertTrue(is_string($result));

        $result = $testMethod->invokeArgs($widget, [$itemModel, [], false, null, '5', '4']);
        $this->assertTrue(is_string($result));
    }

    public function testGenerateGlobalButtons()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $testMethod = $reflectionClass->getMethod('generateGlobalButtons');
        $testMethod->setAccessible(true);

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

        $GLOBALS['TL_LANG']['tl_fieldpalette']['modalTitle'] = '%s : %s';
        $GLOBALS['TL_LANG']['tl_fieldpalette']['paletteField'][0] = 'Testfeld';
        $GLOBALS['TL_LANG']['tl_fieldpalette']['new'] = ['Neuer Beitrag', 'Neuen Beitrag anlegen'];

        $buttonElementMock = $this->getMockBuilder(ButtonElement::class)->disableOriginalConstructor()->setMethods(['generate'])->getMock();
        $buttonElementMock->method('generate')->willReturn('Result');

        System::getContainer()->set('huh.fieldpalette.element.button', $buttonElementMock);

        $this->assertSame('Result', $testMethod->invokeArgs($widget, [$itemModel]));
        $options = System::getContainer()->get('huh.fieldpalette.element.button')->getOptions();

        $this->assertSame('create', $options['act']);
        $this->assertSame('Testfeld : Neuen Beitrag anlegen', $options['modalTitle']);
        $this->assertSame('Neuer Beitrag', $options['label']);
        $this->assertSame('Neuer Beitrag', $options['title']);

        unset($GLOBALS['TL_LANG']['tl_fieldpalette']['paletteField'][0]);

        $testMethod->invokeArgs($widget, [$itemModel]);
        $options = System::getContainer()->get('huh.fieldpalette.element.button')->getOptions();
        $this->assertSame('paletteField : Neuen Beitrag anlegen', $options['modalTitle']);
    }

    public function testReviseTable()
    {
        $reflectionClass = new \ReflectionClass(FieldPaletteWizard::class);
        $testMethod = $reflectionClass->getMethod('reviseTable');
        $testMethod->setAccessible(true);

        $reflectionPropertyDca = $reflectionClass->getProperty('dca');
        $reflectionPropertyDca->setAccessible(true);
        $reflectionPropertyRecord = $reflectionClass->getProperty('objDca');
        $reflectionPropertyRecord->setAccessible(true);

        $widget = $this->getFieldPaletteWizardMock([]);
        System::getContainer()->setParameter('huh.fieldpalette.table', 'tl_fieldpalette');

        // Test without changes
        // Ajax request false
        // Reload false

        $reflectionPropertyDca->setValue($widget, []);
        $dcaObject = new \stdClass();
        $dcaObject->activeRecord = new \stdClass();
        $reflectionPropertyRecord->setValue($widget, $dcaObject);

        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        System::getContainer()->get('session')->set('new_records', null);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        System::getContainer()->get('session')->set('new_records', '');
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        System::getContainer()->get('session')->set('new_records', []);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        System::getContainer()->get('session')->set('new_records', 42);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        System::getContainer()->get('session')->set('new_records', []);
        $reflectionPropertyDca->setValue($widget, ['config' => ['ptable' => 'tl_news']]);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        $reflectionPropertyDca->setValue($widget, ['config' => ['ptable' => 'tl_news']]);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        $reflectionPropertyDca->setValue($widget, ['config' => ['ptable' => 'tl_news', 'dynamicPtable' => true]]);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        $reflectionPropertyDca->setValue($widget, ['config' => ['ctable' => 'tl_news', 'dynamicPtable' => true]]);
        $result = $testMethod->invokeArgs($widget, []);
        $this->assertFalse($result);

        // Test with changes
        // Ajax request false
        // Reload true

        System::getContainer()->get('session')->set('new_records', ['tl_news' => [3, 4, 5]]);
        $reflectionPropertyDca->setValue($widget, []);
        $dcaObject = new \stdClass();
        $dcaObject->activeRecord = new \stdClass();
        $dcaObject->activeRecord->id = 3;
        $reflectionPropertyRecord->setValue($widget, $dcaObject);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));

        System::getContainer()->get('session')->set('new_records', []);
        $this->affectedRows = 2;
        $reflectionPropertyDca->setValue($widget, ['config' => ['ptable' => 'tl_news']]);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));

        $reflectionPropertyDca->setValue($widget, ['config' => ['ptable' => 'tl_news', 'dynamicPtable' => true]]);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));

        $framework = System::getContainer()->get('contao.framework');
        $controllerAdapter = $this->mockAdapter(['reload', 'loadDataContainer']);
        $controllerAdapter->method('reload')->willReturn('redirect');
        $controllerAdapter->expects($this->once())->method('loadDataContainer');
        $framework = $this->mockContaoFramework([
            Environment::class => $framework->getAdapter(Environment::class),
            Controller::class => $controllerAdapter,
        ]);
        $this->mockFrameworkMethods($framework);
        System::getContainer()->set('contao.framework', $framework);
        $GLOBALS['loadDataContainer']['tl_news_archive'] = true;
        $reflectionPropertyDca->setValue($widget, ['config' => ['ctable' => ['tl_news', 'tl_news_archive']]]);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));

        $framework = System::getContainer()->get('contao.framework');
        $controllerAdapter = $this->mockAdapter(['reload', 'loadDataContainer']);
        $controllerAdapter->method('reload')->willReturn('redirect');
        $controllerAdapter->expects($this->once())->method('loadDataContainer');
        $framework = $this->mockContaoFramework([
            Environment::class => $framework->getAdapter(Environment::class),
            Controller::class => $controllerAdapter,
        ]);
        $this->mockFrameworkMethods($framework);
        System::getContainer()->set('contao.framework', $framework);
        $this->table = [];
        $GLOBALS['TL_DCA']['tl_news_archive']['config']['dynamicPtable'] = true;
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));
        $this->assertCount(2, $this->table);

        unset($GLOBALS['TL_DCA']['tl_news_archive']['config']['dynamicPtable']);

        $controllerAdapter = $this->mockAdapter(['reload', 'loadDataContainer']);
        $controllerAdapter->method('reload')->willReturn('redirect');
        $controllerAdapter->method('loadDataContainer');
        $framework = $this->mockContaoFramework([
            Environment::class => $framework->getAdapter(Environment::class),
            Controller::class => $controllerAdapter,
        ]);
        $this->mockFrameworkMethods($framework);
        System::getContainer()->set('contao.framework', $framework);
        $GLOBALS['loadDataContainer']['tl_news'] = true;
        $GLOBALS['loadDataContainer']['tl_news_archive'] = true;
        $this->table = [];
        $reflectionPropertyDca->setValue($widget, ['config' => ['ctable' => ['tl_news', 'tl_news_archive', '']]]);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));
        $this->assertCount(2, $this->table);

        $this->table = [];
        $reflectionPropertyDca->setValue($widget, ['config' => ['ctable' => [
            'tl_news', 'tl_news_archive', '', null, ['Hallo'], ]]]);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));
        $this->assertCount(2, $this->table);

        // Test with changes and AJAX
        // Ajax request true
        // Reload true

        $environmentAdapter = $this->mockAdapter(['get']);
        $environmentAdapter->method('get')->willReturn(true);
        $framework = $this->mockContaoFramework([
            Environment::class => $environmentAdapter,
            Controller::class => $framework->getAdapter(Controller::class),
        ]);
        $this->mockFrameworkMethods($framework);
        System::getContainer()->set('contao.framework', $framework);
        $this->assertTrue($testMethod->invokeArgs($widget, []));

        // Test with changes
        // Ajax request true
        // Reload false

        System::getContainer()->get('session')->set('new_records', null);
        $reflectionPropertyDca->setValue($widget, []);
        $environmentAdapter = $this->mockAdapter(['get']);
        $environmentAdapter->method('get')->willReturn(true);
        $framework = $this->mockContaoFramework([
            Environment::class => $environmentAdapter,
            Controller::class => $framework->getAdapter(Controller::class),
        ]);
        $this->mockFrameworkMethods($framework);
        System::getContainer()->set('contao.framework', $framework);
        $this->assertFalse($testMethod->invokeArgs($widget, []));

        // Test Hooks
        // Ajax request false
        // Reload false

        $GLOBALS['TL_HOOKS']['reviseTable'] = [
            function ($table, $records) {
                $this->table[] = $table;

                return false;
            },
            [],
            [CallbackListener::class, 'setTableAndFalse'],
            [CallbackListener::class, 'setTableAndTrue'],
            'Test',
        ];
        $this->table = [];
        System::getContainer()->get('session')->set('new_records', null);
        $controllerAdapter = $this->mockAdapter(['reload', 'loadDataContainer']);
        $controllerAdapter->expects($this->once())->method('reload')->willReturn('redirect');
        $controllerAdapter->method('loadDataContainer');
        $framework = $this->getFramework();
        $framework = $this->mockContaoFramework([
            System::class => $framework->getAdapter(System::class),
            Controller::class => $controllerAdapter,
            Environment::class => $framework->getAdapter(Environment::class),
        ]);
        System::getContainer()->set('contao.framework', $framework);
        $this->assertSame('redirect', $testMethod->invokeArgs($widget, []));
        $this->assertCount(3, $this->table);
    }
}
