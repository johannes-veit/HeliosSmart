<?php
require_once __DIR__.'/../libs/EWTEnvironment.php';
function echeck($ok,$reason){if(!$ok){fwrite(STDERR,"FAIL EWT WEATHER: ".$reason."\n");exit(1);}}
$now=1800000000;
$hour=($now-1000);
$raw=['hourly'=>[
    'time'=>[$hour,$now+3600],
    'wind_speed_10m'=>[12.2,5.1],
    'precipitation'=>[0.7,0.0],
    'shortwave_radiation'=>[440,500],
]];
$x=JVHeliosEWTEnvironment::parse($raw,$now);
echeck($x['quality']==='MODEL_CONTEXT_ONLY_NOT_MEASURED','model weather never counted as measurement');
echeck($x['source']==='OPEN_METEO_BEST_MATCH_FORECAST','source provenance');
echeck(abs($x['wind_speed_kmh']-12.2)<0.01,'wind');
echeck(abs($x['precipitation_mm']-0.7)<0.01,'rain');
echeck(abs($x['solar_wm2']-440)<0.01,'solar');
echeck(JVHeliosEWTEnvironment::parse(['hourly'=>['time'=>[$now-9000]]],$now)['quality']==='UNAVAILABLE',
 'outdated external model not accepted');
echeck(JVHeliosEWTEnvironment::parse(['hourly'=>['time'=>[$now],'wind_speed_10m'=>[-200]]],$now)['quality']==='UNAVAILABLE',
 'unreasonable model weather not accepted');
echo "EWT optional modeled wind, rain and solar inputs tests OK\n";
