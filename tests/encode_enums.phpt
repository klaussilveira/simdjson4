--TEST--
simdjson_encode encodes enums exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80100) { echo "skip requires PHP 8.1+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';
enum Suit: string {
    case Hearts = 'H';
}
enum Size: int {
    case Large = 3;
}
enum Pure {
    case One;
}
encode_compare_all(['backed' => [Suit::Hearts, Size::Large], 'pure' => [1, Pure::One]], [0, JSON_PRETTY_PRINT, JSON_PARTIAL_OUTPUT_ON_ERROR], 'enums');
echo simdjson_encode(['s' => Suit::Hearts, 'n' => Size::Large]), "\n";
try {
    simdjson_encode([Pure::One]);
} catch (SimdJsonException $e) {
    var_dump($e->getCode() === JSON_ERROR_NON_BACKED_ENUM, $e->getMessage());
}
?>
--EXPECT--
enums: 6 checked, 0 different
{"s":"H","n":3}
bool(true)
string(46) "Non-backed enums have no default serialization"
