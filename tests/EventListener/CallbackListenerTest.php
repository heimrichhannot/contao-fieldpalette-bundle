<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Test\EventListener;

use Contao\Controller;
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

    public function setUp()
    {
        parent::setUp();
        $this->testCounter = 0;
    }

    /**
     * @return \Contao\CoreBundle\Framework\ContaoFramework|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getFrameworkMock()
    {
        $controllerAdapter = $this->mockAdapter(['loadDataContainer']);
        $controllerAdapter->method('loadDataContainer')->willReturn(true);

        $framework = $this->mockContaoFramework([
            Controller::class => $controllerAdapter,
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
                    $model->pid = 2;

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
    public function getContainerUtilMock()
    {
        $util = $this->createMock(ContainerUtil::class);

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
        $result = $listener->setTable('null', 1, []);
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
}
