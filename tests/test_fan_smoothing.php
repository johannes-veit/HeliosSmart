<?php
require_once __DIR__.'/../libs/FanSmoothing.php';
function assertSmooth(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAIL FAN SMOOTHING: ".$message."\n");exit(1);}
}
$now=1793850000;
$owned=['mode'=>2,'stage'=>2,'sent_at'=>$now-300,'until'=>$now+600];
$device=['mode'=>2,'fan'=>2];
$base=['execute'=>true,'target_stage'=>1,'reason'=>'SHORT_LOW_DEMAND_QUIET',
    'route'=>'WHISPER','minutes'=>15,'fallback_weekly'=>false];
$autoStart=JVHeliosFanSmoothing::decide($base,
    ['mode'=>0,'fan'=>3],[],[],$now);
assertSmooth($autoStart['plan']['target_stage']===2
    && $autoStart['status']==='ESTABLISH_S2_BEFORE_QUIET',
    'AUTO S3 goes to stable S2 before a later S1 night interval');
$autoStage2=JVHeliosFanSmoothing::decide($base,
    ['mode'=>0,'fan'=>2],[],[],$now);
assertSmooth($autoStage2['plan']['target_stage']===2
    && $autoStage2['state']['pending_stage']===1,
    'AUTO S2 is not immediately forced to S1 at night');
$r=JVHeliosFanSmoothing::decide($base,$device,$owned,[],$now);
assertSmooth($r['plan']['target_stage']===2
    && $r['status']==='HOLD_CURRENT_STAGE_UNTIL_STABLE'
    && $r['remaining_seconds']===20*60,
    'low-demand S1 only after 20 minutes continuous evidence, holding owned S2');
$r2=JVHeliosFanSmoothing::decide($base,$device,$owned,$r['state'],$now+19*60);
assertSmooth($r2['plan']['target_stage']===2 && $r2['remaining_seconds']===60,
    'still on S2 one minute before quiet confirmation');
$r3=JVHeliosFanSmoothing::decide($base,$device,$owned,$r2['state'],$now+20*60);
assertSmooth($r3['plan']['target_stage']===1 && $r3['status']==='UNOWNED_OR_AUTO_NO_DELAY',
    'expired ownership must not authorize a previously queued S1');
$ownedLate=['mode'=>2,'stage'=>2,'sent_at'=>$now+15*60,'until'=>$now+30*60];
$r4=JVHeliosFanSmoothing::decide($base,$device,$ownedLate,$r2['state'],$now+20*60);
assertSmooth($r4['plan']['target_stage']===1
    && $r4['status']==='STABLE_TARGET_CONFIRMED',
    'continuously proven quiet S1 can start after timely own S2 renewal');
$change=['execute'=>true,'target_stage'=>3,'reason'=>'SAFE_PASSIVE_COOLING',
    'minutes'=>15,'route'=>'PARTY','fallback_weekly'=>false];
$a=JVHeliosFanSmoothing::decide($change,$device,$owned,[],$now);
$b=JVHeliosFanSmoothing::decide($change,$device,$ownedLate,$a['state'],$now+10*60);
assertSmooth($a['plan']['target_stage']===2 && $b['plan']['target_stage']===3,
    'discretionary cooling requires 10 stable minutes before S2 to S3 change');
$urgent=array_replace($change,['reason'=>'WETROOM_AIR_NEED']);
$e=JVHeliosFanSmoothing::decide($urgent,$device,$owned,[],$now);
assertSmooth($e['plan']['target_stage']===3
    && $e['status']==='URGENT_NEED_IMMEDIATE',
    'confirmed wetroom boost is never delayed');
$urgent4=array_replace($change,['target_stage'=>4,
    'reason'=>'CONFIRMED_WETROOM_S4_BOOST']);
$e4=JVHeliosFanSmoothing::decide($urgent4,$device,$owned,[],$now);
assertSmooth($e4['plan']['target_stage']===4,
    'strong independently verified S4 demand is never delayed');
$owned3=['mode'=>2,'stage'=>3,'sent_at'=>$now-120,'until'=>$now+780];
$device3=['mode'=>2,'fan'=>3];
$down=array_replace($base,['target_stage'=>2,
    'reason'=>'ENERGY_EFFICIENT_BASE','route'=>'PARTY']);
$d1=JVHeliosFanSmoothing::decide($down,$device3,$owned3,[],$now);
$owned3New=['mode'=>2,'stage'=>3,'sent_at'=>$now+10*60,'until'=>$now+25*60];
$d2=JVHeliosFanSmoothing::decide($down,$device3,$owned3New,$d1['state'],$now+20*60);
assertSmooth($d1['plan']['target_stage']===3
    && $d2['plan']['target_stage']===2,
    'S3 to S2 energy step requires 20 min stable lack of extra demand');
$disabled=array_replace($down,['execute'=>false,'reason'=>'NATIVE_SENSOR_READINGS_STALE_KEEP_OWN_DEMAND']);
$stop=JVHeliosFanSmoothing::decide($disabled,$device3,$owned3,[],$now);
assertSmooth(!$stop['plan']['execute']
    && $stop['status']==='SAFETY_OR_FOREIGN_DEVICE_PRIORITY',
    'invalid native sensors immediately return manufacturer authority');
$foreign=JVHeliosFanSmoothing::decide($base,
    ['mode'=>3,'fan'=>1],[],[],$now);
assertSmooth($foreign['plan']===$base
    && $foreign['status']==='UNOWNED_OR_AUTO_NO_DELAY',
    'foreign Whisper is never hijacked or labelled owned');
$wake=JVHeliosFanSmoothing::decide($down,
    ['mode'=>3,'fan'=>1],
    ['mode'=>3,'stage'=>1,'sent_at'=>$now-600,'until'=>$now+300],[],$now);
assertSmooth($wake['plan']['target_stage']===2
    && $wake['status']==='S1_RECOVERY_IMMEDIATE',
    'must return from limited S1 without delaying hygiene minimum');
$recover=JVHeliosFanSmoothing::decide($down,
    ['mode'=>0,'fan'=>4],[],[],$now);
assertSmooth($recover['plan']['target_stage']===2,
    'no delay rescuing needless manufacturer AUTO/S4 with verified normal sensors');
echo "Fan smoothing, urgent bypass, night stage protection and no foreign takeover OK\n";
