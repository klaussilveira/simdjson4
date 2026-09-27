<?php

declare(strict_types=1);

namespace SimdjsonBench;

use PhpBench\Benchmark\Metadata\Annotations\Subject;

if (!function_exists('simdjson_encode')) {
        exit;
}

/**
 * @Revs(1000)
 * @Iterations(5)
 * @Warmup(3)
 * @OutputTimeUnit("milliseconds", precision=5)
 * @BeforeMethods({"init"})
 * @Groups({"encode"})
 */
class EncodeBench
{

    private array $data;

    private string $expected;

    public function init(): void
    {
        $json = <<<EOF
{ 
  "result" : [ 
    { 
      "_key" : "70614", 
      "_id" : "products/70614",
      "_rev" : "_al3hU1K---", 
      "Hello3" : "World3" 
    }, 
    { 
      "_key" : "70616", 
      "_id" : "products/70616", 
      "_rev" : "_al3hU1K--A", 
      "Hello4" : "World4" 
    } 
  ], 
  "hasMore" : false, 
  "count" : 2, 
  "cached" : false, 
  "extra" : { 
    "stats" : { 
      "writesExecuted" : 0, 
      "writesIgnored" : 0, 
      "scannedFull" : 4, 
      "scannedIndex" : 0, 
      "filtered" : 0, 
      "httpRequests" : 0, 
      "executionTime" : 0.00014734268188476562, 
      "peakMemoryUsage" : 2558 
    }, 
    "warnings" : [ ] 
  }, 
  "error" : false, 
  "code" : 201 
}
EOF;
        $this->data = json_decode($json, true);
        $this->expected = json_encode($this->data);
    }

    /**
     * @Subject()
     */
    public function jsonEncode(): void
    {
        if ($this->expected !== json_encode($this->data)) {
            throw new \RuntimeException('error');
        }
    }

    /**
     * @Subject()
     */
    public function simdjsonEncode(): void
    {
        if ($this->expected !== \simdjson_encode($this->data)) {
            throw new \RuntimeException('error');
        }
    }

}
