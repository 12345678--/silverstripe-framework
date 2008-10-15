<?php
/**
 * A simple extension to dropdown field, pre-configured to list countries.
 * It will default to the country of the current visiotr.
 * @package forms
 * @subpackage fields-relational
 */
class CountryDropdownField extends DropdownField {
	protected $defaultToVisitorCountry = true;
	
	function __construct($name, $title, $source = null, $value = "", $form=null) {
		if(!is_array($source)) {
			$source = Geoip::getCountryDropDown();
		}
		parent::__construct($name, $title, $source, $value, $form);
	}
	
	function defaultToVisitorCountry($val) {
		$this->defaultToVisitorCountry = $val;
	}
	
	function Field() {
		$source = $this->getSource();
		if($this->defaultToVisitorCountry && !$this->value || !isset($source[$this->value])) {
			$this->value = ($vc = Geoip::visitor_country()) ? $vc : Geoip::$default_country_code;
		}
		return parent::Field();
	}
}

?>