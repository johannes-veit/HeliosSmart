<?php
/**
 * Explicit cross-module cooling evidence gate. A theoretical cool supply
 * MUST NOT become an actuator trigger without comfort, moisture and season
 * confirmation. This is a source-quality and sensor-consistency gate,
 * not a separate actuator or override of manufacturer frost protection.
 */
final class JVHeliosSmartCoolingGate
{
    public static function decide(array $d):array
    {
        $r=['cooling_confirmed'=>false,'reason'=>'COOLING_MODE_NOT_REQUESTED',
            'supply_gap_k'=>null,'moisture_confirmed'=>false,
            'comfort_confirmed'=>false,'room_cooling_floor_safe'=>false];
        if(($d['mode']??'')!=='PASSIVE_COOLING')return $r;
        if(($d['weather_quality']??'')!=='VERIFIED'
            ||($d['trusted_weather']??false)!==true){
            $r['reason']='WEATHER_NOT_VERIFIED';
            return $r;
        }
        if(in_array(($d['season_signal']??'UNKNOWN'),
            ['HEATING_SEASON','POSSIBLE_HEATING'],true)){
            $r['reason']='HEATING_CONDITIONS_PRIORITY';
            return $r;
        }
        $comfort=(array)($d['comfort_control']??[]);
        $summer=(array)($d['summer_airflow']??[]);
        $r['comfort_confirmed']=($comfort['status']??'')==='READY'
            && ($comfort['weather_boost_compatible']??false)===true;
        $r['room_cooling_floor_safe']=($comfort['night_precooling_safe']??false)===true;
        $moisture=(array)($summer['moisture_comfort']??[]);
        $r['moisture_confirmed']=($moisture['status']??'')==='READY'
            &&($moisture['weather_cooling_boost_allowed']??false)===true
            &&($moisture['humidity_import_risk']??true)===false;
        if(!$r['comfort_confirmed'] || !$r['room_cooling_floor_safe']){
            $r['reason']='ROOM_COMFORT_OR_COOLING_FLOOR_VETO';return $r;
        }
        if(!$r['moisture_confirmed']){
            $r['reason']='MOISTURE_IMPORT_OR_UNVERIFIED';return $r;
        }
        if(!in_array($summer['regime']??'',[
            'MEASURED_COOLING_OPPORTUNITY','HEATWAVE_PRECOOL_WINDOW'],true)
            || (int)($summer['recommended_stage']??0)<3){
            $r['reason']='SUMMER_STAGE_3_OR_HIGHER_NOT_JUSTIFIED';return $r;
        }
        $supply=$d['supply_temperature']??null;
        $indoor=$d['indoor_temperature']??null;
        if(!is_numeric($supply)||!is_numeric($indoor)
            ||!is_finite((float)$supply)||!is_finite((float)$indoor)){
            $r['reason']='ACTUAL_COOL_SUPPLY_UNVERIFIED';return $r;
        }
        $gap=(float)$indoor-(float)$supply;
        $r['supply_gap_k']=round($gap,2);
        if($gap<2.2){
            $r['reason']='SUPPLY_NOT_COOL_ENOUGH';return $r;
        }
        $r['cooling_confirmed']=true;
        $r['reason']='MEASURED_COOL_SUPPLY_WITH_ROOM_AND_HUMIDITY_GATES';
        return $r;
    }
}
