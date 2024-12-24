<?php
/*

expressionPHP
version 0.3

discrete evaluator of integers, booleans, strings,
                      string functions and integer expressions

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

*/



namespace Kalei\Expression;



// If the script is called directy from the CLI
// it is evaluated the expression passed as first param
// and result returned to stdout.

if( count( get_included_files() ) === 1 )
{
    if( ! isset( $argv[1] ) )
    {
        echo "expected expression to evaluate as first (and only) parameter\n";
        exit();
    }

    $result = expression( $argv[1], $error );
    if( $error )
    {
        echo "Error: $error\n";
    }
    else
    {
        $type = gettype( $result );
        echo "Error:  none\nType:   $type";
        if( $type === 'boolean' )
        {
            $result = $result ? 'true' : 'false';
        }
        echo "\nResult: $result\n";
    }
    exit;
}



//
// functions
//

const FUNCTIONS = [
    "fact"          => "Fac",
    "pow"           => "Pow",
    "max"           => "Max",
    "min"           => "Min",
    "average"       => "Avg",
    "avg"           => "Avg",
    "length"        => "Len",
    "len"           => "Len",
    "strlen"        => "Len",
    "strpos"        => "Stp",
    "trim"          => "Trm",
    "substr"        => "Sst",
    "bin2hex"       => "B2h",
    "chr"           => "Chr",
    "hex2bin"       => "H2b",
    "htmlentities"  => "Hte",
    "ord"           => "Ord",
    "ltrim"         => "Ltr",
    "rtrim"         => "Rtr",
    "sha1"          => "Sha",
    "md5"           => "Md5"
];



//
// main
//

function expression( $expression, &$error = "", $parameters = null, &$elapsedTime = null )
{
    //
    // execution time measurement
    //

    $startTime = _microsecondsSince();



    //
    // parameters check and storing
    //

    if( ! is_string( $expression ) )
    {
        throw new \Exception( "expression must be string" );
    }

    if( $parameters === null )
    {
        $parameters = [];
    }
    else
    {
        foreach( $parameters as $name => $value )
        {
            if( ! is_string( $name ) )
            {
                throw new \Exception( "parameter name must be a string" );
            }

            if( ! ctype_alnum( $name ) )
            {
                throw new \Exception( "parameter name must be an alphanumeric string" );
            }

            if( ctype_digit( substr( $name, 0, 1 ) ) )
            {
                throw new \Exception( "parameter name must not begin with a digit" );
            }

            if( ! is_int( $value ) && ! is_string( $value ) && ! is_bool( $value ) )
            {
                throw new \Exception( "parameter value must be a integer, boolean or string" );
            }

            if( array_key_exists( $name, FUNCTIONS ) !== false )
            {
                throw new \Exception( "parameter name must not be a reserved keyword: $name" );
            }
        }
    }

    // `true` and `false` are added as parameters to improve speed parsing here --> [***]

    $parameters[ 'true' ] = true;
    $parameters[ 'false'] = false;

    uksort( $parameters, function ( $key1, $key2 )
    {
        return strlen( $key2 ) - strlen( $key1 );
    } );

    // store params for fastest parsing

    $paramsName = [];
    $paramsVal  = [];
    $paramsLen  = [];

    foreach( $parameters as $name => $value )
    {
        $paramsName[] = $name;
        $paramsVal[]  = $value;
        $paramsLen[]  = strlen( $name );
    }

    $paramsCnt = count( $parameters );

    //
    // main
    //

    $eval = new \StdClass();
    $eval->expression  = $expression . "\0\0\0\0";
    // the parser will always met a 0x00 at the end

    $eval->paramsName  = $paramsName;
    $eval->paramsVal   = $paramsVal;
    $eval->paramsLen   = $paramsLen;
    $eval->paramsCnt   = $paramsCnt;

    $eval->cursor = 0;
    $eval->RBC    = 0; // Round Brackets Count
    $eval->error  = "";

    //
    // begin
    //

    $result = _coreParse( $eval, -1, true, false, $tokenThatCausedBreak );

    if( $eval->error )
    {
        $error = "$eval->error\n$eval->expression\n" . str_repeat( " ", $eval->cursor ) . "^---\n";
        $elapsedTime = _microsecondsSince( $startTime );
        return null;
    }
    else
    {
        $error = "";
        $elapsedTime = _microsecondsSince( $startTime );
        return $result;
    }
}



//
// private functions
//



// Parses and evaluates expression parts between lower precedence operators:
// `+` sum
// `-` subtraction
// `.` string concatenation
// `|` logical OR
// and
// `)` closed round bracked (decrementing count)
// breakOnRBC, breakOnEOF, breakOnCOMMA define cases where the function must return
// The function is going to be called recursively
// The function calls `_coreParseHiger()` to parse parts between higher
// precedence operators.

function _coreParse( $eval,
                     $breakOnRBC,                   // If open brackets count goes down to this count then exit;
                     $breakOnEOF,                   // exit if the end of the string is met;
                     $breakOnCOMMA,                 // exit if a comma is met;
                    &$tokenThatCausedBreak = null )  // if not null the token/symbol that caused the function to exit;
{
    $leftToken = null;
    $rightToken =null;

    $value = 0;
    $result = null;
    $rightToken = "Sum";

    do
    {
        $leftToken = $rightToken;

        $value = _coreParseHigher( $eval,
                                   null,        // `null` as value lets $rightToken be accepted despite type
                                   "Mul",
                                   false,
                                   $rightToken );
        if( $eval->error ) return null;

        /**/if( $leftToken === "Sum" )
        {
            if( $result === null )
            {
                $result = $value;
            }
            elseif( is_int( $value ) && is_int( $result ) )
            {
                $result = $result + $value;
            }
            else
            {
                $eval->error = "left and right addends must be integers";
                return null;
            }
        }
        elseif( $leftToken === "Sub" )
        {
            if( is_int( $value ) && is_int( $result ) )
            {
                $result = $result - $value;
            }
            else
            {
                $eval->error = "left and right operands must be integers";
                return null;
            }
        }
        elseif( $leftToken === "Conc" )
        {
            if( is_string( $value ) && is_string( $result ) )
            {
                $result = $result . $value;
            }
            else
            {
                $eval->error = "left and right operands must be strings";
                return null;
            }
        }
        elseif( $leftToken === "Or" )
        {
            if( is_bool( $value ) && is_bool( $result ) )
            {
                $result = (bool) ( $result | $value );
            }
            else
            {
                $eval->error = "left and right operands must be booleans";
                return null;
            }
        }
    }
    while( $rightToken === "Sum" || $rightToken === "Sub" || $rightToken === "Conc" || $rightToken === "Or" );

    // A round close bracket:
    // check for negative count.

    if( $rightToken === "RBC" )
    {
        $eval->RBC--;
        if( $eval->RBC < 0 )
        {
            $eval->error = "unexpected close round bracket";
            return null;
        }
    }

    // Returns the token that caused the function to exit

    $tokenThatCausedBreak = $rightToken;

    // Check if we must exit

    if( ( $eval->RBC === $breakOnRBC ) || ( $breakOnEOF && $rightToken === "EOF" ) || ( $breakOnCOMMA && $rightToken === "COM" ) )
    {
        return $result;
    }

    // If not it's an error.

    switch( $rightToken )
    {
        case "EOF":
            $eval->error = "unexpected end of expression";
            break;

        case "RBC":
            $eval->error = "unexpected close round bracket";
            break;

        case "COM":
            $eval->error = "unexpected comma";
            break;

        default:
            $eval->error = "unexpected symbol";
            break;
    }

    return null;
}



// Evaluates expression parts between higher precedencedence operators...
// `*` product
// `/` division
// `&` logical AND
// `!` factorial
// `^` exponentiation
//
// ...`(` open round braket (calls _coreParse with a mutual recursion logic)...
//
// ...and functions


// Expression parts can be explicit values or functions
// "breakOn" parameter define cases where the function must exit.

function _coreParseHigher( $eval,
                           $leftValue, // The value (already fetched) on the left to be computed with what follows
                           $op,        // the operation to perform;
                           $isExponent,// is an exponent being evaluated ?
                          &$leftOp )   // RETURN: factors are over, this is the next operator (token).
{
    $token = "";
    $nextOp = "";

    $rightValue = 0;
    $sign = 1;
    $not = 0;

    $functionTokens =
    [
        "Fac",
        "Pow",
        "Max",
        "Min",
        "Avg",
        "Len",
        "Trm",
        "Sst",
        "Stp",
        "B2h",
        "Chr",
        "H2b",
        "Hte",
        "Md5",
        "Ord",
        "Ltr",
        "Rtr",
        "Sha"
    ];

    do
    {
        $rightValue = _parseToken( $eval, $token, $leftValue );
        if( $eval->error ) return null;

        // Unary minus, plus, logical not?
        // store the sign and get the next token

        if( $token === "Sub" )
        {
            $sign = -1;
            $rightValue = _parseToken( $eval, $token, $leftValue );
            if( $eval->error ) return null;
        }
        elseif( $token === 'Not')
        {
            $not = 1;
            $rightValue = _parseToken( $eval, $token, $leftValue );
            if( $eval->error ) return null;
        }
        elseif( $token === "Sum" )
        {
            $sign = 1;
            $rightValue = _parseToken( $eval, $token, $leftValue );
            if( $eval->error ) return null;
        }
        else
        {
            $sign = 1;
            $not = 0;
        }

        // Open round bracket?
        // The expression between brackets is evaluated.

        if( $token === "RBO" )
        {
            $eval->RBC++;

            $rightValue = _coreParse( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return null;

            $token = "Val";
        }

        // A function ?

        if( in_array( $token, $functionTokens ) )
        {
            $rightValue = _evaluateFunction( $eval, $token );
            if( $eval->error ) return null;

            $token = "Val";
        }

        // Excluded previous cases then
        // the token must be a value.

        if( $token !== "Val" )
        {
            $eval->error = "expected value";
            return null;
        }

        // Get beforehand the next token
        // to see if it's an exponential or factorial operator

        _parseToken( $eval, $nextOp, $rightValue );
        if( $eval->error ) return null;

        if( $nextOp === "Fct" )
        {
            $rightValue = _parseFactorial( $eval, $rightValue, $nextOp );
            if( $eval->error ) return null;
        }

        if( $nextOp === "Exc" )
        {
            $rightValue = _evaluateExponentiation( $eval, $rightValue, $nextOp );
            if( $eval->error ) return null;
        }

        // multiplication/division is finally
        // calculated

        if( $op === "Mul" )
        {
            if( $leftValue === null )
            {
                if( $sign === -1 && ! is_int( $rightValue ) )
                {
                    $eval->error = "unary minus before non integer value";
                    return null;
                }
                if( $not === 1 && ! is_bool( $rightValue ) )
                {
                    $eval->error = "unary `not` before non boolean value";
                    return null;
                }

                if( is_int( $rightValue ) )
                {
                    $leftValue = $rightValue * $sign;
                }
                elseif( is_bool( $rightValue ) )
                {
                    $leftValue = $not === 1 ? ( ! $rightValue ) : $rightValue;
                }
                else
                {
                    $leftValue = $rightValue;
                }
            }
            elseif( is_int( $leftValue ) && is_int( $rightValue ) )
            {
                $leftValue = $leftValue * $rightValue * $sign;
            }
            else
            {
                $eval->error = "left and right operands must be integers";
                return null;
            }
        }
        elseif( $op === "Div" )
        {
            if( is_int( $leftValue ) && is_int( $rightValue ) )
            {
                $leftValue = intdiv( $leftValue, $rightValue ) * $sign;

            }
            else
            {
                $eval->error = "left and right operands must be integers";
                return null;
            }

        }
        elseif( $op === "And" )
        {
            if( is_bool( $leftValue ) && is_bool( $rightValue ) )
            {
                if( $not === 0 )
                {
                    $leftValue = (bool) ( $leftValue & $rightValue );
                }
                else
                {
                    $leftValue = (bool) ( $leftValue & ( ! $rightValue ) );
                }
            }
            else
            {
                $eval->error = "left and right operands must be booleans";
                return null;
            }
        }

        // The next operator has already been fetched.

        $op = $nextOp;

        // Go on as long as `*`, `/` or `&` operators are met...
        // ...unless an exponent is evaluated
        // (because exponentiation ^ operator have higher precedence)
    }
    while( ( $op === "Mul" || $op === "Div" || $op === "And" ) && ! $isExponent );

    $leftOp = $op;

    return $leftValue;
}



// Evaluates the expression(s) (comma separated params if multiple)
// inside the round brackets then computes the function
// specified by the token `func`.

function _evaluateFunction( $eval, $func )
{
    // Skip an open round bracket incementing count

    _parseToken( $eval, $token, null );
    if( $eval->error ) return null;

    if( $token !== "RBO" )
    {
        $eval->error = "expected open round bracket after function name";
        return null;
    }

    $eval->RBC++;

    switch( $func )
    {
        case "Fac":
            $operand = _coreParse( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return null;

            if( ! is_int( $operand ) )
            {
                $eval->error = "expected integer";
                return null;
            }

            if( $operand < 0 )
            {
                $eval->error = "attempt to evaluate factorial of negative number";
                return null;
            }
            if( $operand > 20 )
            {
                $eval->error = "result exceeds signed integer";
                return null;
            }

            $tmp = 1;
            $result = 1;
            for( $tmp = 1; $tmp <= $operand; $tmp++ )
            {
                $result *= $tmp;
            }
            break;



        case "Pow":
            $base = _coreParse( $eval, -1, false, true );
            if( $eval->error ) return null;

            if( ! is_int( $base ) )
            {
                $eval->error = "expected integer";
                return null;
            }

            $exponent = _coreParse( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return null;

            if( ! is_int( $exponent ) )
            {
                $eval->error = "expected integer";
                return null;
            }

            $result = pow( $base, $exponent );

            if( ! is_int( $result ) )
            {
                $eval->error = "result is not integer";
                return null;
            }
            break;



        case "Max":
            $greatestValue = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_int( $greatestValue ) )
            {
                $eval->error = "expected integer";
                return null;
            }

            while( $tokenThatCausedBreak === "COM" )
            {
                $greaterValueMaybe = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_int( $greaterValueMaybe ) )
                {
                    $eval->error = "expected integer";
                    return null;
                }

                if( $greaterValueMaybe > $greatestValue )
                {
                    $greatestValue = $greaterValueMaybe;
                }
            }
            $result = $greatestValue;
            break;



        case "Min":
            $smallestValue = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_int( $smallestValue ) )
            {
                $eval->error = "expected integers";
                return null;
            }

            while( $tokenThatCausedBreak === "COM" )
            {
                $smallerValueMaybe = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_int( $smallerValueMaybe ) )
                {
                    $eval->error = "expected integers";
                    return null;
                }

                if( $smallerValueMaybe < $smallestValue )
                {
                    $smallestValue = $smallerValueMaybe;
                }
            }
            $result = $smallestValue;
            break;



        case "Avg":
            $total = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_int( $total ) )
            {
                $eval->error = "expected integers";
                return null;
            }

            $count = 1;
            while( $tokenThatCausedBreak === "COM" )
            {
                $value = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_int( $value ) )
                {
                    $eval->error = "expected integers";
                    return null;
                }

                $total += $value;
                $count++;
            }

            $result = intdiv( $total, $count );
            break;



        case "Len":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            $result = strlen( $text );
            break;



        case "Trm":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            if( $tokenThatCausedBreak === "COM" )
            {
                $charsToTrim = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_string( $charsToTrim ) )
                {
                    $eval->error = "expected string";
                    return null;
                }

                $result = trim( $text, $charsToTrim );
            }
            else
            {
                $result = trim( $text );
            }
            break;



        case "Sst":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            if( $tokenThatCausedBreak !== "COM" )
            {
                $eval->error = "expected comma";
                return null;
            }

            $startIndex = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_int( $startIndex ) )
            {
                $eval->error = "expected integer";
                return null;
            }

            if( $tokenThatCausedBreak === "RBC" )
            {
                $result = substr( $text, $startIndex );
            }
            elseif( $tokenThatCausedBreak === "COM" )
            {
                $charactersCount = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_int( $charactersCount ) )
                {
                    $eval->error = "expected integer";
                    return null;
                }

                $result = substr( $text, $startIndex, $charactersCount );
            }
            else
            {
                $eval->error = "expected integer or close round bracket";
                return null;
            }
            break;



        case "Stp":
            $haystack = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_string( $haystack ) )
            {
                $eval->error = "expected string";
                return null;
            }

            if( $tokenThatCausedBreak !== "COM" )
            {
                $eval->error = "expected comma";
                return null;
            }

            $needle = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_string( $needle ) )
            {
                $eval->error = "expected string";
                return null;
            }

            if( $tokenThatCausedBreak === "COM" )
            {
                $offset = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_int( $offset ) )
                {
                    $eval->error = "expected integer";
                    return null;
                }

                $result = strpos( $haystack, $needle, $offset );
            }
            else
            {
                $result = strpos( $haystack, $needle );
            }

            if( $result === false )
            {
                $result = -1;
            }
            break;



        case "B2h":
            $bin = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }
            if( ! is_string( $bin ) )
            {
                $eval->error = "expected string";
                return null;
            }

            $result = bin2hex( $bin );
            break;



        case "Chr":
            $ascii = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_int( $ascii ) )
            {
                $eval->error = "expected integer";
                return null;
            }

            $result = chr( $ascii );
            break;



        case "H2b":
            $hex = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_string( $hex ) || ! ctype_xdigit( $hex ) )
            {
                $eval->error = "expected hex string";
                return null;
            }

            $result = hex2bin( $hex );
            break;



        case "Hte":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }
            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            $result = htmlentities( $text );
            break;



        case "Md5":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            $result = md5( $text );
            break;



        case "Ord":
            $char = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_string( $char ) )
            {
                $eval->error = "expected character";
                return null;
            }

            $result = ord( $char );
            break;



        case "Ltr":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            if( $tokenThatCausedBreak === "COM" )
            {
                $charsToTrim = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_string( $charsToTrim ) )
                {
                    $eval->error = "expected string";
                    return null;
                }

                $result = ltrim( $text, $charsToTrim );
            }
            else
            {
                $result = ltrim( $text );
            }
            break;



        case "Rtr":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            if( $tokenThatCausedBreak === "COM" )
            {
                $charsToTrim = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( ! is_string( $charsToTrim ) )
                {
                    $eval->error = "expected string";
                    return null;
                }

                $result = rtrim( $text, $charsToTrim );
            }
            else
            {
                $result = rtrim( $text );
            }
            break;



        case "Sha":
            $text = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error )
            {
                return null;
            }

            if( ! is_string( $text ) )
            {
                $eval->error = "expected string";
                return null;
            }

            $result = sha1( $text );
            break ;



        default:
            $result = null;
            break;
    }

    return $result;
}



// Evaluates an exponentiation.

function _evaluateExponentiation( $eval,
                                  $base,      // The base has already been fetched;
                                 &$rightOp )  // RETURN: the token (operator) that follows.
{
    $exponent = 0;
    $result = 0;

    if( ! is_int( $base ) )
    {
        $eval->error = "base must be integer";
        return null;
    }

    $exponent = _coreParseHigher( $eval, 1, "Mul", true, $rightOp );
    if( $eval->error ) return null;

    if( ! is_int( $exponent) )
    {
        $eval->error = "exponent must be integer";
        return null;
    }

    if( $exponent < 0 )
    {
        $eval->error = "exponent must be zero or positive";
        return null;
    }

    $result = pow( $base, $exponent );

    if( $result >= 9223372036854775808 /* -2^63 */ || $result <= -9223372036854775808 /* -2^63 */ )
    {
        $eval->error = "exponentiation result exceeds integer limit";
        return null;
    }

    return intval( $result );
}



// Evaluates a factorial

function _parseFactorial( $eval,
                            $value,     // The value to compute has already been fetched;
                           &$rightOp )  // RETURN: the token (operator) that follows.
{
    if( $value < 0 )
    {
        $eval->error = "attempt to evaluate factorial of negative number";
        $rightOp = "ERR";
        return null;
    }

    if( ! is_int( $value ) )
    {
        $eval->error = "attempt to evaluate factorial of a non-integer value";
        $rightOp = "ERR";
        return null;
    }

    if( $value > 20 )
    {
        $eval->error = "factorial result exceeds integer limit";
        return null;
    }

    $result = 1;
    for( $i = 1; $i <= $value; $i++ )
    {
        $result *= $i;
    }

    _parseToken( $eval, $rightOp, $value );
    if( $eval->error ) return null;

    return $result;
}



// Parses the next token and advances the cursor.
// The function returns a number, a string or a boolean if the token is a value or a param,
// otherwise `null` is returned. Whitespace is skipped.

function _parseToken( $eval,
                     &$token,      // RETURN: the token.
                      $leftValue ) // in case of `!' operator: a value on the left implies `factorial`
{   //                                                         otherwise implies `logical NOT`
    // skip whitespace                                         (and a trailing value is expected)

    while( true )
    {
        $chr = ( $eval->expression )[ $eval->cursor ];
        if( $chr === " " || $chr === "\n" || $chr === "\r" || $chr === "\t" )
        {
            $eval->cursor++;
        }
        else
        {
            break;
        }
    }

    // value maybe

    $chr = ( $eval->expression )[ $eval->cursor ];
    if( ( $chr >= "0" && $chr <= "9" ) || $chr === "\"" ) // there is no need to catch 't'rue and 'f'alse here
    {                                               // as they are added as params [***]
        $value = _parseValue( $eval );
        if( $eval->error )
        {
            $token = "ERR";
            return null;
            /*--- EXIT POINT ---*/
        }
        else
        {
            $token = "Val";
            return $value;
            /*--- EXIT POINT ---*/
        }
    }

    // parameter maybe

    $n = $eval->paramsCnt;
    for( $i = 0; $i < $n; $i++ )
    {
        if( substr( $eval->expression, $eval->cursor, $eval->paramsLen[ $i ] ) === $eval->paramsName[ $i ] )
        {
            $token = "Val";
            $eval->cursor += $eval->paramsLen[ $i ];
            return $eval->paramsVal[ $i ];
            /*--- EXIT POINT ---*/
        }
    }

    // operator maybe

    switch( $chr )
    {
        case "+":
            if( _twoConsecutivePlusTokensMaybe( $eval ) )
            {
                $token = "ERR"; // a subsequent + is not allowed
            }
            else
            {
                $token = "Sum";
            }
            break;

        case "-":
            $token = "Sub";
            $eval->cursor++;
            break;

        case ".":
            $token = "Conc";
            $eval->cursor++;
            break;

        case "*":
            $token = "Mul";
            $eval->cursor++;
            break;

        case "/":
            $token = "Div";
            $eval->cursor++;
            break;

        case "^":
            $token = "Exc";
            $eval->cursor++;
            break;

        case "!": // if it trails a number then `Factorial` otherwise `Not`
            $token = is_int( $leftValue ) ? "Fct" : "Not";
            $eval->cursor++;
            break;

        case "&":
            $token = "And";
            $eval->cursor++;
            if( ( $eval->expression )[ $eval->cursor ] === "&" ) // && is an alias of &
            {
                $eval->cursor++;
            }
            break;

        case "|":
            $token = "Or";
            $eval->cursor++;
            if( ( $eval->expression )[ $eval->cursor ] === "|" ) // || is an alias of |
            {
                $eval->cursor++;
            }
            break;

        case "(":
            $token = "RBO";
            $eval->cursor++;
            break;

        case ")":
            $token = "RBC";
            $eval->cursor++;
            break;

        case "\0":
            $token = "EOF";
            $eval->cursor++;
            break;

        case ",":
            $token = "COM";
            $eval->cursor++;
            break;

        default:
            $token = null;
            break;
    }

    if( $token !== null )
    {
        return null;
        /*--- EXIT POINT ---*/
    }

    // function maybe

    foreach( FUNCTIONS as $name => $funtok )
    {
        $len = strlen( $name ) ;
        if( substr( $eval->expression, $eval->cursor, $len ) === $name )
        {
            $token = $funtok;
            $eval->cursor += $len;
            return null;
            /*--- EXIT POINT ---*/
        }
    }

    // none of the above: raise error

    $token = "ERR";
    $eval->error = "unexpected symbol";
    return null;
}



// Parses what follows an (already fetched) plus token
// ensuring that two consecutive plus are not present.
// Expressions such as 2++2 (binary plus
// followed by unitary plus) are not allowed.
// Advances the cursor.
// Always returns 0.

function _twoConsecutivePlusTokensMaybe( $eval )
{
    do
    {
        $eval->cursor++;
        $chr = ( $eval->expression )[ $eval->cursor ];
    } while( $chr === " " || $chr === "\n" || $chr === "\r" || $chr === "\t" );

    return ( $chr === "+" );
}



// Parse an explicit value: integer, string or boolean
// cursor is not changed (advanced) in case of parsing error,
// and null is returned

function _parseValue( $eval )
{
    $result = null;
    $str = $eval->expression;
    $idx = $eval->cursor;

    // Skip leading whitespace (despite should have already been skipped previously)

    while( $str[ $idx ] === ' ' || $str[ $idx ] === '\t' || $str[ $idx ] === '\n' || $str[ $idx ] === '\r' )
    {
        $idx++;
    }

    // Check for boolean

    if( substr( $str, $idx, 4 ) === "true" )
    {
        $idx += 4;
        $eval->cursor = $idx;
        return true;
    }

    if( substr( $str, $idx, 5 ) === "false" )
    {
        $idx += 5;
        $eval->cursor = $idx;
        return false;
    }

    // It is a string: parse it

    if( $str[ $idx ] === "\"" )
    {
        $result = _parseString( $str, $idx );
        if( $result === false ) // parse error
        {
            $result = null;
        }
        $eval->cursor = $idx;
        return $result;
    }

    // It is an integer: parse it

    $isnum = false; // Will be set to true as a number is detected

    $sign = 1;
    if( $str[ $idx ] === '-' )
    {
        $sign = -1;
        $idx++;
    }
    elseif( $str[ $idx ] === '+' )
    {
        $idx++;
    }

    // Parse the integer part

    $integer_part = 0;
    while( $str[ $idx ] >= '0' && $str[ $idx ] <= '9' )
    {
        $integer_part = $integer_part * 10 + (int)$str[ $idx ];
        $idx++;
        $isnum = true;
    }

    // Done

    if( $isnum ) // parsing succeeded
    {
        $result = $sign * $integer_part;
    }

    $eval->cursor = $idx;
    return $result;
}



// parse a string starting from `$cursor`
// false is returned if parsing fails

function _parseString( $str, &$cursor )
{
    // calling function already detected open double quotes

    $cursor++;

    $result = "";

    while( true ) // if parsed string unexpectedly ends null byte is reached raising error
    {
        $chr = $str[ $cursor ];

        if( $chr < " " ) // characters code below ascii value 0x20 MUST be escaped
        {
            $cursor++;
            return false;
        }

        if( $chr === "\"" ) // closing double quotes
        {
            $cursor++;
            return $result;
        }

        if( $chr === "\\" ) // escape character
        {
            $cursor++;
            $chr = $str[ $cursor ];

            /**/if( $chr === "n" )  // lf
            {
                $result .= "\n";
            }
            elseif( $chr === "r" )  // cr
            {
                $result .= "\r";
            }
            elseif( $chr === "t" )  // tab
            {
                $result .= "\t";
            }
            elseif( $chr === "0" )  // null byte
            {
                $result .= "\0";
            }
            elseif( $chr === "\"" ) // double quote
            {
                $result .= "\"";
            }
            elseif( $chr === "\\" ) // backslash
            {
                $result .= "\\";
            }
            elseif( $chr === "/" )  // slash (it can be optionally escaped too)
            {
                $result .= "/";
            }
            // do implement \0x{hex byte} ?
            else
            {
                break;
            }
            $cursor++;
        }
        else // regular character
        {
            $result .= $chr;
            $cursor++;
        }
    }

    return false;
}



// returns microseconds since passed timestamp (in µs)
// or unix epoch time

function _microsecondsSince( $since = 0 )
{
    $mt = explode( ' ', microtime() );
    return intval( $mt[1] * 1E6 ) + intval( round( $mt[0] * 1E6 ) ) - intval( $since );
}