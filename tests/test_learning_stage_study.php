<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function ss(bool $ok,string $why):void {
    if(!$ok){fwrite(STDERR,"FAIL ".$why."\n");exit(1);}
    echo "PASS ".$why."\n";
}
$tz=new DateTimeZone('Europe/Berlin');
$begin=(new DateTimeImmutable('2026-07-12 00:00:00',$tz))->getTimestamp();
$now=(new DateTimeImmutable('2026-07-18 12:00:00',$tz))->getTimestamp();
function buildStudy(int $begin,int $now):array {
    $tz=new DateTimeZone('Europe/Berlin');$out=[];
    for($at=$begin;$at<=$now;$at+=7200) {
        $dt=(new DateTimeImmutable('@'.$at))->setTimezone($tz);
        $hour=(int)$dt->format('G');
        $date=$hour<=9?$dt->modify('-1 day'):$dt;
        $stage=((int)$date->format('j'))%2===0?2:3;
        if($hour>9 &&$hour<19)$stage=2;
        $evening=$hour>=19;
        $morning=$hour<=9;
        $temp=$evening?27.0:($morning?($stage===2?26.5:25.8):26.5);
        $out[]=['at'=>$at,'c'=>$temp,
            'context'=>['simultaneous_only'=>true,
                'fan_level'=>$stage,'supply_c'=>19.5,
                'outside_c'=>20.0,'outside_dewpoint_c'=>11.0]];
    }
    return $out;
}
$samples=buildStudy($begin,$now);
$sampleStudy=JVHeliosLearningStageStudy::analyze($samples,$now);
ss($sampleStudy['status']==='MEHRERE_NATUERLICHE_STUFENVERGLEICHE_NUR_BEOBACHTUNG'
    &&$sampleStudy['stage_snapshot_nights']>=5
    &&$sampleStudy['matched_weather_stage_pairs']>=2,
    'Different observed stage nights matched on same indoor/outdoor starting air and dewpoint');
ss($sampleStudy['higher_stage_mean_delta_difference_c']<0.0
    &&$sampleStudy['higher_stage_mean_delta_difference_c']> -1.3,
    'Observed stronger-stage night delta is reported only as raw descriptive difference');
ss($sampleStudy['continuous_stage_proven']===false
    &&$sampleStudy['causal_cooling_effect_proven']===false
    &&$sampleStudy['bypass_stability_proven']===false
    &&$sampleStudy['fbh_and_internal_gains_controlled']===false
    &&$sampleStudy['suggested_fan_level']===null
    &&$sampleStudy['can_actuate']===false,
    'Even many matched nights never prove causal fan effect, continuous stage, or hardware permission');
ss($sampleStudy['data_gap']==='KAUSALITAET_BYPASS_FBH_FENSTER_NICHT_BELEGT'
    &&!$sampleStudy['pair_directions_disagree'],
    'Even consistent observational pairs still require FBH, bypass and opening status before any causal conclusion');
$conflicting=$samples;
foreach($conflicting as &$p){
    $local=(new DateTimeImmutable('@'.$p['at']))->setTimezone($tz);
    if($local->format('Y-m-d')==='2026-07-14'
        &&in_array((int)$local->format('G'),[6,8],true)
        &&(int)$p['context']['fan_level']===3)
        $p['c']=27.2;
}unset($p);
$conflict=JVHeliosLearningStageStudy::analyze($conflicting,$now);
ss($conflict['matched_weather_stage_pairs']>=2
    &&$conflict['pair_directions_disagree']
    &&$conflict['higher_stage_mean_delta_difference_c']===null
    &&$conflict['status']==='WIDERSPRUECHLICHE_STUFENBEOBACHTUNGEN'
    &&$conflict['data_gap']==='STOEREINFLUESSE_UNBEKANNT',
    'Conflicting observed stage outcomes suppress misleading average and name unknown confounders');
$unexpected=JVHeliosLearningStageStudy::analyze($conflicting,$now);
ss($unexpected['pair_directions_disagree']
    &&$unexpected['data_gap']==='STOEREINFLUESSE_UNBEKANNT',
    'Mixed results yield explicit confounder warning instead of numeric recommendation');
$all2=$samples;
foreach($all2 as &$p)$p['context']['fan_level']=2;
unset($p);
$r=JVHeliosLearningStageStudy::analyze($all2,$now);
ss($r['matched_weather_stage_pairs']===0
    &&$r['status']==='KEINE_AEHNLICHEN_WETTERNAECHTE_MIT_ANDEREN_STUFEN',
    'No stage contrast observed: study cannot invent another stage or an experiment');
$change=$samples;
foreach($change as &$p) {
    $d=(new DateTimeImmutable('@'.$p['at']))->setTimezone($tz);
    if($d->format('Y-m-d')==='2026-07-15'
        &&(int)$d->format('G')===2)$p['context']['fan_level']=4;
}
unset($p);
$r=JVHeliosLearningStageStudy::analyze($change,$now);
ss($r['stage_transitions_seen']>=1
    &&$r['matched_weather_stage_pairs']<$sampleStudy['matched_weather_stage_pairs'],
    'A switched stage inside one night removes the night from stable-snapshot comparisons');
$missing=$samples;
foreach($missing as &$p){
    $d=(new DateTimeImmutable('@'.$p['at']))->setTimezone($tz);
    if($d->format('Y-m-d')==='2026-07-15'
        &&(int)$d->format('G')===2)unset($p['context']);
}unset($p);
$r=JVHeliosLearningStageStudy::analyze($missing,$now);
ss($r['missing_context_nights']>=1
    &&$r['matched_weather_stage_pairs']<$sampleStudy['matched_weather_stage_pairs'],
    'A single missing intermediate fan/weather/supply sample cannot be silently filled');
$warmSupply=$samples;
foreach($warmSupply as &$p)$p['context']['supply_c']=27.0;
unset($p);
$r=JVHeliosLearningStageStudy::analyze($warmSupply,$now);
ss($r['matched_weather_stage_pairs']===0
    &&$r['supply_not_cooler_nights']>0,
    'Warm measured supply after enthalpy exchanger vetoes any claim of cooling observations');
$wet=$samples;
foreach($wet as &$p)$p['context']['outside_dewpoint_c']=19.0;
unset($p);
$r=JVHeliosLearningStageStudy::analyze($wet,$now);
ss($r['matched_weather_stage_pairs']===0
    &&$r['status']==='ZU_WENIGE_STUFENBEOBACHTUNGEN',
    'Humid outside dewpoint blocks the preliminary dry-night stage comparison');
$differentWeather=$samples;
foreach($differentWeather as &$p){
    if((int)$p['context']['fan_level']===3) {
        $p['context']['outside_c']=26.0;
        $p['context']['outside_dewpoint_c']=14.0;
    }
}unset($p);
$r=JVHeliosLearningStageStudy::analyze($differentWeather,$now);
ss($r['matched_weather_stage_pairs']===0,
    'Different outside conditions cannot be interpreted as a comparable fan-stage change');
$differentStart=$samples;
foreach($differentStart as &$p){
    $d=(new DateTimeImmutable('@'.$p['at']))->setTimezone($tz);
    if((int)$p['context']['fan_level']===3
        &&(int)$d->format('G')>=19)$p['c']=29.0;
}unset($p);
$r=JVHeliosLearningStageStudy::analyze($differentStart,$now);
ss($r['matched_weather_stage_pairs']===0,
    'Different starting indoor temperature prevents apparent fan-stage advantage');
$startNoBypass=$samples;
foreach($startNoBypass as &$p)$p['context']['fan_level']=1;
unset($p);
$r=JVHeliosLearningStageStudy::analyze($startNoBypass,$now);
ss($r['matched_weather_stage_pairs']===0
    &&$r['continuous_stage_proven']===false,
    'Research does not recommend or optimize the manufacturer hygiene minimum stage');
$winterBegin=(new DateTimeImmutable('2026-01-12 00:00:00',$tz))->getTimestamp();
$winterNow=(new DateTimeImmutable('2026-01-18 12:00:00',$tz))->getTimestamp();
$r=JVHeliosLearningStageStudy::analyze(buildStudy($winterBegin,$winterNow),$winterNow);
ss($r['status']==='AUSSERHALB_SOMMERSTUDIE',
    'Winter heat recovery remains outside the cooling stage experiment');
$recent=$samples;
foreach($recent as &$p)$p['at']-=$now-(new DateTimeImmutable(
    '2026-07-30 12:00:00',$tz))->getTimestamp();
unset($p);
// Simulated many alternative weather/dewpoint cases; none may activate.
for($i=0;$i<121;$i++){
    $case=$samples;
    foreach($case as &$p){
        $p['context']['outside_c']=12+$i/10;
        $p['context']['outside_dewpoint_c']=3+$i/9;
    }unset($p);
    $o=JVHeliosLearningStageStudy::analyze($case,$now);
    if($o['can_actuate']||$o['continuous_stage_proven']
        ||$o['causal_cooling_effect_proven']
        ||$o['suggested_fan_level']!==null
        ||$o['humidity_safety_proven']||$o['bypass_stability_proven']){
        fwrite(STDERR,"FAIL Stage safety scenario $i\n");exit(1);
    }
}
ss(true,'121 summer sensor and weather sensitivity scenarios remain strictly non-actuating');
$one=['name'=>'EG Wohnen','temperature'=>26.5,'rh'=>47,
    'temperature_observed_at'=>$now,'humidity_observed_at'=>$now];
$learn=JVHeliosLearningDiagnostics::update([
    'rooms'=>['EG Wohnen'=>['samples'=>$samples]]
],[$one],[
    'weather_quality'=>'UNAVAILABLE',
    'fan_level'=>3,'mode'=>0,'program'=>3
],$now);
ss($learn['observed_stage_study_pairs']>=2
    &&str_contains($learn['report'],'Stufen-Lernvergleich:')
    &&str_contains($learn['report'],'STUFEN_NUR_MOMENTAUFNAHMEN')
    &&str_contains($learn['report'],'Stufen-Messluecken (max. 3 Raeume):'),
    'Existing ChatGPT diagnosis explains preliminary stage observations and any missing evidence');
ss(!isset($learn['compact_state']['study'])
    &&!isset($learn['compact_state']['stage_study'])
    &&!isset($learn['compact_state']['stage_pairs'])
    &&strlen(json_encode($learn['compact_state']))<50000,
    'Added multi-stage analysis creates no new long-term keys or memory growth');
$wetRoom=JVHeliosLearningStageStudy::analyze($samples,$now,'EG Bad');
ss($wetRoom['status']==='KEIN_WOHN_ODER_SCHLAFRAUM'
    &&$wetRoom['matched_weather_stage_pairs']===0,
    'Bathroom cannot be counted as a living-room stage comparison even with synthetic stage contrasts');
$roomNames=[
    'EG Wohnen','OG Schlafen 1','OG Schlafen 2',
    'EG Büro','EG Küche','EG Schlafen',
    'EG Bad','OG Bad','EG Gäste WC','OG Flur','OG Ankleide/HWR',
];
$historical=[];$current=[];
foreach($roomNames as $name){
    $isEligible=in_array($name,['EG Wohnen','OG Schlafen 1','OG Schlafen 2'],true);
    $history=$isEligible?$samples:
        ($name==='EG Küche'?$wet:$all2);
    // Secondary rooms deliberately have otherwise good synthetic evidence:
    // only room-type gating is allowed to exclude their false positives.
    if(in_array($name,['EG Bad','OG Bad','EG Gäste WC','OG Flur','OG Ankleide/HWR'],true))
        $history=$samples;
    $historical[$name]=['samples'=>$history];
    $current[]=['name'=>$name,'temperature'=>26.5,'rh'=>48,
        'temperature_observed_at'=>$now,'humidity_observed_at'=>$now];
}
$whole=JVHeliosLearningDiagnostics::update(
    ['rooms'=>$historical],$current,[
        'weather_quality'=>'UNAVAILABLE','fan_level'=>3,'mode'=>0,'program'=>3
    ],$now);
ss($whole['stage_study_rooms']===3
    &&$whole['observed_stage_study_pairs']>=6,
    'Eleven-room rehearsal counts exactly three eligible living/bedrooms with naturally matched levels');
$gaps=implode(' ', $whole['stage_next_data_gaps']);
ss(!str_contains($gaps,'Bad')&&!str_contains($gaps,'Flur')
    &&!str_contains($gaps,'Ankleide')&&!str_contains($gaps,'WC'),
    'Missing-evidence prompts never ask for an experimental bathroom or hallway fan comparison');
$compact=$whole['compact_state'];
ss(strlen(json_encode($compact))<50000
    &&count($compact['r'])===11
    &&array_keys(array_intersect_key($compact,array_fill_keys(['v','t','d','r','m'],true)))===['v','t','d','r','m']
    &&count($compact['q']??[])<=11,
    'Eleven-room stage-quality learning fits compact v2 below 50KB with at most one bounded weekly field, no new Symcon attribute');
ss(!$whole['control_authorized']&&!$whole['learning_calibrated'],
    'Even whole-house paired evidence does not unlock automatic ventilation control');
echo "PASSIVE SUMMER FAN-STAGE STUDY TESTS PASSED\n";
