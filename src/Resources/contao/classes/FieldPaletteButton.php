<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2016 Heimrich & Hannot GmbH
 *
 * @package ${CARET}
 * @author  Rico Kaltofen <r.kaltofen@heimrich-hannot.de>
 * @license http://www.gnu.org/licences/lgpl-3.0.html LGPL
 */

namespace HeimrichHannot\FieldPalette;


use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;

class FieldPaletteButton
{

	/**
	 * Current object instance (do not remove)
	 *
	 * @var FieldPaletteButton|object
	 */
	protected static $objInstance;

	protected $arrOptions = [];

	protected function __construct()
	{
		$this->init();
	}

	/**
	 * Prevent cloning of the object (Singleton)
	 */
	final public function __clone()
	{
	}

	/**
	 * Instantiate a new user object (Factory)
	 *
	 * @return static The object instance
	 */
	public static function getInstance()
	{
		if (static::$objInstance === null) {
			static::$objInstance = new static();
		}

		return static::$objInstance;
	}

	public function setType($strType)
	{
		$this->act      = $strType;
		$this->cssClass = $strType;

		switch ($strType) {
			case 'create':
				$this->cssClass = 'header_new';
				break;
		}
	}

	public function setModalTitle($varValue)
	{
		$this->arrOptions['modalTitle'] = $varValue;

		return $this;
	}

	public function setHref($varValue)
	{
		$this->arrOptions['href'] = $varValue;

		return $this;
	}

	public function setTitle($varValue)
	{
		$this->arrOptions['title'] = $varValue;

		return $this;
	}

	public function setLabel($varValue)
	{
		$this->arrOptions['label'] = $varValue;

		return $this;
	}

	public function setId($varValue)
	{
		$this->arrOptions['id'] = $varValue;

		return $this;
	}

	public function generate()
	{
		$objT = new \FrontendTemplate('fieldpalette_button_default');
		$objT->setData($this->arrOptions);

		$objT->href = $this->generateHref();

		$strAttribues = '';

		if (is_array($this->arrOptions['attributes'])) {
			foreach ($this->arrOptions['attributes'] as $key => $arrValues) {
				$strAttribues .= implode(' ', $this->arrOptions['attributes']);
			}
		}
		
		

		$objT->attributes = strlen($strAttribues) > 0 ? ' ' . $strAttribues : '';

		return $objT->parse();
	}

	protected function generateHref()
	{
		$strUrl = $this->base;

		$arrParameters = $this->prepareParameter($this->act);

        // for nested fielpalettes, fieldpalette must always be dca context
        if($arrParameters['table'] != $this->table)
        {
            $arrParameters['table'] = $this->table;
        }

		foreach ($arrParameters as $key => $value) {
			$strUrl = \Haste\Util\Url::addQueryString($key . '=' . $value, $strUrl);
		}

		if (in_array('popup', $arrParameters)) {
			$strUrl                           = \Haste\Util\Url::addQueryString('popup=1', $strUrl);
			$this->arrOptions['attributes']['onclick'] =
				'onclick="FieldPaletteBackend.openModalIframe({\'action\':\'' . FieldPalette::$strFieldpaletteRefreshAction . '\',\'syncId\':\'' . $this->syncId
				. '\',\'width\':768,\'title\':\'' . specialchars(sprintf($this->modalTitle, $this->id)) . '\',\'url\':this.href});return false;"';

		}

		$strUrl = \Haste\Util\Url::addQueryString('rt=' . \RequestToken::get(), $strUrl);
		// TODO: DC_TABLE : 2097 - catch POST and Cookie from saveNClose and do not redirect and just close modal
//		$strUrl = \Haste\Util\Url::addQueryString('nb=1', $strUrl);

		// required by DC_TABLE::getNewPosition() within nested fieldpalettes
		$strUrl = \Haste\Util\Url::addQueryString('mode=2', $strUrl);

		return $strUrl;
	}

	protected function prepareParameter($act)
	{
		$arrParameters = [
			'do'                   => $this->do,
			'ptable'               => $this->ptable,
			'table'                => $this->table,
			'act'                  => $this->act,
			'pid'                  => $this->pid,
			'id'                   => $this->id,
			$this->fieldpaletteKey => $this->fieldpalette,
			'popup'                => $this->popup
        ];

		$arrAllowed = array_keys($arrParameters);

		switch ($act) {
			case 'create':
				$arrAllowed = ['do', 'ptable', 'table', 'act', 'pid', 'fieldpalette', 'popup', 'popupReferer'];

                // nested fieldpalettes
                if($this->ptable == \Config::get('fieldpalette_table') && ($objModel = FieldPaletteModel::findByPk($this->pid)) !== null)
                {
                    $arrParameters['table'] = FieldPalette::getParentTable($objModel, $objModel->id);
                }
				break;
			case 'toggle':
				$arrAllowed          = ['do', 'table', 'state', 'tid', 'id'];
				$arrParameters['id'] = $this->pid;
				break;
			case 'edit':
				$arrAllowed = ['do', 'table', 'act', 'id', 'popup', 'popupReferer'];
				break;
            case 'copy':
                $arrAllowed = ['do', 'table', 'act', 'id', 'popup', 'popupReferer'];
                break;
			case 'show':
				$arrAllowed = ['do', 'table', 'act', 'id', 'popup', 'popupReferer'];
				break;
			case 'delete':
				$arrAllowed = ['do', 'table', 'act', 'id'];
				break;
		}

		$arrParameters = array_intersect_key($arrParameters, array_flip($arrAllowed));

		return $arrParameters;
	}

	protected function init()
	{
        	$this->arrOptions['base'] = 'contao';
	}

	/**
	 * Set an object property
	 *
	 * @param string $strKey
	 * @param mixed  $varValue
	 */
	public function __set($strKey, $varValue)
	{
		$this->arrOptions[$strKey] = $varValue;
	}


	/**
	 * Return an object property
	 *
	 * @param string $strKey
	 *
	 * @return mixed
	 */
	public function __get($strKey)
	{
		if (isset($this->arrOptions[$strKey])) {
			return $this->arrOptions[$strKey];
		}
	}


	/**
	 * Check whether a property is set
	 *
	 * @param string $strKey
	 *
	 * @return boolean
	 */
	public function __isset($strKey)
	{
		return isset($this->arrOptions[$strKey]);
	}


	public function getOptions()
	{
		return $this->arrOptions;
	}

	public function addOptions(array $arrOptions = [])
	{
		$this->arrOptions = array_merge($this->arrOptions, $arrOptions);

		return $this;
	}

	public function setAttributes(array $arrAttributes)
	{
		$this->arrOptions['attributes'] = $arrAttributes;

		return $this;
	}

	public function getHref()
	{
		return $this->generateHref();
	}
}
