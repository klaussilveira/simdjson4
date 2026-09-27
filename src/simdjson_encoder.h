#ifndef SIMDJSON_PHP_ENCODER_H
#define SIMDJSON_PHP_ENCODER_H

#include "php.h"
#include "zend_smart_str.h"

int simdjson_encode_to_smart_str(smart_str *buf, zval *value, zend_long options, zend_long depth);

#endif
