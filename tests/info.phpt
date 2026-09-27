--TEST--
simdjson reports its version and active implementation in phpinfo()
--FILE--
<?php
(new ReflectionExtension('simdjson4'))->info();
?>
--EXPECTF--
simdjson4

simdjson4 support => enabled
Version => %s
Support => https://github.com/klaussilveira/simdjson4
Implementation => %s
