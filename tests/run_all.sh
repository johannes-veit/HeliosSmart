#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
for p in libs/*.php WeatherZentrale/*.php HeliosSmart/*.php simulation/*.php tests/*.php; do php -l "$p" >/dev/null; done
php tests/test_core.php
php tests/test_symcon_guard.php

php tests/test_resilient_weather.php

php tests/test_advisor.php
php tests/test_wiring_and_sensor_ages.php

php tests/test_lcn_native_sources.php

php tests/test_forecast_advisor.php

php tests/test_daily_strategy.php
php tests/test_drying_interpretation.php

php tests/test_comfort_season.php

php tests/test_need_and_horizon.php

php tests/test_wetroom_events.php

php tests/test_wetroom_trends.php

php tests/test_cooking_presence.php
php tests/test_cooking_hour_event.php

php tests/test_cooking_events.php

php tests/test_away_presence.php
php tests/test_party_commissioning.php

php tests/test_party_live_executor.php
php tests/test_live_symcon_integration.php
php tests/test_party_monitor.php

php tests/test_comfort_controller.php
php tests/test_moisture_comfort.php
php tests/test_summer_airflow.php

php tests/test_theoretical_ventilation.php
php tests/test_horizon_planner.php
php tests/test_model_fitter.php
php tests/test_multi_zone_planner.php
php tests/test_multi_zone_house.php
php tests/test_archive_replay.php
php tests/test_archive_change_export.php
php tests/test_simple_decision.php
