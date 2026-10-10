<?php



require_once( __DIR__ . "/../expression.php" );



use function \Kalei\Expression\expression;



define( "Success", "success" );
define( "Failure", "failure" );



define( "StopAtFirstFail", isset( $argv[ 1 ] ) );



$testCount = 0;
$fails     = 0;
$totalTime = 0;
$singleTime= [];



RunTests();



/*
 * RunTests
 *
 * Execute the expression tests, including multibyte delimiters in between().
 *
 * edited by Samantha Allman (aka Codex GPT-6)
 *
 */

function RunTests()
{
    global $testCount;
    global $fails;
    global $totalTime;
    global $singleTime;

    $b = 0.0;
    $e = 0.0;
    $r = 0.0;

    $fails = 0;


    // Parameters

    Test( __LINE__, Success, 1,       "foo", [ "foo" => 1    ] );
    Test( __LINE__, Success, 1.0,     "foo", [ "foo" => 1.0  ] );
    Test( __LINE__, Success, 0.0,     "foo", [ "foo" => 0.0  ] );
    Test( __LINE__, Success, "A",     "foo", [ "foo" => "A"  ] );
    Test( __LINE__, Success, true,    "foo", [ "foo" => true ] );


    // Integers expressions

    Test( __LINE__, Success, 2,       "+2" );         // plus as unary operator
    Test( __LINE__, Success, 0,       "2+-2" );       // plus as binary operator, minus as unary: 2 + ( -2 )
    Test( __LINE__, Success, 0,       "2-+2" );       // vice-versa: 2 - ( -2 )
    Test( __LINE__, Success, 4,       "2--2" );       // minus as both binary and unary operator 2 - ( -2 )
    Test( __LINE__, Success, 0,       "+2-(+2)" );    // leading plus
    Test( __LINE__, Success, 6,       "+2*(+3)" );    //
    Test( __LINE__, Success, -3,      "1*-3" );       //
    Test( __LINE__, Success, -2,      "-3+1" );       //
    Test( __LINE__, Success, 1234,    "1234" );       //
    Test( __LINE__, Success, 1234,    " 001234" );    //
    Test( __LINE__, Success, 1234,    "   1234" );    //
    Test( __LINE__, Success, 1234,    "1234   " );    //
    Test( __LINE__, Success, 6,       "2*+3" );       //
    Test( __LINE__, Failure, null,    "12  34" );     //
    Test( __LINE__, Failure, null,    "-+3" );        // *
    Test( __LINE__, Failure, null,    "+-3" );        // *
    Test( __LINE__, Failure, null,    "2++2" );       // * two plus as consecutive binary and unary operators not allowed
    Test( __LINE__, Failure, null,    "2---2" );      // * three minus ? not allowed
    Test( __LINE__, Failure, null,    "--2" );        // * beginning with two minus ? no, a value is expected


    // Test with params

    Test( __LINE__, Success, 5,       " 2* foo - bar", [ "foo" => 3, 'bar' => 1] );
    Test( __LINE__, Failure, null,   " 2* foo - BAZ", [ "foo" => 3, 'bar' => 1] );


    // Non integer single numbers, and exponent

    Test( __LINE__, Success, 0.2,       ".2"     );
    Test( __LINE__, Success, -0.2,      "-.2"    );
    Test( __LINE__, Success, 12.34,     "12.34"  );
    Test( __LINE__, Success, (int)12E2, "12E2"   );
    Test( __LINE__, Success, 12E-2,     "12.0E-2");
    Test( __LINE__, Success, 12,        "12E0"   );
    Test( __LINE__, Success, -0.2,      "-.2"    );
    Test( __LINE__, Success, (4.0+30.0+200.0+1000.0)/10e3,    ".1234"  );
    Test( __LINE__, Success, 0.1234,    "0.1234" );
    Test( __LINE__, Success, 0.1234,    "+.1234" );
    Test( __LINE__, Success, -0.123,    "-0.123" );
    Test( __LINE__, Failure, null,      ".-2"    );
    Test( __LINE__, Failure, null,      "12E-2"  );
    Test( __LINE__, Failure, null,      "12.e"   );
    Test( __LINE__, Failure, null,      "12a0"   );
    Test( __LINE__, Failure, null,      "12e+"   );
    Test( __LINE__, Failure, null,      "1.+1"   );
    Test( __LINE__, Failure, null,      "12.e"   );
    Test( __LINE__, Failure, null,      "12.e+"  );
    Test( __LINE__, Failure, null,      "12.e1"  );
    Test( __LINE__, Failure, null,      "12E2.5" );


    // Round brackets

    Test( __LINE__, Success, 1,       "(1)" );
    Test( __LINE__, Success, 42,      "1+(2*(3+(4+5+6))-1)+6" );
    Test( __LINE__, Success, 1,       "(((((((((((1)))))))))))" );
    Test( __LINE__, Success, -1,      "-(((((((((((1)))))))))))" );
    Test( __LINE__, Success, 1,       "+(((((((((((1)))))))))))" );
    Test( __LINE__, Success, -1,      "+(((((((((((-1)))))))))))" );
    Test( __LINE__, Success, 1,       "-(((((((((((-1)))))))))))" );
    Test( __LINE__, Failure, null,   "+2*(+-3)" );                   // *
    Test( __LINE__, Failure, null,   "1+(2*(3+(4+5+6))-1+6" );       // * missing close bracket
    Test( __LINE__, Failure, null,   "1+(2*(3+(4+5+6))-1))+6" );     // * too many close brackets
    Test( __LINE__, Failure, null,   "1+()" );                       // * empty expression
    Test( __LINE__, Failure, null,   ".(((((((((((1)))))))))))" );   // *


    // Booleans

    Test( __LINE__, Success, true,    "true" );
    Test( __LINE__, Success, false,   "false" );
    Test( __LINE__, Success, false,   "!true" );
    Test( __LINE__, Success, true,    "!false" );
    Test( __LINE__, Success, true,    "false||true" );
    Test( __LINE__, Success, true,    "true||true" );
    Test( __LINE__, Success, true,    "true&&true" );
    Test( __LINE__, Success, false,   "false&&true" );
    Test( __LINE__, Success, true,    "!false||true" );
    Test( __LINE__, Success, true,    "!true||true" );
    Test( __LINE__, Success, false,   "!true&&true" );
    Test( __LINE__, Success, true,    "!false&&true" );
    Test( __LINE__, Success, false,   "false||!true" );
    Test( __LINE__, Success, true,    "true||!true" );
    Test( __LINE__, Success, false,   "true&&!true" );
    Test( __LINE__, Success, false,   "false&&!true" );
    Test( __LINE__, Success, true,    "true&&(false||true)" );
    Test( __LINE__, Success, false,   "true&&(false&&true)" );
    Test( __LINE__, Success, true,    "true||(false||true)" );
    Test( __LINE__, Success, true,    "true||(false&&true)" );
    Test( __LINE__, Success, false,   "true&&!(false||true)" );
    Test( __LINE__, Success, true,    "true&&!(false&&true)" );
    Test( __LINE__, Success, true,    "true&&!false||true" );
    Test( __LINE__, Success, true,    "true&&!false&&true" );


    // Bitwise operators

    Test( __LINE__, Success, 10&60,    "10&60"   );
    Test( __LINE__, Success, 10&60+2,  "10&60+2" );
  //Test( __LINE__, Success, 10&60*2,  "10&60*2" );    // TOFIX
    Test( __LINE__, Success, 10|60,    "10|60"   );
    Test( __LINE__, Success, 10|60*3,  "10|60*3"   );
  //Test( __LINE__, Success, 10|60+3,  "10|60+3"   );  // TOFIX



    // Factorial

    Test( __LINE__, Success, 24,      " 4! " );
    Test( __LINE__, Success, 26,      " 2+4! " );
    Test( __LINE__, Success, 26,      " 4!+2 " );
    Test( __LINE__, Success, 24,      " (4)! " );
    Test( __LINE__, Failure, null,    " !4 " );
    Test( __LINE__, Failure, null,    " 4!! " );


    // Exponentiation and precedence

    Test( __LINE__, Success, 8,      "2^3" );
    Test( __LINE__, Success, 64,     "2^3!" ); // Factorial has higher precedence than exponentiation
    Test( __LINE__, Success, 12,     "2^3+4" );// The exponentiation has higher precedence that sum...
    Test( __LINE__, Success, 32,     "2^3*4" );// ...and product
    Test( __LINE__, Success, 4096,   "2^(3*4)" );
    Test( __LINE__, Failure, null,   "9^0.5" );  // left and right operands must be both integers or float
    Test( __LINE__, Success, 3.0,    "9.0^.5" );
    Test( __LINE__, Success, 1/3,    "9.0^-.5" );


    // Exponentiation with **

    Test( __LINE__, Success, 8,      "2**3" );
    Test( __LINE__, Success, 64,     "2**3!" ); // Factorial has higher precedence than exponentiation
    Test( __LINE__, Success, 12,     "2**3+4" );// The exponentiation has higher precedence that sum...
    Test( __LINE__, Success, 32,     "2**3*4" );// ...and product
    Test( __LINE__, Success, 4096,   "2**(3*4)" );


    // Exponentiation with ^

    Test( __LINE__, Success, 8,      "2^3" );
    Test( __LINE__, Success, 64,     "2^3!" );
    Test( __LINE__, Success, 12,     "2^3+4" );
    Test( __LINE__, Success, 32,     "2^3*4" );
    Test( __LINE__, Success, 4096,   "2^(3*4)" );


    // Operator precedence

    Test( __LINE__, Success, 14,  "2+3*4" );  // + < *
    Test( __LINE__, Success, 19,  "1+2*3^2" );// + < * < ^
    Test( __LINE__, Success, 10,  "1+3^2" );  //
    Test( __LINE__, Success, 15,  "2+3*4+1" );//
    Test( __LINE__, Success, 20,  "1+2\n\t*3^2+1");
    Test( __LINE__, Success, 11,  "1+3   ^2+1" );
    Test( __LINE__, Success, 24,  "2^3*3" );
    Test( __LINE__, Success, 64,  "2^3!" );   // =2^6 (since ^ < !)
    Test( __LINE__, Success, -6,  "2*-3" );   // unary minus > *

    Test( __LINE__, Success, -1.5,"3.0/-2.0" );   // unary minus > /
    Test( __LINE__, Success,1/9.0,"3.0^-2.0" );   // unary minus > ^
    Test( __LINE__, Success, -1  ,"3  :-2  " );
    Test( __LINE__, Success, -1  ,"3  ÷-2  " );


    // Operator precedence, exponentiation with **

    Test( __LINE__, Success, 19,  "1+2*3**2" );// + < * < **
    Test( __LINE__, Success, 10,  "1+3**2" );  //
    Test( __LINE__, Success, 15,  "2+3*4+1" );//
    Test( __LINE__, Success, 20,  "1+2\n\t*3**2+1");
    Test( __LINE__, Success, 11,  "1+3   **2+1" );
    Test( __LINE__, Success, 24,  "2**3*3" );
    Test( __LINE__, Success, 64,  "2**3!" );   // =2^6 (since ** < !)


    // Unary minus has lowest precedence

    Test( __LINE__, Success, -9,  "-3^2" );   // -(3^2)
    Test( __LINE__, Success,  1,  "5+-2^2" ); //
    Test( __LINE__, Success, -4,  "-2^2" );   //
    Test( __LINE__, Success, -6,  "-3!" );    // -(3!)

    Test( __LINE__, Success, -9,  "-3**2" );
    Test( __LINE__, Success,  1,  "5+-2**2" ); //
    Test( __LINE__, Success, -4,  "-2**2" );   //


    // Integer division

    Test( __LINE__, Success,  5, "2+6//2" );
    Test( __LINE__, Success,  4, "8//2" );
    Test( __LINE__, Success,  9, "3*3" );
    Test( __LINE__, Success,  8, "15//2+1" );
    Test( __LINE__, Success, 11, "5*2+1" );
    Test( __LINE__, Success,  4, "9//2" );
    Test( __LINE__, Success, 10, "20//2" );
    Test( __LINE__, Success,  8, "3+15//3" );
    Test( __LINE__, Success,  9, "5+8//2" );
    Test( __LINE__, Success,  9, "13//2+3" );
    Test( __LINE__, Success,  6, "3+10//3" );
    Test( __LINE__, Success, 15, "12+9//3" );
    Test( __LINE__, Success,  1, "5//5" );
    Test( __LINE__, Success, 13, "10+13//4" );
    Test( __LINE__, Success, 20, "18+6//3" );
    Test( __LINE__, Success, 17, "20-6//2" );
    Test( __LINE__, Success, 30, "25+10//2" );
    Test( __LINE__, Success,  9, "7+8//4" );
    Test( __LINE__, Success, 15, "18-15//5" );
    Test( __LINE__, Success,  7, "3*5//2" );

    Test( __LINE__, Success,  5, "2+6//2" );
    Test( __LINE__, Success,  4, "8//2" );
    Test( __LINE__, Success,  9, "3*3" );
    Test( __LINE__, Success,  8, "15//2+1" );
    Test( __LINE__, Success, 11, "5*2+1" );
    Test( __LINE__, Success,  4, "9//2" );
    Test( __LINE__, Success, 10, "20//2" );
    Test( __LINE__, Success,  8, "3+16//3" );
    Test( __LINE__, Success,  9, "5+8//2" );
    Test( __LINE__, Success,  9, "13//2+3" );
    Test( __LINE__, Success,  6, "3+10//3" );
    Test( __LINE__, Success, 15, "12+9//3" );
    Test( __LINE__, Success,  1, "5//5" );
    Test( __LINE__, Success, 13, "10+12//4" );
    Test( __LINE__, Success, 20, "18+6//3" );
    Test( __LINE__, Success, 17, "20-6//2" );
    Test( __LINE__, Success, 30, "25+10//2" );
    Test( __LINE__, Success,  9, "7+8//4" );
    Test( __LINE__, Success, 15, "18-15//5" );
    Test( __LINE__, Success,  6, "3*4//2" );


    // ÷

    Test( __LINE__, Success,  62, " 120  ÷   4  +  (  (   80   ÷  (  2  +  1  )  +  (  2   *   3  )  )  )  " );
    Test( __LINE__, Success, 101, " 300  ÷   5  +  (  (   50   ÷  2  +  (  4   *  (  3  +  1  )  )  )  )   " );
    Test( __LINE__, Success, 126, " 144  ÷   3  +  (  (  128   ÷  2  +  (  (  5  +  2  )  *  2  )  )  )    " );
    Test( __LINE__, Success,  45, "  90  ÷   3  +  (  (   55   ÷  5  +  (  2  ^  (  3  -  1  )  )  )  )    " );
    Test( __LINE__, Success, 101, " 200  ÷   4  +  (  (  100   ÷  (  2  +  1  )  +  (  6   *  3  )  )  )   " );
    Test( __LINE__, Success, 231, " 500  ÷   5  +  (  (  (  220  ÷  2  )  +  (  7  *  (  2  +  1  )  )  )) " );
    Test( __LINE__, Success,  99, " 150  ÷   3  +  (  (   82   ÷  2  +  (  (  3  ^  2  )  -  1  )  )  )    " );
    Test( __LINE__, Success, 139, " 250  ÷   5  +  (  (  130   ÷  2  +  (  4   *  (  4  +  2  )  )  )  )   " );
    Test( __LINE__, Success, 148, " 400  ÷   8  +  (  (  132   ÷  2  +  (  (  3  ^  3  )  +  5  )  )  )    " );
    Test( __LINE__, Success,  79, " 120  ÷   4  +  (  (   68   ÷  2  +  (  5   *  (  2  +  1  )  )  )  )   " );
    Test( __LINE__, Success, 197, " 600  ÷   6  +  (  (  (  178  ÷  2  )  +  (  2  ^  (  4  -  1  )  )  ) )" );
    Test( __LINE__, Success, 145, " 170  ÷   2  +  (  (  (   90  ÷  3  )  +  (  6  *  (  3  +  2  )  )  ) )" );
    Test( __LINE__, Success, 144, " 280  ÷   4  +  (  (  128   ÷  2  +  (  (  3  ^  2  )  +  1  )  )  )    " );
    Test( __LINE__, Success, 108, " 240  ÷   4  +  (  (  (   72  ÷  2  )  +  (  4  *  (  2  +  1  )  )  ) )" );
    Test( __LINE__, Success, 124, " 300  ÷   5  +  (  (  (   96  ÷  2  )  +  (  2  ^  (  3  +  1  )  )  ) )" );
    Test( __LINE__, Success, 127, " 353  ÷   5  +  (  (   88   ÷  2  +  (  (  5  *  3  )  -  2  )  )  )    " );
    Test( __LINE__, Success,  99, " 141  ÷   2  +  (  (   54   ÷  3  +  (  (  3  ^  2  )  +  2  )  )  )    " );
    Test( __LINE__, Success, 130, " 211  ÷   3  +  (  (  (   84  ÷  2  )  +  (  6  *  (  2  +  1  )  )  )) " );
    Test( __LINE__, Success, 157, " 481  ÷   6  +  (  (  110   ÷  2  +  (  (  4  ^  2  )  +  6  )  )  )    " );
    Test( __LINE__, Success, 182, " 363  ÷   4  +  (  (  128   ÷  2  +  (  (  2  ^  5  )  -  4  )  )  )    " );

    Test( __LINE__, Success, 2, "10 * 3 // 15" ); // ATTENTION: Those equals `( 10 * 3 ) ÷ 15 = 2`, NOT `10 * (3 ÷ 15)`;
    Test( __LINE__, Success, 2, "10 * 3 :  15" ); //            the same rule applies, most programming languages
    Test( __LINE__, Success, 2, "10 * 3 ÷  15" ); //            and math.


    // Boolean expressions

    Test( __LINE__, Success, true,      "2==2" );
    Test( __LINE__, Success, false,     "2!=2" );
    Test( __LINE__, Success, false,     "2==3" );
    Test( __LINE__, Success, true,      "2!=3" );
    Test( __LINE__, Success, true,      "3+1>1+1*2" );
    Test( __LINE__, Success, true,      "2<3" );
    Test( __LINE__, Success, true,      "2<=3" );
    Test( __LINE__, Success, true,      "3>=2" );
    Test( __LINE__, Success, true,      "3==3==true" );
    Test( __LINE__, Failure, null,      "2==true" );
    Test( __LINE__, Failure, null,      "3==3==3" );


    // If

    Test( __LINE__, Success, true,      'if(3==3,"foo","bar") == "foo"');
    Test( __LINE__, Success, "bar",     'if(2==3,"foo","bar")' );


    // fact

    Test( __LINE__, Success,    6, 'fact( 3 )' );
    Test( __LINE__, Failure, null, 'fact( 3.0 )' );


    // pow

    Test( __LINE__, Success,    8, 'pow( 2, 3)' );
    Test( __LINE__, Success,  1/8, 'pow( 2.0, -3.0)' );
    Test( __LINE__, Failure, null, 'pow( 2.0, 3)' );
    Test( __LINE__, Failure, null, 'pow( 2.0, -3)' );



    // max

    Test( __LINE__, Success,    8, 'max( 1, 8, -1 )' );
    Test( __LINE__, Failure, null, 'max( 1.0, 8, -1 )' );
    Test( __LINE__, Success,    8, 'max( 8 )' );
    Test( __LINE__, Success,  8.0, 'max( 1.0, 8.0, -1.0 )' );
    Test( __LINE__, Failure, null, 'max( )' );



    // min

    Test( __LINE__, Success,   -1, 'min( 1, 8, -1 )' );
    Test( __LINE__, Failure, null, 'min( 1.0, 8, -1 )' );
    Test( __LINE__, Success,    8, 'min( 8 )' );
    Test( __LINE__, Success, -1.0, 'min( 1.0, 8.0, -1.0 )' );
    Test( __LINE__, Failure, null, 'min( )' );



    // avg

    Test( __LINE__, Success,   10, 'avg( 0, 10, 20 )' );
    Test( __LINE__, Success,   10, 'avg( 0, 10, 21 )' );
    Test( __LINE__, Success,  7.5, 'avg( 10.0, 5.0 )' );
    Test( __LINE__, Failure, null, 'avg( 10.0, 5 )' );


    // len

    Test( __LINE__, Success,    6, 'len( "foobar" )' );
    Test( __LINE__, Success,    0, 'len( "" )' );
    Test( __LINE__, Failure, null, 'len( 6 )' );
    Test( __LINE__, Success,    1, 'len( "😀" )' );
    Test( __LINE__, Success,    6, 'strlen( "foobar" )' );


    // trim


    // substr


    // between

    Test( __LINE__, Success,   "ABC", 'between( "aABCz", "a", "z" )' );
    Test( __LINE__, Success,   "ABC", 'between( "àABCz", "à", "z" )' );
    Test( __LINE__, Success,   "été", 'between( "pré😀été終", "😀", "終" )' );
    Test( __LINE__, Success,   "ABC", 'between( "àABC", "à", "" )' );
    Test( __LINE__, Success, "[ABC]", 'between( "àABCz", "à", "z", "[", "]" )' );


    // replace


    // strpos


    // bin2hex


    // hex2bin


    // chr


    // ord


    // htmlentities


    // md5


    // sha1


    // ltrim


    // rtrim


    // int

    Test( __LINE__, Success,    6, 'int( "6" )' );
    Test( __LINE__, Success,    6, 'int( 6 )' );
    Test( __LINE__, Success,    6, 'int( 6.0 )' );
    Test( __LINE__, Success,    1, 'int( true )' );
    Test( __LINE__, Success,    6, 'int( "6A" )' );
    Test( __LINE__, Success, 6000, 'int( "6E3" )' );


    // str

    Test( __LINE__, Success,  "6", 'str( 6 )' );
    Test( __LINE__, Success,  "6", 'str( "6" )' );
    Test( __LINE__, Success,  "1", 'str( true )' );
    Test( __LINE__, Success,   "", 'str( false )' );
    Test( __LINE__, Success,"6.0", 'str( "6.0" )' );


    // float

    Test( __LINE__, Success,   6.0, 'float( "6" )' );
    Test( __LINE__, Success,   6.0, 'float( 6 )' );
    Test( __LINE__, Success,   6.0, 'float( 6.0 )' );
    Test( __LINE__, Success,   1.0, 'float( true )' );
    Test( __LINE__, Success,   6.0, 'float( "6A" )' );
    Test( __LINE__, Success,6000.0, 'float( "6E3" )' );


    // bool

    Test( __LINE__, Success,  true, 'bool( "6" )' );
    Test( __LINE__, Success,  true, 'bool( "0" )' );
    Test( __LINE__, Success,  true, 'bool( 6 )' );
    Test( __LINE__, Success,  true, 'bool( 6.0 )' );
    Test( __LINE__, Success,  true, 'bool( true )' );
    Test( __LINE__, Success, false, 'bool( "" )' );
    Test( __LINE__, Success, false, 'bool( 0 )' );
    Test( __LINE__, Success, false, 'bool( false )' );


    //
    // All tests passed?
    //

    if( $fails === 0 )
    {
        $text = "\nexpressionPHP Version: " . \Kalei\Expression\version . "\n\n";
        $text .= "All $testCount tests passed!\n\nElapsed time: " . number_format( $totalTime / 1000, 3 ) . " ms.\n";
        $text .= "Average time per test: " . round( $totalTime / $testCount ) . " µs.\n";
        arsort( $singleTime );
        $text .= "Worst time at line " . key( $singleTime ) . ": " . reset( $singleTime ) . " µs.\n\n";
        echo $text;
        if( substr( __DIR__, -19, 19 ) === "/expressionPHP/test" )
        {
            file_put_contents( __DIR__ . "/test-outcome.txt", $text );
        }
    }
    else
    {
        echo "$fails test" . ( $fails > 1 ? "s" : "" ) . " failed.\n\n";
    }
}



//
// Test function: compare expected exit status (success/error) and expected result.
//

function Test( $lineNumber, $expectedStatus, $expectedResult, $expression, $parameters = null )
{
    global $testCount;
    global $fails;
    global $totalTime;
    global $singleTime;

    $testCount++;

    $inStyleOutError = "rainbow";

    $returnedResult = expression( $expression, $inStyleOutError, $parameters, $elapsedTime );

    $totalTime += $elapsedTime;
    $singleTime[ $lineNumber ] = $elapsedTime;

    if( $returnedResult === null )
    {
        $returnedStatus = Failure;
    }
    else
    {
        $returnedStatus = Success;
    }

    // floats are compared with 1E-6 tolerance due to floating point limited precision

    if( $returnedStatus === $expectedStatus && is_float( $returnedResult ) && is_float( $expectedResult ) && abs( $returnedResult - $expectedResult ) < 1e-6 )
    {
        return;
    }

    if( $returnedStatus === $expectedStatus && $returnedResult === $expectedResult )
    {
        return;
    }

    if( $fails === 0 ) { echo "\n"; }

    $expectedResultStr = $expectedResult;
    $returnedResultStr = $returnedResult;

    if( $expectedResult === true ) { $expectedResultStr = "true";  }
    if( $expectedResult === false) { $expectedResultStr = "false"; }
    if( $expectedResult === null ) { $expectedResultStr = "NULL";  }
    if( $returnedResult === true ) { $returnedResultStr = "true";  }
    if( $returnedResult === false) { $returnedResultStr = "false"; }
    if( $returnedResult === null ) { $returnedResultStr = "NULL";  }

    echo "Test failed at line: $lineNumber\n\n";

    echo "Expression:          $expression\n\n";

    echo "Expected status is:  $expectedStatus\n";
    echo "Returned status is:  $returnedStatus\n";
    echo "\n";

    echo "Expected result is:  $expectedResultStr\n";
    echo "Returned result is:  $returnedResultStr\n";
    echo "\n";

    echo "Expected type is:    " . gettype( $expectedResult ) . "\n";
    echo "Returned type is:    " . gettype( $returnedResult ) . "\n";
    echo "\n";

    if( $returnedStatus === Failure ) { echo "$inStyleOutError\n"; }

    echo "\n";

    if( StopAtFirstFail ) exit;

    echo "---\n\n\n";

    $fails++;
}
