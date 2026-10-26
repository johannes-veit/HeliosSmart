<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function nr(bool $ok,string $why):void {
    if(!$ok){fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
$tz=new DateTimeZone('Europe/Berlin');
$start=(new DateTimeImmutable('2026-07-11 00:00:00',$tz))->getTimestamp();
$now=(new DateTimeImmutable('2026-07-17 12:00:00',$tz))->getTimestamp();
function scenario(int $start,int $now,string $variant='weak'):array {
    $tz=new DateTimeZone('Europe/Berlin');
    $all=[];
    for($at=$start;$at<=$now;$at+=7200) {
        $hour=(int)(new DateTimeImmutable('@'.$at))->setTimezone($tz)->format('G');
        $evening=$hour>=19&&$hour<=23;
        $morning=$hour>=5&&$hour<=9;
        $c=26.5;
        if($morning)$c=$variant==='strong'?25.3:26.4;
        $outside=($evening?21.2:($morning?20.5:22.0));
        $dew=$variant==='humid'?19.0:11.0;
        if($variant==='warmoutside')$outside=25.8;
        $all[]=['at'=>$at,'c'=>$c,
            'context'=>['simultaneous_only'=>true,'outside_c'=>$outside,
                'outside_dewpoint_c'=>$dew,'supply_c'=>19.8,'fan_level'=>3]];
    }
    return $all;
}
function review(string $name,array $history,int $now):array {
    $heat=JVHeliosLearningOverheat::recent($name,$history,$now);
    $nights=JVHeliosLearningPatterns::analyze($history,$now);
    $summer=JVHeliosLearningOverheat::summerProfile($name,[]);
    return JVHeliosLearningNightOpportunity::analyze($name,$heat,$nights,$summer,$now);
}
$base=scenario($start,$now);
$a=review('EG Wohnen',$base,$now);
nr($a['status']==='GERINGE_NACHTABKUEHLUNG_TROTZ_KUEHLER_AUSSENLUFT'
   &&$a['review_priority']==='HOHE_PRIORITAET_FUER_REINEN_DATENVERGLEICH'
   &&$a['nights_outside_thermally_cool']>=3,
   'Repeated hot living room and genuine weakly cooling nights lead to learning REVIEW only');
nr(!$a['humidity_safety_proven']&&!$a['actual_supply_response_calibrated']
   &&!$a['cooling_effect_proven']&&!$a['can_change_fan']
   &&!$a['can_change_bypass']&&!$a['can_change_program'],
   'Even high learning review cannot authorize fan, bypass or weekly-plan modifications');
$strong=review('EG Wohnen',scenario($start,$now,'strong'),$now);
nr($strong['status']==='NACHTABKUEHLUNG_BEREITS_BEOBACHTET'
    &&$strong['review_priority']==='BESTEHENDES_VERHALTEN_WEITER_BEOBACHTEN',
    'Night already cools rooms reliably: avoid claiming need to increase airflow');
$humid=review('EG Wohnen',scenario($start,$now,'humid'),$now);
nr($humid['status']==='AUSSENWETTER_BISHER_NICHT_HILFREICH'
    &&$humid['nights_outside_thermally_cool']===0,
    'Warm and high-dewpoint exterior nights are not called useful night cooling');
$hotOutside=review('EG Wohnen',scenario($start,$now,'warmoutside'),$now);
nr($hotOutside['status']==='AUSSENWETTER_BISHER_NICHT_HILFREICH',
    'Outside nights without 2K cooling gradient are not thermal opportunities');
$noWeather=scenario($start,$now);
foreach($noWeather as &$row)unset($row['context']);
unset($row);
$missing=review('EG Wohnen',$noWeather,$now);
nr($missing['status']==='AUSSENWETTER_NACHTVERGLEICH_FEHLT'
    &&$missing['nights_without_paired_weather']>2,
    'Real room nights without trusted concurrent weather remain unqualified');
$missingNight=array_values(array_filter($base,static function($v)use($tz) {
    $local=(new DateTimeImmutable('@'.$v['at']))->setTimezone($tz);
    return !($local->format('Y-m-d')==='2026-07-15'
        &&(int)$local->format('G')>=20)
        &&!($local->format('Y-m-d')==='2026-07-16'
        &&(int)$local->format('G')<=8);
}));
$gap=review('EG Wohnen',$missingNight,$now);
nr($gap['status']==='AKTUELLE_HITZEDATEN_UNZUVERLAESSIG',
    'Large missing temperature section does not get silently interpolated');
$onlyOne=array_slice($base,-8);
$short=review('EG Wohnen',$onlyOne,$now);
nr($short['status']==='WARM_ABER_NACHTDATEN_FEHLEN'
    &&$short['review_priority']==='WEITERE_NACHTMESSUNGEN_ABWARTEN',
    'Recent warm measurements without multiple full nights request more observation');
$cold=scenario($start,$now);
foreach($cold as &$row) $row['c']=22.8;
unset($row);
$comfortable=review('EG Wohnen',$cold,$now);
nr($comfortable['status']==='KEINE_AKTUELLE_WAERMEBELASTUNG_BELEGT',
    'Comfortable house not called heat-loaded even if outside was cool');
$excluded=review('OG Bad',$base,$now);
nr($excluded['status']==='KEIN_WOHN_ODER_SCHLAFRAUM',
    'Moist bathroom not treated as summer sleeping room');
$winterStart=(new DateTimeImmutable('2026-01-10 00:00:00',$tz))->getTimestamp();
$winterNow=(new DateTimeImmutable('2026-01-16 12:00:00',$tz))->getTimestamp();
$winter=review('EG Wohnen',scenario($winterStart,$winterNow),$winterNow);
nr($winter['status']==='AUSSERHALB_SOMMERBEOBACHTUNG',
    'Underfloor winter heating does not cause summer night-cooling suggestions');
$old=review('EG Wohnen',$base,$now+8*3600);
nr($old['status']==='AKTUELLE_HITZEDATEN_UNZUVERLAESSIG',
    'Outdated room source prevents up-to-date night review');
$room=['name'=>'EG Wohnen','temperature'=>26.5,'rh'=>51,
    'temperature_observed_at'=>$now,
    'humidity_observed_at'=>$now];
$prior=['rooms'=>['EG Wohnen'=>['samples'=>$base]]];
$report=JVHeliosLearningDiagnostics::update($prior,[$room],[
    'weather_quality'=>'UNAVAILABLE','fan_level'=>3,
    'mode'=>0,'program'=>3],$now);
nr($report['night_high_review_priority_rooms']===1
    &&str_contains($report['report'],'Nacht-Lernbewertung:')
    &&str_contains($report['report'],'NUR VERGLEICHSBEDARF, keine Luefterfreigabe'),
    'Existing sole diagnostic string reports combined pressure/night evidence safely');
nr(!$report['control_authorized'] && !$report['learning_calibrated']
    &&!array_key_exists('night_opportunity',$report['compact_state'])
    &&!array_key_exists('night_review',$report['compact_state']),
    'Combined night review consumes no additional stored per-room or global state');
// A recently hot room cannot be assigned high learning priority based
// solely on cool weather nearly a week ago.
$oldNights=JVHeliosLearningPatterns::analyze($base,$now);
foreach($oldNights['night_history'] as &$night)
    $night['at_morning']=$now-6*86400;
unset($night);
$heatNow=JVHeliosLearningOverheat::recent('EG Wohnen',$base,$now);
$longTerm=JVHeliosLearningOverheat::summerProfile('EG Wohnen',[]);
$staleReview=JVHeliosLearningNightOpportunity::analyze(
    'EG Wohnen',$heatNow,$oldNights,$longTerm,$now);
nr($staleReview['status']==='NACHTVERGLEICHE_NICHT_AKTUELL'
    &&$staleReview['nights_outside_thermally_cool']===0
    &&$staleReview['older_nights_ignored']>=3,
    'Nearly week-old cool nights cannot be misapplied to a new hot-room episode');
nr($a['recent_nights_with_weather']>=2
    &&$a['older_nights_ignored']>=1,
    'Recent matched nights drive the composite signal; older ones remain historical only');
// Vary 121 outdoor/dewpoint pair combinations without ever producing
// an actuator entitlement or a false physical-cooling-efficiency claim.
for($i=0;$i<=120;$i++){
    $probe=$base;
    $outside=10+$i*0.15;
    $dew= -2+$i*0.16;
    foreach($probe as &$row){
        $row['context']['outside_c']=$outside;
        $row['context']['outside_dewpoint_c']=$dew;
    }unset($row);
    $trial=review('OG Schlafen 1',$probe,$now);
    if($trial['can_change_fan']||$trial['can_change_program']
        ||$trial['can_change_bypass']||$trial['humidity_safety_proven']
        ||$trial['cooling_effect_proven']){
        fwrite(STDERR,"FAIL Actuator safety scenario $i\n");exit(1);
    }
}
nr(true,'121 diverse night weather/dewpoint scenarios cannot activate ventilation');
echo "PASSIVE NIGHT-LEARNING PRIORITIZATION PASSED\n";
