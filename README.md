# simdjson4

Fast JSON decoding and encoding for PHP. Decoding is powered by [simdjson](https://github.com/simdjson/simdjson), which parses JSON with SIMD instructions. Both directions return exactly what `json_decode()` and `json_encode()` return, only faster.

[![Build Status](https://github.com/klaussilveira/simdjson4/actions/workflows/integration.yml/badge.svg?branch=master)](https://github.com/klaussilveira/simdjson4/actions/workflows/integration.yml?query=branch%3Amaster)

## Performance

Measured on an Intel Core i7-9700F (AVX2) with a release build of PHP 8.5.11 on Linux. Each number is the median of 13 interleaved runs, comparing against the PHP functions in the same run. Your numbers will vary with CPU and data, so run the [benchmarks](./benchmark) on your own hardware.

### Decoding

| Data | PHP | simdjson4 | Speedup |
|---|---|---|---|
| `twitter.json`, to arrays | `json_decode` | `simdjson_decode` | **3.2x** |
| `twitter.json`, to objects | `json_decode` | `simdjson_decode` | **3.3x** |
| `citm_catalog.json` (1.7 MB) | `json_decode` | `simdjson_decode` | **2.7x** |
| `canada.json` (2.2 MB, floats) | `json_decode` | `simdjson_decode` | **4.7x** |
| `gsoc-2018.json` (3.3 MB) | `json_decode` | `simdjson_decode` | **4.1x** |
| `github_events.json` (65 KB) | `json_decode` | `simdjson_decode` | **3.6x** |
| `demo.json` (387 bytes) | `json_decode` | `simdjson_decode` | **2.3x** |
| Validate `twitter.json` | `json_validate` | `simdjson_is_valid` | **8.6x** |
| Validate `canada.json` | `json_validate` | `simdjson_is_valid` | **10.2x** |
| Read one value from `twitter.json` | `json_decode` + array access | `simdjson_key_value` | **9.4x** |
| Count one array in `twitter.json` | `count(json_decode(...))` | `simdjson_key_count` | **9.7x** |

### Encoding

| Data | PHP | simdjson4 | Speedup |
|---|---|---|---|
| Decoded `twitter.json` | `json_encode` | `simdjson_encode` | **1.2x** |
| Decoded `twitter.json`, `JSON_UNESCAPED_UNICODE` | `json_encode` | `simdjson_encode` | **1.7x** |
| Decoded `twitter.json`, `JSON_PRETTY_PRINT` | `json_encode` | `simdjson_encode` | **1.3x** |
| 10,000 database-style rows | `json_encode` | `simdjson_encode` | **1.7x** |
| Decoded `gsoc-2018.json` | `json_encode` | `simdjson_encode` | **2.1x** |
| Decoded `canada.json` (floats) | `json_encode` | `simdjson_encode` | **11.1x** |
| Decoded `mesh.json` (floats and integers) | `json_encode` | `simdjson_encode` | **7.7x** |
| 100,000 floats | `json_encode` | `simdjson_encode` | **13.8x** |
| Long ASCII string | `json_encode` | `simdjson_encode` | **4.6x** |
| Decoded `demo.json` (387 bytes) | `json_encode` | `simdjson_encode` | **1.1x** |

Encoding a single scalar such as an integer is about 5% slower than `json_encode`, because of a small fixed per-call cost.

## Requirements

- Decoding: PHP 7.0 or newer. Encoding (`simdjson_encode()`): PHP 8.0 or newer. Tested up to PHP 8.5 and PHP 8.6 RC.
- A C++17 compiler (g++ 7 or newer, or clang++ 6 or newer) and a 64-bit system.

## Installing

### With PIE

Install with [PIE](https://github.com/php/pie), the PHP Installer for Extensions:

```
pie install klaussilveira/simdjson4
```

PIE builds the extension from source on Linux and macOS, and installs pre-built DLLs on Windows. The extension is loaded as `simdjson4`.

The `simdjson` package on PECL is the original 4.0.0 release from [crazyxman/simdjson_php](https://github.com/crazyxman/simdjson_php). It does not include `simdjson_encode()` or any of the changes in this repository.

### From source

```
$ phpize
$ ./configure
$ make
$ make test
$ make install
```

Add the following line to your php.ini

```
extension=simdjson4.so
```

## Usage

### Decoding

```php
$jsonString = <<<'JSON'
{
  "Image": {
    "Width":  800,
    "Height": 600,
    "Title":  "View from 15th Floor",
    "Thumbnail": {
      "Url":    "http://www.example.com/image/481989943",
      "Height": 125,
      "Width":  100
    },
    "Animated" : false,
    "IDs": [116, 943, 234, 38793, {"p": "30"}]
  }
}
JSON;

// Decode like json_decode(): arrays with true, stdClass objects with false.
$data = simdjson_decode($jsonString, true);
var_dump($data['Image']['Width']); // int(800)

$object = simdjson_decode($jsonString);
var_dump($object->Image->Title); // string(20) "View from 15th Floor"

// Invalid JSON throws SimdJsonException instead of returning null.
try {
    simdjson_decode('{"broken":');
} catch (SimdJsonException $e) {
    echo $e->getMessage(), "\n";
}

// Check whether a string is valid JSON without building PHP values.
var_dump(simdjson_is_valid($jsonString)); // bool(true)

// Read, check and count values by JSON pointer without decoding the whole document.
var_dump(simdjson_key_value($jsonString, "/Image/Thumbnail/Url")); // string(38) "http://www.example.com/image/481989943"
var_dump(simdjson_key_value($jsonString, "/Image/IDs/4", true));   // array(1) { ["p"]=> string(2) "30" }
var_dump(simdjson_key_exists($jsonString, "/Image/IDs/1"));        // bool(true)
var_dump(simdjson_key_count($jsonString, "/Image/IDs"));           // int(5)
```

### Encoding

```php
$data = ['id' => 42, 'name' => 'Ada', 'tags' => ['math', 'code'], 'score' => 9.5, 'url' => 'https://example.com/a/b'];

echo simdjson_encode($data);
// {"id":42,"name":"Ada","tags":["math","code"],"score":9.5,"url":"https:\/\/example.com\/a\/b"}

// Every json_encode() flag is supported.
echo simdjson_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
// {
//     "id": 42,
//     "name": "Ada",
//     "tags": [
//         "math",
//         "code"
//     ],
//     "score": 9.5,
//     "url": "https://example.com/a/b"
// }

echo simdjson_encode((object)['price' => 10.0], JSON_PRESERVE_ZERO_FRACTION);
// {"price":10.0}

// Values that cannot be encoded throw SimdJsonException, with the JSON_ERROR_* code json_encode() would report.
try {
    simdjson_encode(['value' => NAN]);
} catch (SimdJsonException $e) {
    echo $e->getMessage(); // Inf and NaN cannot be JSON encoded
}
```

## simdjson4 API

```php
<?php

/**
 * Takes a JSON encoded string and converts it into a PHP variable.
 * Similar to json_decode()
 *
 * @param string $json The JSON string being decoded
 * @param bool $associative When true, JSON objects will be returned as associative arrays.
 *                          When false, JSON objects will be returned as objects.
 * @param int $depth the maximum nesting depth of the structure being decoded.
 * @return array|stdClass|string|float|int|bool|null
 * @throws SimdJsonException for invalid JSON
 *                           (or $json over 4GB long)
 * @throws SimdJsonValueError for invalid $depth
 */
function simdjson_decode(string $json, bool $associative = false, int $depth = 512) {}

/**
 * Returns true if json is valid.
 *
 * @param string $json The JSON string being decoded
 * @param int $depth the maximum nesting depth of the structure being decoded.
 * @return bool
 * @throws SimdJsonValueError for invalid $depth
 */
function simdjson_is_valid(string $json, int $depth = 512) : bool {}

/**
 * Parses $json and returns the number of keys in $json matching the JSON pointer $key
 *
 * @param string $json The JSON string being decoded
 * @param string $key The JSON pointer being requested
 * @param int $depth The maximum nesting depth of the structure being decoded.
 * @param bool $throw_if_uncountable If true, then throw SimdJsonException instead of
                                     returning 0 for JSON pointers
                                     to values that are neither objects nor arrays.
 * @return int
 * @throws SimdJsonException for invalid JSON or invalid JSON pointer
 *                           (or document over 4GB)
 * @throws SimdJsonValueError for invalid $depth
 * @see https://www.rfc-editor.org/rfc/rfc6901.html
 */
function simdjson_key_count(string $json, string $key, int $depth = 512, bool $throw_if_uncountable = false) : int {}

/**
 * Returns true if the JSON pointer $key could be found.
 *
 * @param string $json The JSON string being decoded
 * @param string $key The JSON pointer being requested
 * @param int $depth the maximum nesting depth of the structure being decoded.
 * @return bool (false if key is not found)
 * @throws SimdJsonException for invalid JSON or invalid JSON pointer
 *                           (or document over 4GB)
 * @throws SimdJsonValueError for invalid $depth
 * @see https://www.rfc-editor.org/rfc/rfc6901.html
 */
function simdjson_key_exists(string $json, string $key, int $depth = 512) : bool {}

/**
 * Returns the value at the json pointer $key
 *
 * @param string $json The JSON string being decoded
 * @param string $key The JSON pointer being requested
 * @param int $depth the maximum nesting depth of the structure being decoded.
 * @param bool $associative When true, JSON objects will be returned as associative arrays.
 *                          When false, JSON objects will be returned as objects.
 * @return array|stdClass|string|float|int|bool|null the value at $key
 * @throws SimdJsonException for invalid JSON or invalid JSON pointer
 *                           (or document over 4GB)
 * @throws SimdJsonValueError for invalid $depth
 * @see https://www.rfc-editor.org/rfc/rfc6901.html
 */
function simdjson_key_value(string $json, string $key, bool $associative = false, int $depth = 512) {}

/**
 * Returns the JSON representation of $value.
 * Produces the same output as json_encode() for every value and flag. Requires PHP 8.0+.
 *
 * @param mixed $value The value being encoded
 * @param int $flags Any combination of the JSON_* flags accepted by json_encode().
 *                   JSON_THROW_ON_ERROR has no effect because errors always throw.
 *                   With JSON_PARTIAL_OUTPUT_ON_ERROR, the partial output is returned instead of throwing.
 * @param int $depth The maximum nesting depth of the structure being encoded.
 * @return string
 * @throws SimdJsonException when the value cannot be encoded.
 *                           The error code is the JSON_ERROR_* constant json_last_error() would report.
 * @throws SimdJsonValueError for invalid $depth
 */
function simdjson_encode(mixed $value, int $flags = 0, int $depth = 512) : string {}

/**
 * An error thrown by simdjson when processing json.
 *
 * The error code is available as $e->getCode().
 * This can be compared against the `SIMDJSON_ERR_*` constants.
 *
 * Before simdjson 2.1.0, a regular RuntimeException with an error code of 0 was thrown.
 */
class SimdJsonException extends RuntimeException {
}

/**
 * Thrown for error conditions on fields such as $depth that are not expected to be
 * from user-provided JSON, with similar behavior to php 8.0.
 *
 * NOTE: https://www.php.net/valueerror was added in php 8.0.
 * In older php versions, this extends Error instead.
 *
 * When support for php 8.0 is dropped completely,
 * a major release of simdjson will likely switch to a standard ValueError.
 */
class SimdJsonValueError extends ValueError {
}
```

## Edge cases

### Decoding

`simdjson_decode()` returns the same values as `json_decode()`, including for numbers outside the 64-bit range: integers that do not fit become floats, and exponents beyond the range of a double become `INF` or `-INF`. (The simdjson library itself rejects such numbers; this extension bundles a copy patched to match `json_decode()`.) The remaining differences are:

1) Invalid JSON throws a `SimdJsonException` (a `RuntimeException`) with a `SIMDJSON_ERR_*` code, instead of returning `null` and setting `json_last_error()`. The error messages differ from `json_last_error_msg()`. The `$flags` argument of `json_decode()` is not supported.

2) The maximum string length that can be passed to `simdjson_decode()` is 4GiB (4294967295 bytes).
`json_decode()` can decode longer strings.

3) The handling of max depth is counted slightly differently for empty vs non-empty objects/arrays.
In `json_decode`, an array with a scalar has the same depth as an array with no elements.
In `simdjson_decode`, an array with a scalar is one level deeper than an array with no elements.
For typical use cases, this shouldn't matter.
(e.g. `simdjson_decode('[[]]', true, 2)` will succeed but `json_decode('[[]]', true, 2)` and `simdjson_decode('[[1]]', true, 2)` will fail.)

### Encoding

`simdjson_encode()` produces byte-for-byte the same output as `json_encode()` for every value and flag. The differences are in error handling:

1) Errors throw a `SimdJsonException` whose code is the `JSON_ERROR_*` constant `json_encode()` would report, instead of returning `false`. `JSON_THROW_ON_ERROR` therefore has no effect. With `JSON_PARTIAL_OUTPUT_ON_ERROR`, the partial output is returned and nothing is thrown, like `json_encode()`.

2) `$depth` must be at least 1. `json_encode()` also accepts 0.

3) `json_last_error()` is never changed by `simdjson_encode()`.

## Credits

This project forks [crazyxman/simdjson_php](https://github.com/crazyxman/simdjson_php).

`simdjson_encode()` formats floats with [Dragonbox](https://github.com/jk-jeon/dragonbox) by Junekey Jeon, bundled as `src/dragonbox.h` under the Boost Software License 1.0 (see `src/dragonbox-LICENSE-Boost`).

## Benchmarks

See the [benchmark](./benchmark) folder to run the benchmarks yourself.
