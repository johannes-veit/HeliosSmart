<?php
require_once __DIR__.'/../libs/CookingAdvisor.php';
require_once __DIR__.'/../libs/BoostCoordinator.php';
function ev(bool $ok,string $label):void {if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";}
$start=strtotime('2026-10-25T17:00:00Z'); // 18:00 Europe/Berlin (winter time)
$fixture=[
 'light'=>['usable'=>true,'on'=>false,'on_minutes'=>0],
 'motions'=>['Wohnkueche'=>['recent'=>false],'EG Flur'=>['recent'=>false]],
 'power'=>['usable'=>true,'watts'=>1200,'sample_updated_at'=>$start-360],
 'power_threshold_w'=>2000,'current_fan_level'=>2,'helios_automatic_mode'=>true,
 'residents'=>['Carina'=>['status'=>'ZUHAUSE','source_recent'=>true]]
];
$idle=JVHeliosCookingAdvisor::analyze($fixture,$start-300);
ev($idle['state']['baseline_power_w']===1200.0,'Idle baseline learned away from kitchen');
$busy=$fixture;$busy['light']=['usable'=>true,'on'=>true,'on_minutes'=>9];
$busy['motions']['Wohnkueche']['recent']=true;
$busy['power']=['usable'=>true,'watts'=>1670,'sample_updated_at'=>$start+8*60];
$one=JVHeliosCookingAdvisor::analyze($busy,$start+9*60,$idle['state']);
ev($one['advice']['classification']!=='COOKING_ACTIVITY_LIKELY',
 'One moderate source sample not sufficient');
$repeat=JVHeliosCookingAdvisor::analyze($busy,$start+11*60,$one['state']);
ev($repeat['advice']['classification']!=='COOKING_ACTIVITY_LIKELY',
 'Repeated poll of unchanged Tasmota timestamp is not evidence');
$busy['light']['on_minutes']=17;
$busy['power']=['usable'=>true,'watts'=>1720,'sample_updated_at'=>$start+16*60];
$two=JVHeliosCookingAdvisor::analyze($busy,$start+17*60,$repeat['state']);
ev($two['advice']['classification']==='COOKING_ACTIVITY_LIKELY',
 'One-hour real cooking with moderate cyclic stove load is detected within 17 min');
ev($two['advice']['power_moderate_samples']>=2,'Two genuine moderate input messages');
ev($two['advice']['power_baseline_w']===1200.,'Cooking did not train baseline upward');
$proposal=JVHeliosBoostCoordinator::propose(
 ['mode'=>'WEEKLY_BASE','wetrooms'=>[]],$two['advice'],2,true);
ev($proposal['source']==='COOKING' && $proposal['suggested_level']===3
  && $proposal['suggested_minutes']===15,'Moderate proof only requests S3/15, never S4');
$alone=$busy;$alone['light']['on']=false;$alone['light']['on_minutes']=0;
$oneAlone=JVHeliosCookingAdvisor::analyze($alone,$start+17*60,$one['state']);
ev($oneAlone['advice']['classification']!=='COOKING_ACTIVITY_LIKELY',
 'Power without extended kitchen-light context cannot trigger moderate cooking');
$old=$busy;$old['power']['usable']=false;
ev(JVHeliosCookingAdvisor::analyze($old,$start+17*60,$one['state'])['advice']['potential_odor_boost']===false,
 'Stale power update cannot trigger moderate cooking');
$cycle=$fixture;$cycle['power']['watts']=850;
$baseline=JVHeliosCookingAdvisor::analyze($cycle,$start-300)['state'];
$cycle['light']=['usable'=>true,'on'=>true,'on_minutes'=>3];
$cycle['motions']['Wohnkueche']['recent']=true;
$cycle['power']=['usable'=>true,'watts'=>2400,'sample_updated_at'=>$start+2*60];
$first=JVHeliosCookingAdvisor::analyze($cycle,$start+3*60,$baseline);
$cycle['power']=['usable'=>true,'watts'=>1050,'sample_updated_at'=>$start+5*60];
$trough=JVHeliosCookingAdvisor::analyze($cycle,$start+5*60,$first['state']);
ev($trough['state']['last_high_sample_at']>0,'Induction low-cycle preserves earlier independent high sample');
$cycle['power']=['usable'=>true,'watts'=>2450,'sample_updated_at'=>$start+9*60];
$second=JVHeliosCookingAdvisor::analyze($cycle,$start+10*60,$trough['state']);
ev($second['advice']['classification']==='COOKING_LIKELY',
 'Two genuine stove high peaks across low trough now trigger cooking');
echo "COOKING HOUR SIMULATION PASSED\n";
