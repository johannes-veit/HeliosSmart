<?php
/**
 * OPTIONAL MANUAL IP-SY MCON SCRIPT for archive export research.
 * NEVER called by HeliosSmart/module.php, no timers, no event registration,
 * no SetValue, no Archive logging change, no Helios actuator commands.
 *
 * REQUIRES the helper simulation/ArchiveChangeExport.php at relative path.
 * Place BOTH files as a research-only package before manual execution.
 *
 * Output is compressed JSON (gzip + base64) printed to script output only,
 * not saved to a variable, file, hook or remote endpoint.
 */
declare(strict_types=1);
require_once __DIR__.'/../simulation/ArchiveChangeExport.php';
require_once __DIR__.'/../../libs/LCNRoomSources.php';

$smartInstanceID=52187; // Existing HELIOS SMART instance: READ PROPERTIES ONLY
$lastHours=48;           // 24-72h research window; 48h default
$maxOutputBytes=1200000; // refuse oversized JSON instead of truncating silently

if(!function_exists('IPS_GetInstanceListByModuleID')
    ||!function_exists('IPS_InstanceExists')
    ||!function_exists('IPS_VariableExists')
    ||!function_exists('IPS_GetObject')
    ||!function_exists('IPS_GetProperty')
    ||!function_exists('AC_GetLoggedValues')
    ||!function_exists('AC_GetLoggingStatus')) {
    echo "NOT_IN_SYMCON_OR_MISSING_READ_APIS\n";return;
}
if(!IPS_InstanceExists($smartInstanceID)) {
    echo "HELIOS_SMART_INSTANCE_MISSING\n";return;
}
$archives=IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
if(count($archives)!==1) {
    echo 'EXPECTED_ONE_ARCHIVE_GOT_'.count($archives)."\n";return;
}
$archiveID=(int)$archives[0];
$end=intdiv(time(),3600)*3600;
$start=$end-max(24,min(72,$lastHours))*3600;
$config=json_decode((string)IPS_GetProperty($smartInstanceID,'Rooms'),true);
$roomDefs=JVHeliosLCNRoomSources::merge(is_array($config)?$config:[]);
$sources=[];
foreach($roomDefs as $room) {
    $name=(string)($room['name']??'');
    if($name===''||strlen($name)>90)continue;
    $id=(int)($room['temperature']??0);
    if($id>0)$sources['room.'.$name.'.temperature']=[
        'id'=>$id,'role'=>'ROOM_TEMPERATURE_C','source_type'=>'LCN_RAW'];
    $id=(int)($room['humidity']??0);
    if($id>0)$sources['room.'.$name.'.humidity']=[
        'id'=>$id,'role'=>'ROOM_RH_PCT','source_type'=>'LCN_RAW'];
}
// Resolve current INSTANCE SETTINGS, not hard-coded values that could have
// been changed by the operator. The helper rejects Link objects.
foreach([
    'HeliosFanVariable'=>'helios.fan_stage',
    'HeliosModeVariable'=>'helios.operating_mode',
    'HeliosProgramVariable'=>'helios.week_program',
    'HeliosSupplyTemperature'=>'helios.supply_after_exchanger_c',
    'HeliosIntakeAfterEWT'=>'helios.intake_after_ewt_c',
    'HousePowerVariable'=>'house.total_electric_w',
] as $property=>$name) {
    $id=(int)IPS_GetProperty($smartInstanceID,$property);
    $sources[$name]=['id'=>$id,'role'=>$name,'source_type'=>'NATIVE_RAW_VARIABLE'];
}
// No guessed historic outdoor weather: WetterZentrale snapshot is current,
// NOT an archive series. Add verified independent raw-weather variables only
// after explicit source-ID inspection, not by naming or searching links.
$sources['weather.actual_outside_temperature_c']=[
    'id'=>0,'role'=>'MUST_CONFIGURE_VERIFIED_WEATHER_ARCHIVE_ID',
    'source_type'=>'NOT_YET_RESOLVED'];
$sources['weather.actual_outside_dewpoint_c']=[
    'id'=>0,'role'=>'MUST_CONFIGURE_VERIFIED_DEWPOINT_ARCHIVE_ID',
    'source_type'=>'NOT_YET_RESOLVED'];

$export=JVHeliosArchiveChangeExport::build(
    $archiveID,$start,$end,$sources,
    static fn(int $arch,int $id,int $from,int $to,int $limit):array=>
        AC_GetLoggedValues($arch,$id,$from,$to,$limit),
    static fn(int $arch,int $id):bool=>(bool)AC_GetLoggingStatus($arch,$id),
    static fn(int $id):bool=>IPS_VariableExists($id),
    static fn(int $id):bool=>(int)(IPS_GetObject($id)['ObjectType']??-1)===6
);
$json=json_encode($export,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
if(!is_string($json) || strlen($json)>$maxOutputBytes) {
    echo "EXPORT_TOO_LARGE_OR_JSON_ERROR; reduce lastHours; nothing stored\n";return;
}
if(!function_exists('gzencode')) {
    echo "GZIP_NOT_AVAILABLE; no output stored\n";return;
}
$gz=gzencode($json,7);
if($gz===false){echo "GZIP_FAILED\n";return;}
echo "JVHS_ARCHIVE_GZIP_BASE64_V1\n".base64_encode($gz)."\n";
