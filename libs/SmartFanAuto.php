<?php
/**
 * Automatic HELIOS fan stage selection with manufacturer *device-timed*
 * Party/Whisper overrides. The stored weekly program is the fallback,
 * not the normal stage target. No actuator calls live in this class.
 *
 * Fail safe: no command when weather / room sensors / native status fail;
 * never reduce an independently observed high CO2 or urgent wetroom demand;
 * no "real airflow measured" claim based on estimated fan stages.
 */
final class JVHeliosSmartFanAuto
{
    public static function decide(array $d,int $now):array
    {
        $out=['status'=>'FALLBACK_WEEKLY','reason'=>'UNSAFE_OR_INSUFFICIENT_INPUT',
            'target_stage'=>null,'minutes'=>0,'execute'=>false,
            'fallback_weekly'=>true,'route'=>null,'evidence'=>[]];
        if(($d['enabled']??false)!==true){
            $out['reason']='TAKEOVER_DISABLED';return $out;
        }
        if(($d['native_ok']??false)!==true || ($d['live_fresh']??false)!==true
            || !in_array($d['mode']??null,[0,2,3],true)){
            $out['reason']='NATIVE_NOT_HEALTHY_OR_NOT_AUTO';return $out;
        }
        $program=$d['native_week_program']??3;
        // Native programme 5 is the *Symcon* weekly-event mode, not a
        // manufacturer-stored schedule. It may send parallel events and
        // does not satisfy the promised hardware fallback on SymBox loss.
        if(!is_int($program)||$program<0||$program>4){
            $out['reason']='NATIVE_WEEKLY_FALLBACK_NOT_DEVICE_STORED';
            return $out;
        }
        $mode=$d['mode']??null;
        if($mode===2||$mode===3){
            // Distinguish a device-timed mode we own from a human/external party.
            if(($d['owned_mode']??null)!==$mode
                || ($d['owned_until']??0)<$now
                || ($d['owned_last_at']??0)<=0
                ||($d['owned_stage']??null)!==($d['actual_fan']??null)){
                $out['reason']='FOREIGN_PARTY_OR_WHISPER';return $out;
            }
        }
        if(!in_array($d['weather_quality']??'',['VERIFIED','DWD_ONLY'],true)
            || ($d['weather_age_seconds']??PHP_INT_MAX)>900
            || ($d['weather_age_seconds']??-1)<0){
            $out['reason']='WEATHER_NOT_VERIFIED_FRESH';return $out;
        }
        if((int)($d['room_temp_count']??0)<9
            ||(int)($d['room_humidity_count']??0)<8){
            $out['reason']='ROOM_DATA_INCOMPLETE';return $out;
        }
        $fan=$d['actual_fan']??null;
        if(!is_int($fan)||$fan<1||$fan>4){
            $out['reason']='FAN_READBACK_INVALID';return $out;
        }
        $co2=$d['max_co2_ppm']??null;
        $voc=$d['max_voc_ppm']??null;
        $badAir=(is_numeric($co2)&&(float)$co2>=1100)
            ||(is_numeric($voc)&&(float)$voc>=1000);
        $nativeRh=$d['native_extract_rh']??null;
        $nativeHighRh=is_numeric($nativeRh)
            && (float)$nativeRh>=70.0;
        // At 60-70% extract RH the manufacturer's own humidity controller
        // may demand a higher stage at any moment. Do not mask that control
        // with a 15-minute SMART lower-stage Party/Whisper.
        $nativeMoistureCaution=is_numeric($nativeRh)
            && (float)$nativeRh>=60.0 && (float)$nativeRh<70.0;
        // HELIOS Party/Whisper temporarily OVERRIDE native CO2/VOC/RH
        // automatic regulation at *every* original stage, not only S4.
        // Unknown sensor readings therefore prevent any SMART timed mode.
        if(($d['native_sensor_stale']??false)===true){
            $out['reason']='NATIVE_SENSOR_READINGS_STALE_KEEP_OWN_DEMAND';
            return $out;
        }
        // Native anti-icing is a hardware-priority condition and must
        // not be overridden by energy-saving fan presets.
        if(($d['native_defrost_active']??false)===true){
            $out['reason']='NATIVE_HEAT_EXCHANGER_DEFROST_ACTIVE';
            return $out;
        }
        if(($d['native_defrost_unknown_near_frost']??false)===true){
            $out['reason']='NATIVE_DEFROST_STATUS_UNKNOWN_NEAR_FREEZING';
            return $out;
        }
        // Existing sensor-driven stage4 cannot be downgraded merely
        // because its CO2/VOC or exhaust RH telemetry is absent. When a
        // controller is enabled but its measurement is not represented
        // in the SMART inputs, let the original regulator stay in charge.
        if(($d['native_sensor_control_unverified']??false)===true){
            $out['reason']='NATIVE_S4_SENSOR_DEMAND_UNVERIFIABLE';
            return $out;
        }
        $wet=(bool)($d['wet_need']??false);
        $cook=(bool)($d['cooking_confirmed']??false);
        $cool=(bool)($d['cooling_confirmed']??false);
        $strongWet=(bool)($d['strong_wet_need']??false);
        $strongCook=(bool)($d['strong_cooking_need']??false);
        $highPower=(bool)($d['high_power_enabled']??false)
            && (bool)($d['high_power_safety']??false)
            && (bool)($d['combustion_safe']??false);
        // A genuinely high native CO2/VOC sensor request must NOT be
        // silently downgraded to S3 simply because S4 approval is absent.
        // Keep the manufacturer's own sensor/weekly regulation in charge.
        if($nativeMoistureCaution){
            $out['reason']='NATIVE_EXTRACT_HUMIDITY_ELEVATED_KEEP_DEVICE';
            return $out;
        }
        if(($badAir || $nativeHighRh) && !$highPower){
            // Even if the manufacturer currently reports S2/S3, sending
            // a timed SMART S3 could lock out a forthcoming native
            // sensor demand for S4. Do not take over in that condition.
            $out['reason']=$badAir
                ?'NATIVE_CO2_VOC_HIGH_NO_S4_APPROVAL'
                :'NATIVE_EXTRACT_HUMIDITY_HIGH_KEEP_DEVICE';
            return $out;
        }
        // Similarly never force a lower stage than the already measured
        // S4 during an independently demonstrated strong wet/cooking event.
        if(($strongWet || $strongCook) && !$highPower){
            $out['reason']='STRONG_WET_OR_COOKING_KEEP_NATIVE_REGULATION';
            return $out;
        }
        // Safety-priority cases always beat energy savings or absence.
        if($strongWet || $strongCook || $badAir || $nativeHighRh){
            $level=$highPower?4:3;
            $reason=$badAir?'NATIVE_CO2_OR_VOC_HIGH'
                :($nativeHighRh?'NATIVE_HUMIDITY_HIGH'
                    :($strongWet?'STRONG_WETROOM_NEED':'CONFIRMED_COOKING'));
        } elseif($wet || $cook || $cool){
            $level=3;
            $reason=$wet?'WETROOM_AIR_NEED':($cook?'COOKING':'SAFE_PASSIVE_COOLING');
        }else{
            $canReduce=(bool)($d['minimum_airflow_validated']??false)
                ||(bool)($d['model_airflow_accepted']??false);
            $level=$canReduce?2:3; // S3 = nominal, not a fictitious minimum validation
            $reason=$canReduce?'ENERGY_EFFICIENT_BASE':'NOMINAL_FALLBACK_UNMEASURED_AIRFLOW';
            $quiet=(bool)($d['quiet_requested']??false);
            $quietRecently=(int)($d['quiet_started_at']??0)>0
                && $now-(int)$d['quiet_started_at']>=30*60;
            $quietCooldown=(int)($d['quiet_cooldown_until']??0)>$now;
            if($quiet && $canReduce && !$quietRecently && !$quietCooldown){
                $level=1;$reason='SHORT_LOW_DEMAND_QUIET';
            }
        }
        if($level===4 && !$highPower)$level=3;
        $route=$level===1?'WHISPER':'PARTY';
        // Both are native Helios device timers; loss of the SymBox does
        // NOT leave MANUELL active indefinitely.
        $out['status']='SMART_OVERRIDE_READY';
        $out['reason']=$reason;
        $out['target_stage']=$level;
        $out['minutes']=15;
        $out['route']=$route;
        $out['execute']=true;
        $out['fallback_weekly']=false;
        $out['evidence']=[
            'room_temp_count'=>$d['room_temp_count'],
            'room_humidity_count'=>$d['room_humidity_count'],
            'high_co2_or_voc'=>$badAir,
            'high_native_extract_rh'=>$nativeHighRh,
            'wetroom_demand'=>$wet,
            'cooking_demand'=>$cook,
            'cooling_demand'=>$cool,
            'model_airflow_accepted'=>(bool)($d['model_airflow_accepted']??false),
            'minimum_airflow_validated'=>(bool)($d['minimum_airflow_validated']??false),
        ];
        return $out;
    }
}
