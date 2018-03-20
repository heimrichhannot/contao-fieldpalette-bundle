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

class CallbackListenerTest extends ContaoTestCase
{
    protected $testCounter = 0;

    protected function setUp()
    {
        parent::setUp();
        $this->testCounter = 0;
    }

    public function testSetTable()
    {
        $listener = new CallbackListener($this->getFrameworkMock(), $this->getModelManagerMock(), $this->getDcaHandlerMock());

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

    /**
     * @return \Contao\CoreBundle\Framework\ContaoFramework|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getFrameworkMock()
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
    protected function getModelManagerMock()
    {
        $manager = $this->createMock(FieldPaletteModelManager::class);
        $manager->method('getModelByTable')->willReturnCallback(function ($table) {
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
    protected function getDcaHandlerMock()
    {
        $handler = $this->createMock(DcaHandler::class);
        $handler->method('getPaletteFromRequest')->willReturn('tl_news');

        return $handler;
    }
}
