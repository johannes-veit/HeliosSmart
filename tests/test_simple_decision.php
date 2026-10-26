<?php
require_once __DIR__.'/simulation/SimpleDecision.php';
function sd(bool $ok,string $why):void {
    if(!$ok) {fwrite(STDERR,"FAIL ".$why."\n");exit(1);}
    echo "PASS ".$why."\n";
}
$now=strtotime('2026-07-15 03:00:00 UTC');
$rooms=[
 ['name'=>'EG Wohnen','temperature'=>26.7,'rh'=>51,
  'temperature_observed_at'=>$now-70,'humidity_observed_at'=>$now-120],
 ['name'=>'OG Schlafen 1','temperature'=>26.3,'rh'=>53,
  'temperature_observed_at'=>$now-90,'humidity_observed_at'=>$now-100],
 ['name'=>'OG Schlafen 2','temperature'=>25.9,'rh'=>54,
  'temperature_observed_at'=>$now-110,'humidity_observed_at'=>$now-130],
 ['name'=>'EG Bad','temperature'=>31.0,'rh'=>94,
  'temperature_observed_at'=>$now-30,'humidity_observed_at'=>$now-30],
];
$d=[
 'now'=>$now,'season_signal'=>'SUMMER',
 'weather_quality'=>'VERIFIED','trusted_weather'=>true,
 'fresh_room_count'=>11,'fresh_humidity_room_count'=>10,
 'supply_source'=>'HELIOS_SUPPLY_AFTER_EXCHANGER',
 'supply_observed_at'=>$now-90,'outside_observed_at'=>$now-120,
 'outside_temperature'=>18.5,'outside_dewpoint'=>11.,
 'outside_absolute_humidity'=>9.5,
 'supply_temperature'=>19.8,
 'current_fan_level'=>2,'helios_automatic_mode'=>true,
 'forecast_advice'=>['status'=>'READY','high_confidence_hours'=>20,
     'longest_contiguous_hours'=>18,'temperature_max_c'=>34],
];
$r=JVHeliosSimpleDecision::advise($d,$rooms,$now);
sd($r['status']==='ZUSATZNUTZEN_BELEGT_ABER_NUR_THEORETISCH'
   && $r['entscheidung']==='ZUSATZLUEFTUNG_NUR_NACH_SICHERHEITSPRUEFUNG',
 'Muggy? No: cool/dry night yields research-only extra-ventilation suggestion');
sd($r['zusatzstufe']===4 && $r['max_dauer_min']===30 && $r['vorteil_k']>5,
 'Actual cooled supply gives bounded 30-minute stage-4 suggestion');
sd($r['read_only'] && $r['offline_only'] && !$r['device_command_allowed']
 && !$r['reduce_weekly'] && !$r['bypass_command_allowed'],
 'No Symcon, bypass, weekly program or forced command from research decision');
sd($r['komfortraeume']===3 && $r['behaglichkeit']!=='UNKNOWN',
 'Bathroom spikes excluded from occupied-room comfort decision');
sd($r['feuchtewirkung']==='AUSSEN_TROCKENER',
 'Indoor vapor pressure compared with drier independent outdoor weather');

$wet=$d;$wet['outside_dewpoint']=19.2;$wet['outside_absolute_humidity']=16.5;
$wet['outside_temperature']=22;
$w=JVHeliosSimpleDecision::advise($wet,$rooms,$now);
sd($w['entscheidung']==='WOCHENPLAN_BELASSEN'
 && $w['grund']==='AUSSENLUFT_FEUCHTE_UNGUENSTIG',
 'Cooler yet muggy outdoor air must not raise weather-only fan stage');

$stale=$d;$stale['supply_observed_at']=$now-3600;
sd(JVHeliosSimpleDecision::advise($stale,$rooms,$now)['grund']
 ==='ZULUFT_ODER_WETTER_NICHT_VERIFIZIERT',
 'Stale original supply update cannot trigger summer cooling');
$stale=$d;$stale['outside_observed_at']=$now-2*3600;
sd(JVHeliosSimpleDecision::advise($stale,$rooms,$now)['grund']
 ==='ZULUFT_ODER_WETTER_NICHT_VERIFIZIERT',
 'Old outdoor sample even with nominal VERIFIED weather is insufficient');
$stale=$d;$stale['supply_source']='HELIOS_INTAKE_AFTER_EWT';
sd(JVHeliosSimpleDecision::advise($stale,$rooms,$now)['grund']
 ==='ZULUFT_ODER_WETTER_NICHT_VERIFIZIERT',
 'Post-Sole-EWT intake cannot be passed as actual supply into house');
$stale=$d;$stale['trusted_weather']=false;
sd(JVHeliosSimpleDecision::advise($stale,$rooms,$now)['grund']
 ==='ZULUFT_ODER_WETTER_NICHT_VERIFIZIERT',
 'Untrusted outside source blocks candidate');

$staleRooms=$rooms;
foreach($staleRooms as &$room) $room['humidity_observed_at']=$now-5000;
unset($room);
sd(JVHeliosSimpleDecision::advise($d,$staleRooms,$now)['grund']
 ==='ZU_WENIGE_FRISCHE_WOHNRAEUME',
 'Fewer than three recent living-room pairs => original weekly mode');
$cold=$rooms;$cold[2]['temperature']=18.8;
sd(JVHeliosSimpleDecision::advise($d,$cold,$now)['grund']
 ==='EIN_RAUM_BEREITS_ZU_KUEHL',
 'One cooled bedroom vetoes whole-house weather ventilation');

$winter=$d;$winter['season_signal']='HEATING_SEASON';
$winterAdvice=JVHeliosSimpleDecision::advise($winter,$rooms,$now);
sd($winterAdvice['status']==='WINTER'
 && $winterAdvice['entscheidung']==='WOCHENPLAN_BELASSEN',
 'Winter heat recovery and manufacturer schedule remain untouched');
$unknown=$d;$unknown['season_signal']='UNKNOWN';
sd(JVHeliosSimpleDecision::advise($unknown,$rooms,$now)['grund']==='SAISON_UNKLAR',
 'Unknown season never interpreted as summer');
$manual=$d;$manual['helios_automatic_mode']=false;
sd(JVHeliosSimpleDecision::advise($manual,$rooms,$now)['grund']
 ==='HELIOS_BETRIEBSZUSTAND_UNKLAR',
 'Manual fan mode refuses extra weather-only action');
$already=$d;$already['current_fan_level']=4;
sd(JVHeliosSimpleDecision::advise($already,$rooms,$now)['grund']
 ==='HOECHSTE_STUFE_BEREITS_AKTIV',
 'Existing stage 4 not extended or restarted by research rule');

$noCooling=$d;$noCooling['supply_temperature']=28.5;
$noCool=JVHeliosSimpleDecision::advise($noCooling,$rooms,$now);
sd($noCool['entscheidung']==='WOCHENPLAN_BELASSEN'
    && $noCool['zuluftwirkung']==='WAERMT',
 'Warm supply cannot be called a cooling opportunity');
$neutral=$d;$neutral['supply_temperature']=25.5;
sd(JVHeliosSimpleDecision::advise($neutral,$rooms,$now)['entscheidung']
 ==='WOCHENPLAN_BELASSEN',
 'Small temperature advantage without material drying cannot demand S4');

$dry=$rooms;
foreach($dry as &$room) if($room['name']!=='EG Bad')$room['rh']=29;
unset($room);
sd(JVHeliosSimpleDecision::advise($d,$dry,$now)['entscheidung']
 ==='WOCHENPLAN_BELASSEN',
 'Do not dehumidify already very dry living rooms for a minor weather improvement');

$humid=$rooms;
foreach($humid as &$room) if($room['name']!=='EG Bad') {
    $room['temperature']=27.5;$room['rh']=77;
}
unset($room);
$drying=$d;
$drying['outside_temperature']=21.5;
$drying['outside_dewpoint']=17.6;
$drying['outside_absolute_humidity']=14.7;
$drying['supply_temperature']=22.;
$relief=JVHeliosSimpleDecision::advise($drying,$humid,$now);
sd($relief['grund']==='HIGH_INDOOR_HUMIDITY_RELIEF'
   && $relief['entscheidung']==='ZUSATZLUEFTUNG_NUR_NACH_SICHERHEITSPRUEFUNG',
 'Substantially wetter house can gain from markedly drier outdoor air despite 17.6C outside dewpoint');
sd($relief['verbessern']!=='' && !$relief['supply_humidity_measured'],
 'Advice does not invent a dehumidifier or measured supply moisture');

for($i=0;$i<120;$i++) {
    $scenario=$d;
    $scenario['outside_dewpoint']=10+$i*0.095;
    $scenario['outside_absolute_humidity']=9.5+$i*0.06;
    $scenario['supply_temperature']=18+$i*0.1;
    $trial=JVHeliosSimpleDecision::advise($scenario,$rooms,$now);
    if($trial['device_command_allowed']||$trial['reduce_weekly']
        ||$trial['bypass_command_allowed']||!$trial['offline_only']) {
        fwrite(STDERR,"FAIL Hardware invariant $i\n");exit(1);
    }
    if($trial['zusatzstufe']!==null && $trial['max_dauer_min']>30) {
        fwrite(STDERR,"FAIL Extra weather window too long $i\n");exit(1);
    }
}
sd(true,'120 temperature/dewpoint sensitivity variants remain non-actuating');

echo "SIMPLE 3-QUESTION HELIOS RESEARCH DECISION PASSED\n";
