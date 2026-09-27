--TEST--
simdjson_encode encodes keys shared between many arrays and objects exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--INI--
memory_limit=1G
--FILE--
<?php
require __DIR__ . '/encode.inc';
function rows(array $columns, int $count) {
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = array_combine($columns, array_map(fn($c) => $i . ':' . strlen($c), $columns));
    }
    return $rows;
}
$escaping = ['id', 'a/b', "\xc3\xa9", 'q"t', '<tag>', '', "\xe2\x80\xa8", 'k7'];
$many = array_map(fn($i) => "column_$i", range(0, 99));
$long = array_map(fn($i) => str_repeat(chr(97 + $i % 26), 40) . $i, range(0, 19));
$values = [
    'escaping rows' => rows($escaping, 200),
    'escaping objects' => array_map(fn($r) => (object)$r, rows($escaping, 200)),
    'many columns' => rows($many, 50),
    'invalid key' => rows(['ok', "bad\xff"], 3),
    'large' => ['nested' => [rows($long, 3000), (object)['again' => rows($long, 2)]]],
];
$flags = [0, JSON_PRETTY_PRINT, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS];
encode_compare_all($values, $flags, 'shared keys');
echo simdjson_encode(rows(['a/b', 'c'], 2)), "\n";
?>
--EXPECT--
shared keys: 25 checked, 0 different
[{"a\/b":"0:3","c":"0:1"},{"a\/b":"1:3","c":"1:1"}]
