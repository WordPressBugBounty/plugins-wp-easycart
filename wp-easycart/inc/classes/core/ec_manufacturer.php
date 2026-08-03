<?php
class ec_manufacturer {
	public $manufacturer_id;
	public $name;

	function __construct( $id, $name ) {
		$this->manufacturer_id = (int) $id;
		$this->name = $name;
	}
}
