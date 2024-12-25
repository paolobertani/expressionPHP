<?php



//  /***/ = COMMENTED UNTIL FLOAT IS IMPLEMENTED



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



//
// Execute all tests.
//

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

    // Unary minus has lowest precedence (with exceptions)
    Test( __LINE__, Success, -9,  "-3^2" );   // -(3^2)
    /***/ // Test( __LINE__, Success,  8,  "64^-2" );  // unary minus has highest precedence after a binary operator but...
    Test( __LINE__, Success,  1,  "5+-2^2" ); // ...has lowest precedence after `+`
    Test( __LINE__, Success, -4,  "-2^2" );   //
    Test( __LINE__, Success, -6,  "-3!" );    // -(3!)


    // Integer functions

    Test( __LINE__, Success, 10,  "avg( 0, 10, 20 )" );


    // Strings

    Test( __LINE__, Success, "BAR",  "substr( \"fooBAR\", 3 )" );
    Test( __LINE__, Success, "BAR",  "substr( \"fooBAR\", 3, 3 )" );
    Test( __LINE__, Success, "BAR",  "substr( \"fooBAR\", -3, 3 )" );
    Test( __LINE__, Success, "ooBAR","substr( \"fooBAR\", 1, 100 )" );
    Test( __LINE__, Success, "BAR",  "substr( \"fooBAR\", 1+1+1, 6/2 )" );
    Test( __LINE__, Success, "BAR",  "substr( \"foo\" . \"BAR\", 1+1+1, 6/2 )" );

    Test( __LINE__, Success, "BAR",  "trim( \"   BAR  \\t\\n\\r\" )" );
    Test( __LINE__, Success, "BAR",  "trim( \"****BAR==*==\", \"*=\" )" );

    Test( __LINE__, Success, 3,    "strpos( \"fooBAR\", \"BAR\" )" );
    Test( __LINE__, Success, 3,    "strpos( \"fooBAR BAR\", \"BAR\", 3 )" );
    Test( __LINE__, Success, 7,    "strpos( \"fooBAR BAR\", \"BAR\", 4 )" );
    Test( __LINE__, Success, 7,    "strpos( \"fooBAR BAR\", \"BAR\", strpos( \"fooBAR\", \"BAR\" ) + 2 )" );
    Test( __LINE__, Failure, null, "strpos( \"fooBAR BAR\", \"BAR\", 4, 1)" );

    Test( __LINE__, Success, "FOObar", "\"FOO\".\"\".\"bar\"" );


    // Functions

    Test( __LINE__, Success, bin2hex("FOOBAR\n"), "bin2hex(\"FOOBAR\\n\")" );
    Test( __LINE__, Success, "FOOBAR\n", "hex2bin(bin2hex(\"FOOBAR\\n\"))" );
    Test( __LINE__, Success, bin2hex("FOO❤️BAR\n"), "bin2hex(\"FOO❤️BAR\\n\")" );
    Test( __LINE__, Success, "FOO❤️BAR\n", "hex2bin(bin2hex(\"FOO❤️BAR\\n\"))" );
    Test( __LINE__, Success, bin2hex("FOO❤️\0\0\0BAR\n"), "bin2hex(\"FOO❤️\\0\\0\\0BAR\\n\")" );
    Test( __LINE__, Success, "FOO❤️\0\0\0BAR\n", "hex2bin(bin2hex(\"FOO❤️\\0\\0\\0BAR\\n\"))" );

    Test( __LINE__, Success, 0, "fact(16)-16!" );
    Test( __LINE__, Failure, null, "fac(30)" );
    Test( __LINE__, Success, 0, "pow(4,7)-4^7" );
    Test( __LINE__, Failure, null, "pow(2,64)" );
    Test( __LINE__, Success, 270, "max(1,2,3,10*3^3)" );
    Test( __LINE__, Success, 90, "min(91,92,10*3^2)" );
    Test( __LINE__, Success, 0, "avg(1,-1)" );
    Test( __LINE__, Success, intdiv( 33, 2 ), "avg(30,3)" );
    Test( __LINE__, Success, 7, "len(\"foo\")+strlen(\"BAZZ\")+length(\"\")" );
    Test( __LINE__, Success, strlen("👍"), "len(\"👍\")" );
    Test( __LINE__, Success, trim( " booo \n\t"), "trim(\" booo \\n\\t\")   " );
    Test( __LINE__, Success, trim( "** booo ****", "*"), "trim(\"** booo ****\", \"*\" )   " );


    // ChatGPT generated

    Test( __LINE__, Success, 6, 'strpos("Hello World", "World")' );
    Test( __LINE__, Success, 4, 'strpos("Hello World", "o")' );
    Test( __LINE__, Success, 0, 'strpos("Hello World", "Hello")' );
    Test( __LINE__, Success, -1, 'strpos("Hello World", "x")' );
    Test( __LINE__, Success, 4, 'strpos("abc abc abc", "abc", 4)' );
    Test( __LINE__, Success, 0, 'strpos("testing", "test")' );
    Test( __LINE__, Success, 4, 'strpos("abcdef", "ef")' );
    Test( __LINE__, Success, 6, 'strpos("repetition", "t", 6)' );
    Test( __LINE__, Success, 4, 'strpos("12345", "5")' );
    Test( __LINE__, Success, 0, 'strpos("needle in haystack", "needle")' );
    Test( __LINE__, Success, 'Hello World', 'trim("  Hello World  ")' );
    Test( __LINE__, Success, 'Hello', 'trim("xxHelloxx", "x")' );
    Test( __LINE__, Success, 'bcb', 'trim("abcba", "a")' );
    Test( __LINE__, Success, 'whitespace', 'trim("   whitespace   ")' );
    Test( __LINE__, Success, 'test', 'trim("--test--", "-")' );
    Test( __LINE__, Success, 'number', 'trim("00number00", "0")' );
    Test( __LINE__, Success, 'text', 'trim("xyztextyz", "xyz")' );
    Test( __LINE__, Success, 'PHP', 'trim("  PHP  ")' );
    Test( __LINE__, Success, 'example', 'trim("=example=", "=")' );
    Test( __LINE__, Success, 'important', 'trim("!!important!!", "!")' );
    Test( __LINE__, Success, 5, 'strlen("Hello")' );
    Test( __LINE__, Success, 1, 'strlen(" ")' );
    Test( __LINE__, Success, 10, 'strlen("1234567890")' );
    Test( __LINE__, Success, 0, 'strlen("")' );
    Test( __LINE__, Success, 6, 'strlen("abcdef")' );
    Test( __LINE__, Success, 3, 'strlen("PHP")' );
    Test( __LINE__, Success, 1, 'strlen("a")' );
    Test( __LINE__, Success, 11, "strlen(\"multi\\n line\")" );
    Test( __LINE__, Success, 10, 'strlen("symbols@#%")' );
    Test( __LINE__, Success, 1, 'strlen(" ")' );
    Test( __LINE__, Success, 'Hello', 'hex2bin("48656c6c6f")' );
    Test( __LINE__, Success, '48656c6c6f', 'bin2hex("Hello")' );
    Test( __LINE__, Success, 'MyS', 'hex2bin("4d7953")' );
    Test( __LINE__, Success, '54657374', 'bin2hex("Test")' );
    Test( __LINE__, Success, 'Foo', 'hex2bin("466f6f")' );
    Test( __LINE__, Success, '466f6f64', 'bin2hex("Food")' );
    Test( __LINE__, Success, 'Java', 'hex2bin("4a617661")' );
    Test( __LINE__, Success, '4a617661', 'bin2hex("Java")' );
    Test( __LINE__, Success, 'Node', 'hex2bin("4e6f6465")' );
    Test( __LINE__, Success, '4e6f6465', 'bin2hex("Node")' );
    Test( __LINE__, Success, '&lt;b&gt;bold&lt;/b&gt;', 'htmlentities("<b>bold</b>")' );
    Test( __LINE__, Success, '&lt;script&gt;alert(1)&lt;/script&gt;', 'htmlentities("<script>alert(1)</script>")' );
    Test( __LINE__, Success, '&amp;', 'htmlentities("&")' );
    Test( __LINE__, Success, '5f4dcc3b5aa765d61d8327deb882cf99', 'md5("password")' );
    Test( __LINE__, Success, '827ccb0eea8a706c4c34a16891f84e7b', 'md5("12345")' );
    Test( __LINE__, Success, '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'sha1("password")' );
    Test( __LINE__, Success, '8cb2237d0679ca88db6464eac60da96345513964', 'sha1("12345")' );
    Test( __LINE__, Success, 65, 'ord("A")' );
    Test( __LINE__, Success, 122, 'ord("z")' );
    Test( __LINE__, Success, 'Hello', 'ltrim("   Hello")' );
    Test( __LINE__, Success, 'Test', 'ltrim("---Test", "-")' );
    Test( __LINE__, Success, 'Hello', 'rtrim("Hello   ")' );
    Test( __LINE__, Success, 'Test', 'rtrim("Test---", "-")' );

    // All tests passed?

    if( $fails === 0 )
    {
        echo "All $testCount tests passed!\nElapsed time: " . $totalTime / 1000 . " ms.\n";
        arsort( $singleTime );
        echo "Worst at line " . key( $singleTime ) . " took " . reset( $singleTime ) . "µs.\n";
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

    $returnedResult = expression( $expression, $error, $parameters, $elapsedTime );

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