<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function wq(bool $ok,string $why):void {
    if(!$ok){fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
$tz=new DateTimeZone('Europe/Berlin');
function wStart(string $local):int {
    return (new DateTimeImmutable($local,new DateTimeZone('Europe/Berlin')))
        ->getTimestamp();
}
function sampleWeek(int $start,int $direction=-1,bool $context=true):array {
    $tz=new DateTimeZone('Europe/Berlin');$rows=[];
    for($i=0;$i<84;$i++){
        $ts=$start+$i*7200;
        $dt=(new DateTimeImmutable('@'.$ts))->setTimezone($tz);
        $hour=(int)$dt->format('G');
        $night=$hour>=19 ||$hour<=9;
        $nightDay=$hour<=9?$dt->modify('-1 day'):$dt;
        $stage=((int)$nightDay->format('z'))%2===0?2:3;
        if(!$night)$stage=2;
        $c=$hour>=19?27.0:($hour<=9
            ?($stage===2?26.4:($direction<0?25.5:26.9)):26.6);
        $p=['at'=>$ts,'c'=>$c];
        if($context)$p['context']=[
            'simultaneous_only'=>true,'outside_c'=>20.1,
            'outside_dewpoint_c'=>10.1,'supply_c'=>19.0,
            'fan_level'=>$stage,
        ];
        $rows[]=$p;
    }
    return $rows;
}
// Deterministic overnight behaviour, all timestamps original, with the
// same small completed-week q record, not an additional time series.
function thermalWeek(int $start,string $kind):array {
    $tz=new DateTimeZone('Europe/Berlin');$rows=[];
    for($i=0;$i<84;$i++){
        $at=$start+$i*7200;
        $dt=(new DateTimeImmutable('@'.$at))->setTimezone($tz);
        $h=(int)$dt->format('G');
        $night=$h>=20||$h<=9;
        $localNight=$h<=9?$dt->modify('-1 day'):$dt;
        $choice=$kind==='mixed'
            ?((int)$localNight->format('j')%2===0?'cool':'flat')
            :$kind;
        $step=$h>=20?($h-20)/2:($h+4)/2;
        $c=$night&&$choice==='cool'?26.0-$step*0.2:26.0;
        $rows[]=['at'=>$at,'c'=>round($c,2),'context'=>[
            'simultaneous_only'=>true,'outside_c'=>20.0,
            'outside_dewpoint_c'=>12.0,'supply_c'=>19.0,
            'fan_level'=>2,
        ]];
    }
    return $rows;
}
$empty=JVHeliosLearningWeeklyQuality::capture(
    [],[],'EG Wohnen',wStart('2026-07-13 00:05:00'));
wq($empty===[],
    'New installation without a previous-week original sensor record creates no unnecessary persistent weekly marker');
$week=wStart('2026-07-06 00:00:00');
$now=wStart('2026-07-13 00:05:00');
$first=sampleWeek($week);
$captured=JVHeliosLearningWeeklyQuality::capture([],$first,'EG Wohnen',$now);
wq(count($captured)===1 &&$captured[0][0]===202628,
    'First fully completed ISO calendar week is captured once using original timestamped samples');
wq($captured[0][1]===3 &&$captured[0][2]===84
    &&$captured[0][3]===84 &&$captured[0][4]>=2
    &&$captured[0][5]===-1 &&$captured[0][6]===0,
    'Full original sample coverage, trusted paired outdoor/supply and coherent stage observations yield grade 3');
wq(JVHeliosLearningWeeklyQuality::capture($captured,$first,'EG Wohnen',$now+300)===$captured,
    'Repeated five-minute Evaluate within same ISO week never creates duplicate week or persistence change');
$tooFew=JVHeliosLearningWeeklyQuality::capture(
    [],array_slice($first,0,11),'EG Wohnen',$now);
wq($tooFew===[],
    'A failed sensor with fewer than twelve historical originals creates no dummy week and saves flash writes');
$dstStart=wStart('2026-10-19 00:00:00');
$dstEnd=wStart('2026-10-26 00:00:00');
$dstRows=[];
for($t=$dstStart;$t<$dstEnd;$t+=7200)
    $dstRows[]=['at'=>$t,'c'=>21.5,
        'context'=>['simultaneous_only'=>true,
            'outside_c'=>10.0,'outside_dewpoint_c'=>7.0,
            'supply_c'=>19.0,'fan_level'=>2]];
$dstWeek=JVHeliosLearningWeeklyQuality::capture(
    [],$dstRows,'EG Wohnen',$dstEnd+300);
wq(count($dstRows)===85 &&$dstWeek[0][1]===2
    &&$dstWeek[0][2]===84 &&$dstWeek[0][3]===84,
    'Berlin October time change includes true extra hour without creating an 85th stored count or fake weekly time interval');
$firstThermal=JVHeliosLearningWeeklyQuality::capture(
    [],thermalWeek($week,'cool'),'EG Wohnen',$now);
wq(count($firstThermal[0])===8 &&$firstThermal[0][7]===1
    &&$firstThermal[0][1]>=2,
    'Only one optional integer persists repeated 6h room cooling in a real finished week');
$flatThermal=JVHeliosLearningWeeklyQuality::capture(
    [],thermalWeek($week,'flat'),'EG Wohnen',$now);
wq(count($flatThermal[0])===8 &&$flatThermal[0][7]===2,
    'Flat observed summer nights are encoded by one distinct finite weekly value');
$wetRoomThermal=JVHeliosLearningWeeklyQuality::capture(
    [],thermalWeek($week,'cool'),'OG Bad',$now);
wq($wetRoomThermal[0][7]===0,
    'Wet room has no thermic study sign even if its sensors report benign night conditions');
$three=[];
for($i=0;$i<4;$i++){
    $monday=(new DateTimeImmutable('2026-07-06 00:00:00',$tz))
        ->modify('+'.$i.' weeks');
    $firstAt=$monday->getTimestamp();
    $nextMonday=$monday->modify('+1 week')->getTimestamp()+300;
    $three=JVHeliosLearningWeeklyQuality::capture(
        $three,thermalWeek($firstAt,'cool'),'EG Wohnen',$nextMonday);
}
$threeView=JVHeliosLearningWeeklyQuality::assess($three);
wq(count($three)===4&&$threeView['thermal_cooling_weeks']===4
    &&$threeView['thermal_status']==='MEHRWOECHIG_RAUMLUFT_ABGEKUEHLT',
    'Four independent completed hot-season weeks show repeatable observed room cooling without a causal effect');
$thirdFlipped=$three;
$thirdFlipped[3][7]=2;
$contrary=JVHeliosLearningWeeklyQuality::assess($thirdFlipped);
wq($contrary['thermal_status']==='THERMIKWOCHEN_WIDERSPRUECHLICH'
    &&$contrary['thermal_stable_weeks']===1,
    'Opposite thermal pattern in one independent week vetoes a repeated direction');
$formerSeven=[[202628,2,80,78,0,9,0]];
wq(JVHeliosLearningWeeklyQuality::decode($formerSeven)===$formerSeven
    &&JVHeliosLearningWeeklyQuality::assess($formerSeven)['thermal_cooling_weeks']===0,
    'Old 7-number weekly footprints survive unchanged and never acquire invented historical thermics');
$illegalEight=[[202628,2,80,78,0,9,0,9]];
wq(JVHeliosLearningWeeklyQuality::decode($illegalEight)===[],
    'Unexpected or corrupt eighth thermal state is rejected at compact-storage boundary');
$legacy=['v'=>2,'t'=>$now,'d'=>$now,'r'=>[],'m'=>[]];
$decoded=JVHeliosLearningStore::decode($legacy);
wq($decoded['weekly']===[],'Old v2 learning state without optional weekly quality field decodes without inventing previous history');
$state=JVHeliosLearningStore::encode(['EG Wohnen'=>['samples'=>$first]],[],$now,0,
    ['EG Wohnen'=>$captured]);
$round=JVHeliosLearningStore::decode($state);
wq($round['weekly']['EG Wohnen']===$captured
    &&$round['rooms']['EG Wohnen']['samples'][0]['at']===$week,
    'Compact weekly quality and original 2-hour samples survive encode/decode without altering source timestamps');
$complete=$captured;
for($j=1;$j<7;$j++){
    $w=wStart('2026-07-06 00:00:00')+$j*7*86400;
    $at=$w+7*86400+300;
    $complete=JVHeliosLearningWeeklyQuality::capture(
        $complete,sampleWeek($w),'EG Wohnen',$at);
}
wq(count($complete)===4 &&$complete[0][0]< $complete[3][0],
    'Seven completed observation weeks remain only four bounded fingerprints (rolling quality evidence)');
$quality=JVHeliosLearningWeeklyQuality::assess($complete);
wq($quality['status']==='WIEDERHOLTE_STUFENASSOZIATION_NUR_BEOBACHTUNG'
    &&$quality['good_room_weeks']===4
    &&$quality['weather_supported_weeks']===4
    &&$quality['stage_supported_weeks']===4
    &&$quality['stage_negative_weeks']===4,
    'At least three independent completed weeks can repeat directional stage association without calling it causal');
wq(!$quality['multiweek_effect_proven']
    &&!$quality['historical_stage_continuity_proven']
    &&!$quality['full_fbh_solar_window_controlled']
    &&!$quality['can_actuate'],
    'No multiweek pattern can authorize automation or prove bypass/FBH/solar control');
$opposite=$complete;
$last=$opposite[count($opposite)-1];
$opposite[count($opposite)-1][5]=1;
$contradiction=JVHeliosLearningWeeklyQuality::assess($opposite);
wq($contradiction['status']==='WIDERSPRUECHLICHE_WOCHEN_STUFENBEOBACHTUNGEN'
    &&$contradiction['stage_positive_weeks']===1
    &&$contradiction['stage_negative_weeks']===3,
    'Contrary stage direction in an independent week reports contradiction, never averages it away');
$short=sampleWeek($week);
$short=array_values(array_filter($short,static fn($p)=>$p['at']>=$week+3*86400));
$missing=JVHeliosLearningWeeklyQuality::capture([],$short,'EG Wohnen',$now);
wq($missing[0][1]===0,
    'No fabricated full-week quality if the source archive covers only the final four days');
$withoutWeather=JVHeliosLearningWeeklyQuality::capture(
    [],sampleWeek($week,-1,false),'EG Wohnen',$now);
wq($withoutWeather[0][1]===1 &&$withoutWeather[0][3]===0,
    'Complete room-temperature week without trusted outdoor context earns only room-data quality, no stage-effect claim');
$tooManyGaps=sampleWeek($week);
$tooManyGaps=array_values(array_filter($tooManyGaps,
    static fn($p)=>((int)(($p['at']-$week)/7200)%4)<2));
$gapped=JVHeliosLearningWeeklyQuality::capture(
    [],$tooManyGaps,'EG Wohnen',$now);
wq($gapped[0][1]===0 &&$gapped[0][6]>2,
    'Repeated four-hour-plus sensor gaps prevent sufficient weekly quality');
$bath=JVHeliosLearningWeeklyQuality::capture([],$first,'OG Bad',$now);
wq($bath[0][1]===2 &&$bath[0][4]===0,
    'Bathroom temperature and weather can be quality-checked but never used as bedroom fan-stage experiment');
$winterAt=wStart('2027-01-04 00:00:00');
$winter=JVHeliosLearningWeeklyQuality::capture([],
    sampleWeek($winterAt),'EG Wohnen',$winterAt+7*86400+300);
wq($winter[0][1]===2 &&$winter[0][5]===9,
    'Winter may certify sensor/weather completeness but never learns summertime cooling-stage effect');
$malicious=[[202628,9,300,900,300,-1,900], [202629,1,60,0,0,9,0]];
wq(count(JVHeliosLearningWeeklyQuality::decode($malicious))===1,
    'Corrupt or unbounded weekly signatures are discarded instead of bloating the SymBox attribute');
$many=[];
for($i=0;$i<11;$i++)$many['Room '.$i]=$complete;
$packed=JVHeliosLearningStore::encode(
    ['EG Wohnen'=>['samples'=>$first]],[],$now,0,$many);
wq(strlen(json_encode($packed))<8000
    &&count($packed['q'])===11,
    'Four compact fingerprints for all 11 rooms cost only a few KB and do not grow with years');
$allRoomDetails=[];
for($i=0;$i<11;$i++)$allRoomDetails['Room '.$i]=['samples'=>$first];
$fullBound=JVHeliosLearningStore::encode($allRoomDetails,[],$now,0,$many);
wq(strlen(json_encode($fullBound))<50000,
    'Full 11-room 84-point weather/supply observations PLUS four weekly footprints per room remain below 50KB in synthetic payload');
$diagnostic=JVHeliosLearningDiagnostics::update(
    ['v'=>2,'t'=>$now,'d'=>0,'r'=>['EG Wohnen'=>array_map(
        static fn($p)=>[$p['at'],(int)round($p['c']*100),[
            190,201,101,(int)$p['context']['fan_level']]],$first)],
      'm'=>[],'q'=>['EG Wohnen'=>$complete]],
    [['name'=>'EG Wohnen','temperature'=>26.4,'rh'=>47,
        'temperature_observed_at'=>$now-30,'humidity_observed_at'=>$now-30]],
    ['weather_quality'=>'UNAVAILABLE','fan_level'=>2,'mode'=>0,'program'=>3],$now);
wq(str_contains($diagnostic['report'],'Mehrwochen-Qualitaet')
    &&$diagnostic['per_room_multiweek_quality']['EG Wohnen']['completed_weeks']===4
    &&isset($diagnostic['compact_state']['q']['EG Wohnen']),
    'Existing single ChatGPT diagnosis includes completed weeks while maintaining compact v2 optional q records');
wq(!$diagnostic['control_authorized']&&!$diagnostic['learning_calibrated']
    &&!isset($diagnostic['compact_state']['effects']),
    'Multiweek quality screen never learns unsupported fan cooling physics or creates actuator permissions');
echo "COMPACT MULTIWEEK QUALITY TESTS PASSED\n";
