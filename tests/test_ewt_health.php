<?php
require_once __DIR__.'/../libs/EWTPumpHealth.php';
function hcheck(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAIL SHELLY HEALTH: ".$message."\n");exit(1);}
}
$now=1800000000;
$base=[
 'now'=>$now,'online'=>true,'online_updated_at'=>$now-30,
 'online_changed_at'=>$now-1000,
 'heartbeat_updated_at'=>$now-30,'switch'=>false,
 'switch_updated_at'=>$now-15,
 'power_w'=>0.0,'power_updated_at'=>$now-15,
];
$h=static fn(array $a):array=>JVHeliosEWTPumpHealth::assess(array_replace($base,$a));
$x=$h([]);
hcheck($x['link']==='ONLINE_FRESH','recent online LWT + heartbeat');
hcheck($x['reported']==='OFF_REPORTED' && $x['confirmed_off_reported'],'online and fresh OFF reported');
hcheck($x['state']==='OFF_REPORTED_ZERO_POWER','online off plus zero power stronger evidence');
hcheck($x['actuation_allowed']===true,'healthy Shelly permits commands');
hcheck($h(['online'=>false])['state']==='UNKNOWN',
 'offline cached OFF never confirmed');
hcheck($h(['online'=>false])['actuation_allowed']===false,
 'offline commands suppressed');
hcheck($h(['online'=>null])['link']==='NOT_CONFIGURED',
 'missing online variable is not ONLINE');
hcheck($h(['online_updated_at'=>$now-3600,'heartbeat_updated_at'=>$now-3600])['state']==='UNKNOWN',
 'online true but no fresh heartbeat is uncertain');
hcheck($h(['online_updated_at'=>$now-3600])['link']==='ONLINE_FRESH',
 'periodic heartbeat can validate a long-lived MQTT online session');
hcheck($h(['switch_updated_at'=>$now-1800])['reported']==='UNKNOWN',
 'switch status older than 15min cannot certify OFF');
hcheck($h(['online_changed_at'=>$now-5,'switch_updated_at'=>$now-30])['reported']==='UNKNOWN',
 'retained switch OFF before reconnect is untrusted');
hcheck($h(['switch'=>true,'switch_updated_at'=>$now-30])['reported']==='ON_REPORTED',
 'online live ON is valid reported status');
hcheck($h(['power_w'=>120])['power_conflict']===true,
 'output OFF with high current power is inconsistent');
hcheck($h(['power_w'=>120])['actuation_allowed']===false,
 'power conflict disables new commands');
hcheck(JVHeliosEWTPumpHealth::ack($x,false,$now-200)===true,
 'new OFF report acknowledges OFF command');
hcheck(JVHeliosEWTPumpHealth::ack($x,false,$now-10)===false,
 'previous OFF report cannot acknowledge new OFF command');
hcheck(JVHeliosEWTPumpHealth::ack($h(['online'=>false]),false,$now-200)===false,
 'offline cannot acknowledge action');
echo "Shelly online, output freshness and power consistency tests OK\n";
