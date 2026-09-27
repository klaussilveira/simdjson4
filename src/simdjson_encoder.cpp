extern "C" {
#include "php.h"
#include "zend_smart_str.h"
}

#if PHP_VERSION_ID >= 80000

extern "C" {
#include "ext/json/php_json.h"
#if PHP_VERSION_ID >= 80300 && defined(ZEND_CHECK_STACK_LIMIT)
#include "zend_call_stack.h"
#endif
}

#include <cmath>
#include <cstdint>
#include <cstring>

#if defined(__SSE2__) || defined(_M_X64) || (defined(_M_IX86_FP) && _M_IX86_FP >= 2)
#include <emmintrin.h>
#define SIMDJSON_ENCODE_SSE2 1
#if defined(_MSC_VER) && !defined(__clang__)
#include <intrin.h>
#endif
#endif

#include "simdjson_encoder.h"
#include "dragonbox.h"

#define SIMDJSON_ENCODE_FALLBACK_OPTIONS (PHP_JSON_NUMERIC_CHECK | PHP_JSON_PARTIAL_OUTPUT_ON_ERROR | PHP_JSON_INVALID_UTF8_IGNORE | PHP_JSON_INVALID_UTF8_SUBSTITUTE)
#define SIMDJSON_SWAR_ONES 0x0101010101010101ULL
#define SIMDJSON_SWAR_HIGH 0x8080808080808080ULL

#define SIMDJSON_KEY_CACHE_SIZE 32

struct simdjson_key_cache_entry {
    const zend_string *key;
    size_t offset;
    size_t length;
};

struct simdjson_encoder {
    bool key_cache_ready;
    simdjson_key_cache_entry key_cache[SIMDJSON_KEY_CACHE_SIZE];
    int depth;
    int max_depth;
    int options;
    const uint8_t *escape_table;
    const uint64_t *escape_extra;
    int escape_extra_count;
};

struct simdjson_escape_tables {
    uint8_t table[16][256];
    uint64_t extra[16][5];
    int extra_count[16];
};

static constexpr simdjson_escape_tables simdjson_make_escape_tables() {
    simdjson_escape_tables tables = {};
    for (int variant = 0; variant < 16; variant++) {
        for (int c = 0; c < 256; c++) {
            bool special = c < 0x20 || c == '"' || c == '\\' || c >= 0x80
                || ((variant & 1) && c == '/')
                || ((variant & 2) && (c == '<' || c == '>'))
                || ((variant & 4) && c == '&')
                || ((variant & 8) && c == '\'');
            tables.table[variant][c] = special ? 1 : 0;
        }
        const char extra[] = {'/', '<', '>', '&', '\''};
        for (char c : extra) {
            if (tables.table[variant][(unsigned char)c]) {
                tables.extra[variant][tables.extra_count[variant]++] = SIMDJSON_SWAR_ONES * (unsigned char)c;
            }
        }
    }
    return tables;
}

static constexpr simdjson_escape_tables simdjson_escape_tables_data = simdjson_make_escape_tables();

static const char simdjson_hex_digits[] = "0123456789abcdef";

static const char simdjson_two_digits[] =
    "00010203040506070809101112131415161718192021222324252627282930313233343536373839"
    "40414243444546474849505152535455565758596061626364656667686970717273747576777879"
    "8081828384858687888990919293949596979899";

static bool simdjson_encode_zval(smart_str *buf, zval *val, simdjson_encoder *encoder);

static void simdjson_encoder_init(simdjson_encoder *encoder, int options, int max_depth) {
    encoder->key_cache_ready = false;
    encoder->depth = 0;
    encoder->max_depth = max_depth;
    encoder->options = options;
    int variant = (options & PHP_JSON_UNESCAPED_SLASHES ? 0 : 1)
        | (options & PHP_JSON_HEX_TAG ? 2 : 0)
        | (options & PHP_JSON_HEX_AMP ? 4 : 0)
        | (options & PHP_JSON_HEX_APOS ? 8 : 0);
    encoder->escape_table = simdjson_escape_tables_data.table[variant];
    encoder->escape_extra = simdjson_escape_tables_data.extra[variant];
    encoder->escape_extra_count = simdjson_escape_tables_data.extra_count[variant];
}

#ifdef SIMDJSON_ENCODE_SSE2
static zend_always_inline unsigned int simdjson_first_set_bit(unsigned int mask) {
#if defined(_MSC_VER) && !defined(__clang__)
    unsigned long index;
    _BitScanForward(&index, mask);
    return (unsigned int)index;
#else
    return (unsigned int)__builtin_ctz(mask);
#endif
}
#endif

#ifndef SIMDJSON_ENCODE_SSE2
static zend_always_inline uint64_t simdjson_swar_less_than(uint64_t x, uint64_t n) {
    return (x - SIMDJSON_SWAR_ONES * n) & ~x & SIMDJSON_SWAR_HIGH;
}

static zend_always_inline uint64_t simdjson_swar_equal(uint64_t x, uint64_t pattern) {
    return simdjson_swar_less_than(x ^ pattern, 1);
}

static zend_always_inline bool simdjson_swar_is_clean(uint64_t x, const simdjson_encoder *encoder) {
    uint64_t special = (x & SIMDJSON_SWAR_HIGH)
        | simdjson_swar_less_than(x, 0x20)
        | simdjson_swar_equal(x, SIMDJSON_SWAR_ONES * '"')
        | simdjson_swar_equal(x, SIMDJSON_SWAR_ONES * '\\');
    for (int i = 0; i < encoder->escape_extra_count; i++) {
        special |= simdjson_swar_equal(x, encoder->escape_extra[i]);
    }
    return special == 0;
}
#endif

static zend_always_inline void simdjson_append_unicode_escape(smart_str *buf, unsigned int us) {
    char *dst = smart_str_extend(buf, 6);
    dst[0] = '\\';
    dst[1] = 'u';
    dst[2] = simdjson_hex_digits[(us >> 12) & 0xf];
    dst[3] = simdjson_hex_digits[(us >> 8) & 0xf];
    dst[4] = simdjson_hex_digits[(us >> 4) & 0xf];
    dst[5] = simdjson_hex_digits[us & 0xf];
}

static zend_always_inline size_t simdjson_decode_utf8(const unsigned char *s, size_t avail, unsigned int *code_point) {
    unsigned int c = s[0];
    if (c < 0xc2) {
        return 0;
    }
    if (c < 0xe0) {
        if (avail < 2 || (s[1] & 0xc0) != 0x80) {
            return 0;
        }
        *code_point = ((c & 0x1f) << 6) | (s[1] & 0x3f);
        return 2;
    }
    if (c < 0xf0) {
        if (avail < 3 || (s[1] & 0xc0) != 0x80 || (s[2] & 0xc0) != 0x80) {
            return 0;
        }
        unsigned int cp = ((c & 0x0f) << 12) | ((s[1] & 0x3f) << 6) | (s[2] & 0x3f);
        if (cp < 0x800 || (cp >= 0xd800 && cp <= 0xdfff)) {
            return 0;
        }
        *code_point = cp;
        return 3;
    }
    if (c < 0xf5) {
        if (avail < 4 || (s[1] & 0xc0) != 0x80 || (s[2] & 0xc0) != 0x80 || (s[3] & 0xc0) != 0x80) {
            return 0;
        }
        unsigned int cp = ((c & 0x07) << 18) | ((s[1] & 0x3f) << 12) | ((s[2] & 0x3f) << 6) | (s[3] & 0x3f);
        if (cp < 0x10000 || cp > 0x10ffff) {
            return 0;
        }
        *code_point = cp;
        return 4;
    }
    return 0;
}

static bool simdjson_escape_string(smart_str *buf, const char *s, size_t len, const simdjson_encoder *encoder) {
    int options = encoder->options;
    const uint8_t *table = encoder->escape_table;
    size_t pos = 0;
    size_t start = 0;
    bool raw_unicode = options & PHP_JSON_UNESCAPED_UNICODE;
    bool raw_line_terminators = options & PHP_JSON_UNESCAPED_LINE_TERMINATORS;

#ifdef SIMDJSON_ENCODE_SSE2
    const __m128i below_space = _mm_set1_epi8(0x20);
    const __m128i quote = _mm_set1_epi8('"');
    const __m128i backslash = _mm_set1_epi8('\\');
    __m128i extra[5];
    int extra_count = encoder->escape_extra_count;
    for (int i = 0; i < extra_count; i++) {
        extra[i] = _mm_set1_epi8((char)(encoder->escape_extra[i] & 0xff));
    }
#endif

    smart_str_alloc(buf, len + 2, 0);
    smart_str_appendc(buf, '"');
    while (true) {
#ifdef SIMDJSON_ENCODE_SSE2
        while (pos + 16 <= len) {
            __m128i chunk = _mm_loadu_si128((const __m128i *)(s + pos));
            __m128i special = _mm_or_si128(_mm_cmplt_epi8(chunk, below_space),
                _mm_or_si128(_mm_cmpeq_epi8(chunk, quote), _mm_cmpeq_epi8(chunk, backslash)));
            for (int i = 0; i < extra_count; i++) {
                special = _mm_or_si128(special, _mm_cmpeq_epi8(chunk, extra[i]));
            }
            unsigned int mask = (unsigned int)_mm_movemask_epi8(special);
            if (mask) {
                pos += simdjson_first_set_bit(mask);
                break;
            }
            pos += 16;
        }
#else
        while (pos + 8 <= len) {
            uint64_t chunk;
            memcpy(&chunk, s + pos, 8);
            if (!simdjson_swar_is_clean(chunk, encoder)) {
                break;
            }
            pos += 8;
        }
#endif
        while (pos < len && !table[(unsigned char)s[pos]]) {
            pos++;
        }
        if (pos >= len) {
            break;
        }
        unsigned int us = (unsigned char)s[pos];
        if (us >= 0x80) {
            size_t n = simdjson_decode_utf8((const unsigned char *)s + pos, len - pos, &us);
            if (UNEXPECTED(n == 0)) {
                return false;
            }
            if (raw_unicode && (raw_line_terminators || us < 0x2028 || us > 0x2029)) {
                pos += n;
                continue;
            }
            if (pos > start) {
                smart_str_appendl(buf, s + start, pos - start);
            }
            if (us >= 0x10000) {
                us -= 0x10000;
                simdjson_append_unicode_escape(buf, (us >> 10) | 0xd800);
                us = (us & 0x3ff) | 0xdc00;
            }
            simdjson_append_unicode_escape(buf, us);
            pos += n;
            start = pos;
            continue;
        }
        if (pos > start) {
            smart_str_appendl(buf, s + start, pos - start);
        }
        pos++;
        start = pos;
        switch (us) {
            case '"':
                if (options & PHP_JSON_HEX_QUOT) {
                    smart_str_appendl(buf, "\\u0022", 6);
                } else {
                    smart_str_appendl(buf, "\\\"", 2);
                }
                break;
            case '\\':
                smart_str_appendl(buf, "\\\\", 2);
                break;
            case '/':
                smart_str_appendl(buf, "\\/", 2);
                break;
            case '\b':
                smart_str_appendl(buf, "\\b", 2);
                break;
            case '\f':
                smart_str_appendl(buf, "\\f", 2);
                break;
            case '\n':
                smart_str_appendl(buf, "\\n", 2);
                break;
            case '\r':
                smart_str_appendl(buf, "\\r", 2);
                break;
            case '\t':
                smart_str_appendl(buf, "\\t", 2);
                break;
            case '<':
                smart_str_appendl(buf, "\\u003C", 6);
                break;
            case '>':
                smart_str_appendl(buf, "\\u003E", 6);
                break;
            case '&':
                smart_str_appendl(buf, "\\u0026", 6);
                break;
            case '\'':
                smart_str_appendl(buf, "\\u0027", 6);
                break;
            default:
                simdjson_append_unicode_escape(buf, us);
                break;
        }
    }
    if (pos > start) {
        smart_str_appendl(buf, s + start, pos - start);
    }
    smart_str_appendc(buf, '"');
    return true;
}

static zend_always_inline bool simdjson_encode_key(smart_str *buf, zend_string *key, simdjson_encoder *encoder) {
    if (!ZSTR_IS_INTERNED(key) && GC_REFCOUNT(key) == 1) {
        return simdjson_escape_string(buf, ZSTR_VAL(key), ZSTR_LEN(key), encoder);
    }
    if (UNEXPECTED(!encoder->key_cache_ready)) {
        memset(encoder->key_cache, 0, sizeof(encoder->key_cache));
        encoder->key_cache_ready = true;
    }
    simdjson_key_cache_entry *entry = &encoder->key_cache[((uintptr_t)key >> 4) & (SIMDJSON_KEY_CACHE_SIZE - 1)];
    if (entry->key == key) {
        char *dst = smart_str_extend(buf, entry->length);
        memcpy(dst, ZSTR_VAL(buf->s) + entry->offset, entry->length);
        return true;
    }
    size_t offset = buf->s ? ZSTR_LEN(buf->s) : 0;
    if (!simdjson_escape_string(buf, ZSTR_VAL(key), ZSTR_LEN(key), encoder)) {
        return false;
    }
    entry->key = key;
    entry->offset = offset;
    entry->length = ZSTR_LEN(buf->s) - offset;
    return true;
}

static zend_always_inline size_t simdjson_write_digits(uint64_t value, char *out) {
    char tmp[20];
    char *end = tmp + sizeof(tmp);
    char *p = end;
    while (value >= 100) {
        uint64_t index = (value % 100) * 2;
        value /= 100;
        p -= 2;
        p[0] = simdjson_two_digits[index];
        p[1] = simdjson_two_digits[index + 1];
    }
    if (value >= 10) {
        p -= 2;
        p[0] = simdjson_two_digits[value * 2];
        p[1] = simdjson_two_digits[value * 2 + 1];
    } else {
        *--p = (char)('0' + value);
    }
    size_t n = (size_t)(end - p);
    memcpy(out, p, n);
    return n;
}

static zend_always_inline void simdjson_append_long(smart_str *buf, zend_long value) {
    char *dst = smart_str_extend(buf, 21);
    size_t n = 0;
    uint64_t magnitude = (uint64_t)value;
    if (value < 0) {
        dst[n++] = '-';
        magnitude = 0 - magnitude;
    }
    n += simdjson_write_digits(magnitude, dst + n);
    ZSTR_LEN(buf->s) -= 21 - n;
}

static size_t simdjson_format_shortest_double(double value, char *buf) {
    char *dst = buf;
    if (value == 0) {
        if (std::signbit(value)) {
            *dst++ = '-';
        }
        *dst++ = '0';
        return (size_t)(dst - buf);
    }
    auto decimal = jkj::dragonbox::to_decimal(value);
    if (decimal.is_negative) {
        *dst++ = '-';
    }
    char digits[20];
    int n = (int)simdjson_write_digits(decimal.significand, digits);
    int decpt = n + decimal.exponent;
    if (decpt > 17 || decpt < -3) {
        int exponent = decpt - 1;
        *dst++ = digits[0];
        *dst++ = '.';
        if (n == 1) {
            *dst++ = '0';
        } else {
            memcpy(dst, digits + 1, n - 1);
            dst += n - 1;
        }
        *dst++ = 'e';
        if (exponent < 0) {
            *dst++ = '-';
            exponent = -exponent;
        } else {
            *dst++ = '+';
        }
        dst += simdjson_write_digits((uint64_t)exponent, dst);
    } else if (decpt <= 0) {
        *dst++ = '0';
        *dst++ = '.';
        for (int i = decpt; i < 0; i++) {
            *dst++ = '0';
        }
        memcpy(dst, digits, n);
        dst += n;
    } else if (decpt >= n) {
        memcpy(dst, digits, n);
        dst += n;
        for (int i = n; i < decpt; i++) {
            *dst++ = '0';
        }
    } else {
        memcpy(dst, digits, decpt);
        dst += decpt;
        *dst++ = '.';
        memcpy(dst, digits + decpt, n - decpt);
        dst += n - decpt;
    }
    return (size_t)(dst - buf);
}

static void simdjson_encode_double(smart_str *buf, double d, int options) {
    char num[PHP_DOUBLE_MAX_LENGTH];
    size_t len;
    if (EXPECTED(PG(serialize_precision) == -1)) {
        len = simdjson_format_shortest_double(d, num);
    } else {
        php_gcvt(d, (int)PG(serialize_precision), '.', 'e', num);
        len = strlen(num);
    }
    if ((options & PHP_JSON_PRESERVE_ZERO_FRACTION) && memchr(num, '.', len) == NULL && len < PHP_DOUBLE_MAX_LENGTH - 2) {
        num[len++] = '.';
        num[len++] = '0';
    }
    smart_str_appendl(buf, num, len);
}

static zend_always_inline void simdjson_pretty_print_newline(smart_str *buf, const simdjson_encoder *encoder) {
    if (encoder->options & PHP_JSON_PRETTY_PRINT) {
        size_t indent = 4 * (size_t)encoder->depth;
        char *dst = smart_str_extend(buf, 1 + indent);
        dst[0] = '\n';
        memset(dst + 1, ' ', indent);
    }
}

static bool simdjson_array_is_list(HashTable *ht) {
#if PHP_VERSION_ID >= 80100
    return zend_array_is_list(ht);
#else
    if (zend_hash_num_elements(ht) == 0) {
        return true;
    }
    if (HT_IS_PACKED(ht) && HT_IS_WITHOUT_HOLES(ht)) {
        return true;
    }
    zend_string *key;
    zend_ulong index;
    zend_ulong expected = 0;
    ZEND_HASH_FOREACH_KEY(ht, index, key) {
        if (key || index != expected) {
            return false;
        }
        expected++;
    } ZEND_HASH_FOREACH_END();
    return true;
#endif
}

static bool simdjson_encode_array(smart_str *buf, HashTable *ht, bool force_object, bool is_object, simdjson_encoder *encoder) {
#if PHP_VERSION_ID >= 80300 && defined(ZEND_CHECK_STACK_LIMIT)
    if (UNEXPECTED(zend_call_stack_overflowed(EG(stack_limit)))) {
        return false;
    }
#endif
    if (GC_IS_RECURSIVE(ht)) {
        return false;
    }
    bool as_object = force_object || !simdjson_array_is_list(ht);
    bool pretty = encoder->options & PHP_JSON_PRETTY_PRINT;
    bool need_comma = false;
    zend_string *key;
    zend_ulong index;
    zval *data;

    GC_TRY_PROTECT_RECURSION(ht);
    smart_str_appendc(buf, as_object ? '{' : '[');
    ++encoder->depth;
    ZEND_HASH_FOREACH_KEY_VAL_IND(ht, index, key, data) {
        if (as_object && key && is_object && ZSTR_VAL(key)[0] == '\0' && ZSTR_LEN(key) > 0) {
            continue;
        }
        if (need_comma) {
            smart_str_appendc(buf, ',');
        } else {
            need_comma = true;
        }
        simdjson_pretty_print_newline(buf, encoder);
        if (as_object) {
            if (key) {
                if (!simdjson_encode_key(buf, key, encoder)) {
                    GC_TRY_UNPROTECT_RECURSION(ht);
                    return false;
                }
            } else {
                smart_str_appendc(buf, '"');
                simdjson_append_long(buf, (zend_long)index);
                smart_str_appendc(buf, '"');
            }
            if (pretty) {
                smart_str_appendl(buf, ": ", 2);
            } else {
                smart_str_appendc(buf, ':');
            }
        }
        if (!simdjson_encode_zval(buf, data, encoder)) {
            GC_TRY_UNPROTECT_RECURSION(ht);
            return false;
        }
    } ZEND_HASH_FOREACH_END();
    GC_TRY_UNPROTECT_RECURSION(ht);

    if (encoder->depth > encoder->max_depth) {
        return false;
    }
    --encoder->depth;
    if (need_comma) {
        simdjson_pretty_print_newline(buf, encoder);
    }
    smart_str_appendc(buf, as_object ? '}' : ']');
    return true;
}

static bool simdjson_encode_zval(smart_str *buf, zval *val, simdjson_encoder *encoder) {
again:
    switch (Z_TYPE_P(val)) {
        case IS_NULL:
            smart_str_appendl(buf, "null", 4);
            return true;
        case IS_TRUE:
            smart_str_appendl(buf, "true", 4);
            return true;
        case IS_FALSE:
            smart_str_appendl(buf, "false", 5);
            return true;
        case IS_LONG:
            simdjson_append_long(buf, Z_LVAL_P(val));
            return true;
        case IS_DOUBLE:
            if (UNEXPECTED(!zend_finite(Z_DVAL_P(val)))) {
                return false;
            }
            simdjson_encode_double(buf, Z_DVAL_P(val), encoder->options);
            return true;
        case IS_STRING:
            return simdjson_escape_string(buf, Z_STRVAL_P(val), Z_STRLEN_P(val), encoder);
        case IS_ARRAY:
            return simdjson_encode_array(buf, Z_ARRVAL_P(val), encoder->options & PHP_JSON_FORCE_OBJECT, false, encoder);
        case IS_OBJECT: {
            zend_object *obj = Z_OBJ_P(val);
            if (obj->ce != zend_standard_class_def || obj->handlers != &std_object_handlers) {
                return false;
            }
            if (!obj->properties) {
                if (encoder->depth + 1 > encoder->max_depth) {
                    return false;
                }
                smart_str_appendl(buf, "{}", 2);
                return true;
            }
            return simdjson_encode_array(buf, obj->properties, true, true, encoder);
        }
        case IS_REFERENCE:
            val = Z_REFVAL_P(val);
            goto again;
        default:
            return false;
    }
}

int simdjson_encode_to_smart_str(smart_str *buf, zval *value, zend_long options, zend_long depth) {
    if (!(options & SIMDJSON_ENCODE_FALLBACK_OPTIONS)) {
        simdjson_encoder encoder;
        simdjson_encoder_init(&encoder, (int)options, (int)depth);
        if (simdjson_encode_zval(buf, value, &encoder)) {
            return 0;
        }
        smart_str_free(buf);
    }
#if PHP_VERSION_ID >= 80600
    php_json_error_details saved_error = JSON_G(error_details);
    php_json_encode_ex(buf, value, (int)options, depth);
    php_json_error_code error = JSON_G(error_details).code;
    JSON_G(error_details) = saved_error;
#else
    php_json_error_code saved_error = JSON_G(error_code);
    php_json_encode_ex(buf, value, (int)options, depth);
    php_json_error_code error = JSON_G(error_code);
    JSON_G(error_code) = saved_error;
#endif
    return error;
}

#endif
