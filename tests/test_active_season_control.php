<?php
require_once __DIR__.'/../libs/SeasonProgramControl.php';
function chkSeason($ok,$label){if(!$ok){fwrite(STDERR,"FAIL ACTIVE SEASON: $label\n");exit(1);}}
$now=1793430000;
$input=[
    'enabled'=>true,'native_healthy'=>true,'mode'=>0,'program'=>4,
    'fresh_live'=>true,'weather_quality'=>'VERIFIED','outside_temperature'=>7.0,
    'outside_age_seconds'=>60,'season_signal'=>'HEATING_SEASON','last_attempt_at'=>0,
];
$run=static fn(array $x=[])=>JVHeliosSeasonProgramControl::decide(array_replace($input,$x),$now);
$expected=$run();
chkSeason($expected['may_send'] && $expected['target_program']===3,
    '7C verified hot summer 4 -> normal winter 3');
chkSeason($expected['leaves_helios_in_automatic'] && !$expected['may_override_sensors'],
    'native AUTO and hardware CO2/RH regulation remain');
foreach([
    [['enabled'=>false],'SMART_PROFILE_CONTROL_DISABLED'],
    [['native_healthy'=>false],'NATIVE_DEVICE_NOT_VERIFIED_IN_AUTO'],
    [['mode'=>1],'NATIVE_DEVICE_NOT_VERIFIED_IN_AUTO'],
    [['mode'=>2],'NATIVE_DEVICE_NOT_VERIFIED_IN_AUTO'],
    [['fresh_live'=>false],'NATIVE_DEVICE_NOT_VERIFIED_IN_AUTO'],
    [['weather_quality'=>'UNAVAILABLE'],'OUTDOOR_WEATHER_UNTRUSTED'],
    [['outside_age_seconds'=>2100],'OUTSIDE_NOT_RECENT'],
    [['outside_temperature'=>14],'NOT_COLD_ENOUGH_FOR_IMMEDIATE_PROFILE_CHANGE'],
    [['season_signal'=>'TRANSITION'],'HEATING_SEASON_NOT_SUPPORTED'],
    [['program'=>3],'NOT_KNOWN_HOT_SUMMER_PROGRAM'],
    [['program'=>0],'NOT_KNOWN_HOT_SUMMER_PROGRAM'],
    [['last_attempt_at'=>$now-30],'24_HOUR_COMMAND_COOLDOWN'],
] as [$x,$reason]){
    $p=$run($x);
    chkSeason(!$p['may_send'] && $p['reason']===$reason,
        'safety veto '.json_encode($x));
}
chkSeason($run(['weather_quality'=>'DWD_ONLY'])['may_send'],
    'fresh verified DWD reference accepted for winter profile');
echo "HELIOS SMART controlled winter-profile selection tests OK\n";
