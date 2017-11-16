<?php

namespace FourthPlanetDev;

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
 * @example mantissaBaseConversion(1.5,2) = 1.1
 * @example mantissaBaseConversion(M_PI,8,10) = 3.1103755243
 */
function mantissaBaseConversion($decimal,$base,$scale = null,$digits=array()) {
	if (empty($digits))
	{
		$digits = array_merge(range(0,9),range('a','z'));
	}

	if ($base > count($digits))
	{
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
?>