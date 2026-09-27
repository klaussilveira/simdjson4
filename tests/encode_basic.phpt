--TEST--
simdjson_encode encodes scalars, strings, arrays and objects
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; }
if (PHP_INT_SIZE < 8) { echo "skip 64-bit only\n"; }
?>
--FILE--
<?php
$values = [
    null, true, false, 0, -1, PHP_INT_MAX, PHP_INT_MIN, 1.5, -0.0, 0.1, 1e25, 1e-7, 100.0,
    "", "a", "a/b", "\"\\", "\n\t\r\x08\x0c\x01\x1f", "é", "😀", "<>&'",
    [], [1, 2, 3], [1 => 1], ["a" => 1, "b" => [true, null]], [0 => 'a', 2 => 'b'],
    new stdClass(), (object)['a' => 1, 'b' => (object)[]],
];
foreach ($values as $value) {
    echo simdjson_encode($value), "\n";
}
var_dump(simdjson_encode(1));
echo simdjson_encode("<>&'\"", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), "\n";
echo simdjson_encode("é/😀", JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
echo simdjson_encode("\u{2028}", JSON_UNESCAPED_UNICODE), "\n";
echo bin2hex(simdjson_encode("\u{2028}", JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS)), "\n";
echo simdjson_encode([1, 2], JSON_FORCE_OBJECT), "\n";
echo simdjson_encode([10.0, -0.0, 1e17, 0.5], JSON_PRESERVE_ZERO_FRACTION), "\n";
echo simdjson_encode(['a' => [1, 2], 'b' => [], 'c' => new stdClass()], JSON_PRETTY_PRINT), "\n";
?>
--EXPECT--
null
true
false
0
-1
9223372036854775807
-9223372036854775808
1.5
-0
0.1
1.0e+25
1.0e-7
100
""
"a"
"a\/b"
"\"\\"
"\n\t\r\b\f\u0001\u001f"
"\u00e9"
"\ud83d\ude00"
"<>&'"
[]
[1,2,3]
{"1":1}
{"a":1,"b":[true,null]}
{"0":"a","2":"b"}
{}
{"a":1,"b":{}}
string(1) "1"
"\u003C\u003E\u0026\u0027\u0022"
"é/😀"
"\u2028"
22e280a822
{"0":1,"1":2}
[10.0,-0.0,1.0e+17,0.5]
{
    "a": [
        1,
        2
    ],
    "b": [],
    "c": {}
}
