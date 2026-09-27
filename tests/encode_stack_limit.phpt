--TEST--
simdjson_encode reports a depth error instead of overflowing the native stack
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80300) { echo "skip requires PHP 8.3+\n"; }
if (ini_get('zend.max_allowed_stack_size') === false) { echo "skip stack limit not available\n"; }
?>
--INI--
zend.max_allowed_stack_size=2M
zend.reserved_stack_size=512K
--FILE--
<?php
$value = 1;
for ($i = 0; $i < 30000; $i++) {
    $value = [$value];
}
var_dump(json_encode($value, 0, 100000), json_last_error() === JSON_ERROR_DEPTH);
try {
    simdjson_encode($value, 0, 100000);
    echo "no exception\n";
} catch (SimdJsonException $e) {
    var_dump($e->getCode() === JSON_ERROR_DEPTH, $e->getMessage());
}
?>
--EXPECT--
bool(false)
bool(true)
bool(true)
string(28) "Maximum stack depth exceeded"
