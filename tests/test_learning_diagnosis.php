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
ld(str_contains($a['report'],'-- Naechste Pruefschritte fuer ChatGPT (max. 5) --')
 && str_contains($a['report'],'Pruefauftrag: Bitte Datenluecken priorisieren')
 && str_contains($a['report'],'KEIN_NACHWEIS_DER_HELIOS_WIRKUNG'),
 'One copyable report tells ChatGPT what evidence to prioritize without pretending physical calibration');
ld(str_contains($a['report'],'Sole-EWT Eintritt (NICHT Wetter-Aussenluft): 15.0C')
 && str_contains($a['report'],'reale Aussenluft 9.3C')
 && str_contains($a['report'],'Wetteralter ?'),
 'Device intake explicitly distinguished from real outdoor weather; missing age not guessed');
ld(str_contains($a['report'],'Aussenwetter-Snapshot fuer zeitgleiche Lernpaare zu alt')
 && str_contains($a['report'],'Tatsaechliche Zuluft nach Waermetauscher fuer zeitgleiche Lernpaare zu alt'),
 'Unspecified sensor ages are reported as missing proof, not fresh by assumption');
$sourceAges=$snapshot;
$sourceAges['outside_age_minutes']=2;
$sourceAges['intake_age_minutes']=4;
$updatedReport=JVHeliosLearningDiagnostics::update([],$rooms,$sourceAges,$now);
ld(str_contains($updatedReport['report'],'Original-Messalter 4m')
 && str_contains($updatedReport['report'],'Wetteralter 2m'),
 'Native intake and independent outside age appear as separate original timestamps');
$untrustedWeather=$sourceAges;
$untrustedWeather['weather_quality']='MODEL_SUPPORTED';
$unverified=JVHeliosLearningDiagnostics::update([],$rooms,$untrustedWeather,$now);
ld(str_contains($unverified['report'],'Verifiziertes Aussenwetter fehlt'),
 'A mere weather model is never passed to ChatGPT as verified outdoor input');

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
ld(str_contains($staleResult['report'],'Alter 960m')
 && str_contains($staleResult['report'],'nur Indikation'),
 'Several-hours-old room RH is shown with real age but explicitly only indicative');
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
// Multi-day evidence must remain descriptive, not a learned fan effect.
$twoDay=[];
for($h=0;$h<=72;$h+=2)
    $twoDay[]=['at'=>$now+$h*3600,'c'=>20.0+round($h/24,1)*0.4];
$stats=JVHeliosLearningDiagnostics::describeHistory($twoDay,$now+72*3600);
ld($stats['evidence']==='MEHRTAGES_TREND_BEOBACHTBAR'
 && $stats['trend_24h']==='WAERMER' && $stats['delta_24h_c']>=0.3,
 'Three days of genuine support points make an observed 24h warming trend measurable');
ld($stats['paired_context_points']===0 && $stats['trend_24h']!=='NOCH_NICHT_MESSBAR',
 'Observed 24h temperature trend remains possible without making up weather or Helios context');
ld($stats['gaps_gt_3h']===0 && $stats['jumps_gt_3c']===0
 && $stats['min_7d_c']===20.0,'Regular samples have no fake gaps or jumps');
$constant=[];
for($h=0;$h<=30;$h+=2) $constant[]=['at'=>$now+$h*3600,'c'=>21.5];
$flat=JVHeliosLearningDiagnostics::describeHistory($constant,$now+30*3600);
ld($flat['trend_24h']==='NAHEZU_GLEICH'
 && $flat['delta_24h_c']===0.0,
 'Constant verified room temperature is stable, not a sensor-failure diagnosis');
$gapped=$twoDay;
$gapped=array_values(array_filter($gapped,static fn($v)=>$v['at']<$now+40*3600
    ||$v['at']>$now+62*3600));
$gap=JVHeliosLearningDiagnostics::describeHistory($gapped,$now+72*3600);
ld($gap['gaps_gt_3h']>=1 && $gap['trend_24h']==='NOCH_NICHT_MESSBAR',
 'Big missing daily interval is reported, not interpolated into a trend');
$spike=$twoDay;
$spike[count($spike)-2]['c']+=5.5;
$noise=JVHeliosLearningDiagnostics::describeHistory($spike,$now+72*3600);
ld($noise['jumps_gt_3c']>=1 && $noise['delta_24h_c']===null,
 'Implausible short-period temperature jump suspends 24h trend');
$old=JVHeliosLearningDiagnostics::describeHistory($twoDay,$now+90*3600);
ld($old['delta_24h_c']===null,
 'No recent end measurement: no stale trend advertised as current');

// Store supply/weather CONTEXT only if observed at nearly the same time,
// genuinely trusted/fresh, and never treat a snapshot as period-wide proof.
$pairedSnapshot=$snapshot;
$pairedSnapshot['weather_quality']='VERIFIED';
$pairedSnapshot['outside_age_minutes']=2;
$near=JVHeliosLearningDiagnostics::update([],[$rooms[0]],$pairedSnapshot,$now);
$ctx=$near['state']['rooms']['EG Wohnen']['samples'][0]['context']??null;
ld(is_array($ctx) && $ctx['simultaneous_only']===true
    && $ctx['supply_c']===20.0 && $ctx['outside_c']===9.3,
 'True near-simultaneous weather and Helios supply context saved as an explicitly point-in-time snapshot');
ld($near['paired_context_points']===1 && !$near['control_authorized'],
 'Paired evidence remains observational and non-actuating');
$untrusted=$pairedSnapshot;$untrusted['outside_age_minutes']=90;
$notPaired=JVHeliosLearningDiagnostics::update([],[$rooms[0]],$untrusted,$now);
ld($notPaired['paired_context_points']===0,
 'Old weather snapshot cannot be paired with a room measurement');
$slightlyOld=$pairedSnapshot;$slightlyOld['outside_age_minutes']=12;
$notPaired=JVHeliosLearningDiagnostics::update([],[$rooms[0]],$slightlyOld,$now);
ld($notPaired['paired_context_points']===0,
 'Outside data 12 minutes old is fine for ordinary monitoring but not a tightly matched learning pair');
$oldSupply=$pairedSnapshot;$oldSupply['supply_age_minutes']=14;
$notPaired=JVHeliosLearningDiagnostics::update([],[$rooms[0]],$oldSupply,$now);
ld($notPaired['paired_context_points']===0,
 'Stale supply is not incorrectly tied to current room temperature for learning');
$tooFar=$rooms[0];$tooFar['temperature_observed_at']=$now-20*60;
$nonConcurrent=JVHeliosLearningDiagnostics::update([],[$tooFar],$pairedSnapshot,$now);
ld($nonConcurrent['paired_context_points']===0,
 'Room and weather observed twenty minutes apart cannot be labelled simultaneous');
$missing=JVHeliosLearningDiagnostics::update([],[],['weather_quality'=>'UNAVAILABLE'],$now);
ld($missing['room_count']===0
 && str_contains($missing['report'],'BEOBACHTUNG_DATEN_TEILWEISE_FEHLEN'),
 'Missing rooms and weather produce clear non-calibrated fail-safe report');
echo "HELIOS LEARNING DIAGNOSIS TESTS PASSED\n";
