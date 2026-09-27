--TEST--
simdjson_encode formats integers and integer keys exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';
$ints = [0, PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MAX - 1, PHP_INT_MIN + 1];
for ($p = 1; $p > 0 && $p <= intdiv(PHP_INT_MAX, 10) * 10; $p *= 10) {
    foreach ([$p - 1, $p, $p + 1] as $v) {
        $ints[] = $v;
        $ints[] = -$v;
    }
    if ($p > intdiv(PHP_INT_MAX, 10)) {
        break;
    }
}
encode_compare_all($ints, [0], 'integers');
encode_compare_all([array_flip(array_map('strval', $ints)), array_fill_keys($ints, true)], [0, JSON_PRETTY_PRINT], 'integer keys');
echo simdjson_encode([-5 => 1, 0 => 2, 99 => 3, 100 => 4]), "\n";
?>
--EXPECTF--
integers: %d checked, 0 different
integer keys: 4 checked, 0 different
{"-5":1,"0":2,"99":3,"100":4}
