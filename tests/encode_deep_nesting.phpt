--TEST--
simdjson_encode handles deeply nested arrays and objects exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--INI--
memory_limit=512M
--FILE--
<?php
require __DIR__ . '/encode.inc';
$array = 'leaf';
$object = 'leaf';
for ($i = 0; $i < 2000; $i++) {
    $array = [$array];
    $o = new stdClass();
    $o->k = $object;
    $object = $o;
}
foreach ([1999, 2000, 2001, 4000] as $depth) {
    foreach (['array' => $array, 'object' => $object] as $name => $value) {
        foreach ([0, JSON_PRETTY_PRINT] as $flags) {
            $result = encode_compare($value, $flags, $depth);
            echo "$name depth $depth flags $flags: ", $result ?? 'same', "\n";
        }
    }
}
echo strlen(simdjson_encode($array, 0, 2001)), "\n";
?>
--EXPECT--
array depth 1999 flags 0: same
array depth 1999 flags 128: same
object depth 1999 flags 0: same
object depth 1999 flags 128: same
array depth 2000 flags 0: same
array depth 2000 flags 128: same
object depth 2000 flags 0: same
object depth 2000 flags 128: same
array depth 2001 flags 0: same
array depth 2001 flags 128: same
object depth 2001 flags 0: same
object depth 2001 flags 128: same
array depth 4000 flags 0: same
array depth 4000 flags 128: same
object depth 4000 flags 0: same
object depth 4000 flags 128: same
4006
