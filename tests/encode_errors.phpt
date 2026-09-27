--TEST--
simdjson_encode throws for recursion, depth and invalid arguments and leaves json_last_error untouched
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';
$cycle = [1];
$cycle[] = &$cycle;
$shared = [1];
$ref = 5;
$nested = [1, [2, [3, [4]]]];
$values = [
    'cycle' => $cycle,
    'shared' => [$shared, $shared],
    'references' => [&$ref, &$ref],
    'utf8 inside too deep' => [["\xff"]],
    'inf then bad key' => ["\xff" => INF, 'x' => 1],
    'bad key then inf' => ["x" => 1, "\xff" => 1, 'y' => NAN],
    'empty object too deep' => [new stdClass()],
];
encode_compare_all($values, [0, JSON_PARTIAL_OUTPUT_ON_ERROR], 'errors depth 512');
foreach ([1, 2, 3, 4, 5] as $depth) {
    $failed = 0;
    foreach (array_merge($values, ['nested' => $nested]) as $name => $value) {
        foreach ([0, JSON_PRETTY_PRINT, JSON_PARTIAL_OUTPUT_ON_ERROR] as $flags) {
            if (($result = encode_compare($value, $flags, $depth)) !== null) {
                $failed++;
                echo "depth $depth $name $flags: $result\n";
            }
        }
    }
    echo "depth $depth: $failed different\n";
}

echo simdjson_encode([1], 0, 1), "\n";
try {
    simdjson_encode([[1]], 0, 1);
} catch (SimdJsonException $e) {
    var_dump($e->getCode() === JSON_ERROR_DEPTH, $e->getMessage());
}
try {
    simdjson_encode($cycle);
} catch (SimdJsonException $e) {
    var_dump($e->getCode() === JSON_ERROR_RECURSION, $e->getMessage());
}
foreach ([0, -1, PHP_INT_MAX] as $depth) {
    try {
        simdjson_encode([1], 0, $depth);
    } catch (SimdJsonValueError $e) {
        echo $e->getMessage(), "\n";
    }
}
try {
    simdjson_encode(NAN, JSON_THROW_ON_ERROR);
} catch (SimdJsonException $e) {
    echo get_class($e), "\n";
}
var_dump(simdjson_encode([1, NAN, "\xff", 2], JSON_PARTIAL_OUTPUT_ON_ERROR));

json_decode('{');
$before = json_last_error();
simdjson_encode([1]);
simdjson_encode([new ArrayObject([1])]);
try {
    simdjson_encode(NAN);
} catch (SimdJsonException $e) {
}
var_dump($before === JSON_ERROR_SYNTAX, json_last_error() === $before);
?>
--EXPECTF--
errors depth 512: 14 checked, 0 different
depth 1: 0 different
depth 2: 0 different
depth 3: 0 different
depth 4: 0 different
depth 5: 0 different
[1]
bool(true)
string(28) "Maximum stack depth exceeded"
bool(true)
string(18) "Recursion detected"
simdjson_encode(): Argument #3 ($depth) must be greater than zero
simdjson_encode(): Argument #3 ($depth) must be greater than zero
simdjson_encode(): Argument #3 ($depth) exceeds maximum allowed value of %d
SimdJsonException
string(12) "[1,0,null,2]"
bool(true)
bool(true)
