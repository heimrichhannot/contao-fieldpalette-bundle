<?php

/*
 * Copyright (c) 2021 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Element;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use HeimrichHannot\FieldpaletteBundle\DcaHelper\DcaHandler;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

/**
 * @property mixed        $act
 * @property mixed|string $cssClass
 * @property mixed        $table
 * @property mixed        $syncId
 * @property mixed        $do
 * @property mixed        $ptable
 * @property mixed        $pid
 * @property mixed        $modalTitle
 * @property mixed        $fieldpaletteKey
 * @property mixed        $id
 * @property mixed        $fieldpalette
 * @property mixed        $popup
 */
class ButtonElement
{
    private ContaoFramework $framework;
    private Environment $twig;
    private DcaHandler $dcaHandler;
    private Utils $utils;
    private ParameterBagInterface $parameterBag;

    private string $defaultTable;
    protected array $options = [];

    public function __construct(
        ContaoFramework $framework,
        Environment $twig,
        DcaHandler $dcaHandler,
        Utils $utils,
        ParameterBagInterface $parameterBag
    ) {
        $this->framework = $framework;
        $this->twig = $twig;
        $this->dcaHandler = $dcaHandler;
        $this->utils = $utils;
        $this->parameterBag = $parameterBag;

        $this->defaultTable = $parameterBag->get('huh.fieldpalette.table');
    }

    /**
     * Set an object property.
     *
     * @param string $property
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
        $this->options[$property] = $value;
    }

    /**
     * Return an object property.
     *
     * @param string $property
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        if (isset($this->options[$property])) {
            return $this->options[$property];
        }
    }

    /**
     * Check whether a property is set.
     *
     * @param string $property
     *
     * @return bool
     */
    public function __isset($property)
    {
        if (property_exists($this, $property)) {
            return true;
        }

        return isset($this->options[$property]);
    }

    public function generate(): string
    {
        $parameter = $this->options;
        $parameter['href'] = $this->generateHref();

        $attributes = '';

        if (\is_array($this->options['attributes'])) {
            foreach ($this->options['attributes'] as $key => $arrValues) {
                $attributes .= implode(' ', $this->options['attributes']);
            }
        }

        $parameter['attributes'] = $attributes;

        return $this->twig->render(
            '@HeimrichHannotContaoFieldpalette/element/fieldpalette_element_button.html.twig',
            $parameter
        );
    }

    public function setType($strType): void
    {
        $this->act = $strType;
        $this->cssClass = $strType;

        switch ($strType) {
            case 'create':
                $this->cssClass = 'header_new';
                break;
        }
    }

    public function setModalTitle($varValue): self
    {
        $this->options['modalTitle'] = $varValue;

        return $this;
    }

    public function setHref($varValue): self
    {
        $this->options['href'] = $varValue;

        return $this;
    }

    public function setTitle($varValue): self
    {
        $this->options['title'] = $varValue;

        return $this;
    }

    public function setLabel($varValue): self
    {
        $this->options['label'] = $varValue;

        return $this;
    }

    public function setId($varValue): self
    {
        $this->options['id'] = $varValue;

        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function addOptions(array $arrOptions = []): self
    {
        $this->options = array_merge($this->options, $arrOptions);

        return $this;
    }

    public function setAttributes(array $attributes): self
    {
        $this->options['attributes'] = $attributes;

        return $this;
    }

    public function getHref(): string
    {
        return $this->generateHref();
    }

    protected function generateHref(): string
    {
        $parameter = $this->prepareParameter($this->act);

        // for nested fielpalettes, fieldpalette must always be dca context
        if ($parameter['table'] !== $this->table) {
            $parameter['table'] = $this->table;
        }

        if (isset($parameter['popup']) && $parameter['popup']) {
            $parameter['popup'] = 1;
            $this->options['attributes']['onclick'] =
                'onclick="FieldPaletteBackend.openModalIframe({\'action\':\'' . DcaHandler::FieldpaletteRefreshAction . '\',\'syncId\':\'' . $this->syncId
                . '\',\'width\':768,\'title\':\'' . StringUtil::specialchars(sprintf($this->modalTitle, $this->id)) . '\',\'url\':this.href});return false;"';
        }

        // TODO: DC_TABLE : 2097 - catch POST and Cookie from saveNClose and do not redirect and just close modal
        //		$strUrl = \Haste\Util\Url::addQueryString('nb=1', $strUrl);

        // required by DC_TABLE::getNewPosition() within nested fieldpalettes
        $parameter['mode'] = 2;

        return $this->utils->routing()->generateBackendRoute($parameter, true);
    }

    /**
     * @throws \Exception
     */
    protected function prepareParameter($act): array
    {
        $parameters = [
            'do' => $this->do,
            'ptable' => $this->ptable,
            'table' => $this->table,
            'act' => $this->act,
            'pid' => $this->pid,
            'id' => $this->id,
            'nb' => 1, // don't show saveNclose button
            $this->fieldpaletteKey => $this->fieldpalette,
            'popup' => $this->popup,
        ];

        $allowed = array_keys($parameters);

        switch ($act) {
            case 'create':
                $allowed = ['do', 'ptable', 'table', 'act', 'pid', 'fieldpalette', 'popup', 'popupReferer', 'nb'];

                // nested fieldpalettes
                if (
                    $this->ptable === $this->defaultTable
                    && ($model = $this->framework->getAdapter(FieldPaletteModel::class)->findByPk($this->pid))
                ) {
                    $parameters['table'] = $this->dcaHandler->getParentTable($model, $model->id);
                }
                break;
            case 'toggle':
                $allowed = ['do', 'table', 'state', 'tid', 'id'];
                $parameters['id'] = $this->pid;
                break;
            case 'edit':
                $allowed = ['do', 'table', 'ptable', 'act', 'id', 'popup', 'popupReferer', 'nb'];
                break;
            case 'copy':
                $allowed = ['do', 'table', 'act', 'id', 'popup', 'popupReferer'];
                break;
            case 'show':
                $allowed = ['do', 'table', 'act', 'id', 'popup', 'popupReferer'];
                break;
            case 'delete':
                $allowed = ['do', 'table', 'act', 'id'];
                break;
        }

        $parameters = array_intersect_key($parameters, array_flip($allowed));

        return $parameters;
    }
}
