<?php
function encode_compare($value, int $flags = 0, int $depth = 512) {
    try {
        $expected = json_encode($value, $flags, $depth);
        $expected_error = ($flags & JSON_THROW_ON_ERROR) ? 0 : json_last_error();
        $expected_message = ($flags & JSON_THROW_ON_ERROR) ? 'No error' : json_last_error_msg();
    } catch (JsonException $e) {
        $expected = false;
        $expected_error = $e->getCode();
        $expected_message = $e->getMessage();
    }
    if ($flags & JSON_PARTIAL_OUTPUT_ON_ERROR) {
        $expected_error = 0;
    }
    try {
        $actual = simdjson_encode($value, $flags, $depth);
        $actual_error = 0;
    } catch (SimdJsonException $e) {
        $actual = false;
        $actual_error = $e->getCode();
        if ($e->getMessage() !== $expected_message) {
            return sprintf("message %s differs from %s", var_export($e->getMessage(), true), var_export($expected_message, true));
        }
    }
    if ($expected === $actual && $expected_error === $actual_error) {
        return null;
    }
    return sprintf("expected %s (error %d), got %s (error %d)", encode_excerpt($expected, $actual), $expected_error, encode_excerpt($actual, $expected), $actual_error);
}

function encode_excerpt($value, $other) {
    if (!is_string($value) || !is_string($other)) {
        return var_export($value, true);
    }
    $at = 0;
    $max = min(strlen($value), strlen($other));
    while ($at < $max && $value[$at] === $other[$at]) {
        $at++;
    }
    return sprintf("%s at offset %d of %d", var_export(substr($value, max(0, $at - 20), 60), true), $at, strlen($value));
}

function encode_compare_all(array $values, array $flag_sets, string $label) {
    $checked = 0;
    $failed = 0;
    foreach ($values as $name => $value) {
        foreach ($flag_sets as $flags) {
            $checked++;
            $result = encode_compare($value, $flags);
            if ($result !== null) {
                $failed++;
                if ($failed <= 10) {
                    echo "$label $name flags=$flags: $result\n";
                }
            }
        }
    }
    echo "$label: $checked checked, $failed different\n";
}

function encode_flag_subsets(array $flags) {
    $sets = [];
    $n = count($flags);
    for ($mask = 0; $mask < (1 << $n); $mask++) {
        $set = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($mask & (1 << $i)) {
                $set |= $flags[$i];
            }
        }
        $sets[] = $set;
    }
    return $sets;
}
