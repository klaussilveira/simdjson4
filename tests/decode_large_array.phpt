--TEST--
simdjson_decode decodes lists with more elements than the simdjson size limit of 0xFFFFFF
--SKIPIF--
<?php
if (PHP_INT_SIZE < 8) { echo "skip maybe not enough continuous memory\n"; }
if (!($_ENV['SIMDJSON_HIGH_MEMORY_TESTS'] ?? null)) { echo "skip requires SIMDJSON_HIGH_MEMORY_TESTS=1\n"; }
?>
--INI--
memory_limit=2G
--FILE--
<?php
foreach ([0xFFFFFE, 0xFFFFFF, 0x1000000, 0x1100000] as $n) {
    $json = '[' . str_repeat('7,', $n - 1) . '8]';
    foreach ([true, false] as $associative) {
        $list = simdjson_decode($json, $associative);
        var_dump(count($list), $list[0], $list[$n - 1], isset($list[$n]));
        $list[] = 9;
        var_dump($list[$n]);
        unset($list);
    }
}
?>
--EXPECT--
int(16777214)
int(7)
int(8)
bool(false)
int(9)
int(16777214)
int(7)
int(8)
bool(false)
int(9)
int(16777215)
int(7)
int(8)
bool(false)
int(9)
int(16777215)
int(7)
int(8)
bool(false)
int(9)
int(16777216)
int(7)
int(8)
bool(false)
int(9)
int(16777216)
int(7)
int(8)
bool(false)
int(9)
int(17825792)
int(7)
int(8)
bool(false)
int(9)
int(17825792)
int(7)
int(8)
bool(false)
int(9)
