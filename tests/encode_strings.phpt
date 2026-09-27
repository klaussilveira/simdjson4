--TEST--
simdjson_encode escapes strings exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';
$string_flags = encode_flag_subsets([JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS, JSON_HEX_QUOT,
    JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_LINE_TERMINATORS]);

$bytes = [];
for ($i = 0; $i < 256; $i++) {
    $bytes[$i] = chr($i);
}
encode_compare_all($bytes, $string_flags, 'single bytes');

$blocks = [];
for ($block = 0; $block < 256; $block++) {
    if ($block >= 0xD8 && $block <= 0xDF) {
        continue;
    }
    $s = '';
    for ($i = 0; $i < 256; $i++) {
        $s .= mb_chr_compat($block * 256 + $i);
    }
    $blocks[$block] = $s;
}
$blocks['astral'] = mb_chr_compat(0x10000) . mb_chr_compat(0x1F600) . 'x' . mb_chr_compat(0x10FFFF) . mb_chr_compat(0x2F800);
$unicode_flags = [0, JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT, JSON_UNESCAPED_SLASHES];
encode_compare_all($blocks, $unicode_flags, 'code points');

$invalid = ["\x80", "\xc0\x80", "\xc2", "\xe0\x80\x80", "\xed\xa0\x80", "\xf4\x90\x80\x80", "\xf8\x88\x80\x80\x80",
    "\xff", "a\xffb", "\xe2\x82", "\xf0\x9f\x98", "é\x80"];
for ($n = 0; $n <= 40; $n++) {
    $invalid[] = str_repeat('a', $n) . "\xff" . str_repeat('b', 3);
}
$sequences = [];
$trails = [0x00, 0x7f, 0x80, 0x8f, 0x90, 0x9f, 0xa0, 0xbf, 0xc0, 0xff];
for ($lead = 0x80; $lead <= 0xff; $lead++) {
    for ($second = 0; $second <= 0xff; $second++) {
        $sequences[] = chr($lead) . chr($second);
    }
    foreach ($trails as $second) {
        foreach ($trails as $third) {
            $sequences[] = chr($lead) . chr($second) . chr($third) . 'z';
            foreach ($lead >= 0xf0 ? $trails : [] as $fourth) {
                $sequences[] = chr($lead) . chr($second) . chr($third) . chr($fourth);
            }
        }
    }
}
encode_compare_all($sequences, [0, JSON_UNESCAPED_UNICODE], 'utf-8 sequences');

$invalid_flags = [0, JSON_UNESCAPED_UNICODE, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE,
    JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE, JSON_PARTIAL_OUTPUT_ON_ERROR];
encode_compare_all($invalid, $invalid_flags, 'invalid utf-8');
encode_compare_all([$invalid], $invalid_flags, 'invalid utf-8 in array');

$positions = [];
foreach (['"', '\\', '/', "\n", "\x01", "\x1f", '<', '&', "'", 'é', "\u{2028}", '😀', "\x7f"] as $special) {
    for ($len = 0; $len <= 70; $len++) {
        for ($pos = 0; $pos <= $len; $pos++) {
            $positions[] = str_repeat('x', $pos) . $special . str_repeat('y', $len - $pos);
        }
    }
}
encode_compare_all($positions, [0, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT], 'positions');

$keys = [
    ["a\"b" => 1, "c/d" => 2, "é" => 3, "\n" => 4, "" => 5, "123" => 6, "-1" => 7, "01" => 8],
    ["\xff" => 1],
    ["ok" => 1, "\xff" => INF],
    [5 => 'a', "x" => 'b', -3 => 'c'],
];
encode_compare_all($keys, [0, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, JSON_PRETTY_PRINT, JSON_INVALID_UTF8_SUBSTITUTE, JSON_PARTIAL_OUTPUT_ON_ERROR], 'keys');
encode_compare_all(array_map(fn($k) => (object)$k, $keys), [0, JSON_PRETTY_PRINT, JSON_PARTIAL_OUTPUT_ON_ERROR], 'object keys');

function mb_chr_compat(int $cp) {
    if ($cp < 0x80) {
        return chr($cp);
    }
    if ($cp < 0x800) {
        return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
    }
    if ($cp < 0x10000) {
        return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
    return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
}
?>
--EXPECT--
single bytes: 32768 checked, 0 different
code points: 1245 checked, 0 different
utf-8 sequences: 123136 checked, 0 different
invalid utf-8: 318 checked, 0 different
invalid utf-8 in array: 6 checked, 0 different
positions: 99684 checked, 0 different
keys: 20 checked, 0 different
object keys: 12 checked, 0 different
