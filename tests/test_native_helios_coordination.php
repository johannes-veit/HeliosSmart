<?php
require_once __DIR__.'/../libs/NativeHeliosCoordination.php';
function okNative($ok,$label):void{
    if(!$ok){fwrite(STDERR,"FAIL NATIVE HELIOS: ".$label."\n");exit(1);}
}
$valid=[
    'notif_script_active'=>false,'notifscript_id'=>0,
    'notif_pushmsg_active'=>false,'webfrontinstance_id'=>0,
    'notif_mail_active'=>false,'smtpinstance_id'=>0,
    'timerinterval'=>61,'log_fanlevel'=>false,
];
$a=JVHeliosNativeCoordination::assess($valid,102,[
    'CO2Control'=>2,'HumidityControl'=>2,'VOCControl'=>2,
    'HumidityControlInternal'=>true,'SettingSensorControlSleepMode'=>true,
],4,true);
okNative($a['write_allowed']===true,'native healthy 102 can allow separately opted-in Party');
okNative($a['native_sensor_regulation_possible']===true,
    'CO2, VOC and humidity original controls can explain stage4 without weekly-only attribution');
okNative($a['native_poll_interval_seconds']===61,
    'Helios native update rhythm from the original backup retained');
okNative($a['custom_program4_hot_summer_selected_in_heating']===true,
    'hot-summer program 4 flagged during heating context');
okNative(!str_contains($a['status'],'KWL password'),
    'no unrelated credentials in sanitized status');
$broken=JVHeliosNativeCoordination::assess(array_replace($valid,[
    'notif_script_active'=>true,'notifscript_id'=>0,
]),209,['CO2Control'=>2],4,false);
okNative($broken['native_own_script_error']===true,
    'user screenshot 209 missing script matches vendor ConfigCheck');
okNative($broken['write_allowed']===false,
    'block HELIOS SMART fan write while original Helios module cannot initialize');
okNative(in_array('OWN_NOTIFICATION_SCRIPT_ENABLED_WITHOUT_SCRIPT',$broken['reasons'],true),
    'clear native script error diagnostic');
$missing=JVHeliosNativeCoordination::assess(null,102,[],3,false);
okNative($missing['write_allowed']===false,
    'missing original configuration never grants fan write');
$stale=JVHeliosNativeCoordination::assess($valid,null,[],3,false);
okNative($stale['write_allowed']===false,
    'unknown native status never grants fan write');
$push=JVHeliosNativeCoordination::assess(array_replace($valid,[
    'notif_pushmsg_active'=>true,'webfrontinstance_id'=>0,
]),102,[],3,false);
okNative($push['write_allowed']===false && $push['native_bad_push']===true,
    'native push without webfront blocks, just like vendor ConfigCheck');
$mail=JVHeliosNativeCoordination::assess(array_replace($valid,[
    'notif_mail_active'=>true,'smtpinstance_id'=>0,
]),102,[],3,false);
okNative($mail['write_allowed']===false && $mail['native_bad_mail']===true,
    'native SMTP without instance detected');
$heatingProgram3=JVHeliosNativeCoordination::assess($valid,102,[],3,true);
okNative($heatingProgram3['custom_program4_hot_summer_selected_in_heating']===false,
    'custom 1 not falsely labeled hot-summer program');
echo "Original Helios/Symcon integration, errors, seasonal profile and sensor regulator tests OK\n";
