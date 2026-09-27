cmake_minimum_required(VERSION 3.10)
project(simdjson4)

set(CMAKE_CXX_STANDARD 17)

add_compile_definitions(HAVE_SIMDJSON4)

set(SOURCE_FILES
    php_simdjson.h
    php_simdjson.cpp
    simdjson_arginfo.h
    src/simdjson_bindings_defs.h
    src/simdjson_bindings.cpp
    src/simdjson_encoder.h
    src/simdjson_encoder.cpp
    src/dragonbox.h
    src/simdjson.h
    src/simdjson.cpp
)

execute_process (
        COMMAND php-config --include-dir
        OUTPUT_VARIABLE PHP_SOURCE
)
string(REGEX REPLACE "\n$" "" PHP_SOURCE "${PHP_SOURCE}")

message("Using source directory: ${PHP_SOURCE}")

include_directories(
    "${PHP_SOURCE}"
    "${PHP_SOURCE}/main"
    "${PHP_SOURCE}/Zend"
    "${PHP_SOURCE}/TSRM"
    "${PROJECT_SOURCE_DIR}"
)

add_custom_target(configure
        COMMAND phpize && ./configure
        DEPENDS ${SOURCE_FILES}
        WORKING_DIRECTORY ${PROJECT_SOURCE_DIR})

add_library(___ EXCLUDE_FROM_ALL ${SOURCE_FILES})
