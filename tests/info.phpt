--TEST--
simdjson reports its version and active implementation in phpinfo()
--FILE--
<?php
(new ReflectionExtension('simdjson'))->info();
?>
--EXPECTF--
simdjson

simdjson support => enabled
Version => %s
Support => https://github.com/crazyxman/simdjson_php
Implementation => %s
