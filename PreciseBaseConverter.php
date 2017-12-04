<?php

namespace FourthPlanetDev;

/*
 * Objectified version of procedural methods below.
 * */
class PreciseBaseConvert {
	public $scale;

	protected $_number;
	protected $_digits;

	/**
	 * Class constructor
	 * @param Numeric $number
	 * @param [optional] int $base - Defaults to 10.
	 * @param [optional] int $scale - Defaults to ini_get('precision')
	 * @param [optional] array $digits - Defaults to [0-9a-z]
	 */
	public function __construct($number,$base=10,$scale=null,array $digits=array()) {
		if (is_null($scale)) {
			$scale = ini_get('precision');
		}

		$this->scale = $scale;
		if (empty($digits)) {
			$this->resetDigits();
		} else {
			$this->setDigits($digits);
		}

		$this->setNumber($number,$base);

	}

	/**
	 * Called when re/setting the base number we are working with from a non-base 10 perspective.
	 * @param Numeric $number - The number we are working with
	 * @param int $base - It's base.
	 */
	protected function _toBaseTen($number,$base) {
		return precise_BaseToDecConversion($number,$base,$this->scale,$this->_digits);
	}

	/**
	 * Do the conversion work.
	 * @param int $base - The base to convert to
	 * @throws \Exception
	 */
	public function toBase($base) {
		if (count($this->_digits) < $base) {
			throw new \Exception('Invalid base submitted.');
		}

		return precise_DecToBaseConversion($this->_number, $base, $this->scale, $this->_digits);
	}

	/**
	 * Convert to multiple bases at once
	 * @param array $bases
	 */
	public function toBases($bases) {
		$ret = array();
		foreach($bases as $base) {
			$ret[$base] = $this->toBase($base);
		}
		return $ret;
	}

	/**
	 * Sets the number to work with
	 * @param Numeric $number
	 * @param int $base - Defaults to 10
	 */
	public function setNumber($number,$base=10) {
		if ($base == 10) {
			// confirm is a valid number...
			$this->_number = $number;
		} else {
			$this->_number = $this->_toBaseTen($number,$base);
		}
	}

	/**
	 * Call to reset digits that are used to [0-9a-z].  Supports up to base-36
	 */
	public function resetDigits() {
		$this->setDigits(array_merge(range(0,9),range('a','z')));
	}
	/**
	 * Call to update the digits that are used.
	 * @param array $digits
	 */
	public function setDigits($digits) {
		$this->_digits = array_values(array_unique($digits));
	}
}


/**
 * Converts a given decimal number to any given base.
 *
 * @param decimal $decimal - the number to convert (ex: 3.14159)
 * @param int $base - what base we are converting to
 * @param int $scale - how many decimal places to go to.  Defaults to the current scale used by bc* operations.
 * @param array $digits - Override the digits to be used.  Defaults to (0-9a-z)
 * @return string - The Number converted
 *
 * @throws Exception - when not enough digits available for requested base.
 *
 * @example precise_DecToBaseConversion(1.5,2) = 1.1
 * @example precise_DecToBaseConversion(M_PI,8,10) = 3.1103755243
 */
function precise_DecToBaseConversion($decimal,$base,$scale = null,$digits=array()) {
	if (empty($digits)) {
		$digits = array_merge(range(0,9),range('a','z'));
	}

	if ($base > count($digits)) {
		throw new \Exception('Invalid base submitted.');
	}

	if (!isset($scale))
		$scale = (strlen(bcsqrt(2))-2);

	if (strpos($decimal,'.') === false) {
		$int = $decimal;
		$decimal = 0;
	} else {
		list($int,$decimal) = explode('.',$decimal);
		$decimal = "." . $decimal;
	}

	// add 1 because floor(log(1000000,10)) is returned as 5, not 6
	$retVal = "";
	$topPower = floor(log($int,$base))+1;

	while ($topPower > 0) {
		$expVal = bcpow($base,$topPower,$scale);
		$tmp = (int) bcdiv($int,$expVal,$scale);
		$retVal .= $digits[$tmp];
		$int = bcsub($int,bcmul($tmp,$expVal,$scale),0);
		$topPower--;
	}

	$retVal .= $int;
	$retVal = ltrim($retVal,'0');
	if ($scale > 0)
		$retVal .= '.';

	$precisionSoFar=1;

	while (bccomp($decimal,0,$scale) > 0 && $precisionSoFar<=$scale) {
		$expVal = bcdiv(1,bcpow($base,$precisionSoFar,$scale),$scale);
		if (bccomp(0, $expVal,$scale) ==0 )
			break;
		$tmp = (int) bcdiv($decimal, $expVal,$scale);
		$retVal .= $digits[$tmp];
		$decimal = bcsub($decimal,bcmul($tmp,$expVal,$scale),$scale);
		$precisionSoFar++;
	}
	return $retVal;
}

/*
 * Converts a given number in any given base to decimal
 * @param number $number - the number to convert (ex: 1.111)
 * @param int $base - what base we are converting from
 * @param int $scale - how many decimal places to go to.  Defaults to the current scale used by bc* operations.
 * @param array $digits - Override the digits used for the base.  Defaults to (0-9a-z)
 * @return string - The Number converted
 *
 * @throws Exception - when not enough digits available for requested base.
 *
 * @example precise_BaseToDecConversion(1.111,2) = 1.875
 * @example precise_BaseToDecConversion(1.1,3,5) = 1.33333
 */
function precise_BaseToDecConversion($number,$base,$scale = null,$digits = array()) {
	if (empty($digits)) {
		$digits = array_merge(range(0,9),range('a','z'));
	}

	if ($base > count($digits)) {
		throw new \Exception('Invalid base submitted.');
	}

	$digits = array_flip($digits);

	if (!isset($scale))
		$scale = (strlen(bcsqrt(2))-2);

	if (strpos($number,'.') !== false)
		list($int,$decimal) = explode('.',$number);
	else
		list($int,$decimal) = array($number,"0");

	$digit = 0;
	$num_digits = strlen($int);
	$retVal = 0;


	while ($digit < $num_digits) {
		$retVal = bcadd($retVal,bcmul($digits[$int[$digit]],bcpow($base, $num_digits-$digit-1)));
		$digit++;
	}
	$digit = 0;
	$num_digits = strlen($decimal);

	while ($digit < $num_digits) {
		$retVal = bcadd($retVal, bcmul($digits[$decimal[$digit]],bcpow($base,-($digit+1),$scale+5),$scale+5), $scale+5);
		$digit++;
	}

	return bcadd($retVal,0,$scale);
}
?>