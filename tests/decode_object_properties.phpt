--TEST--
simdjson_decode builds stdClass properties with the same semantics as json_decode
--FILE--
<?php
$o = simdjson_decode('{"a":1,"b":2,"a":3}');
var_dump($o);

$o = simdjson_decode('{"0":"zero","123":"num","-1":"neg","01":"lead","1.5":"float"}');
var_dump($o);
var_dump($o->{'0'}, $o->{'123'}, $o->{'-1'}, $o->{'01'}, $o->{'1.5'});
var_dump(isset($o->{'123'}), isset($o->{'124'}));

$o = simdjson_decode('{"a":1,"b":2}');
$o->c = 3;
unset($o->a);
$o->b = 20;
var_dump($o);

$o = simdjson_decode('{}');
$o->added = true;
var_dump($o);

$o = simdjson_decode('[{"k":1},{"k":2}]');
$o[0]->k = 10;
var_dump($o[0]->k, $o[1]->k);

$parts = [];
for ($i = 0; $i < 40; $i++) {
    $parts[] = "\"k$i\":$i";
}
$o = simdjson_decode('{' . implode(',', $parts) . '}');
$vars = get_object_vars($o);
var_dump(count($vars), $o->k0, $o->k39, implode(',', array_slice(array_keys($vars), 0, 5)));
?>
--EXPECTF--
object(stdClass)#%d (2) {
  ["a"]=>
  int(3)
  ["b"]=>
  int(2)
}
object(stdClass)#%d (5) {
  ["0"]=>
  string(4) "zero"
  ["123"]=>
  string(3) "num"
  ["-1"]=>
  string(3) "neg"
  ["01"]=>
  string(4) "lead"
  ["1.5"]=>
  string(5) "float"
}
string(4) "zero"
string(3) "num"
string(3) "neg"
string(4) "lead"
string(5) "float"
bool(true)
bool(false)
object(stdClass)#%d (2) {
  ["b"]=>
  int(20)
  ["c"]=>
  int(3)
}
object(stdClass)#%d (1) {
  ["added"]=>
  bool(true)
}
int(10)
int(2)
int(40)
int(0)
int(39)
string(14) "k0,k1,k2,k3,k4"
