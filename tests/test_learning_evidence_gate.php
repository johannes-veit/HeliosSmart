<?php
require_once __DIR__.'/../libs/LearningEvidenceGate.php';
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function eg(bool $value,string $message):void {
    if(!$value){fwrite(STDERR,"FAIL $message\n");exit(1);}
    echo "PASS $message\n";
}
$now=(new DateTimeImmutable('2026-07-20 14:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$trend=['delta_24h_c'=>-0.2,'recent_points'=>11,
    'gaps_gt_3h'=>0,'jumps_gt_3c'=>0];
$nights=['valid_nights'=>4,'night_history'=>[
    ['at_morning'=>$now-2*86400,'delta_c'=>-0.3],
    ['at_morning'=>$now-86400,'delta_c'=>-0.3],
    ['at_morning'=>$now-8*3600,'delta_c'=>-0.4],
]];
$stage=['matched_weather_stage_pairs'=>3,
    'status'=>'MEHRERE_NATUERLICHE_STUFENVERGLEICHE_NUR_BEOBACHTUNG',
    'pair_directions_disagree'=>false];
$weekly=[
 [202626,3,80,75,2,-1,0],
 [202627,3,81,76,2,-1,0],
 [202628,3,82,78,2,-1,0],
 [202629,3,82,77,2,-1,0],
];
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,$stage,$weekly,$now);
eg($r['daily']==='BESCHREIBUNG_ZULAESSIG'
    &&$r['night']==='BESCHREIBUNG_ZULAESSIG'
    &&$r['multiweek']==='BESCHREIBUNG_ZULAESSIG'
    &&$r['stage']==='WIEDERHOLTE_KORRELATION_NUR_FORSCHUNG'
    &&$r['status']==='VERGLEICHSQUALITAET_FUER_FORSCHUNG',
    'All four distinct analyses become reviewable with fresh originals and four independent quality weeks');
eg($r['physical_model']==='SPERRE_NICHT_KALIBRIERT'
   &&!$r['learning_may_change_fan']
   &&!$r['learning_may_change_bypass']
   &&!$r['learning_may_change_weekly_program']
   &&!$r['real_airflow_verified']
   &&!$r['supply_humidity_verified']
   &&!$r['confounders_controlled']
   &&!$r['effect_proven'],
   'Even maximum historical correlation never unlocks actuator or claims proven thermodynamics');

$thermalWeeks=[
 [202626,2,80,72,0,9,0,1],
 [202627,2,81,74,0,9,0,1],
 [202628,2,82,75,0,9,0,1],
 [202629,2,82,77,0,9,0,1],
];
$freshThermal=[
 'status'=>'WIEDERHOLT_RAUMLUFT_ABGEKUEHLT_BEI_KUEHLER_AUSSENLUFT',
 'newest_window_age_h'=>12,
];
$tg=JVHeliosLearningEvidenceGate::review(
 'EG Wohnen',$trend,$nights,$stage,$thermalWeeks,$now,$freshThermal);
eg($tg['thermal_repeatability']==='WIEDERHOLT_BEOBACHTBAR_NICHT_KALIBRIERT'
   &&!$tg['learning_may_change_fan']
   &&$tg['physical_model']==='SPERRE_NICHT_KALIBRIERT',
   'Three independent thermal weeks PLUS fresh matching original windows allow descriptive repeatability only');
$staleThermal=$freshThermal;$staleThermal['newest_window_age_h']=110;
$tg=JVHeliosLearningEvidenceGate::review(
 'EG Wohnen',$trend,$nights,$stage,$thermalWeeks,$now,$staleThermal);
eg($tg['thermal_repeatability']==='SPERRE_AKTUELLE_MESSFENSTER_FEHLEN',
   'Old thermal history cannot replace absent current night windows');
$changedThermal=[
 'status'=>'WIEDERHOLT_RAUMLUFT_KAUM_ABGEKUEHLT',
 'newest_window_age_h'=>10,
];
$tg=JVHeliosLearningEvidenceGate::review(
 'EG Wohnen',$trend,$nights,$stage,$thermalWeeks,$now,$changedThermal);
eg($tg['thermal_repeatability']==='SPERRE_AKTUELL_ANDERER_VERLAUF',
   'New stable behaviour vetoes historic cooling pattern even after four good weeks');
$oppositeThermal=$thermalWeeks;$oppositeThermal[3][7]=2;
$tg=JVHeliosLearningEvidenceGate::review(
 'EG Wohnen',$trend,$nights,$stage,$oppositeThermal,$now,$freshThermal);
eg($tg['thermal_repeatability']==='SPERRE_WIDERSPRUECHLICHE_WOCHEN',
   'Opposing independent thermal week requires explicit contradiction outcome');
$tg=JVHeliosLearningEvidenceGate::review(
 'OG Bad',$trend,$nights,$stage,$thermalWeeks,$now,$freshThermal);
eg($tg['thermal_repeatability']==='NEBENRAUM_NICHT_BEWERTET',
   'Shower heat and humidity in bathrooms are never mistaken for sleep-room thermal repeatability');
$conflict=$weekly;$conflict[3][5]=1;
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,$stage,$conflict,$now);
eg($r['stage']==='SPERRE_WIDERSPRUECHLICHE_WOCHEN'
    &&$r['status']==='WIDERSPRUECHLICHE_WOCHEN',
    'One contradictory completed week blocks staged-learning assessment despite current apparently good pairs');
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,[
        'status'=>'WIDERSPRUECHLICHE_STUFENBEOBACHTUNGEN',
        'matched_weather_stage_pairs'=>3,
        'pair_directions_disagree'=>true],$weekly,$now);
eg($r['stage']==='SPERRE_AKTUELLE_WIDERSPRUECHE'
    &&$r['status']==='WIDERSPRUECHLICHE_NACHTPAARE',
    'Contradictory current nighttime evidence overrules even four agreeing historical weeks');

$noDay=$trend;$noDay['delta_24h_c']=null;
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$noDay,$nights,$stage,$weekly,$now);
eg($r['stage']==='SPERRE_AKTUELLE_QUELLEN_ODER_NAECHTE'
    &&$r['multiweek']==='BESCHREIBUNG_ZULAESSIG',
    'Historic week evidence cannot override missing current 24h room-source trend');

$oldOnly=$nights;
foreach($oldOnly['night_history'] as &$item)$item['at_morning']=$now-6*86400;
unset($item);
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$oldOnly,$stage,$weekly,$now);
eg($r['stage']==='SPERRE_AKTUELLE_QUELLEN_ODER_NAECHTE',
    'Three historical summer stage weeks do not unlock current stage review when nights are stale');

$unreliable=$trend;$unreliable['jumps_gt_3c']=1;
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$unreliable,$nights,$stage,$weekly,$now);
eg($r['daily']==='SPERRE_QUELLEN'
    &&$r['stage']==='SPERRE_STUFENBELEGE'
    &&$r['status']==='SPERRE_TEMPERATURSPRUNG',
    'A 3C original sensor jump invalidates new inferences even with good stored week counters');

$gapped=$trend;$gapped['gaps_gt_3h']=3;
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$gapped,$nights,$stage,$weekly,$now);
eg($r['status']==='SPERRE_MESSLUECKEN'
   &&$r['stage']==='SPERRE_STUFENBELEGE',
   'Unreliable current source history blocks staged learning regardless of past weeks');

$oldNights=$nights;
foreach($oldNights['night_history'] as &$n)$n['at_morning']=$now-6*86400;
unset($n);
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$oldNights,$stage,$weekly,$now);
eg($r['night']==='SPERRE_NACHTMESSUNGEN'
    &&$r['multiweek']==='BESCHREIBUNG_ZULAESSIG',
    'Stale nighttime evidence cannot be called current, while historical multiweek quality remains descriptive');

$earlyWeekly=array_slice($weekly,0,2);
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,$stage,$earlyWeekly,$now);
eg($r['multiweek']==='SPERRE_WOCHEN'
    &&$r['stage']==='SPERRE_STUFENBELEGE',
    'Two independent weeks are insufficient for multiweek research classification');

$noWeather=[
 [202626,1,78,0,0,9,0],
 [202627,1,80,0,0,9,0],
 [202628,1,81,0,0,9,0],
 [202629,1,80,0,0,9,0],
];
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,$stage,$noWeather,$now);
eg($r['multiweek']==='NUR_RAUMMESSUNGEN'
    &&$r['stage']==='SPERRE_STUFENBELEGE',
    'Repeated temperatures without independent source weather cannot support stage/weather conclusions');
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,[
        'matched_weather_stage_pairs'=>1,
        'status'=>'ERSTES_NATUERLICHES_STUFENPAAR_NUR_BEOBACHTUNG',
        'pair_directions_disagree'=>false],$weekly,$now);
eg($r['multiweek']==='BESCHREIBUNG_ZULAESSIG'
    &&$r['stage']==='SPERRE_STUFENBELEGE',
    'Single current fan-stage comparison does not unlock multiweek stage-research review');

$winter=(new DateTimeImmutable('2027-01-18 14:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',$trend,$nights,$stage,$weekly,$winter);
eg($r['stage']==='SPERRE_AUSSERHALB_SOMMER'
    &&!$r['learning_may_change_fan'],
    'Winter heat recovery never gets assigned summer fan-study readiness');

$r=JVHeliosLearningEvidenceGate::review(
    'OG Bad',$trend,$nights,$stage,$weekly,$now);
eg($r['status']==='NUR_RAUMBEOBACHTUNG'
    &&$r['stage']==='SPERRE_STUFENBELEGE',
    'Bathroom and hallways never admitted to whole-house stage learning');
$r=JVHeliosLearningEvidenceGate::review(
    'EG Wohnen',['gaps_gt_3h'=>0],[],[],[], $now);
eg($r['status']==='MESSDATEN_NOCH_SAMMELN'
    &&$r['next_data']==='ECHTE_TEMPERATUR_ZEITPUNKTE_SAMMELN',
    'No original observations return a useful next evidence request, not an invented result');

// Stress-test multiple contradictory inputs; actuator permissions remain
// hard-false no matter what "learning" data contains.
for($i=0;$i<150;$i++){
    $w=$weekly;$s=$stage;$t=$trend;
    $w[3][5]=$i%3===0?1:-1;
    $s['pair_directions_disagree']=$i%5===0;
    $s['matched_weather_stage_pairs']=$i%4;
    $t['jumps_gt_3c']=$i%7===0?1:0;
    $out=JVHeliosLearningEvidenceGate::review(
        'EG Wohnen',$t,$nights,$s,$w,$now);
    if($out['learning_may_change_fan']
       ||$out['learning_may_change_bypass']
       ||$out['learning_may_change_weekly_program']
       ||$out['real_airflow_verified']
       ||$out['effect_proven']
       ||$out['physical_model']!=='SPERRE_NICHT_KALIBRIERT'){
        fwrite(STDERR,"FAIL actuator gate stress test $i\n");exit(1);
    }
}
eg(true,'150 contradictory data variants never yield hardware permission or claim causal physical calibration');

// Integration with one reporting variable and optional q week summaries:
// no new keys are ever appended to long-lived compact v2 data.
$sourceStart=$now-6*86400;
$history=[];
for($h=0;$h<72;$h++){
    $at=$sourceStart+$h*7200;
    $history[]=['at'=>$at,'c'=>25.8,
      'context'=>['simultaneous_only'=>true,'supply_c'=>19.0,
          'outside_c'=>20.0,'outside_dewpoint_c'=>12.0,'fan_level'=>2]];
}
$report=JVHeliosLearningDiagnostics::update([
    'v'=>2,'t'=>$now,'d'=>0,'m'=>[],
    'r'=>['EG Wohnen'=>array_map(static fn($p)=>[
        $p['at'],(int)round($p['c']*100),
        [190,200,120,2]
    ],$history)],
    'q'=>['EG Wohnen'=>$weekly]
], [
 ['name'=>'EG Wohnen','temperature'=>25.8,'rh'=>49,
     'temperature_observed_at'=>$now-20,
     'humidity_observed_at'=>$now-30],
],[
    'weather_quality'=>'UNAVAILABLE','fan_level'=>2,'mode'=>0,'program'=>3
],$now);
eg(str_contains($report['report'],'Lern-Auswertungsfreigabe (NUR LESEN)')
    &&str_contains($report['report'],'PHYSIKALISCHE_KALIBRIERUNG_GESPERRT')
    &&str_contains($report['report'],'Naechste Lernbelege'),
    'The existing lone ChatGPT diagnostic reports explicit tiers and next evidence without adding variables');
$packed=$report['compact_state'];
eg(array_keys($packed)===['v','t','d','r','m','q']
    &&strlen(json_encode($packed))<50000
    &&!$report['control_authorized']
    &&!$report['learning_calibrated'],
    'Learning evidence gates leave compact storage schema and hardware-safety invariants unchanged');
echo "PASSIVE LEARNING EVIDENCE GATE TESTS PASSED\n";
