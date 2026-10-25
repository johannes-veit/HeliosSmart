#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
for p in libs/*.php WeatherZentrale/*.php HeliosSmart/*.php tests/*.php; do php -l "$p" >/dev/null; done
php tests/test_core.php
php tests/test_symcon_guard.php
