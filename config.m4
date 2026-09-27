dnl config.m4 for extension simdjson

PHP_ARG_ENABLE(simdjson4, whether to enable simdjson4, [ --enable-simdjson4   Enable simdjson4])

if test "$PHP_SIMDJSON4" != "no"; then

  PHP_REQUIRE_CXX()

  AC_MSG_CHECKING([PHP version])

  if test -n "$PHP_CONFIG"; then
    php_version=`$PHP_CONFIG --vernum`
  else
    php_version=$PHP_VERSION_ID
  fi

  if test -z "$php_version"; then
    AC_MSG_ERROR([failed to detect PHP version, please report])
  fi

  if test "$php_version" -lt "70000"; then
    AC_MSG_ERROR([You need at least PHP 7.0.0 to be able to use this version of simdjson. PHP $php_version found])
  else
    AC_MSG_RESULT([$php_version, ok])
  fi

  dnl Mark symbols hidden by default if the compiler (for example, gcc >= 4)
  dnl supports it. This can help reduce the binary size and startup time.
  AX_CHECK_COMPILE_FLAG([-fvisibility=hidden],
                        [CXXFLAGS="$CXXFLAGS -fvisibility=hidden"])

  AC_DEFINE(HAVE_SIMDJSON4, 1, [whether simdjson4 is enabled])
  dnl Disable exceptions because PHP is written in C and loads this C++ module, handle errors manually.
  dnl Disable development checks of C simdjson library in php debug builds (can manually override)
  PHP_NEW_EXTENSION(simdjson4, [
      php_simdjson.cpp                    \
      src/simdjson_bindings.cpp           \
      src/simdjson_encoder.cpp            \
      src/simdjson.cpp],
    $ext_shared,, "-std=c++17 -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1 -DSIMDJSON_EXCEPTIONS=0 -DSIMDJSON_DEVELOPMENT_CHECKS=0", cxx)

  PHP_INSTALL_HEADERS([ext/simdjson4], [php_simdjson.h src/simdjson_bindings_defs.h])
  PHP_ADD_MAKEFILE_FRAGMENT
  PHP_ADD_BUILD_DIR(src, 1)
fi
