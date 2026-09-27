--TEST--
simdjson key functions throw the same errors as simdjson_decode for invalid JSON
--FILE--
<?php
$cases = [
    '{"a":' => SIMDJSON_ERR_TAPE_ERROR,
    '[1,2' => SIMDJSON_ERR_TAPE_ERROR,
    '{"a" 1}' => SIMDJSON_ERR_TAPE_ERROR,
    '' => SIMDJSON_ERR_EMPTY,
    'tru' => SIMDJSON_ERR_T_ATOM_ERROR,
];
$calls = [
    'simdjson_key_exists' => function ($json) {
        return simdjson_key_exists($json, 'a');
    },
    'simdjson_key_value' => function ($json) {
        return simdjson_key_value($json, 'a');
    },
    'simdjson_key_count' => function ($json) {
        return simdjson_key_count($json, 'a');
    },
    'simdjson_decode' => function ($json) {
        return simdjson_decode($json);
    },
];
foreach ($cases as $json => $code) {
    foreach ($calls as $name => $call) {
        try {
            $call($json);
            echo "$name did not throw for ", var_export($json, true), "\n";
        } catch (SimdJsonException $e) {
            if ($e->getCode() !== $code) {
                echo "$name threw code {$e->getCode()} for ", var_export($json, true), "\n";
            }
        }
    }
}
echo "done\n";
?>
--EXPECT--
done
