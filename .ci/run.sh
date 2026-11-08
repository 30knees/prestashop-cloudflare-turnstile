#!/usr/bin/env bash
set -euo pipefail
php tests/lint.php
php tests/settings.php
php -d disable_functions=curl_init,curl_setopt,curl_exec,curl_error,curl_getinfo tests/components.php
for version in 9.2.0 9.2.99 9.1.0 9.3.0; do php tests/runtime.php "$version"; done
php tests/mutations.php
python3 tools/build.py
python3 tests/package.py
