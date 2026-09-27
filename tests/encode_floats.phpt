--TEST--
simdjson_encode formats floats exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';
$fixed = [0.0, -0.0, 1.0, 0.5, 0.1, 1 / 3, 2 / 3, 1e15, 1e16, 1e17, 123456789012345678.0, 0.001, 0.0001, 0.00001,
    5e-324, 1.7976931348623157e308, 1e100, -1.5, 3.0e-5, 9007199254740992.0, 0.30000000000000004];
foreach ($fixed as $value) {
    echo simdjson_encode($value), ' ', simdjson_encode($value, JSON_PRESERVE_ZERO_FRACTION), "\n";
}

mt_srand(42);
$random = [];
while (count($random) < 100000) {
    $bytes = pack('N', mt_rand(0, 0xFFFF) << 16 | mt_rand(0, 0xFFFF)) . pack('N', mt_rand(0, 0xFFFF) << 16 | mt_rand(0, 0xFFFF));
    $value = unpack('E', $bytes)[1];
    if (is_finite($value)) {
        $random[] = $value;
    }
}
encode_compare_all($random, [0, JSON_PRESERVE_ZERO_FRACTION], 'random');
encode_compare_all(['all' => $random], [0], 'random array');

foreach ([-1, 0, 1, 5, 14, 17, 20] as $precision) {
    ini_set('serialize_precision', $precision);
    encode_compare_all([0.1, 1 / 3, 1e25, 123.456, -0.0, 5e-324, 1e17], [0, JSON_PRESERVE_ZERO_FRACTION], "precision $precision");
}
ini_set('serialize_precision', -1);

foreach ([INF, -INF, NAN, [1, INF]] as $value) {
    try {
        simdjson_encode($value);
    } catch (SimdJsonException $e) {
        var_dump($e->getCode() === JSON_ERROR_INF_OR_NAN, $e->getMessage());
    }
}
var_dump(simdjson_encode([1, NAN], JSON_PARTIAL_OUTPUT_ON_ERROR));
?>
--EXPECT--
0 0.0
-0 -0.0
1 1.0
0.5 0.5
0.1 0.1
0.3333333333333333 0.3333333333333333
0.6666666666666666 0.6666666666666666
1000000000000000 1000000000000000.0
10000000000000000 10000000000000000.0
1.0e+17 1.0e+17
1.2345678901234568e+17 1.2345678901234568e+17
0.001 0.001
0.0001 0.0001
1.0e-5 1.0e-5
5.0e-324 5.0e-324
1.7976931348623157e+308 1.7976931348623157e+308
1.0e+100 1.0e+100
-1.5 -1.5
3.0e-5 3.0e-5
9007199254740992 9007199254740992.0
0.30000000000000004 0.30000000000000004
random: 200000 checked, 0 different
random array: 1 checked, 0 different
precision -1: 14 checked, 0 different
precision 0: 14 checked, 0 different
precision 1: 14 checked, 0 different
precision 5: 14 checked, 0 different
precision 14: 14 checked, 0 different
precision 17: 14 checked, 0 different
precision 20: 14 checked, 0 different
bool(true)
string(34) "Inf and NaN cannot be JSON encoded"
bool(true)
string(34) "Inf and NaN cannot be JSON encoded"
bool(true)
string(34) "Inf and NaN cannot be JSON encoded"
bool(true)
string(34) "Inf and NaN cannot be JSON encoded"
string(5) "[1,0]"
