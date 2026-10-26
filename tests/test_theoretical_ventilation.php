<?php
/** These are HYPOTHETICAL study parameters, NOT fan measurements. */
require_once __DIR__.'/simulation/ThermalVentilation.php';
function vt(bool $condition,string $message):void {
    if (!$condition) {fwrite(STDERR,"FAIL ".$message."\n");exit(1);}
    echo "PASS $message\n";
}
$stages=[
 1=>['flow_m3h'=>70,'fan_w'=>9,'supply_c'=>21.0,'basis'=>'ASSUMED'],
 2=>['flow_m3h'=>125,'fan_w'=>21,'supply_c'=>21.0,'basis'=>'ASSUMED'],
 3=>['flow_m3h'=>200,'fan_w'=>49,'supply_c'=>21.0,'basis'=>'ASSUMED'],
 4=>['flow_m3h'=>300,'fan_w'=>108,'supply_c'=>21.0,'basis'=>'ASSUMED'],
];
$climate=['weather_verified'=>true,'season'=>'SUMMER',
 'fresh_living_rooms'=>6,'fresh_humidity_rooms'=>9,
 'living_temp_c'=>26,'living_dewpoint_c'=>15,'outdoor_dewpoint_c'=>10,
 'coolest_room_c'=>23.5,'coolest_room_floor_c'=>20.5];
$r=JVHeliosThermalVentilation::evaluate($climate,$stages,180);
vt($r['status']==='SCENARIO_READY_NOT_VALIDATED','Offline model computes but cannot claim validation');
vt($r['recommended_stage']===2,'Example cooling target achieved at hypothetical S2, not wasteful S4');
vt($r['offline_only'] && !$r['hardware_authorized'],'No hardware controls or permissions');
vt($r['stage_results'][4]['input_is_hypothetical'],'Assumed airflow is visibly marked hypothetical');
vt(JVHeliosThermalVentilation::evaluate($climate,$stages,300)['recommended_stage']===3,
 'Larger target can be met by hypothetical S3 without S4');
vt(JVHeliosThermalVentilation::evaluate($climate,$stages,450)['recommended_stage']===4,
 'Very high target might require short S4 in a studied scenario');
vt(JVHeliosThermalVentilation::evaluate($climate,$stages,800)['recommended_stage']===null,
 'Impossible target is not converted into unconditional S4');
vt(JVHeliosThermalVentilation::evaluate($climate,$stages,null)['recommended_stage']===null,
 'No cooling target means compare only, no automatic fan recommendation');
$wet=$climate;$wet['outdoor_dewpoint_c']=18;
vt(JVHeliosThermalVentilation::evaluate($wet,$stages,200)['status']==='MOISTURE_UNSUITABLE',
 'Muggy exterior prohibits pure sensible-cooling strategy');
$cold=$climate;$cold['coolest_room_c']=20.2;
vt(JVHeliosThermalVentilation::evaluate($cold,$stages,200)['status']==='ROOM_OR_DURATION_GUARD',
 'Cold living room vetoes additional whole-house cooling');
$bad=$climate;$bad['weather_verified']=false;
vt(JVHeliosThermalVentilation::evaluate($bad,$stages,200)['status']==='INSUFFICIENT_INPUT',
 'Missing measured weather cannot be masked by theoretical energy model');
$insufficient=[1=>$stages[1],2=>['flow_m3h'=>null,'fan_w'=>5,'supply_c'=>15]];
vt(JVHeliosThermalVentilation::evaluate($climate,$insufficient,180)['status']==='INSUFFICIENT_INPUT',
 'Without at least two credible fan-stage study profiles do not compare');
$count=0;
foreach ([23.5,24,25,26,27,28,29,30] as $t)
 foreach ([16,18,20,22,24,26,28] as $supply)
  foreach ([90,180,270,360,450,800] as $target)
   foreach ([9,17] as $outDp) {
    $d=$climate;$d['living_temp_c']=$t;
    $d['outdoor_dewpoint_c']=$outDp;
    $options=$stages;
    foreach ($options as &$v) $v['supply_c']=$supply;
    unset($v);
    $estimate=JVHeliosThermalVentilation::evaluate($d,$options,$target);
    if ($estimate['hardware_authorized'] || !$estimate['offline_only']) {
        fwrite(STDERR,"FAIL hardware safety invariant\n");exit(1);
    }
    if ($outDp===17 && $estimate['recommended_stage']!==null) {
        fwrite(STDERR,"FAIL humid scenario suggested stage\n");exit(1);
    }
    if ($estimate['recommended_stage']!==null) {
        $index=$estimate['recommended_stage'];
        if ($estimate['stage_results'][$index]['sensible_cooling_w']+0.0001<$target) {
            fwrite(STDERR,"FAIL target not reached\n");exit(1);
        }
        foreach ($estimate['stage_results'] as $other=>$details) {
            if ($details['sensible_cooling_w']>=$target
              && $details['fan_electricity_wh']<$estimate['stage_results'][$index]['fan_electricity_wh']-0.0001) {
                fwrite(STDERR,"FAIL non-optimal electricity stage\n");exit(1);
            }
        }
    }
    $count++;
   }
vt($count===672,'672 combinations passed: supply, temperature, target and moisture risk');
echo "THEORETICAL NON-ACTUATING VENTILATION SIMULATION PASSED\n";
