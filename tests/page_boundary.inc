<?php
function page_boundary_lengths(): array {
    $lengths = range(16, 300);
    foreach ([4096, 8192] as $page_end) {
        $lengths = array_merge($lengths, range($page_end - 120, $page_end + 8));
    }
    return $lengths;
}

function check_page_boundaries() {
    $valid = ['[1,2,3]' => [1, 2, 3], '{"a":true}' => ['a' => true], '123456' => 123456, '"str"' => 'str',
        'true' => true, 'null' => null, '-1.5e3' => -1500.0, '[[[]]]' => [[[]]]];
    $invalid = ['[1,2', '"abc', 'tru', '{"a":', '123a'];
    $keep = [];
    $valid_ok = 0;
    $invalid_rejected = 0;
    foreach (page_boundary_lengths() as $len) {
        foreach ([true, false] as $is_valid) {
            foreach ($is_valid ? array_keys($valid) : $invalid as $core) {
                $doc = str_repeat(' ', $len - strlen($core)) . $core;
                $keep[] = $doc;
                $keep[] = substr(str_repeat('9"]}1', intdiv($len, 5) + 1), 0, $len);
                try {
                    $result = simdjson_decode($doc, true);
                    $threw = false;
                } catch (SimdJsonException $e) {
                    $threw = true;
                }
                if ($is_valid && !$threw && $result === $valid[$core] && simdjson_is_valid($doc)) {
                    $valid_ok++;
                } elseif (!$is_valid && $threw && !simdjson_is_valid($doc)) {
                    $invalid_rejected++;
                } else {
                    echo "Unexpected result for length $len: " . var_export($core, true) . "\n";
                }
            }
        }
    }
    echo "valid documents decoded correctly: $valid_ok\n";
    echo "invalid documents rejected: $invalid_rejected\n";
}
