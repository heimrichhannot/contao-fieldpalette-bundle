<?php

/*
 * Copyright (c) 2018 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\FieldpaletteBundle\Element;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\RequestToken;
use HeimrichHannot\FieldPalette\FieldPalette;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use HeimrichHannot\UtilsBundle\Url\UrlUtil;
use Twig\Environment;

class ButtonElement
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var array
     */
    protected $options = [];
    /**
     * @var ContaoFramework
     */
    private $framework;
    /**
     * @var string
     */
    private $defaultTable;
    /**
     * @var UrlUtil
     */
    private $urlUtil;

    public function __construct(ContaoFramework $framework, string $table, Environment $twig, UrlUtil $urlUtil)
    {
        $this->framework = $framework;
        $this->defaultTable = $table;
        $this->twig = $twig;
        $this->options['base'] = 'contao';
        $this->urlUtil = $urlUtil;
    }

    /**
     * Set an object property.
     *
     * @param string $property
     * @param mixed  $value
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
     *
     * @return mixed
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

    public function generate()
    {
        $parameter = $this->options;
        $parameter['href'] = $this->generateHref();

        $attributes = '';

        if (is_array($this->options['attributes'])) {
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

    public function setType($strType)
    {
        $this->act = $strType;
        $this->cssClass = $strType;

        switch ($strType) {
            case 'create':
                $this->cssClass = 'header_new';
                break;
        }
    }

    public function setModalTitle($varValue)
    {
        $this->options['modalTitle'] = $varValue;

        return $this;
    }

    public function setHref($varValue)
    {
        $this->options['href'] = $varValue;

        return $this;
    }

    public function setTitle($varValue)
    {
        $this->options['title'] = $varValue;

        return $this;
    }

    public function setLabel($varValue)
    {
        $this->options['label'] = $varValue;

        return $this;
    }

    public function setId($varValue)
    {
        $this->options['id'] = $varValue;

        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function addOptions(array $arrOptions = [])
    {
        $this->options = array_merge($this->options, $arrOptions);

        return $this;
    }

    public function setAttributes(array $attributes)
    {
        $this->options['attributes'] = $attributes;

        return $this;
    }

    public function getHref()
    {
        return $this->generateHref();
    }

    protected function generateHref()
    {
        $url = $this->base;

        $parameter = $this->prepareParameter($this->act);

        // for nested fielpalettes, fieldpalette must always be dca context
        if ($parameter['table'] !== $this->table) {
            $parameter['table'] = $this->table;
        }

        foreach ($parameter as $key => $value) {
            $url = $this->urlUtil->addQueryString($key.'='.$value, $url);
        }

        if (in_array('popup', $parameter, true)) {
            $url = $this->urlUtil->addQueryString('popup=1', $url);
            $this->options['attributes']['onclick'] =
                'onclick="FieldPaletteBackend.openModalIframe({\'action\':\''.FieldPalette::$strFieldpaletteRefreshAction.'\',\'syncId\':\''.$this->syncId
                .'\',\'width\':768,\'title\':\''.specialchars(sprintf($this->modalTitle, $this->id)).'\',\'url\':this.href});return false;"';
        }

        $url = $this->urlUtil->addQueryString('rt='.RequestToken::get(), $url);
        // TODO: DC_TABLE : 2097 - catch POST and Cookie from saveNClose and do not redirect and just close modal
        //		$strUrl = \Haste\Util\Url::addQueryString('nb=1', $strUrl);

        // required by DC_TABLE::getNewPosition() within nested fieldpalettes
        $url = $this->urlUtil->addQueryString('mode=2', $url);

        return $url;
    }

    protected function prepareParameter($act)
    {
        $parameters = [
            'do' => $this->do,
            'ptable' => $this->ptable,
            'table' => $this->table,
            'act' => $this->act,
            'pid' => $this->pid,
            'id' => $this->id,
            $this->fieldpaletteKey => $this->fieldpalette,
            'popup' => $this->popup,
        ];

        $allowed = array_keys($parameters);

        switch ($act) {
            case 'create':
                $allowed = ['do', 'ptable', 'table', 'act', 'pid', 'fieldpalette', 'popup', 'popupReferer'];

                // nested fieldpalettes
                if (
                    $this->ptable === $this->defaultTable &&
                    ($model = $this->framework->getAdapter(FieldPaletteModel::class)->findByPk($this->pid))
                ) {
                    $parameters['table'] = FieldPalette::getParentTable($model, $model->id);
                }
                break;
            case 'toggle':
                $allowed = ['do', 'table', 'state', 'tid', 'id'];
                $parameters['id'] = $this->pid;
                break;
            case 'edit':
                $allowed = ['do', 'table', 'act', 'id', 'popup', 'popupReferer'];
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
