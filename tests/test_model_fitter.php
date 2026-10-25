<?php
require_once __DIR__.'/../simulation/ModelFitter.php';
function mf(bool $ok,string $msg):void {
    if (!$ok) {fwrite(STDERR,"FAIL $msg\n");exit(1);}
    echo "PASS $msg\n";
}
$start=strtotime('2026-07-15 00:00:00 UTC');
$scenario=['input_basis'=>'ASSUMED_SYSTEM_PARAMETERS',
 'no_hardware_output'=>true,
 'flow_m3h'=>125.0,'envelope_w_per_k'=>95.0,
 'capacity_min_wh_per_k'=>3500.0,'capacity_max_wh_per_k'=>85000.0];
$trueCapacity=14000.;
$samples=[];$temp=25.0;
for ($i=0;$i<144;$i++) {
    $at=$start+$i*3600;
    $local=(int)(new DateTimeImmutable('@'.$at))
       ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
    $day=$local>=9 && $local<20;
    $outside=$day?34.+($i%3)*0.4:18.5+($i%4)*0.3;
    $supply=$day?28.5:20.0;
    $solar=$day?900.0:0.0;
    $gains=300.0;
    $samples[]=['at'=>$at,'room_c'=>$temp,
        'outside_c'=>$outside,'supply_c'=>$supply,
        'solar_w'=>$solar,'internal_w'=>$gains];
    $next=JVHeliosBuildingResponse::step($temp,$outside,$supply,125.,95.,
        $solar+$gains,$trueCapacity,1.0);
    if ($next===null) {fwrite(STDERR,"FAIL synthetic step\n");exit(1);}
    $temp=$next;
}
$r=JVHeliosModelFitter::estimate($samples,$scenario);
mf($r['status']==='SCENARIO_FITTED_NOT_VALIDATED',
 'Simulated 144-hour building history has held-out fit');
mf($r['conditional_on_assumed_gains_ua_airflow'] && !$r['house_parameters_physically_validated'],
 'Conditional scenario fit is never claimed as measured home heat capacity');
mf($r['offline_only'] && !$r['hardware_authorized'],
 'No path from fitted building model to real actuator');
mf(abs($r['suggested_capacity_wh_per_k']-$trueCapacity)/$trueCapacity<0.12,
 'Given exact known simulated gains, learned effective capacity agrees within 12pct');
mf($r['holdout_model_mae_c']<$r['holdout_persistence_mae_c'],
 'Chronological held-out simulation beats naive last-value persistence');
mf($r['holdout_hours']>=24,'True chronological holdout not shuffled training');
$bad=$samples;array_splice($bad,17,1);
mf(JVHeliosModelFitter::estimate($bad,$scenario)['status']==='BAD_OR_GAPPED_HISTORY',
 'Missing sensor hour blocks arbitrary inertia fitting');
$bad=$samples;$bad[26]['room_c']=null;
mf(JVHeliosModelFitter::estimate($bad,$scenario)['status']==='BAD_OR_GAPPED_HISTORY',
 'Missing temperature is not silently interpolated');
$bad=$samples;foreach ($bad as &$x) {
    $x['room_c']=24.;
    $x['outside_c']=24.;
    $x['supply_c']=24.;
    $x['solar_w']=0.;
    $x['internal_w']=0.;
} unset($x);
mf(JVHeliosModelFitter::estimate($bad,$scenario)['status']==='TOO_LITTLE_IDENTIFYING_VARIATION',
 'No thermal excitation means model parameters are unidentifiable');
$invalid=$scenario;$invalid['no_hardware_output']=false;
mf(JVHeliosModelFitter::estimate($samples,$invalid)['status']==='INSUFFICIENT_OR_NON_SCENARIO_DATA',
 'Model fitting explicitly refuses operational hardware mode');
$invalid=$scenario;$invalid['flow_m3h']=null;
mf(JVHeliosModelFitter::estimate($samples,$invalid)['status']==='INVALID_ASSUMPTIONS',
 'Unknown stage flow is not replaced by a fabricated value');
mf(JVHeliosModelFitter::estimate(array_slice($samples,0,48),$scenario)['status']==='INSUFFICIENT_OR_NON_SCENARIO_DATA',
 'Sparse less-than-72h archive not sufficient to learn inertia');
echo "OFFLINE BUILDING INERTIA FITTER PASSED\n";
