--TEST--
simdjson_decode throws and frees partially built lists when an object inside a list has an invalid property
--FILE--
<?php
$cases = [
    '[{"\u0000a":1}]',
    '[1,{"\u0000a":1},3]',
    '["x","y",{"\u0000a":1}]',
    '[[[{"b":[{"\u0000a":1}]}]]]',
    '[' . str_repeat('{"ok":[1,2,"s"]},', 1000) . '{"\u0000a":1}]',
    '{"list":[{"a":"b"},{"\u0000a":1}]}',
];
foreach ($cases as $json) {
    try {
        simdjson_decode($json);
        echo "no exception\n";
    } catch (SimdJsonException $e) {
        var_dump($e->getCode() === SIMDJSON_ERR_INVALID_PROPERTY, $e->getMessage());
    }
    var_dump(count(simdjson_decode($json, true)));
}
var_dump(simdjson_decode('[{"a":1}]'));
?>
--EXPECTF--
bool(true)
string(21) "Invalid property name"
int(1)
bool(true)
string(21) "Invalid property name"
int(3)
bool(true)
string(21) "Invalid property name"
int(3)
bool(true)
string(21) "Invalid property name"
int(1)
bool(true)
string(21) "Invalid property name"
int(1001)
bool(true)
string(21) "Invalid property name"
int(1)
array(1) {
  [0]=>
  object(stdClass)#%d (1) {
    ["a"]=>
    int(1)
  }
}
