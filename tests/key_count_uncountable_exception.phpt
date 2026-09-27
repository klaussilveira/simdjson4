--TEST--
simdjson_key_count throws for values that cannot be counted when $throw_if_uncountable is true
--FILE--
<?php
foreach ([['{"a":5}', 'a'], ['{"a":[1,"x"]}', 'a/1'], ['null', '']] as $case) {
    list($json, $key) = $case;
    var_dump(simdjson_key_count($json, $key));
    try {
        simdjson_key_count($json, $key, 512, true);
    } catch (SimdJsonException $e) {
        var_dump($e->getCode() === SIMDJSON_ERR_KEY_COUNT_NOT_COUNTABLE, $e->getMessage());
    }
}
var_dump(simdjson_key_count('{"a":[1,2,3]}', 'a', 512, true));
?>
--EXPECT--
int(0)
bool(true)
string(53) "JSON pointer refers to a value that cannot be counted"
int(0)
bool(true)
string(53) "JSON pointer refers to a value that cannot be counted"
int(0)
bool(true)
string(53) "JSON pointer refers to a value that cannot be counted"
int(3)
