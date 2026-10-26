<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function oh(bool $cond,string $why):void {
    if(!$cond) {fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
$now=strtotime('2026-07-15 12:00:00 UTC');
$history=[];
for($h=72;$h>=0;$h-=2) {
    $at=$now-$h*3600;
    $history[]=['at'=>$at,'c'=>($h<=48?26.6:24.2)];
}
$r=JVHeliosLearningOverheat::recent('EG Wohnen',$history,$now);
oh($r['status']==='WIEDERHOLT_UEBER_REFERENZ'
 &&$r['warm_points_72h']>=20 &&$r['warm_days']>=2,
 'Many original warm readings on multiple July days establish repeated summer warmth');
oh(!$r['can_actuate']&&!$r['is_forecast']&&!$r['fan_effect_proven'],
 'Warm room observations never imply command authority, prediction or Helios cooling effect');
$bedroom=array_map(static fn($x)=>['at'=>$x['at'],'c'=>25.3],$history);
oh(JVHeliosLearningOverheat::recent('OG Schlafen 1',$bedroom,$now)['status']
 ==='WIEDERHOLT_UEBER_REFERENZ',
 'Bedroom uses separate indicative 25C threshold');
$cold=array_map(static fn($x)=>['at'=>$x['at'],'c'=>25.3],$history);
oh(JVHeliosLearningOverheat::recent('EG Wohnen',$cold,$now)['status']
 ==='KEINE_WIEDERHOLTE_UEBERWAERMUNG_BELEGT',
 'Same original 25.3C room temperatures do not imply warm living room');
$oneSpike=$cold;$oneSpike[8]['c']=29.0;
oh(JVHeliosLearningOverheat::recent('EG Wohnen',$oneSpike,$now)['status']
 ==='KEINE_WIEDERHOLTE_UEBERWAERMUNG_BELEGT',
 'One isolated 29C original sample is not repeated house overheating');
$early=$cold;
for($j=count($early)-3;$j<count($early);$j++)$early[$j]['c']=26.5;
oh(JVHeliosLearningOverheat::recent('EG Wohnen',$early,$now)['status']
 ==='MEHRERE_AKTUELLE_WARME_MESSWERTE',
 'Three consecutive genuine warm 2h points produce cautious early signal');
$gap=array_values(array_filter($history,static function($row)use($now) {
    return $row['at']<$now-30*3600 ||$row['at']>$now-12*3600;
}));
oh(JVHeliosLearningOverheat::recent('EG Wohnen',$gap,$now)['status']
 ==='VERLAUF_MIT_MESSLUECKEN',
 'Larger sensor gap is reported instead of pretending continuous warmth');
$old=array_values(array_filter($history,
    static fn($row)=>$row['at']<$now-5*3600));
oh(JVHeliosLearningOverheat::recent('EG Wohnen',$old,$now)['status']
 ==='LETZTER_WERT_ZU_ALT',
 'No recent source update cannot be turned into a current overheating signal');
$winterTime=strtotime('2026-01-15 12:00:00 UTC');
$shifted=array_map(static fn($x)=>['at'=>$x['at']-($now-$winterTime),
 'c'=>$x['c']],$history);
oh(JVHeliosLearningOverheat::recent('EG Wohnen',$shifted,$winterTime)['status']
 ==='AUSSERHALB_SOMMERBEOBACHTUNG',
 'Winter heat from FBH is not mislabeled summer overheating');
oh(JVHeliosLearningOverheat::recent('OG Bad',$history,$now)['status']
 ==='KEIN_WOHN_ODER_SCHLAFRAUM',
 'Baths and hallways excluded from comparative overheating count');
oh(JVHeliosLearningOverheat::recent('EG Wohnen',[],$now)['status']
 ==='NICHT_GENUG_ORIGINALWERTE',
 'Missing original samples produce unknown, not comfort clearance');
$summer=[
 6=>[24,24*270,265,275,0,0,0],
 7=>[24,24*272,268,280,0,0,0],
 8=>[24,24*245,240,250,0,0,0],
];
$view=JVHeliosLearningOverheat::summerProfile('EG Wohnen',$summer);
oh($view['status']==='MEHRERE_SOMMERMONATE_HOHES_MITTEL'
 &&$view['months_with_high_means']===2,
 'Two adequately observed summer months with high mean imply historical sustained warmth');
oh(!$view['has_year_specific_history']&&!$view['can_actuate'],
 'Multi-year month means cannot infer frequency by calendar year or directly control fan');
$single=$summer;unset($single[7]);
oh(JVHeliosLearningOverheat::summerProfile('EG Wohnen',$single)['status']
 ==='KEIN_MEHRMONATIG_HOHES_MITTEL_BELEGT',
 'One extreme summer month is insufficient as a repeated monthly pattern');
$spike=[6=>[12,12*246,230,350,0,0,0],
 7=>[12,12*243,230,350,0,0,0]];
oh(JVHeliosLearningOverheat::summerProfile('EG Wohnen',$spike)['status']
 ==='KEIN_MEHRMONATIG_HOHES_MITTEL_BELEGT',
 'Two months with hot single maxima but low means are not repeatedly overheated');
$corrupt=$summer;$corrupt[7]=[24,24*400,265,275,0,0,0];
oh(JVHeliosLearningOverheat::summerProfile('EG Wohnen',$corrupt)['status']
 ==='KEIN_MEHRMONATIG_HOHES_MITTEL_BELEGT',
 'Physically inconsistent monthly sums cannot create false heat evidence');
$empty=JVHeliosLearningOverheat::summerProfile('EG Wohnen',[]);
oh($empty['status']==='NICHT_AUSWERTBAR',
 'No historical summer evidence is not interpreted as a cool room');
$room=['name'=>'EG Wohnen','temperature'=>26.6,'rh'=>49,
 'temperature_observed_at'=>$now-60,'humidity_observed_at'=>$now-80];
$snapshot=['weather_quality'=>'UNAVAILABLE',
 'fan_level'=>2,'mode'=>0,'program'=>3];
$prior=['rooms'=>['EG Wohnen'=>['samples'=>$history]]];
$report=JVHeliosLearningDiagnostics::update($prior,[$room],$snapshot,$now);
oh($report['recent_overheating_rooms']===1
    &&str_contains($report['report'],'Sommer-Ueberwaermung')
    &&str_contains($report['report'],'WIEDERHOLT_UEBER_REFERENZ'),
 'Existing single ChatGPT diagnostic includes room overheat history without extra variables');
oh(!$report['control_authorized'] &&!$report['learning_calibrated'],
 'Overheat advice stays observational, not a learned thermal model');
echo "PASSIVE OVERHEATING RESEARCH TESTS PASSED\n";
