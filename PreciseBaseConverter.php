<?php

namespace FourthPlanetDev\MathUtils;

require('MathUtils/MathUtils.php');

/*
 * Objectified version of procedural methods below.
 * */
class PreciseBaseConvert {
	public $scale;

	protected $_number;
	protected $_digits;

	protected $_fraction = array();

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
		if (strpos($number,'.') !== false) {
			list($whole,$decimal) = explode('.',$number);

			$numerator = precise_BaseToDecConversion($decimal,$base,0,$this->_digits);
			$denominator = bcpow($base,strlen($decimal));
		} else {
			$whole = $number;
			$decimal = 0;
		}

		$digit = 0;
		$num_digits = strlen($whole);
		$this->_number = 0;

		while ($digit < $num_digits) {
			$this->_number = bcadd($this->_number,bcmul($this->_digits[$whole[$digit]],bcpow($base, $num_digits-$digit-1)));
			$digit++;
		}

		/* @TODO: reduce $decimal to a lower base, if possible */
		list($numerator,$denominator) = reduceFraction($numerator,$denominator);

		$this->_fraction = array($numerator,$denominator);


	}

	/**
	 * Do the conversion work.
	 * @param int $base - The base to convert to
	 * @param array $repeatNotation - When not empty, instead of repeating..repeating digits, wrap in contents of array.  Note: doing so will remove any scale
	 * @throws \Exception
	 */
	public function toBase($base,$repeatNotation=array()) {
		if (count($this->_digits) < $base) {
			throw new \Exception('Invalid base submitted.');
		}

		if (strpos($this->_number,'.') === false) {
			// whole number or fraction that has repeating digits (i.e., 1/3)
			$int = $this->_number;
			if (empty($this->_fraction)) {
				$decimal = 0;
			} else {
				list($numerator,$denominator) = $this->_fraction;
			}
		} else {
			// easy enough to represent in decimal...but what about other bases???
			list($int,$decimal) = explode('.',$this->_number);
			$decimal = "." . $decimal;
		}

		// add 1 because floor(log(1000000,10)) is returned as 5, not 6
		$retVal = "";
		$topPower = floor(log($int,$base))+1;

		while ($topPower > 0) {
			$expVal = bcpow($base,$topPower,$this->scale);
			$tmp = (int) bcdiv($int,$expVal,$this->scale);
			if ($tmp > 36) echo "$tmp = (int) bcdiv($int,$expVal,{$this->scale})" . PHP_EOL;
			$retVal .= $this->_digits[$tmp];
			$int = bcsub($int,bcmul($tmp,$expVal,$this->scale),0);
			$topPower--;
		}

		$retVal .= $int;
		$retVal = ltrim($retVal, $this->_digits[0]);
		if ($this->scale > 0)
			$retVal .= '.';

		$precisionSoFar=1;

		if ($this->_fraction) {

			list($non_repeating,$repeating) = $this->_longDivision($numerator,$denominator,$base);
			// remove leading 0.
			$retVal .= substr($non_repeating,2);
			if ($repeating) {
				if ($repeatNotation) {
					array_push($repeatNotation,'');	// in case of "wrap" only at beginning..
					list($begin,$end) = $repeatNotation;
					$retVal .= $begin . $repeating . $end;
					return $retVal;
				} else {
					while ((strlen(strstr($retVal,'.'))-1) < $this->scale) {
						$retVal .= $repeating;
					}

					$retVal = substr($retVal,0,strlen($retVal) - ((strlen(strstr($retVal,'.'))-1) - $this->scale));

					return $retVal;
				}


			} else {
				// this doesn't work..
				$retVal = substr($retVal,0,strlen($retVal) - ((strlen(strstr($retVal,'.'))-1) - $this->scale));
				return $retVal;
			}
		} else {
			while (bccomp($decimal,0,$this->scale) > 0 && $precisionSoFar<=$this->scale) {
				$expVal = bcdiv(1,bcpow($base,$precisionSoFar,$this->scale),$this->scale+5);
				if (bccomp(0, $expVal,$this->scale) ==0 )
					break;
					$tmp = (int) bcdiv($decimal, $expVal,$this->scale+5);
					if ($tmp > 36) echo "Base: $base; $tmp = (int) bcdiv($decimal,$expVal,{$this->scale}+5)" . PHP_EOL;
					$retVal .= $this->_digits[$tmp];
					$decimal = bcsub($decimal,bcmul($tmp,$expVal,$this->scale+5),$this->scale);
					$precisionSoFar++;
			}
			return $retVal;
		}


	}

	protected function _longDivision($number,$divisor,$base=10) {
		// actually performs long division..
		$answer = floor($number / $divisor);
		if ($remainder = ($number - $answer))
		{
			$answer .= ".";

			$length = 2;
			while ($remainder != 0 ) {

				$remainder *= $base;
				if (isset($repeatingStack[$remainder])) {
					return array(substr($answer,0,$repeatingStack[$remainder]),
						substr($answer,$repeatingStack[$remainder]));
				}

				$repeatingStack[$remainder] = $length++;
				$nextDigit = floor($remainder / $divisor);
				$answer .= $this->_digits[$nextDigit];
				$remainder -= ($divisor*$nextDigit);
			}
		}
		return array($answer,null);
	}

	/**
	 * Convert to multiple bases at once
	 * @param array $bases
	 * @param array $repeatNotation - Notation to use for repeating digits
	 */
	public function toBases($bases,$repeatNotation=array()) {
		$ret = array();
		foreach($bases as $base) {
			$ret[$base] = $this->toBase($base,$repeatNotation);
		}
		return $ret;
	}

	/**
	 * Sets the number to work with
	 * @param Numeric $number
	 * @param int $base - Defaults to 10
	 */
	public function setNumber($number,$base=10) {
		if (!$this->_isValidNumber($number,$base))
			throw new \Exception("Invalid number.");
		elseif ($base == 10) {
			if (strpos($number,'.') !== false) list($whole,$fraction) = explode('.',$number);
			else list($whole,$fraction) = array($number,0);
			$this->_number = $whole;
			$denominator = pow(10,strlen($fraction));
			$this->_fraction = reduceFraction($fraction, $denominator);
		} else {
			$this->_toBaseTen($number,$base);
		}
	}

	protected function _isValidNumber($number,$base)
	{
		if ($base == 10) {
			return (preg_match('/^\d+(\.\d+)?$/',$number));
		} else {
			$escapedDigts = array_slice($this->_digits,0,$base);
			foreach($escapedDigts as &$digit)
				$digit = preg_quote($digit);
			unset($digit);
			$validDigitRegex = implode("|",$escapedDigts);
			return (preg_match("/^($validDigitRegex)+(\.($validDigitRegex)+)?$/",$number));
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

	/**
	 * Adds multiple PreciseBaseConvert objects together
	 * @param PreciseBaseConvert
	 * @param PreciseBaseConvert[optional]...
	 * @return PreciseBaseConvert
	 */
	public static function add() {
		$Numbers = func_get_args();
		$val = new PreciseBaseConvert(0);
		$val->_fraction = array(0,1);

		foreach($Numbers as $Number) {
			$val->_number += $Number->_number;
			if (isset($Number->_fraction)) {
				list($numerator1,$denominator1) = $val->_fraction;
				list($numerator2,$denominator2) = $Number->_fraction;

				if ($denominator1 == $denominator2) {
					// ex: 1/6 + 2/6 = 3/6
					$val->_fraction = array($numerator1+$numerator2,$denominator1);
				} elseif ($denominator1 % $denominator2 == 0) {
					// ex: 1/6 + 1/3 = 1/6 + 2/6 = 3/6
					$val->_fraction = array($numerator1 + ($numerator2*($denominator1/$denominator2)),$denominator1);
				} elseif ($denominator2 % $denominator1 == 0) {
					// ex: 1/3 + 1/6 = 2/6 + 1/6 = 3/6
					$val->_fraction = array($numerator2 + ($numerator1*($denominator2/$denominator1)),$denominator2);
				} else {
					// ex: 1/3 + 1/2 = 2/6 + 3/6 = 5/6
					$val->_fraction = array(($numerator1*$denominator2)+($numerator2*$denominator1),$denominator1*$denominator2);
				}

				list($numerator1,$denominator1) = $val->_fraction;
				if ($numerator1 > $denominator1) {
					$val->_number++;
					$numerator1-=$denominator1;
				}

				$val->_fraction = reduceFraction($numerator1, $denominator1);
			}
		}
		return $val;
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

$Number = PreciseBaseConvert::add(new PreciseBaseConvert(1.5),new PreciseBaseConvert(1.1,3),new PreciseBaseConvert(1.5,6));
// 3/2 + 4/3 + 11/6
// 9/6 + 8/6 + 11/6
// 28/6 --> 4 4/6 --> 4 2/3

echo $Number->toBase(10,array('[','')) . PHP_EOL;
?>