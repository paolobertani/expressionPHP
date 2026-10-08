expressionPHP - A discrete expression evaluator in PHP
===

&nbsp;

**expression.php** is a lighweight and fast library to evaluate a discrete expressions stored in a string without the need to use PHP's `eval()` function.

&nbsp;

**expressionPHP** allows computation of:

- integer math
- floating point math
- boolean math

String functions and the `.` (concatenation) operator are available to manipulate and evaluate string.

The **`if`** function is available to set a result based on the outcome of a boolean expression.



&nbsp;Example usage:

```php
<?php

// Include in your script

require_once( __DIR__ . "/../expression.php" );


// Let's `expression()` be directly available in your namespace

use function \Kalei\Expression\expression;


// Set parameters that will be referenced inside the expression

$parameters = [
    "string_1" => "foo",
    "string_2" => "BAR",
    "integer_value" => 1
];


// This is the expression to evaluate

$expression = "( strlen( substr( string_1 . string_2, 3 ) ) + integer_value ) ^ 2"


// Evaluation

$result = expression( $expression,
                      $error,         // returned by ref.
                      $parameters,
                      $elapsedTime ); // returned by ref. (µsecs)
             
             
//              
                         
if( $result === null )
{
   echo "Error: $error\n";
}
else
{
    echo "$result\n"; // ==> 16
}

*/

	    
```

---

&nbsp;

### Available Types

&nbsp;

**Signed Integer**

`-12345`

&nbsp;

**Float**

`123.456`
`123.0`
`.123`
 
The dot `.` qualifies the value as float

&nbsp;

**String**

`"foobar"`

`"foo\"bar"`

&nbsp;


Double quotes must be used to enclose the string

**Boolean**

`true`
`false`

&nbsp;

### Common Operators

`+`

`-`

`*`

`/` floating point division

Operands must be numbers of the same type and the result is of the same type of operands. Floating point division is allowed only between floats.

`//` integer division; allowed only between integers

`^` power

`!` factorial; allowed only between integers

`|` bitwise or; allowed only between integers

`&` bitwise and; allowed only between integers

`!` unary not; allowed only before a integer

`||` logical or; allowed only between booleans

`&&` logical and; allowed only between booleans

**Comparison operarors**

Operands must be of the same type and result is always boolean

`==`

`!=`

`>`

`<`

`>=`

`<=`

**String concatenation**

`.` allowed only between strings




&nbsp;

### IF function

`if( (boolean) expression, (any type) result1, (same type) result2 )` `==>` `(same type) result`

`expression` must be of type **boolean**. If the result of the expression is `true` then `result` will be `result1`, otherwise if will be `result2`.

Both `result1` and `result2` are evaluated and must be the of the same type and this will be the type of `result`

&nbsp;

### Functions

&nbsp;

`max( (int|float) value1, (int|float) value2, ... )`

The biggest value passed to the function.

All values must be of the same type.

&nbsp;

`min( (int|float) value1, (int|float) value2, ... )`

The smallest value passed to the function.

All values must be of the same type.

&nbsp;

`avg( (int|float) value1, (int|float) value2, ... )`

The average value of the values passed.

All values must be of the same type.

If values are integers the integer division is used to compute the average value.

&nbsp;

`len( (string) str )` `==>` `(int) result`

or `strlen`

The same of PHP's `mb_strlen` function.

&nbsp;

`trim( (string) str [, (string) characters] )` `==>` `(string) result`

The same of PHP's `trim` function.

&nbsp;

`substr( (string) str, (integer) startIndex [, (integer) numberOfCharacters] )` `==>` `(string) result`

The same of PHP's `mb_substr` function.

&nbsp;

`between( (string) str, (string) left, (string) right [, (string) leftPad, (string) rightPad] ) ` `==>` `(string) result`

The substring in `str` between the string `left` and `right`.

If `left` and/or `right` are not found an empty string is returned.

If `left` is an empty string means that left is the beginning of `str`.

If `right` is an empty string means that right is the end of `str`.

Optionally parameters `leftPad` and `rightPad` will be added at the left and the right of the found string.

&nbsp;

`replace( (string) search, (string) replace, (string) subject )` `==>` `(string) result`

The same of PHP's `str_replace` function.

&nbsp;

`strpos( (string) haystack, (string) needle [, (integer) offset] )` `==>` `(int) result`

The same of PHP's `mb_strpos` function, except that if the searched string is not found then `result` is `-1`

&nbsp;

`bin2hex( (string) text )` `==>` `(string) result`

or `bintohex`. The same of PHP's `bin2hex` function.

&nbsp;

`hex2bin( (string) hexString )` `==>` `(string) result`

or `hextobin`. The same of PHP's `hex2bin` function.

&nbsp;

`chr( (int) asciiValue )` `==>` `(string) character`

The same of PHP's `chr` function.

&nbsp;

`ord( (string) character )` `==>` `(int) asciiValue`

The same of PHP's `ord` function.

&nbsp;

`htmlentities( (string) text )` `==>` `(string) result`

The same of PHP's `htmlentities` function.

&nbsp;

`md5( (string) text )` `==>` `(string) result`

The same of PHP's `md5` function.

&nbsp;

`sha1( (string) text )` `==>` `(string) result`

The same of PHP's `sha1` function.

&nbsp;

`ltrim( (string) str [, (string) characters] )` `==>` `(string) result`

The same of PHP's `ltrim` function.

&nbsp;

`rtrim( (string) str [, (string) characters] )` `==>` `(string) result`

The same of PHP's `rtrim` function.







	







 

###

```
Copyright (c) 2024-2025 Paolo Bertani - Kalei S.r.l.
Licensed under the FreeBSD 2-clause license

-------------------------------------------------------------------------------

FreeBSD 2-clause license

Copyright (c) 2024-2025, Paolo Bertani - Kalei S.r.l.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions  of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.
2. Redistributions  in  binary  form must reproduce the above copyright notice,
   this  list  of  conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS \AS IS\ AND
ANY  EXPRESS  OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES  OF  MERCHANTABILITY  AND  FITNESS  FOR  A  PARTICULAR  PURPOSE  ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT,  INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING,  BUT  NOT  LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE,  DATA,  OR  PROFITS;  OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON  ANY  THEORY  OF  LIABILITY,  WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING  NEGLIGENCE  OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

-------------------------------------------------------------------------------
```
