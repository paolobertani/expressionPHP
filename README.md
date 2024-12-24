One example is worth one thousands words...

```php
<?php

require_once( __DIR__ . "/../expression.php" );

use function \Kalei\Expression\expression;

$parameters = [
    "str_1" => "foo",
    "str_2" => "BAR",
    "a_int" => 1
];

$expression = "( strlen( substr( str_1 . str_2, 3 ) ) + a_int ) ^ 2"

$result = expression( $expression,
                      $error,         // returned by ref.
                      $parameters,
                      $elapsedTime ); // returned by ref. (µsecs)
                         
if( $result === null )
{
   echo "Error: $error\n";
}
else
{
    echo "$result\n"; // ==> 16
}

/* step by step:

( strlen( substr( str_1 . str_2, 3 ) ) + a_int ) ^ 2
( strlen( substr( "foo" . "BAR", 3 ) ) +   1   ) ^ 2
( strlen( substr( "fooBAR,       3 ) ) +   1   ) ^ 2
( strlen(            "BAR"           ) +   1   ) ^ 2
(                     3                +   1   ) ^ 2
(                     4                        ) ^ 2

4^2 = 16

*/
                                                  
                                           

// side note: I do code in Allman style  	    
```

---

&nbsp;

# TL;DR

&nbsp;

**FIRST ABONINABLE TEMPORARY README FILE GENERATED WITH DUMB CHATGPH**

&nbsp;

# Expression Function Library

This library provides a collection of PHP functions for string manipulation, mathematical operations, and encoding/decoding. These functions mirror PHP's native capabilities, offering a simple and powerful way to evaluate expressions dynamically.

---

## 📋 Table of Contents
- [Overview](#overview)
- [Available Functions](#available-functions)
  - [Mathematical Functions](#mathematical-functions)
  - [String Manipulation Functions](#string-manipulation-functions)
  - [Encoding/Decoding Functions](#encodingdecoding-functions)
- [Available Operators](#-available-operators)
- [Operator Behavior and Constraints](#-operator-behavior-and-constraints)
- [Function Definitions](#function-definitions)
  - [Mathematical Functions](#mathematical-functions-1)
  - [String Manipulation Functions](#string-manipulation-functions-1)
  - [Encoding/Decoding Functions](#encodingdecoding-functions-1)
- [Usage](#usage)

---

## 📖 Overview
This library aims to simplify the evaluation of complex expressions by exposing a set of PHP functions that handle various tasks such as string manipulation, encoding, and mathematical operations. Each function is implemented to closely follow PHP's native functions.

---

## 🚀 Available Functions

### 🔢 Mathematical Functions
- `fact` – Factorial of a number
- `pow` – Power calculation
- `max` – Maximum of a list of numbers
- `min` – Minimum of a list of numbers
- `average` / `avg` – Calculate the average of a list

### 🧵 String Manipulation Functions
- `length` / `len` / `strlen` – String length
- `strpos` – Find substring position
- `trim` – Trim whitespace from both ends of a string
- `substr` – Extract part of a string
- `ltrim` – Left trim
- `rtrim` – Right trim

### 🔐 Encoding/Decoding Functions
- `bin2hex` – Binary to hexadecimal conversion
- `hex2bin` – Hexadecimal to binary conversion
- `chr` – Get character from ASCII value
- `ord` – Get ASCII value of a character
- `htmlentities` – Convert special characters to HTML entities

---

## ➕ Available Operators

In addition to the provided functions, the following operators are supported for integer and string manipulation:

### 🔢 Integer Operators
- **`+`** – Addition
- **`-`** – Subtraction
- **`*`** – Multiplication
- **`/`** – Division
- **`-`** – Unary minus (negation)
- **`!`** – Factorial (e.g., `5! = 120`)
- **`^`** – Exponentiation (e.g., `2^3 = 8`)

### 🧵 String Operator
- **`.`** – String concatenation (e.g., `'Hello' . ' World'` results in `'Hello World'`)

---

## ⚠️ Operator Behavior and Constraints

- **Integer Operators** (`+`, `-`, `*`, `/`, `^`, `!`) return **integer results**.
- Division (`/`) is performed using PHP's `intdiv()` function, ensuring the result is **always an integer** (floor division).
  ```php
  intdiv(7, 2);  // Returns 3 (not 3.5)
  ```
- Results of all integer operations must fit within a **64-bit signed integer**.
  - Overflowing results will trigger an error.
  - Example: `pow(2, 63)` exceeds the 64-bit limit and will result in an error.

**String Operator** (`.`) performs string concatenation and returns a **string**. There are no overflow limits for string operations.

---

## 🛠️ Function Definitions

### 🔢 Mathematical Functions

#### `fact`
```php
function fact(int $n): int {{
    if ($n < 0) {{
        throw new InvalidArgumentException("Factorial is not defined for negative numbers.");
    }}
    if ($n === 0 || $n === 1) {{
        return 1;
    }}
    $result = 1;
    for ($i = 2; $i <= $n; $i++) {{
        $result *= $i;
    }}
    return $result;
}}
```

#### `pow`
```php
function pow(float $base, float $exponent): float {{
    return $base ** $exponent;
}}
```

#### `max`
```php
function max(float ...$values): float {{
    if (empty($values)) {{
        throw new InvalidArgumentException("At least one value is required.");
    }}
    return max($values);
}}
```

#### `min`
```php
function min(float ...$values): float {{
    if (empty($values)) {{
        throw new InvalidArgumentException("At least one value is required.");
    }}
    return min($values);
}}
```

#### `average`
```php
function average(array $values): float {{
    if (empty($values)) {{
        throw new InvalidArgumentException("Array cannot be empty.");
    }}
    return array_sum($values) / count($values);
}}
```

---

### 🧵 String Manipulation Functions

#### `length`
```php
function length(string $str): int {{
    return strlen($str);
}}
```

#### `strpos`
```php
function strpos(string $haystack, string $needle, int $offset = 0): int {{
    $pos = strpos($haystack, $needle, $offset);
    return ($pos !== false) ? $pos : -1;
}}
```

#### `trim`
```php
function trim(string $str, string $characters = " \\t\\n\\r\\0\\x0B"): string {{
    return trim($str, $characters);
}}
```

---

## 🧪 Usage
1. Import the PHP file into your project.
2. Call the functions directly, or invoke them through dynamic expression parsing.
3. Combine them to perform complex string manipulations and mathematical evaluations.

---


## Contributing


We'd love your help in making **expressionPHP* better! 🚀  

### How You Can Contribute:  
- **Report bugs** – Let us know if something isn’t working.  
- **Request features** – Have an idea? We’d love to hear it!  
- **Fix issues** – Check out the open issues and help solve them.  
- **Improve documentation** – Spot a typo or something unclear? PRs welcome!
- **Extend the project** – Implement new functions (also add tests please)

---

## 📋 Steps to Contribute:  
1. **Fork the repository** (top-right of this page).  
2. **Clone your fork**:  
   ```bash
   git clone https://github.com/your-username/project-name.git
   ```
3. **Create a new branch** for your feature or fix:  
   ```bash
   git checkout -b feature/your-feature
   ```
4. **Make changes** and **commit**:  
   ```bash
   git commit -m "Add your feature"
   ```
5. **Push** to your fork and submit a **Pull Request**:  
   ```bash
   git push origin feature/your-feature
   ```

---

## 💡 Need Ideas to Contribute?  
- Check out the **Issues** tab for tasks labeled `good first issue` or `help wanted`.  
- Join discussions in the **Discussions** tab or open a new thread.  


## About me

C.E.O. and Full Stack Developer at Kalei S.r.l. (based in Italy)

I help commercial businesses reduce costs and improve service levels by facilitating access to product information.

I’m an entrepreneur and software developer.

When I’m not working => dad ❤️ freeclimber 🧗‍♂️ freediver 🌊 🥽

### 📜 License

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
