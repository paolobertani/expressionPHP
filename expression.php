<?php
/*

expressionPHP
version 0.4

discrete evaluator of integers, floats, booleans, strings,
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



/*

    +++ things to do
    ??? things I'm not sure about / design decisions to kale

*/



namespace Kalei\Expression;



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

// If the script is called directy from the CLI
// it is evaluated the expression passed as first param
// and result returned to stdout.

function main( $argv )
{
    if( count( get_included_files() ) === 1 )
    {
        if( ! isset( $argv[1] ) )
        {
            echo "expected expression to evaluate as first (and only) parameter\n";
            exit();
        }

        $inStyleOutError = "rainbow"; // error message style
        $result = expression( $argv[1], $inStyleOutError );
        if( $inStyleOutError )
        {
            echo "$inStyleOutError\n";
        }
        else
        {
            $type = gettype( $result );
            if( $type === "double" ) $type = "64 bit float";
            if( $type === "integer") $type = "64 bit signed integer";
            echo "Error:  none\nType:   $type\n";
            if( $type === 'boolean' )
            {
                $result = $result ? 'true' : 'false';
            }
            echo "Result: $result\n";
        }
        exit;
    }
} main( $argv );



//
// expression
//

function expression( $expression, &$error = null, $parameters = null, &$elapsedTime = null )
{
    //
    // execution time measurement
    //

    $startTime = _microsecondsSince();

    //
    // initialize
    //

    $eval = new \StdClass();
    $eval->expression  = $expression . "\0\0\0\0";
    // The parser, in any case (also when skipping
    // characters) will always met a 0x00 if the
    // end of the expression is met. A bunch
    // of 0x00 ensures this.

    $eval->cursor = 0;
    $eval->RBC    = 0; // Round Brackets Count
    $eval->error  = "";



    //
    // check expression's parameter type
    //

    if( $eval->error === "" )
    {
        if( ! is_string( $eval->expression ) )
        {
            $eval->error = "expression must be string";
            $eval->expression = "[ " . gettype( $eval->expression ) . " ] given!";
            $eval->cursor = -1;
        }
    }



    //
    // `$parameters` parameter checking and storing
    //

    if( $eval->error === "" )
    {
        if( $parameters === null )
        {
            $parameters = [];
        }
        else
        {
            $paramIdx = 0;
            foreach( $parameters as $name => $value )
            {
                $paramIdx++;
                $nameCheck = $name;

                if( $eval->error === "" )
                {
                    if( ! is_string( $nameCheck ) )
                    {
                        $eval->error = "parameter name must be string (param. nr. $paramIdx)";
                        $eval->expression = "[ " . gettype( $nameCheck ) . " ] given!";
                        $eval->cursor = -1;
                    }
                }

                if( $eval->error === "" )
                {
                    if( substr( $nameCheck, 0, 1 ) === "$" ) // `$` is allowed as 1st char (php style)
                    {
                        $nameCheck = substr( $nameCheck, 1 );
                    }

                    if( strpos( "$", $nameCheck ) !== false )
                    {
                        $eval->error = "dolar sign `$` may eventually be only the first character (param. nr. $paramIdx)";
                        $eval->expression = "$name";
                        $eval->cursor = -1;
                    }
                }

                if( $eval->error === "" )
                {
                    $nameCheck = str_replace( "_", "X", $nameCheck );

                    if( ! ctype_alnum( $nameCheck ) )
                    {
                        $eval->error = "parameter name may contain letters, digits or underscores (param. nr. $paramIdx)";
                        $eval->expression = "$name";
                        $eval->cursor = -1;
                    }
                }

                if( $eval->error === "" )
                {
                    if( ctype_digit( substr( $name, 0, 1 ) ) )
                    {
                        $eval->error = "parameter name must not begin with a digit (param. nr. $paramIdx)";
                        $eval->expression = "$name";
                        $eval->cursor = -1;
                    }
                }

                if( $eval->error === "" )
                {
                    if( ! is_int( $value ) && ! is_string( $value ) && ! is_bool( $value ) && ! is_float ( $value ) )
                    {
                        $eval->error = "parameter value must be integer, float, boolean or string (param. nr. $paramIdx)";
                        $eval->expression = "$name : [ " . gettype( $value ) . " ] given!";
                        $eval->cursor = -1;
                    }
                }

                if( $eval->error === "" )
                {
                    if( array_key_exists( $name, FUNCTIONS ) !== false )
                    {
                        $eval->error = "parameter name must not be a reserved keyword (param. nr. $paramIdx)";
                        $eval->expression = "$name : used";
                        $eval->cursor = -1;
                    }
                }
            }
        }
    }

    if( $eval->error === "" )
    {
        // `true` and `false` are added as parameters to improve speed parsing here --> [***]

        $parameters[ 'true' ] = true;
        $parameters[ 'false'] = false;

        uksort( $parameters, function ( $key1, $key2 )
        {
            return strlen( $key2 ) - strlen( $key1 );
        } );

        // optimization in params storing

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

        $eval->paramsName  = $paramsName;
        $eval->paramsVal   = $paramsVal;
        $eval->paramsLen   = $paramsLen;
        $eval->paramsCnt   = $paramsCnt;
    }



    //
    // do it
    //

    if( $eval->error === "" )
    {
        $result = _coreParse( $eval, -1, true, false, $tokenThatCausedBreak );
    }



    //
    // got error?
    //


    if( $eval->error )
    {
        switch( $error )
        {
            case "shortest":
                $error = "error";
                break;

            case "short":
                $error = "Error: {$eval->error}";
                break;

            case "extended":
            case "multiline":
                $error = "Error: {$eval->error}\n{$eval->expression}\n";
                if( $eval->cursor >= 0 ) $error .= str_repeat( " ", $eval->cursor ) . "^---\n";
                break;

            case "rainbow":
            case "multicolor":
                $term = getenv( "TERM" );
                $termHasColors = isset( $term ) && in_array ( $term,
                  [ "xterm", "xterm-256color", "screen", "linux", "cygwin", "rxvt-unicode-256color" ] );

                $Bred = $termHasColors ? "\033[1;31m" : "" ; // BOLD red
                $redd = $termHasColors ?   "\033[31m" : "" ; // red
                $yelw = $termHasColors ?   "\033[33m" : "" ; // yellow
                $mage = $termHasColors ?   "\033[35m" : "" ; // magenta
                $REST = $termHasColors ?    "\033[0m" : "" ; // restore

                $error = "{$Bred}Error:$REST {$redd}{$eval->error}$REST\n$yelw{$eval->expression}$REST\n";
                if( $eval->cursor >= 0 ) $error .= str_repeat( " ", $eval->cursor ) . "$mage^---$REST\n";
                break;

            default:
                $error = $eval->error;
                break;
        }

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
            /**/if( $result === null )
            {
                $result = $value;
            }
            elseif( is_int( $value ) && is_int( $result ) )
            {
                $result = $result + $value;
            }
            elseif( is_float( $value ) && is_float( $result ) )
            {
                $result = $result + $value;
            }
            elseif( is_string( $value ) && is_string( $result ) )
            {
                $result = $result . $value;
            }
            else
            {
                $eval->error = "left and right addends must be both integers, floats or strings";
                return null;
            }
        }
        elseif( $leftToken === "Sub" )
        {
            /**/if( is_int( $value ) && is_int( $result ) )
            {
                $result = $result - $value;
            }
            elseif( is_float( $value ) && is_float( $result ) )
            {
                $result = $result - $value;
            }
            else
            {
                $eval->error = "left and right operands must be both integers or both float";
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
    while( $rightToken === "Sum" || $rightToken === "Sub" || $rightToken === "Or" );

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

        /**/if( $op === "Mul" )
        {
            if( $leftValue === null )
            {
                if( $sign === -1 && ! is_int( $rightValue ) && ! is_float( $rightValue ) )
                {
                    $eval->error = "unary minus before non integer nor float value";
                    return null;
                }
                if( $not === 1 && ! is_bool( $rightValue ) )
                {
                    $eval->error = "unary `not` before non boolean value";
                    return null;
                }

                /**/if( is_int( $rightValue ) || is_float( $rightValue ) )
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
            elseif( is_float( $leftValue ) && is_float( $rightValue ) )
            {
                $leftValue = $leftValue * $rightValue * $sign;
            }
            else
            {
                $eval->error = "left and right operands must be both integers or float";
                return null;
            }
        }
        elseif( $op === "Idv" )
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
        elseif( $op === "Div" )
        {
            if( is_float( $leftValue ) && is_float( $rightValue ) )
            {
                $leftValue = $leftValue / $rightValue * $sign;
            }
            else
            {
                $eval->error = "left and right operands must be float";
                if( is_int( $leftValue ) && is_int( $rightValue ) ) $eval->error .= "; use `//` for integer division";
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
    while( ( $op === "Mul" || $op === "Idv" || $op === "Div" || $op === "And" ) && ! $isExponent );

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



        case "Pow": // ??? Allow mixing integers and float and unpredictable result type?
            $base = _coreParse( $eval, -1, false, true );
            if( $eval->error ) return null;

            if( ! is_int( $base ) && ! is_float( $base ))
            {
                $eval->error = "expected integer or float";
                return null;
            }

            $exponent = _coreParse( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return null;

            if( ! is_int( $exponent ) && ! is_float( $exponent ))
            {
                $eval->error = "expected integer or float";
                return null;
            }

            $result = _evaluatePow( $eval, $base, $exponent );
            if( $result === null ) return null;
            break;



        case "Max":
            $greatestValue = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return null;

            if( ! is_int( $greatestValue ) && ! is_float( $greatestValue ) )
            {
                $eval->error = "expected integer or float";
                return null;
            }

            while( $tokenThatCausedBreak === "COM" )
            {
                $greaterValueMaybe = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( gettype( $greaterValueMaybe ) !== gettype( $greatestValue ) )
                {
                    $eval->error = "values must be all integers or all floats";
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

            if( ! is_int( $smallestValue ) && ! is_float( $smallestValue ) )
            {
                $eval->error = "expected integer or float";
                return null;
            }

            while( $tokenThatCausedBreak === "COM" )
            {
                $smallerValueMaybe = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( gettype( $smallerValueMaybe ) !== gettype( $smallestValue ) )
                {
                    $eval->error = "values must be all integers or all floats";
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

            if( ! is_int( $total ) && ! is_float( $total ) )
            {
                $eval->error = "expected integer or float";
                return null;
            }

            $count = 1;
            while( $tokenThatCausedBreak === "COM" )
            {
                $value = _coreParse( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return null;

                if( gettype( $value ) !== gettype( $total ) )
                {
                    $eval->error = "values must be all integers or all floats";
                    return null;
                }

                $total += $value;
                $count++;
            }

            $result = is_int( $total ) ? intdiv( $total, $count ) : $total / $count;
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

// ??? Allow mixing integers and float and unpredictable result type?

function _evaluateExponentiation( $eval,
                                  $base,      // The base has already been fetched;
                                 &$rightOp )  // RETURN: the token (operator) that follows.
{
    $exponent = 0;
    $result = 0;

    if( ! is_int( $base ) && ! is_float( $base ) )
    {
        $eval->error = "base must be integer or floar";
        return null;
    }

    $exponent = _coreParseHigher( $eval, 1, "Mul", true, $rightOp );
    if( $eval->error ) return null;

    if( ! is_int( $exponent ) && ! is_float( $exponent ) )
    {
        $eval->error = "exponent must be integer or float";
        return null;
    }

    $result = _evaluatePow( $eval, $base, $exponent );

    return $result;
}


function _evaluatePow( $eval, $base, $exponent )
{
    if( is_float( $base ) && is_float( $exponent ) )
    {
        $result = pow( $base, $exponent );

        if( is_nan( $result ) )
        {
            $eval->error = "exponentiation results in a complex number";
            return null;
        }

        return $result;
    }

    if( gettype( $base ) !== gettype( $exponent ) )
    {
        $eval->error = "base and exponent must be both floats or integers";
        return null;
    }

    $result = pow( $base, $exponent );

    if( is_nan( $result ) )
    {
        $eval->error = "exponentiation results in a complex number";
        return null;
    }

    if( ! is_int( $result ) )
    {
        $eval->error = "exponentiation doesn't fit an integer value";
        return null;
    }

    return $result;
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

    if( $value > 170 )
    {
        $eval->error = "factorial result exceeds both integer and float limit";
        return null;
    }

    $result = 1;
    for( $i = 1; $i <= $value; $i++ )
    {
        $result *= $i;
    }

    if( $value <= 20 ) // fits integers
    {
        $result = intval( $result );
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
    if( ( $chr >= "0"   && $chr <= "9"  )
       || $chr === "\"" || $chr === "." ) // there is no need to catch 't'rue and 'f'alse here
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

        case "*":
            $token = "Mul";
            $eval->cursor++;
            break;

        case "/":
            $token = "Div";
            $eval->cursor++;

            if( ( $eval->expression )[ $eval->cursor ] === "/" )
            {
                $token = "Idv";
                $eval->cursor++;
            }
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
    $str = $eval->expression; // copy-on-write is avoided
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

    // Last chanche: a integer or a float

    // +++ Parse a hex value too?

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

    $isfloat = false;

    // Parse the integer part maybe

    $integer_part = 0;
    while( $str[ $idx ] >= '0' && $str[ $idx ] <= '9' && $str[ $idx ] !== "." )
    {
        $integer_part = $integer_part * 10 + (int)$str[ $idx ];
        $idx++;
        $isnum = true;
    }

    // Parse the float part maybe

    if( $str[ $idx ] === "." )
    {
        $idx++;
        $isfloat = true;
        $decimal_part = 0;

        $decimal_digits = 0;
        while( $str[ $idx ] >= '0' && $str[ $idx ] <= '9' )
        {
            $isnum = true;
            $decimal_digits++;
            $decimal_part = ( $decimal_part * 10 ) + intval( $str[ $idx ] );
            $idx++;
        }

        if( $decimal_digits === 0 )
        {
            $isnum = false;
            $result = null;
        }
        else
        {
            $isnum = true;
        }
    }

    // parse the exponent part maybe

    $exp = 0;
    $expisnum = true;
    $expsign = 1;

    if( $isnum )
    {
        if( $str[ $idx ] === 'e' || $str[ $idx ] === 'E' )
        {
            $idx++;
            $expisnum = false; // Will be set to true as a number is detected

            $expsign = 1;
            if( $str[ $idx ] === '-' )
            {
                $expsign = -1;
                $idx++;
            }
            elseif( $str[ $idx ] === '+' )
            {
                $idx++;
            }

            // Parse the exp integer part

            while( $str[ $idx ] >= '0' && $str[ $idx ] <= '9')
            {
                $exp = $exp * 10 + (int)$str[ $idx ];
                $idx++;
                $expisnum = true;
            }
        }
    }

    // Done

    if( $isnum && $expisnum ) // parsing succeeded
    {
        if( $isfloat )
        {
            $result = floatval( ( $sign * $integer_part + $decimal_part * pow( 10, -$decimal_digits ) ) * pow( 10, $expsign * $exp ) );
        }
        else
        {
            if( $expsign === -1 )
            {
                $eval->error = "integer with negative exponent, append `.0` to enter a float";
                $isnum = false;
                $result = null;
            }
            else
            {
                $result = $sign * $integer_part * pow( 10, $exp );

                if( $result >= 9223372036854775808 /* +2^63 */ || $result <= -9223372036854775808 /* -2^63 */ )
                {
                    $eval->error = "integer exceeding integers limit, append `.0` to enter a float";
                    $isnum = false;
                    $result = null;
                }
            }
        }
    }
    else
    {
        $eval->error = "malformed value";
        $result = null;
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