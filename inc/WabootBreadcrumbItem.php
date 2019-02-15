<?php

class WabootBreadcrumbItem implements \WBF\components\breadcrumb\BreadcrumbItemInterface{
	/**
	 * @var string
	 */
	private $link;
	/**
	 * @var string
	 */
	private $label;
	/**
	 * @var string
	 */
	private $rel;
	/**
	 * @var string
	 */
	private $class;

	/**
	 * WabootBreadcrumbItem constructor.
	 *
	 * @param string $label
	 * @param string|null $link
	 */
	public function __construct($label, $link = null) {
		if(!\is_string($label)){
			$label = '-invalidLabel-';
		}
		$this->setLabel($label);
		if($link !== null && \is_string($link)){
			$this->setLink($link);
		}
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * @param string $label
	 */
	public function setLabel( $label ) {
		$this->label = $label;
	}

	/**
	 * @return string
	 */
	public function getLink() {
		return $this->link;
	}

	/**
	 * @param string $link
	 */
	public function setLink( $link ) {
		if(\is_string($link)){
			$this->link = $link;
		}
	}

	/**
	 * @return string
	 */
	public function getRel() {
		if($this->rel === null){
			return 'bookmark';
		}
		return $this->rel;
	}

	/**
	 * @param string $rel
	 */
	public function setRel( $rel ) {
		if(\is_string($rel)){
			$this->rel = $rel;
		}
	}

	/**
	 * @return string
	 */
	public function getClass() {
		if($this->class === null){
			return 'waboot-breadcrumb-item';
		}
		return 'waboot-breadcrumb-item '.$this->class;
	}

	/**
	 * @param string $class
	 */
	public function setClass( $class ) {
		if(\is_string($class)){
			$this->class = $class;
		}
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		if($this->getLink() !== null){
			return sprintf('<a href="%s" rel="%s" class="%s" title="%s">%s</a>',$this->getLink(),$this->getRel(),$this->getClass(),esc_attr($this->getLabel()),$this->getLabel());
		}
		return sprintf('<span class="%s">%s</span>',$this->getClass(),$this->getLabel());
	}
}