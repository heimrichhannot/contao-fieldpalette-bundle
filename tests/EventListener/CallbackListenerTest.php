<?php

/*
 * Copyright (c) 2020 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\EventListener;

use Contao\Controller;
use Contao\Input;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\EventListener\CallbackListener;
use HeimrichHannot\FieldpaletteBundle\Manager\FieldPaletteModelManager;
use HeimrichHannot\UtilsBundle\Container\ContainerUtil;
use HeimrichHannot\UtilsBundle\Request\RoutingUtil;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class CallbackListenerTest extends ContaoTestCase
{
    protected $testCounter = 0;
    protected $loadDataContainerCount = 0;
    protected $inputDo = null;
    protected $inputId = null;
    protected $inputCountGet = 0;
    protected $mockedMethodResult = 0;

    public function setUp()
    {
        parent::setUp();
        $this->testCounter = 0;
        $this->loadDataContainerCount = 0;
        $this->inputCountGet = 0;
        $this->inputId = null;
        $this->inputDo = null;
        $this->mockedMethodResult = null;
    }

    /**
     * @return \Contao\CoreBundle\Framework\ContaoFramework|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getFrameworkMock()
    {
        $controllerAdapter = $this->mockAdapter(['loadDataContainer']);
        $controllerAdapter->method('loadDataContainer')->willReturnCallback(function () {
            ++$this->loadDataContainerCount;

            return true;
        });

        $inputAdapter = $this->mockAdapter(['get']);
        $inputAdapter->method('get')->willReturnCallback(function ($param) {
            ++$this->inputCountGet;
            switch ($param) {
                case 'do':
                    return $this->inputDo;
                case 'id':
                    return $this->inputId;
            }
        });

        $framework = $this->mockContaoFramework([
            Controller::class => $controllerAdapter,
            Input::class => $inputAdapter,
        ]);

        return $framework;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|FieldPaletteModelManager
     */
    public function getModelManagerMock()
    {
        $manager = $this->createMock(FieldPaletteModelManager::class);
        $manager->method('createModelByTable')->willReturnCallback(function ($table) {
            switch ($table) {
                case 'null':
                    return null;
                case 'tl_null':
                    return null;
                default:
                    $model = $this->mockAdapter([
                        'findByPk',
                        'save',
                    ]);
                    $model->method('findByPk')->willReturnCallback(function ($id) {
                        switch ($id) {
                            case 0:
                                return null;
                            default:
                                $model = $this->mockAdapter([
                                    'findByPk',
                                    'save',
                                ]);
                                $model->method('findByPk')->willReturnCallback(function ($id) {
                                    switch ($id) {
                                        case 0:
                                            return null;
                                        default:
                                            $model = $this->mockAdapter(['save']);

                                            return $model;
                                    }
                                });
                                $model->pid = 2;

                                return $model;
                        }
                    });
                    $model->method('save')->willReturnSelf();
                    if ('tl_parentnull' === $table) {
                        $model->pid = 0;
                    } else {
                        $model->pid = 2;
                    }

                    return $model;
            }
        });

        return $manager;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|DcaHandler
     */
    public function getDcaHandlerMock()
    {
        $handler = $this->createMock(DcaHandler::class);
        $handler->method('getPaletteFromRequest')->willReturn('tl_news');
        $handler->method('recursivelyCopyFieldPaletteRecords')->willReturnCallback(function ($id, $newId, $table, $dcaFields) {
            $this->mockedMethodResult = [$id, $newId, $table, $dcaFields];
        });

        return $handler;
    }

    public function getRequestStackMock()
    {
        $router = $this->createMock(RouterInterface::class);
        $requestStack = new RequestStack();
        $request = new Request();
        $request->attributes->set('_contao_referer_id', 'foobar');
        $requestStack->push($request);

        return $requestStack;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ContainerUtil
     */
    public function getContainerUtilMock($isBackend = false)
    {
        $util = $this->createMock(ContainerUtil::class);
        $util->method('isBackend')->willReturn($isBackend);

        return $util;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|UrlUtil
     */
    public function getUrlUtilMock()
    {
        $util = $this->createMock(UrlUtil::class);

        return $util;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    public function getLoggerMock()
    {
        $logger = $this->createMock(LoggerInterface::class);

        return $logger;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|RoutingUtil
     */
    public function getRoutingUtilMock()
    {
        $util = $this->createMock(RoutingUtil::class);

        return $util;
    }

    public function testSetTable()
    {
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock(), $this->getRequestStackMock(), $this->getContainerUtilMock(), $this->getUrlUtilMock(), $this->getRoutingUtilMock(), $this->getLoggerMock());

        $result = $listener->setTable('tl_news', 1, []);
        $this->assertFalse($result);

        $GLOBALS['TL_DCA']['tl_news']['config']['fieldpalette'] = true;
        $GLOBALS['TL_DCA']['tl_null']['config']['fieldpalette'] = true;

        $result = $listener->setTable('null', 1, []);
        $this->assertFalse($result);

        $result = $listener->setTable('tl_null', 1, []);
        $this->assertFalse($result);

        $result = $listener->setTable('tl_parentnull', 1, []);
        $this->assertFalse($result);

        $GLOBALS['TL_DCA']['tl_news']['config']['fieldpalette'] = true;
        $result = $listener->setTable('tl_news', 1, []);
        $this->assertTrue($result);

        $GLOBALS['TL_DCA']['tl_news']['config']['fieldpalette'] = true;
        $result = $listener->setTable('tl_news', 1, ['ptable' => 'tl_news']);
        $this->assertTrue($result);

        $GLOBALS['TL_DCA']['tl_news']['config']['fieldpalette'] = true;
        $result = $listener->setTable('tl_news', 1, ['ptable' => 'tl_fieldpalette']);
        $this->assertTrue($result);
    }

    public function testCopyFieldPaletteRecords()
    {
        $containerUtil = $this->getContainerUtilMock(false);
        $containerUtil->expects($this->once())->method('isBackend')->willReturn(false);
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock(), $this->getRequestStackMock(), $containerUtil, $this->getUrlUtilMock(), $this->getRoutingUtilMock(), $this->getLoggerMock());
        $listener->copyFieldPaletteRecords(3);
        $this->assertSame(0, $this->inputCountGet);

        if (!\defined('CURRENT_ID')) {
            \define('CURRENT_ID', null);
        }

        $containerUtil = $this->getContainerUtilMock(true);
        $containerUtil->expects($this->once())->method('isBackend')->willReturn(true);
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock(), $this->getRequestStackMock(), $containerUtil, $this->getUrlUtilMock(), $this->getRoutingUtilMock(), $this->getLoggerMock());
        $listener->copyFieldPaletteRecords(3);
        $this->assertSame(2, $this->inputCountGet);

        $this->inputCountGet = 0;
        $this->inputId = 5;
        $this->inputDo = null;
        $containerUtil = $this->getContainerUtilMock(true);
        $containerUtil->expects($this->once())->method('isBackend')->willReturn(true);
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock(), $this->getRequestStackMock(), $containerUtil, $this->getUrlUtilMock(), $this->getRoutingUtilMock(), $this->getLoggerMock());
        $listener->copyFieldPaletteRecords(3);
        $this->assertSame(2, $this->inputCountGet);

        $this->inputCountGet = 0;
        $this->inputId = null;
        $this->inputDo = 'news';
        $containerUtil = $this->getContainerUtilMock(true);
        $containerUtil->expects($this->once())->method('isBackend')->willReturn(true);
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock(), $this->getRequestStackMock(), $containerUtil, $this->getUrlUtilMock(), $this->getRoutingUtilMock(), $this->getLoggerMock());
        $listener->copyFieldPaletteRecords(3);
        $this->assertSame(2, $this->inputCountGet);

        $GLOBALS['TL_DCA']['tl_news']['fields'] = ['Felder'];
        $this->loadDataContainerCount = 0;
        $this->inputCountGet = 0;
        $this->inputId = 5;
        $this->inputDo = 'news';
        $containerUtil = $this->getContainerUtilMock(true);
        $containerUtil->expects($this->once())->method('isBackend')->willReturn(true);
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock(), $this->getRequestStackMock(), $containerUtil, $this->getUrlUtilMock(), $this->getRoutingUtilMock(), $this->getLoggerMock());
        $listener->copyFieldPaletteRecords(3);
        $this->assertSame(2, $this->inputCountGet);
        $this->assertSame(1, $this->loadDataContainerCount);
        $this->assertSame($this->inputId, $this->mockedMethodResult[0]);
        $this->assertSame(3, $this->mockedMethodResult[1]);
        $this->assertSame('tl_news', $this->mockedMethodResult[2]);
        $this->assertSame(['Felder'], $this->mockedMethodResult[3]);
    }
}
