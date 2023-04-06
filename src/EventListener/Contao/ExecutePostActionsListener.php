<?php

/*
 * Copyright (c) 2023 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\EventListener\Contao;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Input;
use Contao\Widget;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;

class ExecutePostActionsListener
{
    private ContaoFramework $contaoFramework;

    public function __construct(ContaoFramework $contaoFramework)
    {
        $this->contaoFramework = $contaoFramework;
    }

    public function __invoke(string $action, DataContainer $dc): void
    {
        if (DcaHandler::FieldpaletteRefreshAction === $action) {
            if ($this->contaoFramework->getAdapter(Input::class)->post('field')) {
                $this->contaoFramework->getAdapter(Controller::class)->loadDataContainer($dc->table);

                $name = $this->contaoFramework->getAdapter(Input::class)->post('field');
                $field = $GLOBALS['TL_DCA'][$dc->table]['fields'][$name];

                // Die if the field does not exist
                if (!\is_array($field)) {
                    header('HTTP/1.1 400 Bad Request');
                    exit('Bad Request');
                }

                /** @var class-string<Widget> $class */
                $class = $GLOBALS['BE_FFL'][$field['inputType']];

                // Die if the class is not defined or inputType is not fieldpalette
                if ('fieldpalette' !== $field['inputType'] || !class_exists($class)) {
                    return;
                }

                $attributes = $this->contaoFramework->getAdapter(Widget::class)
                    ->getAttributesFromDca($field, $name, ($dc->activeRecord->{$name} ?? null), $name, $dc->table, $dc);

                /** @var Widget $widget */
                $widget = new $class($attributes);
                $widget->currentRecord = $dc->id;

                $data = ['field' => $name, 'target' => '#ctrl_'.$name, 'content' => $widget->generate()];

                if ($widget->submitOnChange) {
                    $data['autoSubmit'] = $dc->table;
                }

                exit(json_encode($data));
            }
        }
    }
}
