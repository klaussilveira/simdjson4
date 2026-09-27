--TEST--
simdjson_encode encodes objects with property hooks and lazy objects exactly like json_encode
--SKIPIF--
<?php if (PHP_VERSION_ID < 80400) { echo "skip requires PHP 8.4+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';
class Hooked {
    public int $stored = 1;
    public int $doubled { get => $this->stored * 2; }
    public string $name = 'n' { set => strtoupper($value); }
}
class Ghost {
    public $a = 1;
    public $b = [2];
}
$reflector = new ReflectionClass(Ghost::class);
$values = [
    'hooked' => new Hooked(),
    'ghost' => $reflector->newLazyGhost(function (Ghost $g) { $g->a = 10; }),
    'proxy' => [$reflector->newLazyProxy(fn() => new Ghost())],
];
encode_compare_all($values, [0, JSON_PRETTY_PRINT], 'hooks and lazy');
?>
--EXPECT--
hooks and lazy: 6 checked, 0 different
