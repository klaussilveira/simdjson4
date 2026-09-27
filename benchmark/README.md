# Benchmark

The example outputs below were produced on an Intel Core i7-9700F (AVX2) with a release build of PHP 8.5.11 on Linux.

## Build project

Build the project from the project root folder:

```
phpize
./configure
make
make test
```

## Install PHP Composer dependencies

[Install Composer](https://getcomposer.org/download/) if not already done and execute it in the benchmark folder:

```
composer install
```

## Run PHPBench benchmark

Execute from project root folder. The available groups are `decode`, `encode`, `key_value` and `multiple`.

Each benchmark class gets its own table. The `diff` column is the mean time relative to the fastest subject in that table, so `1.00x` is the fastest and `2.50x` takes two and a half times as long.

```
php benchmark/vendor/bin/phpbench run --report=table --group decode
```

```
SingleCharStringsBench
+---------------------+-----------+-----------+-----------+-------+
| subject             | mem_peak  | mean      | best      | diff  |
+---------------------+-----------+-----------+-----------+-------+
| jsonDecodeAssoc     | 751.896kb | 0.03122ms | 0.03087ms | 3.54x |
| jsonDecode          | 751.896kb | 0.03505ms | 0.03457ms | 3.98x |
| simdjsonDecodeAssoc | 751.912kb | 0.00881ms | 0.00873ms | 1.00x |
| simdjsonDecode      | 751.896kb | 0.01027ms | 0.01015ms | 1.17x |
+---------------------+-----------+-----------+-----------+-------+

DecodeBench
+---------------------+-----------+-----------+-----------+-------+
| subject             | mem_peak  | mean      | best      | diff  |
+---------------------+-----------+-----------+-----------+-------+
| jsonDecodeAssoc     | 751.856kb | 0.00305ms | 0.00302ms | 2.52x |
| jsonDecode          | 751.856kb | 0.00336ms | 0.00332ms | 2.78x |
| simdjsonDecodeAssoc | 751.872kb | 0.00121ms | 0.00120ms | 1.00x |
| simdjsonDecode      | 751.856kb | 0.00130ms | 0.00129ms | 1.07x |
+---------------------+-----------+-----------+-----------+-------+
```

```
php benchmark/vendor/bin/phpbench run --report=table --group encode
```

```
EncodeBench
+----------------+-----------+-----------+-----------+-------+
| subject        | mem_peak  | mean      | best      | diff  |
+----------------+-----------+-----------+-----------+-------+
| jsonEncode     | 751.856kb | 0.00147ms | 0.00144ms | 1.87x |
| simdjsonEncode | 751.856kb | 0.00079ms | 0.00077ms | 1.00x |
+----------------+-----------+-----------+-----------+-------+
```

```
php benchmark/vendor/bin/phpbench run --report=table --group key_value
```

```
KeyValueBench
+-------------------------+-----------+-----------+-----------+-------+
| subject                 | mem_peak  | mean      | best      | diff  |
+-------------------------+-----------+-----------+-----------+-------+
| jsonDecode              | 751.856kb | 0.00312ms | 0.00305ms | 6.76x |
| simdjsonDeepString      | 751.872kb | 0.00053ms | 0.00051ms | 1.15x |
| simdjsonDeepStringAssoc | 751.872kb | 0.00052ms | 0.00051ms | 1.12x |
| simdjsonInt             | 751.856kb | 0.00046ms | 0.00046ms | 1.00x |
| simdjsonIntAssoc        | 751.872kb | 0.00047ms | 0.00046ms | 1.03x |
| simdjsonArray           | 751.856kb | 0.00081ms | 0.00081ms | 1.75x |
| simdjsonObject          | 751.856kb | 0.00085ms | 0.00083ms | 1.85x |
+-------------------------+-----------+-----------+-----------+-------+
```

```
php benchmark/vendor/bin/phpbench run --report=table --group multiple
```

```
MultipleAccessBench
+-----------------------------------------+-----------+-----------+-----------+-------+
| subject                                 | mem_peak  | mean      | best      | diff  |
+-----------------------------------------+-----------+-----------+-----------+-------+
| simdjsonMultipleAccessSameDocument      | 751.912kb | 0.00333ms | 0.00327ms | 1.00x |
| simdjsonMultipleAccessDifferentDocument | 751.912kb | 0.00366ms | 0.00362ms | 1.10x |
+-----------------------------------------+-----------+-----------+-----------+-------+
```

## Run benchmark

You may also run a simpler standalone benchmark script on the JSON files in [`jsonexamples`](../jsonexamples) by running the commands:

```
php -d extension=modules/simdjson4.so benchmark/benchmark.php
```

It prints the average time in nanoseconds per call, and the time relative to `json_decode()` or `json_encode()` (lower is faster).
For decoding functions, the benchmark includes both the time to decode the data and the time to garbage collect/free the decoded data.
The encoding columns are only printed on PHP 8.0+, where `simdjson_encode()` is available, and encode the result of `json_decode($json, true)` for each file.

The output should look like this

```
filename|json_decode|simdjson_decode|simdjson_is_valid|relative_decode|relative_is_valid|json_encode|simdjson_encode|relative_encode
---|:--:|---:|---:|---:|--:|:--:|---:|--:
apache_builds.json|438367|170393|41325|0.39x|0.09x|165201|129448|0.78x
canada.json|29561295|6019318|2615612|0.20x|0.09x|56231603|5101575|0.09x
citm_catalog.json|5021009|1794312|576352|0.36x|0.11x|938014|888934|0.95x
github_events.json|231048|94173|21435|0.41x|0.09x|93885|67034|0.71x
gsoc-2018.json|12045805|2233120|1004252|0.19x|0.08x|5572029|2765892|0.50x
instruments.json|835633|324127|93231|0.39x|0.11x|202734|180721|0.89x
marine_ik.json|22034995|11289772|3842123|0.51x|0.17x|32787584|7676053|0.23x
mesh.json|4931792|1496656|872970|0.30x|0.18x|12308844|1561998|0.13x
mesh.pretty.json|8691933|1848730|1092291|0.21x|0.13x|12166235|1570166|0.13x
numbers.json|807511|212434|158722|0.26x|0.20x|4079222|357409|0.09x
random.json|2479882|1087597|279902|0.44x|0.11x|1049808|895421|0.85x
stringifiedphp.json|465255|67253|59785|0.14x|0.13x|303083|101559|0.34x
twitter.json|2114069|725493|224950|0.34x|0.11x|905441|874359|0.97x
twitterescaped.json|2360976|879829|400374|0.37x|0.17x|864055|692466|0.80x
update-center.json|2489384|894594|227576|0.36x|0.09x|1119636|770222|0.69x
```

- `canada.json` is an example of a string with a lot of floats (polygon for a map of canada).
  Same for the `mesh*.json` files and `numbers.json` file
- `stringifiedphp.json` is an example of a single long JSON encoded string (representation of php file with newlines and quotes).
- `twitter.json` is an example of decoding data with a mix of types with a lot of non-ascii codepoints.
  `twitterescaped.json` is the same data with `"\uXXXX"` escaping and no whitespace.
- `random.json` is a large object with a lot of short keys, small objects/arrays, and short string/integer values, with some non-ASCII values.
  `apache_builds.json` contains a lot of whitespace and relatively small objects, string keys, and mostly string values.
