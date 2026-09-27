--TEST--
simdjson_decode returns lists that behave like normal PHP arrays
--FILE--
<?php
foreach ([true, false] as $associative) {
    echo $associative ? "array mode\n" : "object mode\n";

    $a = simdjson_decode('[10,20,30]', $associative);
    var_dump(current($a), key($a));
    $a[] = 40;
    var_dump(array_keys($a));

    $a = simdjson_decode('[[1,2],[],[3]]', $associative);
    $a[0][] = 'x';
    $a[1][] = 'y';
    var_dump($a === [[1, 2, 'x'], ['y'], [3]]);

    $list = simdjson_decode('[' . implode(',', range(0, 9999)) . ']', $associative);
    var_dump(count($list), $list[0], $list[9999], array_sum($list));
    $list[] = 'next';
    var_dump(isset($list[10000]), count($list));
}

$a = simdjson_decode('{"list":[1,"two",3.5,null,true,{"k":[]}]}', true);
$a['list'][] = 'appended';
var_dump($a);
?>
--EXPECT--
array mode
int(10)
int(0)
array(4) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(2)
  [3]=>
  int(3)
}
bool(true)
int(10000)
int(0)
int(9999)
int(49995000)
bool(true)
int(10001)
object mode
int(10)
int(0)
array(4) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(2)
  [3]=>
  int(3)
}
bool(true)
int(10000)
int(0)
int(9999)
int(49995000)
bool(true)
int(10001)
array(1) {
  ["list"]=>
  array(7) {
    [0]=>
    int(1)
    [1]=>
    string(3) "two"
    [2]=>
    float(3.5)
    [3]=>
    NULL
    [4]=>
    bool(true)
    [5]=>
    array(1) {
      ["k"]=>
      array(0) {
      }
    }
    [6]=>
    string(8) "appended"
  }
}
