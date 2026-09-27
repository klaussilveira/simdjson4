--TEST--
simdjson functions reject a leading UTF-8 byte order mark like json_decode
--FILE--
<?php
foreach (["\xEF\xBB\xBF[1]", "\xEF\xBB\xBF", "\xEF\xBB\xBF{\"a\":1}"] as $json) {
    var_dump(json_decode($json), simdjson_is_valid($json));
    foreach (['simdjson_decode', 'simdjson_key_exists', 'simdjson_key_value', 'simdjson_key_count'] as $function) {
        try {
            $function === 'simdjson_decode' ? simdjson_decode($json) : $function($json, 'a');
            echo "$function accepted\n";
        } catch (SimdJsonException $e) {
            var_dump($e->getCode() === SIMDJSON_ERR_TAPE_ERROR);
        }
    }
}
var_dump(bin2hex(simdjson_decode("[\"\xEF\xBB\xBF\"]")[0]));
?>
--EXPECT--
NULL
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
NULL
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
NULL
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
string(6) "efbbbf"
