<?php
/**
 * Limited active SMART BASE regulation; preserves native automatic VOC/CO2/RH
 * and manufacturer anti-icing. The stored winter programme 3 is a *known
 * operator designation*, not an assumed fan-level table.
 */
final class JVHeliosSeasonProgramControl
{
    public static function decide(array $x,int $now):array
    {
        $r=['action'=>'NONE','target_program'=>null,'reason'=>'NO_AUTOMATIC_CHANGE',
            'may_send'=>false,'may_override_sensors'=>false,
            'leaves_helios_in_automatic'=>true];
        if(($x['enabled']??false)!==true) {
            $r['reason']='SMART_PROFILE_CONTROL_DISABLED';return $r;
        }
        if(($x['native_healthy']??false)!==true
            || ($x['mode']??null)!==0
            || !is_int($x['program']??null)
            || ($x['fresh_live']??false)!==true){
            $r['reason']='NATIVE_DEVICE_NOT_VERIFIED_IN_AUTO';return $r;
        }
        if(($x['weather_quality']??'')!=='VERIFIED'
            && ($x['weather_quality']??'')!=='DWD_ONLY'){
            $r['reason']='OUTDOOR_WEATHER_UNTRUSTED';return $r;
        }
        $outside=$x['outside_temperature']??null;
        $age=(int)($x['outside_age_seconds']??100000);
        if(!is_numeric($outside) || !is_finite((float)$outside)
            || $age<0||$age>900){
            $r['reason']='OUTSIDE_NOT_RECENT';return $r;
        }
        // The operator designated custom program 4 as hot summer, 3 as the
        // normal cool-season alternative. Do not change an unrelated 0/1/2/5.
        if($x['program']!==4){
            $r['reason']='NOT_KNOWN_HOT_SUMMER_PROGRAM';return $r;
        }
        if((float)$outside>8.0){
            $r['reason']='NOT_COLD_ENOUGH_FOR_IMMEDIATE_PROFILE_CHANGE';return $r;
        }
        if(($x['season_signal']??'')!=='HEATING_SEASON'
            && ($x['season_signal']??'')!=='POSSIBLE_HEATING'){
            $r['reason']='HEATING_SEASON_NOT_SUPPORTED';return $r;
        }
        $last=(int)($x['last_attempt_at']??0);
        if($last>$now+120 || ($last>0 && $now-$last<24*3600)){
            $r['reason']='24_HOUR_COMMAND_COOLDOWN';return $r;
        }
        $r['target_program']=3;
        $r['action']='SELECT_NORMAL_WINTER_WEEKLY_PROGRAM';
        $r['may_send']=true;
        $r['reason']='HOT_SUMMER_PROGRAM_4_WHILE_HEATING_WEATHER';
        return $r;
    }
}
