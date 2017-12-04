# PreciseBaseConverter
Converts any decimal number (ex: 3.14159) to any base, from 2 to 36, using bc* math functions.

## Why use this over base_convert?
base_convert fails when the number you are looking to convert contains a fraction.  For instance, 
	base_convert(M_PI,10,2) 
returns 111001001001010010111001001000011011011011010.  

Additionally, base_convert has the potential to lose precision when using large numbers.  For instance, 
	base_convert(pow(2,100),10,2) 
returns 100100000001110101111100111101110011101010110000110.

Finally, base_convert fails if you are looking to convert to a large base (>36).  For instance,
	base_convert(100,10,50)
returns false, while issuing the following warning: "PHP Warning:  base_convert(): Invalid `to base' (50)"

## Usage
There are two functions that can be used: precise_DecToBaseConversion() and precise_BaseToDecConversion().  Use precise_DecToBaseConversion() to convert any decimal (base 10) number to any given base, and BaseToDecConversion to convert any given base to decimal.  In both functions, the following arguments are used:
	@$number - The number you are looking to convert.
	@$base - The non-decimal base you are looking to convert from/to.
	@$scale (optional) - How much precision is requested.  Defaults to the current scale used by bc* math operations
	@$digits (optional) - The digits to be used.  Defaults to the character set [0-9a-z].  This can be ignored as long as you are working with bases of size 36 and under.

### Examples:
	precise_DecToBaseConversion(M_PI, 2, 20): 11.00100100001111110110
	precise_DecToBaseConversion(bcpow(2,100), 2): 10000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000
	precise_DecToBaseConversion(100, 50, 0, array_merge(range(0,9),range('a','z'), range('A','Z'))): 20
	precise_BaseToDecConversion(1.1, 3, 5): 1.33333
