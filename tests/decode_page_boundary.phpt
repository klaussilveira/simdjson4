--TEST--
simdjson functions return correct results for inputs ending near a memory page boundary
--FILE--
<?php
require __DIR__ . '/page_boundary.inc';
check_page_boundaries();
?>
--EXPECT--
valid documents decoded correctly: 4344
invalid documents rejected: 2715
