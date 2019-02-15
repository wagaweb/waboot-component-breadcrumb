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
	 * WabootBreadcrumbItem constructor.
	 *
	 * @param $label
	 * @param string|null $link
	 */
	public function __construct($label, $link = null) {
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
		$this->link = $link;
	}

	/**
	 * @return string
	 */
	public function getRel() {
		return $this->rel;
	}

	/**
	 * @param string $rel
	 */
	public function setRel( $rel ) {
		$this->rel = $rel;
	}
}