<?php
require_once __DIR__.'/../libs/FanAttribution.php';
function stageCheck(bool $ok,string $msg):void {
    if(!$ok){fwrite(STDERR,"FAIL FAN SOURCE: ".$msg."\n");exit(1);}
}
$now=1800000000;
$base=[
    'mode'=>0,'fan'=>4,'program'=>4,
    'status_fresh'=>true,'fan_changed_at'=>$now-90,
    'control_enabled'=>false,'high_power_opt_in'=>false,
    'last_command_at'=>0,'owned_level'=>0,'owned_until'=>0,
    'command_attempted'=>false,
];
$check=static fn(array $override=[]) =>
    JVHeliosFanAttribution::inspect(array_replace($base,$override),$now);
$weekly=$check();
stageCheck($weekly['kind']==='DEVICE_AUTOMATIC_WEEKLY_OR_NATIVE',
    'automatic mode means manufacturer weekly OR native, not proven weekly slot');
stageCheck($weekly['actual_fan_level']===4 && $weekly['weekly_program']===4,
    'actual level and selected program retained');
stageCheck(str_contains($weekly['status'],'NUR BEOBACHTEN')
    && str_contains($weekly['status'],'keine eigene neue Stufen'),
    'monitor-only stage4 not falsely attributed to HeliosSmart boost');
stageCheck($weekly['fan_changed_minutes_ago']===1,'fan change age reported');
stageCheck($weekly['not_an_actuator_command'],'pure classifier cannot operate fan');
$untrusted=$check(['status_fresh'=>false]);
stageCheck($untrusted['kind']==='UNKNOWN',
    'stale passive mode variables cannot establish actual stage4 cause');
$man=$check(['mode'=>1]);
stageCheck($man['kind']==='MANUAL_ON_DEVICE',
    'manual HELIOS mode is not mislabelled as a weekly slot');
$party=$check(['mode'=>2]);
stageCheck($party['kind']==='PARTY_NOT_OWNED',
    'foreign or local party not misattributed to HeliosSmart');
$own=$check(['mode'=>2,'owned_level'=>4,'owned_until'=>$now+1200,
    'last_command_at'=>$now-60,'control_enabled'=>true,
    'high_power_opt_in'=>true]);
stageCheck($own['kind']==='OWNED_PARTY_PLAUSIBLE'
    && $own['own_party_active_plausible']===true,
    'matching own-party evidence only labeled plausible, not proven');
$expired=$check(['mode'=>2,'owned_level'=>4,'owned_until'=>$now-20,
    'last_command_at'=>$now-2400]);
stageCheck($expired['kind']==='PARTY_NOT_OWNED',
    'expired party cannot silently own a current stage4');
$quiet=$check(['mode'=>3,'fan'=>1,'program'=>3]);
stageCheck($quiet['kind']==='QUIET_MODE_NOT_OWNED','foreign whisper stays separate mode');
$ownedQuiet=$check(['mode'=>3,'fan'=>1,'owned_level'=>1,
    'owned_until'=>$now+300,'last_command_at'=>$now-50]);
stageCheck($ownedQuiet['kind']==='OWNED_WHISPER_PLAUSIBLE',
    'own stage1 timer is distinguished from foreign Whisper');
$autoOnly=$check(['mode'=>0,'fan'=>4,'owned_level'=>4,
    'owned_until'=>$now+300,'last_command_at'=>$now-50]);
stageCheck($autoOnly['own_party_active_plausible']===false,
    'old ownership record is never accepted as proof of Party in AUTO mode');
$vacation=$check(['mode'=>4,'fan'=>2]);
stageCheck($vacation['kind']==='HOLIDAY_MODE','vacation not weekly');
$issued=$check(['control_enabled'=>true,'command_attempted'=>true]);
stageCheck($issued['party_command_attempted_now']===true
    && str_contains($issued['status'],'Geraeterueckmeldung'),
    'unconfirmed just-attempted command shown as pending rather than proven');
echo "Helios fan stage4 origin and watch-only policy tests OK\n";
