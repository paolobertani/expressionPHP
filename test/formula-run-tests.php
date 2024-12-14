<?php



//  /***/ = COMMENTED UNTIL FLOAT IS IMPLEMENTED



require_once( __DIR__ . "/../formula.php" );



use function \Kalei\Formula\formula;



define( "Success", "success" );
define( "Failure", "failure" );



define( "StopAtFirstFail", ( isset( $argv[ 1 ] ) && $argv[ 1 ] === "stop" ) );



$testCount = 0;
$fails     = 0;
$totalTime = 0;



RunTests();



//
// Execute all tests.
//

function RunTests()
{
    global $testCount;
    global $fails;
    global $totalTime;

    $b = 0.0;
    $e = 0.0;
    $r = 0.0;

    $fails = 0;

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
    Test( __LINE__, Failure, null,   "-+3" );        // *
    Test( __LINE__, Failure, null,   "+-3" );        // *
    Test( __LINE__, Failure, null,   "2++2" );       // * two plus as consecutive binary and unary operators not allowed
    Test( __LINE__, Failure, null,   "2---2" );      // * three minus ? not allowed
    Test( __LINE__, Failure, null,   "--2" );        // * beginning with two minus ? no, a value is expected

    // Test with params

    Test( __LINE__, Success, 5,       " 2* foo - bar", [ "foo" => 3, 'bar' => 1] );
    Test( __LINE__, Failure, null,   " 2* foo - BAZ", [ "foo" => 3, 'bar' => 1] );

    // Non integer single numbers

    Test( __LINE__, Failure, null,   ".2" );
    Test( __LINE__, Failure, null,   "-.2" );
    Test( __LINE__, Failure, null,   "12.34" );
    Test( __LINE__, Failure, null,   "12E2" );
    Test( __LINE__, Failure, null,   "12E-2" );
    Test( __LINE__, Failure, null,   "12E0" );
    Test( __LINE__, Failure, null,   "12a0" );
    Test( __LINE__, Failure, null,   "12.e" );
    Test( __LINE__, Failure, null,   "12e+" );
    Test( __LINE__, Failure, null,   "1.+1" );
    Test( __LINE__, Failure, null,   "12.e" );
    Test( __LINE__, Failure, null,   "12.e+" );
    Test( __LINE__, Failure, null,   "12.e1" );
    Test( __LINE__, Failure, null,   "12E2.5");
    Test( __LINE__, Failure, null,   ".-2" );

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
    Test( __LINE__, Success, true,    "false|true" );
    Test( __LINE__, Success, true,    "true|true" );
    Test( __LINE__, Success, true,    "true&true" );
    Test( __LINE__, Success, false,   "false&true" );
    Test( __LINE__, Success, true,    "!false|true" );
    Test( __LINE__, Success, true,    "!true|true" );
    Test( __LINE__, Success, false,   "!true&true" );
    Test( __LINE__, Success, true,    "!false&true" );
    Test( __LINE__, Success, false,   "false|!true" );
    Test( __LINE__, Success, true,    "true|!true" );
    Test( __LINE__, Success, false,   "true&!true" );
    Test( __LINE__, Success, false,   "false&!true" );
    Test( __LINE__, Success, true,    "true&(false|true)" );
    Test( __LINE__, Success, false,   "true&(false&true)" );
    Test( __LINE__, Success, true,    "true|(false|true)" );
    Test( __LINE__, Success, true,    "true|(false&true)" );
    Test( __LINE__, Success, false,   "true&!(false|true)" );
    Test( __LINE__, Success, true,    "true&!(false&true)" );

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

    // Operator precedence

    Test( __LINE__, Success, 14,  "2+3*4" );  // + < *
    Test( __LINE__, Success, 19,  "1+2*3^2" );// + < * < ^
    Test( __LINE__, Success, 10,  "1+3^2" );  //
    Test( __LINE__, Success, 15,  "2+3*4+1" );//
    Test( __LINE__, Success, 20,  "1+2\n\t*3^2+1");
    Test( __LINE__, Success, 11,  "1+3   ^2+1" );
    Test( __LINE__, Success, 24,  "2^3*3" );
    Test( __LINE__, Success, 64,  "2^3!" );   // ^ < !
    Test( __LINE__, Success, -6,  "2*-3" );   // unary minus > *
    /***/ // Test( __LINE__, Success, -1.5,"3/-2" );   // unary minus > /
    /***/ // Test( __LINE__, Success,1/9.0,"3^-2" );   // unary minus > ^

    // Unary minus precedence

    if( unary_minus_has_highest_precedence )
    {
        // Unary minus has highest precedence
        Test( __LINE__, Success,  9,    "-3^2" );   // (-3)^2
        /***/ // Test( __LINE__, Success,  8,    "64^-2" );
        Test( __LINE__, Success,  9,    "5+-2^2" ); // unary minus has always highest precedence
        Test( __LINE__, Success,  4,    "-2^2" );   //
        Test( __LINE__, Failure,  null, "-3!" );    // * (-3)!
    }
    else
    {
        // Unary minus has lowest precedence (with exceptions)
        Test( __LINE__, Success, -9,  "-3^2" );   // -(3^2)
        /***/ // Test( __LINE__, Success,  8,  "64^-2" );  // unary minus has highest precedence after a binary operator but...
        Test( __LINE__, Success,  1,  "5+-2^2" ); // ...has lowest precedence after `+`
        Test( __LINE__, Success, -4,  "-2^2" );   //
        Test( __LINE__, Success, -6,  "-3!" );    // -(3!)
    }


    // Strings

    // Functions



    // All tests passed?

    if( $fails === 0 )
    {
        echo "All $testCount tests passed! Elapsed time: " . $totalTime / 1000 . " ms.\n";
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

    $testCount++;

    $returnedResult = formula( $expression, $error, $parameters, $elapsedTime );

    $totalTime += $elapsedTime;

    if( $returnedResult === null )
    {
        $returnedStatus = Failure;
    }
    else
    {
        $returnedStatus = Success;
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

    if( $returnedStatus === Failure ) { echo "Error reported is:   $error\n"; }

    echo "\n";

    if( StopAtFirstFail ) exit;

    echo "---\n\n\n";

    $fails++;
}