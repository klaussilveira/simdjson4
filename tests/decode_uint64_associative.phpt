--TEST--
simdjson_decode and simdjson_key_value return floats for integers above PHP_INT_MAX in associative mode
--FILE--
<?php
$decoded = simdjson_decode('[9223372036854775808, 18446744073709551615]', true);
var_dump(count($decoded), $decoded[0] === 9223372036854775808.0, $decoded[1] === 18446744073709551615.0);
$value = simdjson_key_value('{"a":{"b":18446744073709551615}}', 'a', true);
var_dump(array_keys($value), $value['b'] === 18446744073709551615.0);
?>
--EXPECT--
int(2)
bool(true)
bool(true)
array(1) {
  [0]=>
  string(1) "b"
}
bool(true)
