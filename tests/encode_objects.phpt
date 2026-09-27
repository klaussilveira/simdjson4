--TEST--
simdjson_encode encodes objects exactly like json_encode and calls jsonSerialize once
--SKIPIF--
<?php if (PHP_VERSION_ID < 80000) { echo "skip requires PHP 8.0+\n"; } ?>
--FILE--
<?php
require __DIR__ . '/encode.inc';

#[AllowDynamicProperties]
class Visibility {
    public $a = 1;
    protected $b = 2;
    private $c = 3;
    public int $d;
    public $e;
}

class Counted implements JsonSerializable {
    public static $calls = 0;
    public function jsonSerialize(): mixed {
        self::$calls++;
        return ['x' => 1, 'f' => 2.5];
    }
}

class Throws implements JsonSerializable {
    public function jsonSerialize(): mixed {
        throw new RuntimeException('boom');
    }
}

$dynamic = new Visibility();
$dynamic->extra = [1, 2];
$mangled = (object)["\0*\0prot" => 1, "\0A\0priv" => 2, "pub" => 3, "" => 4];
$recursive = new stdClass();
$recursive->self = $recursive;

$values = [
    'stdClass' => (object)['a' => (object)['b' => [1, (object)[]]]],
    'mangled' => $mangled,
    'visibility' => new Visibility(),
    'dynamic' => $dynamic,
    'jsonserializable' => [1, ['nested' => new Counted(), 'f' => 1.5]],
    'datetime' => new DateTime('2020-01-02 03:04:05', new DateTimeZone('UTC')),
    'arrayobject' => new ArrayObject([1, 2]),
    'closure' => function () {},
    'resource' => STDIN,
    'recursive' => $recursive,
    'nested resource' => [1, [STDIN]],
];
encode_compare_all($values, [0, JSON_PRETTY_PRINT, JSON_FORCE_OBJECT, JSON_PARTIAL_OUTPUT_ON_ERROR], 'objects');

Counted::$calls = 0;
echo simdjson_encode([1, ['nested' => new Counted(), 'f' => 1.5]], JSON_PRETTY_PRINT), "\n";
var_dump(Counted::$calls);

echo simdjson_encode($mangled), "\n";
echo simdjson_encode(new Visibility()), "\n";

try {
    simdjson_encode([1, 2, [new Throws()]]);
} catch (Exception $e) {
    printf("%s: %s\n", get_class($e), $e->getMessage());
}
try {
    simdjson_encode(STDIN);
} catch (SimdJsonException $e) {
    var_dump($e->getCode() === JSON_ERROR_UNSUPPORTED_TYPE, $e->getMessage());
}
?>
--EXPECT--
objects: 44 checked, 0 different
[
    1,
    {
        "nested": {
            "x": 1,
            "f": 2.5
        },
        "f": 1.5
    }
]
int(1)
{"pub":3,"":4}
{"a":1,"e":null}
RuntimeException: boom
bool(true)
string(21) "Type is not supported"
