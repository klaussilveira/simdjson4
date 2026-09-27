--TEST--
simdjson_encode returns the same output as json_encode for the bundled example files
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; }
if (!is_dir(dirname(__DIR__) . '/jsonexamples/small')) { echo "skip jsonexamples directory not available\n"; }
?>
--INI--
memory_limit=1G
--FILE--
<?php
require __DIR__ . '/encode.inc';
$dir = dirname(__DIR__) . '/jsonexamples';
$values = [];
foreach (array_merge(glob("$dir/*.json"), glob("$dir/small/*.json")) as $file) {
    $json = file_get_contents($file);
    $values[basename($file) . ' array'] = json_decode($json, true);
    $values[basename($file) . ' object'] = json_decode($json);
}
$flag_sets = [
    0,
    JSON_PRETTY_PRINT,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    JSON_FORCE_OBJECT,
    JSON_PRESERVE_ZERO_FRACTION,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
    JSON_NUMERIC_CHECK,
    JSON_PARTIAL_OUTPUT_ON_ERROR,
    JSON_INVALID_UTF8_SUBSTITUTE,
];
encode_compare_all($values, $flag_sets, 'examples');
?>
--EXPECT--
examples: 420 checked, 0 different
