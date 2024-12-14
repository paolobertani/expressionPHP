<?php
/*

formula
version 0.1

discrete parser / evaluator of integers, booleans, strings,
    string functions and integer expressions

Copyright (c) 2024 Paolo Bertani - Kalei S.r.l.
Licensed under the FreeBSD 2-clause license

--------------------------------------------------------------------------------

FreeBSD 2-clause license

Copyright (c) 2024, Paolo Bertani - Kalei S.r.l.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS \AS IS\ AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

-------------------------------------------------------------------------------

*/



namespace Kalei\Formula;



if( ! defined( "unary_minus_has_highest_precedence" ) )
{
    define( "unary_minus_has_highest_precedence", false );

    //   true:  -2^2  =  (-2)^2  =   4
    //  false:  -2^2  =  -(2^2)  =  -4
}



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

    $result = formula( $argv[1], $error );
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



function formula( $expression, &$error = "", $parameters = null )
{
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
        $reservedKeywords =
        [
            "if",
            "fact",
            "pow",
            "max",
            "min",
            "avg",
            "average",
            "len",
            "trim",
            "substr"
        ];

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

            if( in_array( $name, $reservedKeywords ) !== false )
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
    $eval->expression  = $expression . "\0\0"; // expr is null terminated to allow parser detect EOF as "\0" is met

    $eval->paramsName  = $paramsName;
    $eval->paramsVal   = $paramsVal;
    $eval->paramsLen   = $paramsLen;
    $eval->paramsCnt   = $paramsCnt;

    $eval->cursor = 0;
    $eval->RBC    = 0; // Round Brackets Count
    $eval->error  = "";

    //
    // free setup mem
    //

    unset( $name     );
    unset( $value    );
    unset( $reservedKeywords );
    unset( $paramCnt );
    unset( $paramName);
    unset( $paramVal );
    unset( $paramLen );

    //
    // begin
    //

    $result = _processLow( $eval, -1, true, false );

    if( $eval->error )
    {
        $error = "$eval->error\n$eval->expression\n" . str_repeat( " ", $eval->cursor ) . "^---\n";
        return null;
    }
    else
    {
        $error = "";
        return $result;
    }
}



//
// private functions
//



// implement `intdiv()` if not available

if( ! function_exists('intdiv') )
{
    function intdiv( $dividend, $divisor )
    {
        if( $divisor === 0 )
        {
            Throw new \InvalidArgumentException( "Division by zero" );
        }

        if( ! is_int( $dividend ) || ! is_int( $divisor ) )
        {
            Throw new \InvalidArgumentException( "Both dividend and divisor must be integers" );
        }

        return (int) ( $dividend - $dividend % $divisor ) / $divisor;
    }
}



/// Evaluates expression parts between low-precedence operators:
// + addition; - subtraction; . string concat.; | logical or
// "breakOn" parameter define cases where the function must exit.

function _processLow( $eval,
                      $breakOnRBC,    // If open brackets count goes down to this count then exit;
                      $breakOnEOF,                   // exit if the end of the string is met;
                      $breakOnCOMMA,                 // exit if a comma is met;
                     &$tokenThatCausedBreak = null ) // if not null the token/symbol that caused the function to exit;
{
    $leftToken = null;
    $rightToken =null;

    $value = 0;
    $result = null;
    $rightToken = "Sum";

    do
    {
        $leftToken = $rightToken;

        $value = _processHi( $eval, null, "Mul", false, $rightToken ); // `null` as value lets $rightToken be accepted despite type
        if( $eval->error ) return 0;

        if( $leftToken === "Sum" )
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
                return 0;
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
                return 0;
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
                return 0;
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
                return 0;
            }
        }
    }
    while( $rightToken === "Sum" || $rightToken === "Sub" || $rightToken === "Conc" || $rightToken === "Or" );

    // A round close bracket:
    // check for negative count.

    if( $rightToken === "rbc" )
    {
        $eval->RBC--;
        if( $eval->RBC < 0 )
        {
            $eval->error = "unexpected close round bracket";
            return 0;
        }
    }

    // Returns the token that caused the function to exit

    $tokenThatCausedBreak = $rightToken;

    // Check if we must exit

    if( ( $eval->RBC === $breakOnRBC ) || ( $breakOnEOF && $rightToken === "Eof" ) || ( $breakOnCOMMA && $rightToken === "com" ) )
    {
        return $result;
    }

    // If not it's an error.

    switch( $rightToken )
    {
        case "Eof":
            $eval->error = "unexpected end of expression";
            break;

        case "rbc":
            $eval->error = "unexpected close round bracket";
            break;

        case "com":
            $eval->error = "unexpected comma";
            break;

        default:
            $eval->error = "unexpected symbol";
            break;
    }

    return 0;
}



// Evaluates expression parts between hi-precedencedence operators:
// * product; / division; & logical AND
// Expression parts can be explicit values or functions
// "breakOn" parameter define cases where the function must exit.

function _processHi( $eval,
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
        "Trim",
        "Substr"
    ];

    do
    {
        $rightValue = _processToken( $eval, $token, $leftValue );
        if( $eval->error ) return 0;

        // Unary minus, plus, logical not?
        // store the sign and get the next token

        if( $token === "Sub" )
        {
            $sign = -1;
            $rightValue = _processToken( $eval, $token, $leftValue );
            if( $eval->error ) return 0;
        }
        elseif( $token === 'Not')
        {
            $not = 1;
            $rightValue = _processToken( $eval, $token, $leftValue );
            if( $eval->error ) return 0;
        }
        elseif( $token === "Sum" )
        {
            $sign = 1;
            $rightValue = _processToken( $eval, $token, $leftValue );
            if( $eval->error ) return 0;
        }
        else
        {
            $sign = 1;
            $not = 0;
        }

        // Open round bracket?
        // The expression between brackets is evaluated.

        if( $token === "rbo" )
        {
            $eval->RBC++;

            $rightValue = _processLow( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return 0;

            $token = "Val";
        }

        // A function ?

        if( in_array( $token, $functionTokens ) )
        {
            $rightValue = _processFunction( $eval, $token );
            if( $eval->error ) return 0;

            $token = "Val";
        }

        // Excluded previous cases then
        // the token must be a value.

        if( $token !== "Val" )
        {
            $eval->error = "expected value";
            return 0;
        }

        // Get beforehand the next token
        // to see if it's an exponential or factorial operator

        _processToken( $eval, $nextOp, $rightValue );
        if( $eval->error ) return 0;

        if( $nextOp === "Fct" )
        {
            if( unary_minus_has_highest_precedence )
            {
                $rightValue = _processFactorial( $eval, $rightValue * $sign, $nextOp );
                $sign = 1;
            }
            else
            {
                $rightValue = _processFactorial( $eval, $rightValue, $nextOp );
            }
            if( $eval->error ) return 0;
        }

        if( $nextOp === "Exc" )
        {
            if( unary_minus_has_highest_precedence )
            {
                $rightValue = _processExponentiation( $eval, $rightValue * $sign, $nextOp );
                $sign = 1;
            }
            else
            {
                $rightValue = _processExponentiation( $eval, $rightValue, $nextOp );
            }
            if( $eval->error ) return 0;
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
                    return 0;
                }
                if( $not === 1 && ! is_bool( $rightValue ) )
                {
                    $eval->error = "unary `not` before non boolean value";
                    return 0;
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
                return 0;
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
                return 0;
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
                return 0;
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

function _processFunction( $eval, $func )
{
    $result = 0.0;
    $result2= 0.0;

    $count = 0;

    $tokenThatCausedBreak = "";
    $token = "";

    // Eat an open round bracket and count it

    _processToken( $eval, $token, null );
    if( $eval->error ) return 0;

    if( $token !== "rbo" )
    {
        $eval->error = "expected open round bracket after function name";
        return 0;
    }

    $eval->RBC++;

    switch( $func )
    {
        case "Fac":
            $result = _processLow( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return 0;

            if( ! is_int( $result ) )
            {
                $eval->error = "expected integer";
                return 0;
            }

            if( $result < 0 )
            {
                $eval->error = "attempt to evaluate factorial of negative number";
                return 0;
            }
            if( $result > 20 )
            {
                $eval->error = "result exceeds signed integer";
                return 0;
            }

            $t = 1;
            for( $f = 1; $f <= $result; $f++ )
            {
                $t *= $f;
            }
            $result = $t;
        break;

        case "Pow":
            $result = _processLow( $eval, -1, false, true );
            if( $eval->error ) return 0;

            if( ! is_int( $result ) )
            {
                $eval->error = "expected integer";
                return 0;
            }

            $result2 = _processLow( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return 0;

            if( ! is_int( $result2 ) )
            {
                $eval->error = "expected integers";
                return 0;
            }

            $result = pow( $result, $result2 );

            if( ! is_int( $result ) )
            {
                $eval->error = "result is not integer";
                return 0;
            }
            if( $result > pow( 2, 31 ) )
            {
                $eval->error = "result exceeds signed integer";
                return 0;
            }
            $result = intval( $result );
        break;

        case "Max":
            $result = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return 0;

            if( ! is_int( $result ) )
            {
                $eval->error = "expected integers";
                return 0;
            }

            while( $tokenThatCausedBreak === "com" )
            {
                $result2 = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return 0;

                if( ! is_int( $result2 ) )
                {
                    $eval->error = "expected integers";
                    return 0;
                }

                if( $result2 > $result )
                {
                    $result = $result2;
                }
            }
            break;

        case "Min":
            $result = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return 0;

            if( ! is_int( $result ) )
            {
                $eval->error = "expected integers";
                return 0;
            }

            while( $tokenThatCausedBreak === "com" )
            {
                $result2 = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return 0;

                if( ! is_int( $result2 ) )
                {
                    $eval->error = "expected integers";
                    return 0;
                }

                if( $result2 < $result )
                {
                    $result = $result2;
                }
            }
        break;

        case "Avg":
            $result = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return 0;

            if( ! is_int( $result ) )
            {
                $eval->error = "expected integers";
                return 0;
            }

            $count = 1;
            while( $tokenThatCausedBreak === "com" )
            {
                $result2 = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
                if( $eval->error ) return 0;

                if( ! is_int( $result2 ) )
                {
                    $eval->error = "expected integers";
                    return 0;
                }

                $result += $result2;
                $count++;
            }

            $result = intdiv( $result, $count );
        break;

        case "Len":
            $result = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return 0;

            if( ! is_string( $result ) )
            {
                $eval->error = "expected string";
                return 0;
            }

            $result = strlen( $result );
        break;

        case "Trim":
            $result = _processLow( $eval, $eval->RBC - 1, false, true, $tokenThatCausedBreak );
            if( $eval->error ) return 0;

            if( ! is_string( $result ) )
            {
                $eval->error = "expected string";
                return 0;
            }

            $result = trim( $result );
        break;

        case "Substr":
            $result = _processLow( $eval, -1, false, true );
            if( $eval->error ) return 0;

            if( ! is_string( $result ) )
            {
                $eval->error = "expected string";
                return 0;
            }

            $result2 = _processLow( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return 0;

            if( ! is_int( $result2 ) )
            {
                $eval->error = "expected integers";
                return 0;
            }

            $result3 = _processLow( $eval, $eval->RBC - 1, false, false );
            if( $eval->error ) return 0;

            if( ! is_int( $result3 ) )
            {
                $eval->error = "expected integers";
                return 0;
            }

            $result = substr( $result, $result2, $result3 );
        break;

        default:
            $result = 0;
            break;
    }

    return $result;
}



// Evaluates an exponentiation.

function _processExponentiation( $eval,
                                 $base,      // The base has already been fetched;
                                &$rightOp )  // RETURN: the token (operator) that follows.
{
    $exponent = 0;
    $result = 0;

    if( ! is_int( $base ) )
    {
        $eval->error = "base must be integer";
        return 0;
    }

    $exponent = _processHi( $eval, 1, "Mul", true, $rightOp );
    if( $eval->error ) return 0;

    if( ! is_int( $exponent) )
    {
        $eval->error = "exponent must be integer";
        return 0;
    }

    if( $exponent < 0 )
    {
        $eval->error = "exponent must be zero or positive";
        return 0;
    }

    $result = pow( $base, $exponent );

    if( $result >= 9223372036854775808 /* -2^63 */ || $result <= -9223372036854775808 /* -2^63 */ )
    {
        $eval->error = "exponentiation result exceeds integer limit";
        return 0;
    }

    return intval( $result );
}



// Evaluates a factorial

function _processFactorial( $eval,
                            $value,     // The value to compute has already been fetched;
                           &$rightOp )  // RETURN: the token (operator) that follows.
{
    $result = 0;

    if( $value < 0 )
    {
        $eval->error = "attempt to evaluate factorial of negative number";
        $rightOp = "Err";
        return 0;
    }

    if( ! is_int( $value ) )
    {
        $eval->error = "attempt to evaluate factorial of a non-integer value";
        $rightOp = "Err";
        return 0;
    }

    if( $value > 20 )
    {
        $eval->error = "factorial result exceeds integer limit";
        return 0;
    }

    $result = 1;
    for( $i = 1; $i <= $value; $i++ )
    {
        $result *= $i;
    }

    _processToken( $eval, $rightOp, $value );
    if( $eval->error ) return 0;

    return $result;
}



// Parses the next token and advances the cursor.
// The function returns a number, a string or a boolean if the token is a value or a param.
// Whitespace is ignored.

function _processToken( $eval,
                        &$token,      // RETURN: the token.
                         $leftValue ) // necessary to distinguish `Not from `Fct`
{
   $name = "";
   $t = "Blk";
   $value = 0;

   while( $t === "Blk" )
   {
       // value maybe

       $c = ( $eval->expression )[ $eval->cursor ];
       if( ( $c >= "0" && $c <= "9" ) || $c === "\"" ) // there is no need to catch 't'rue and 'f'alse here
       {                                               // as they are added as params [***]
           $value = _processValue( $eval );
           if( $eval->error )
           {
               $t = "Err";
               return $t;
           }
           else
           {
               $t = "Val";
           }
           break;
       }
       else
       {
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

           // token maybe

           switch( ( $eval->expression )[ $eval->cursor ] )
           {
               case "\n":
               case "\r":
               case "\t":
               case " ":
                   $t = "Blk";
                   $eval->cursor++;
                   break;

               case "+":
                   _processPlusToken( $eval, $t );
                   break;

               case "-":
                   $t = "Sub";
                   $eval->cursor++;
                   break;

               case ".":
                   $t = "Conc";
                   $eval->cursor++;
                   break;

               case "*":
                   $t = "Mul";
                   $eval->cursor++;
                   break;

               case "/":
                   $t = "Div";
                   $eval->cursor++;
                   break;

               case "^":
                   $t = "Exc";
                   $eval->cursor++;
                   break;

               case "!": // if it trails a number then `Factorial` otherwise `Not`
                   $t = is_int( $leftValue ) ? "Fct" : "Not";
                   $eval->cursor++;
                   break;

               case "&":
                   $t = "And";
                   $eval->cursor++;
                   if( ( $eval->expression )[ $eval->cursor ] === "&" ) // && is an alias of &
                   {
                       $eval->cursor++;
                   }
                   break;

               case "|":
                   $t = "Or";
                   $eval->cursor++;
                   if( ( $eval->expression )[ $eval->cursor ] === "|" ) // || is an alias of |
                   {
                       $eval->cursor++;
                   }
                   break;

               case "(":
                   $t = "rbo";
                   $eval->cursor++;
                   break;

               case ")":
                   $t = "rbc";
                   $eval->cursor++;
                   break;

               case "\0":
                   $t = "Eof";
                   $eval->cursor++;
                   break;

               case ",":
                   $t = "com";
                   $eval->cursor++;
                   break;

               case "f":
                   if( substr( $eval->expression, $eval->cursor, 4 ) === "fact" )
                   {
                       $t = "Fac";
                       $eval->cursor += 4;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;

               case "p":
                   if( substr( $eval->expression, $eval->cursor, 3 ) === "pow" )
                   {
                       $t = "Pow";
                       $eval->cursor += 3;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;

               case "s":
                   if( substr( $eval->expression, $eval->cursor, 3 ) === "substr" )
                   {
                       $t = "Substr";
                       $eval->cursor += 6;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;

               case "l":
                   if( substr( $eval->expression, $eval->cursor, 3 ) === "len" )
                   {
                       $t = "Len";
                       $eval->cursor += 3;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;

               case "t":
                   if( substr( $eval->expression, $eval->cursor, 4 ) === "trim" )
                   {
                       $t = "Trim";
                       $eval->cursor += 4;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;

               case "m":
                   if( substr( $eval->expression, $eval->cursor, 3 ) === "max" )
                   {
                       $t = "Max";
                       $eval->cursor += 3;
                   }
                   elseif( substr( $eval->expression, $eval->cursor, 3 ) === "min" )
                   {
                       $t = "Min";
                       $eval->cursor += 3;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;

               case "a":
                   if( substr( $eval->expression, $eval->cursor, 3 ) === "avg" )
                   {
                       $t = "Avg";
                       $eval->cursor += 3;
                   }
                   else
                   {
                       $t = "Err";
                   }
                   break;


               default:
                   $t = "Err";
                   break;
           }
       }
    }

    if( $t === "Err" )
    {
        $eval->error = "unexpected symbol";
    }

    $token = $t;

    return $value;
}



// Parses what follows an (already fetched) plus token
// ensuring that two consecutive plus are not present.
// Expressions such as 2++2 (binary plus
// followed by unitary plus) are not allowed.
// Advances the cursor.
// Always returns 0.

function _processPlusToken( $eval, &$token )
{
    $c = "";

    do
    {
        $eval->cursor++;
        $c =  ($eval->expression)[$eval->cursor];
    } while( $c === " " || $c === "\n" || $c === "\r" || $c === "\t" );

    if( $c === "+" )
    {
        $token = "Err";
    }
    else
    {
        $token = "Sum";
    }

    return 0;
}



// Parse an explicit value: integer, string or boolean
// cursor is not changed (advanced) in case of parsing error,
// and null is returned

function _processValue( $eval )
{
    $result = null;
    $str = $eval->expression;
    $idx = $eval->cursor;

    // Skip leading whitespace (should be already skipped beforehand)

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
        $result = _parse_string( $str, $idx );
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

function _parse_string( $str, &$cursor )
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