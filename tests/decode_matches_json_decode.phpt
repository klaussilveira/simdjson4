--TEST--
simdjson_decode returns the same values as json_decode for the bundled example files
--SKIPIF--
<?php
if (!function_exists('json_decode')) { echo "skip json extension required\n"; }
if (!is_dir(dirname(__DIR__) . '/jsonexamples/small')) { echo "skip jsonexamples directory not available\n"; }
?>
--INI--
memory_limit=1G
--FILE--
<?php
$dir = dirname(__DIR__) . '/jsonexamples';
$files = array_merge(glob("$dir/*.json"), glob("$dir/small/*.json"));
foreach ($files as $file) {
    $json = file_get_contents($file);
    foreach ([true, false] as $associative) {
        $expected = serialize(json_decode($json, $associative));
        $actual = serialize(simdjson_decode($json, $associative));
        printf("%s %s: %s\n", basename($file), $associative ? 'array' : 'object', $expected === $actual ? 'same' : 'DIFFERENT');
    }
}
?>
--EXPECT--
apache_builds.json array: same
apache_builds.json object: same
canada.json array: same
canada.json object: same
citm_catalog.json array: same
citm_catalog.json object: same
github_events.json array: same
github_events.json object: same
gsoc-2018.json array: same
gsoc-2018.json object: same
instruments.json array: same
instruments.json object: same
marine_ik.json array: same
marine_ik.json object: same
mesh.json array: same
mesh.json object: same
mesh.pretty.json array: same
mesh.pretty.json object: same
numbers.json array: same
numbers.json object: same
random.json array: same
random.json object: same
stringifiedphp.json array: same
stringifiedphp.json object: same
twitter.json array: same
twitter.json object: same
twitterescaped.json array: same
twitterescaped.json object: same
update-center.json array: same
update-center.json object: same
adversarial.json array: same
adversarial.json object: same
demo.json array: same
demo.json object: same
flatadversarial.json array: same
flatadversarial.json object: same
repeat.json array: same
repeat.json object: same
truenull.json array: same
truenull.json object: same
twitter_timeline.json array: same
twitter_timeline.json object: same
