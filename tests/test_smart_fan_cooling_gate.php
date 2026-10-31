<?php
require_once __DIR__.'/../libs/SmartFanCoolingGate.php';
function gateOk($test,$description){
    if(!$test){fwrite(STDERR,"FAIL COOLING GATE: $description\n");exit(1);}
}
$base=[
    'mode'=>'PASSIVE_COOLING','weather_quality'=>'VERIFIED','trusted_weather'=>true,
    'season_signal'=>'SUMMER_HEAT','supply_temperature'=>21.0,
    'indoor_temperature'=>25.1,
    'comfort_control'=>[
        'status'=>'READY','weather_boost_compatible'=>true,
        'night_precooling_safe'=>true,
    ],
    'summer_airflow'=>[
        'regime'=>'MEASURED_COOLING_OPPORTUNITY','recommended_stage'=>3,
        'moisture_comfort'=>[
            'status'=>'READY','weather_cooling_boost_allowed'=>true,
            'humidity_import_risk'=>false,
        ],
    ],
];
$run=static fn(array $override=[]) =>
    JVHeliosSmartCoolingGate::decide(array_replace_recursive($base,$override));
$good=$run();
gateOk($good['cooling_confirmed']&&$good['supply_gap_k']===4.1,
    'verified summertime cooling and safe humidity/rooms enabled');
foreach([
    ['override'=>['mode'=>'HEAT_PRESERVATION'],'reason'=>'COOLING_MODE_NOT_REQUESTED'],
    ['override'=>['weather_quality'=>'MODEL_SUPPORTED'],'reason'=>'WEATHER_NOT_VERIFIED'],
    ['override'=>['trusted_weather'=>false],'reason'=>'WEATHER_NOT_VERIFIED'],
    ['override'=>['season_signal'=>'HEATING_SEASON'],'reason'=>'HEATING_CONDITIONS_PRIORITY'],
    ['override'=>['season_signal'=>'POSSIBLE_HEATING'],'reason'=>'HEATING_CONDITIONS_PRIORITY'],
    ['override'=>['comfort_control'=>['night_precooling_safe'=>false]],'reason'=>'ROOM_COMFORT_OR_COOLING_FLOOR_VETO'],
    ['override'=>['comfort_control'=>['weather_boost_compatible'=>false]],'reason'=>'ROOM_COMFORT_OR_COOLING_FLOOR_VETO'],
    ['override'=>['summer_airflow'=>['moisture_comfort'=>['humidity_import_risk'=>true]]],'reason'=>'MOISTURE_IMPORT_OR_UNVERIFIED'],
    ['override'=>['summer_airflow'=>['moisture_comfort'=>['status'=>'INSUFFICIENT']]],'reason'=>'MOISTURE_IMPORT_OR_UNVERIFIED'],
    ['override'=>['summer_airflow'=>['regime'=>'KEEP_HYGIENIC_WEEKLY']],'reason'=>'SUMMER_STAGE_3_OR_HIGHER_NOT_JUSTIFIED'],
    ['override'=>['summer_airflow'=>['recommended_stage'=>1]],'reason'=>'SUMMER_STAGE_3_OR_HIGHER_NOT_JUSTIFIED'],
    ['override'=>['supply_temperature'=>null],'reason'=>'ACTUAL_COOL_SUPPLY_UNVERIFIED'],
    ['override'=>['supply_temperature'=>24.1],'reason'=>'SUPPLY_NOT_COOL_ENOUGH'],
] as $item){
    $r=$run($item['override']);
    gateOk(!$r['cooling_confirmed'] && $r['reason']===$item['reason'],
        'no cooling boost in scenario '.$item['reason'].'/'.json_encode($item['override']));
}
$accepted=$run(['summer_airflow'=>['regime'=>'HEATWAVE_PRECOOL_WINDOW','recommended_stage'=>4]]);
gateOk($accepted['cooling_confirmed']===true,'verified heatwave pre-cooling eligible after all guards');
echo "Cooling weather, humidity, summer season and cold bedroom safety tests OK\n";
