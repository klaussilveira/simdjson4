--TEST--
Bug #69187 compat json_last_error return BC in PHP7
--FILE--
<?php
function compat_decode($value) {
    global $lasterr;
    $lasterr = 0;
    try {
        return simdjson_decode($value);
    } catch (SimdJsonException $e) {
        return sprintf("%s: %d: %s", get_class($e), $e->getCode(), $e->getMessage());
    }
}
var_dump(compat_decode(FALSE));
var_dump(compat_decode(""));

var_dump(compat_decode(0));
var_dump(compat_decode(1));
var_dump(compat_decode(TRUE));

var_dump(compat_decode("\xED\xA0\xB4"));  // strangely, 32 bit error is different and "improper structure" instead of "not valid utf-8"

var_dump(compat_decode("\x00"));

var_dump(compat_decode("\"\xED\xA0\xB4\""));

var_dump(compat_decode("\"\x00\""));
var_dump(SIMDJSON_ERR_UNESCAPED_CHARS);
?>
--EXPECTF--
string(43) "SimdJsonException: 13: EMPTY: no JSON found"
string(43) "SimdJsonException: 13: EMPTY: no JSON found"
int(0)
int(1)
int(1)
string(%d) "SimdJsonException: %d: %s
string(178) "SimdJsonException: 3: TAPE_ERROR: The JSON document has an improper structure: missing or superfluous commas, braces, missing keys, etc.  This is a fatal and unrecoverable error."
string(63) "SimdJsonException: 11: UTF8_ERROR: The input is not valid UTF-8"
string(118) "SimdJsonException: 14: UNESCAPED_CHARS: Within strings, some characters must be escaped, we found unescaped characters"
int(14)