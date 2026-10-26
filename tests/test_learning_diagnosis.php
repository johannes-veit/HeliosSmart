<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function ld(bool $condition,string $text):void {
    if(!$condition) {fwrite(STDERR,"FAIL $text\n");exit(1);}
    echo "PASS $text\n";
}
$now=strtotime('2026-10-26 06:00:00 UTC');
$rooms=[
 ['name'=>'EG Wohnen','temperature'=>23.6,'rh'=>48,
  'temperature_observed_at'=>$now-40,'humidity_observed_at'=>$now-50],
 ['name'=>'OG Schlafen 1','temperature'=>20.3,'rh'=>50,
  'temperature_observed_at'=>$now-80,'humidity_observed_at'=>$now-70],
 ['name'=>'OG Schlafen 2','temperature'=>20.7,'rh'=>51,
  'temperature_observed_at'=>$now-110,'humidity_observed_at'=>$now-90],
 ['name'=>'EG Bad','temperature'=>23.5,'rh'=>62,
  'temperature_observed_at'=>$now-60,'humidity_observed_at'=>$now-100],
];
$snapshot=['weather_quality'=>'VERIFIED','fan_level'=>3,'mode'=>0,
 'program'=>3,'supply_temperature'=>20.0,'supply_age_minutes'=>4,
 'intake_after_ewt'=>15.0,'outside_temperature'=>9.3,
 'outside_dewpoint'=>6.5,'comfort_classification'=>'ANGENEHM',
 'candidate_kind'=>'KEEP_WEEKLY'];
$a=JVHeliosLearningDiagnostics::update([],$rooms,$snapshot,$now);
ld($a['new_distinct_temperature_samples']===4
 && $a['fresh_temperature_rooms']===4
 && $a['fresh_humidity_rooms']===4,'Four true original-temperature timestamps preserved');
ld(str_contains($a['report'],'LERNDIAGNOSE FUER CHATGPT')
 && str_contains($a['report'],'EG Wohnen')
 && str_contains($a['report'],'Luftmengen')
 && str_contains($a['report'],'Keine automatische Uebertragung'),
 'Copyable complete report with input values, limitations and sharing instructions');
ld(!$a['learning_calibrated'] && !$a['control_authorized'],
 'Early learning samples never authorize actuator or claim calibrated thermal inertia');
$again=JVHeliosLearningDiagnostics::update($a['state'],$rooms,$snapshot,$now+5*60);
ld($again['new_distinct_temperature_samples']===0
 && count($again['state']['rooms']['EG Wohnen']['samples'])===1,
 'Five-minute timer cannot duplicate one original sensor update');
ld($again['state']===$a['state'],
 'Repeated 5-minute evaluation leaves durable learning attribute unchanged');
$second=$rooms;
$second[0]['temperature']=23.9;
$second[0]['temperature_observed_at']=$now+2*3600+55*60;
$b=JVHeliosLearningDiagnostics::update($again['state'],$second,$snapshot,$now+3*3600);
ld($b['new_distinct_temperature_samples']===1
 && str_contains($b['report'],'delta +0.3C'),
 'Real changed temperature after one hour is captured, descriptive only');
$stale=$rooms;
foreach($stale as &$room) {
    $room['temperature_observed_at']=$now+3*3600+55*60;
    $room['humidity_observed_at']=$now+3*3600+55*60;
} unset($room);
$stale[0]['humidity_observed_at']=$now-12*3600;
$stale[1]['temperature_observed_at']=$now-3*3600;
$staleResult=JVHeliosLearningDiagnostics::update($b['state'],$stale,$snapshot,$now+4*3600);
ld($staleResult['fresh_humidity_rooms']===3
 && $staleResult['fresh_temperature_rooms']===3,
 'Stale RH or temperature not promoted to fresh by repeated evaluation');
ld(str_contains($staleResult['report'],'Alter 960m'),
 'Original sensor age in minutes remains explicitly visible even when old');
$bad=$rooms;$bad[0]['temperature_observed_at']=$now+3600;
$future=JVHeliosLearningDiagnostics::update([],$bad,$snapshot,$now);
ld($future['new_distinct_temperature_samples']===3,
 'Future original timestamp cannot enter passive learning dataset');
$prior=[];
for($j=0;$j<150;$j++)$prior['rooms']['EG Wohnen']['samples'][]=[
 'at'=>$now-7*86400+$j*3600,'c'=>20.0+$j/100];
$cap=JVHeliosLearningDiagnostics::update($prior,[$rooms[0]],$snapshot,$now);
ld(count($cap['state']['rooms']['EG Wohnen']['samples'])<=84,
 'Per-room two-hour history has strict 84-point seven-day bound');
ld(count($cap['state']['rooms'])===1,
 'Unavailable or removed room histories are pruned');
// Original sensor may change every minute, but the learning evidence
// must not discard the first day after only 72 minutes of regular updates.
$frequent=[];
$minutely=[$rooms[0]];
for($minute=0;$minute<48*60;$minute++) {
    $stamp=$now+$minute*60;
    $minutely[0]['temperature_observed_at']=$stamp;
    $minutely[0]['humidity_observed_at']=$stamp;
    $minutely[0]['temperature']=23.0+($minute>=900?0.8:0.0);
    $frequent=JVHeliosLearningDiagnostics::update(
        $frequent['state']??[],$minutely,$snapshot,$stamp);
}
$samples=$frequent['state']['rooms']['EG Wohnen']['samples'];
ld(count($samples)>=23 && count($samples)<=25,
 '2 days of minute-by-minute original reports become ~24 real 2h support points');
ld($samples[count($samples)-1]['at']-$samples[0]['at']>=46*3600,
 'Data span days rather than only initial minutes');
ld(str_contains($frequent['report'],'delta +0.8C'),
 'True observed temperature shift remains visible after decimation');
foreach($samples as $entry)
    ld($entry['at']>0 && $entry['at']%60===0,
        'Every support point retains a real supplied original timestamp');
$onlyMeasurements=JVHeliosLearningDiagnostics::update([],$rooms,[
    'weather_quality'=>'DWD_ONLY','fan_level'=>3],$now);
ld($onlyMeasurements['fresh_temperature_rooms']===4,
 'Room data remains available when independent verified DWD weather is sole source');
$missing=JVHeliosLearningDiagnostics::update([],[],['weather_quality'=>'UNAVAILABLE'],$now);
ld($missing['room_count']===0
 && str_contains($missing['report'],'BEOBACHTUNG_DATEN_TEILWEISE_FEHLEN'),
 'Missing rooms and weather produce clear non-calibrated fail-safe report');
echo "HELIOS LEARNING DIAGNOSIS TESTS PASSED\n";
