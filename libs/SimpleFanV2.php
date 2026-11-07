<?php
/**
 * HELIOS SMART V2: one deterministic fan policy + one device-timed command
 * state machine. No LCN, weather, learning or EWT writes live in this class.
 *
 * Official easyControls native Party / Whisper (mode 2 / 3) has its OWN
 * countdown. A repeated same-stage Party is NOT assumed to reset it.
 * State changes are always confirmed by a later independent device GET.
 */
final class JVHeliosSimpleFanV2
{
    public const VERSION=2;
    public const TIMER_SECONDS=900;
    public const REARM_SECONDS=10*60;
    public const ACK_SECONDS=90;
    public const ERROR_BACKOFF_SECONDS=15*60;
    public const QUIET_COOLDOWN_SECONDS=8*3600;

    public static function plan(array $d):array
    {
        $o=['status'=>'FALLBACK_WEEKLY','reason'=>'UNVERIFIED',
            'execute'=>false,'target_stage'=>null,'route'=>null,
            'minutes'=>0,'fallback_weekly'=>true,'evidence'=>[],
            'controller'=>'SIMPLE_V2'];
        $deny=static function(string $reason)use($o):array{
            $o['reason']=$reason;return $o;
        };
        if(($d['enabled']??false)!==true)return $deny('DISABLED');
        if(($d['native_ok']??false)!==true
            ||($d['live_fresh']??false)!==true)
            return $deny('NATIVE_NOT_READY');
        if(!in_array($d['mode']??null,[0,2,3],true))
            return $deny('MANUAL_OR_VACATION_MODE');
        if(!in_array($d['actual_fan']??null,[1,2,3,4],true))
            return $deny('FAN_LIVE_INVALID');
        if(!is_int($d['native_week_program']??null)
            ||($d['native_week_program']??5)>4
            ||($d['native_week_program']??-1)<0)
            return $deny('NO_HARDWARE_WEEKLY_FALLBACK');
        if(($d['native_sensor_stale']??false)===true
            ||($d['native_sensor_control_unverified']??false)===true
            ||($d['native_extract_rh_live']??false)!==true)
            return $deny('NATIVE_SENSOR_UNVERIFIED');
        if(($d['native_defrost_active']??false)===true)
            return $deny('DEFROST_KEEP_MANUFACTURER');
        if(($d['native_defrost_known']??false)!==true)
            return $deny('DEFROST_NOT_VERIFIED');
        $rh=$d['native_extract_rh']??null;
        if(!is_numeric($rh)||!is_finite((float)$rh)
            ||(float)$rh<10||(float)$rh>100)
            return $deny('EXTRACT_HUMIDITY_NOT_VERIFIED');
        $highEvidence=($d['direct_hob_boost']??false)===true
            ||($d['strong_wet_need']??false)===true;
        // Never suppress an independently confirmed cook/shower S4
        // solely because exhaust RH reached 60-69%. At >=70% the
        // manufacturer moisture regulator is always given control.
        if((float)$rh>=70.0
            ||((float)$rh>=60.0 &&!$highEvidence))
            return $deny('EXTRACT_HUMIDITY_KEEP_MANUFACTURER');
        if((is_numeric($d['max_co2_ppm']??null)
                &&(float)$d['max_co2_ppm']>=1100)
            ||(is_numeric($d['max_voc_ppm']??null)
                &&(float)$d['max_voc_ppm']>=1000))
            return $deny('AIR_QUALITY_KEEP_MANUFACTURER');

        // Only electrically measured cooktop activity (including its
        // bounded five-minute OFF after-run) or a verified strong bath
        // humidity event may justify S4. Indirect household-power cooking
        // is allowed S3 at most.
        $hob=($d['direct_hob_boost']??false)===true;
        $shower=($d['strong_wet_need']??false)===true;
        $highPower=($d['high_power_enabled']??false)===true
            &&($d['high_power_safety']??false)===true
            &&($d['combustion_safe']??false)===true;
        if(($hob||$shower)&&!$highPower)
            return $deny('S4_SAFETY_RELEASE_MISSING_KEEP_NATIVE');
        if($hob||$shower){
            $level=4;
            $reason=$hob
                ?(($d['direct_hob_afterrun']??false)===true
                    ?'COOKTOP_5MIN_AFTER_RUN':'COOKTOP_POWER_ACTIVE')
                :'CONFIRMED_SHOWER';
        } elseif(($d['wet_need']??false)===true
            ||($d['cooking_confirmed']??false)===true){
            $level=3;
            $reason='HUMIDITY_OR_INFERRED_COOKING';
        } elseif(($d['cooling_confirmed']??false)===true
            &&in_array($d['weather_quality']??'',['VERIFIED','DWD_ONLY'],true)
            &&($d['weather_age_seconds']??PHP_INT_MAX)<=900
            &&($d['weather_age_seconds']??-1)>=0){
            $level=3;
            $reason='VERIFIED_PASSIVE_COOLING';
        } else {
            $flow=($d['minimum_airflow_validated']??false)===true
                ||($d['model_airflow_accepted']??false)===true;
            $level=$flow?2:3;
            $reason=$flow?'ENERGY_EFFICIENT_BASE':'NO_LOWER_AIRFLOW_RELEASE';
            $quiet=(($d['away_daytime_low_demand']??false)===true
                    ||($d['quiet_requested']??false)===true);
            $cooldown=(int)($d['quiet_cooldown_until']??0)>($d['now']??0);
            if($flow &&$quiet &&!$cooldown){
                $level=1;
                $reason=($d['away_daytime_low_demand']??false)===true
                    ?'SHADOW_NO_HOME_BOUNDED_S1':'NIGHT_BOUNDED_S1';
            }
        }
        $o['status']='SMART_OVERRIDE_READY';
        $o['reason']=$reason;
        $o['execute']=true;
        $o['target_stage']=$level;
        $o['route']=$level===1?'WHISPER':'PARTY';
        $o['minutes']=15;
        $o['fallback_weekly']=false;
        $o['evidence']=[
            'hob_active_or_afterrun'=>$hob,
            'shower_high_confidence'=>$shower,
            'native_rh'=>(float)$rh,
            'airflow_real_validated'=>(bool)($d['minimum_airflow_validated']??false),
            'airflow_model_only'=>(bool)($d['model_airflow_accepted']??false),
            'no_unattended_fan_off'=>true,
            'weather_quality'=>$d['weather_quality']??'UNKNOWN',
        ];
        return $o;
    }

    public static function initialState(array $old):array
    {
        // New schema deliberately does not claim ownership of a V1 party.
        // It will expire under its manufacturer's timer and then V2 starts.
        if((int)($old['v']??0)!==self::VERSION)
            return ['v'=>self::VERSION,'phase'=>'IDLE',
                'stage'=>0,'mode'=>0,'sent_at'=>0,'until'=>0,
                'quiet_cooldown_until'=>max(0,
                    (int)($old['quiet_cooldown_until']??0))];
        return $old;
    }

    private static function result(array $s,string $status,
        ?array $action=null,bool $verified=false):array
    {
        return ['state'=>$s,'status'=>$status,'action'=>$action,
            'verified'=>$verified,'fallback_weekly'=>$action===null
                &&!in_array((string)($s['phase']??''),[
                    'ACTIVE','STARTING','STOPPING'],true)];
    }

    private static function start(array $s,int $stage,int $now):array
    {
        $mode=$stage===1?3:2;
        $s['phase']='STARTING';
        $s['mode']=$mode;$s['stage']=$stage;
        $s['sent_at']=$now;$s['native_timer_anchor_at']=$now;
        $s['until']=$now+self::TIMER_SECONDS;
        $s['pending_until']=$now+self::ACK_SECONDS;
        $s['readback_pending_until']=$s['pending_until'];
        $s['last_command_at']=$now;
        $s['quiet_started_at']=$stage===1?$now:0;
        $s['hob_boost']=$stage===4;
        return self::result($s,'SIMPLE_START_REQUESTED',
            ['type'=>'start','mode'=>$mode,'stage'=>$stage,'minutes'=>15]);
    }

    private static function stop(array $s,int $now):array
    {
        $s['phase']='STOPPING';
        $s['pending_until']=$now+self::ACK_SECONDS;
        $s['readback_pending_until']=$s['pending_until'];
        $s['last_command_at']=$now;
        if((int)($s['stage']??0)===1)
            $s['quiet_cooldown_until']=max(
                (int)($s['quiet_cooldown_until']??0),
                $now+self::QUIET_COOLDOWN_SECONDS);
        return self::result($s,'SIMPLE_STOP_REQUESTED',
            ['type'=>'stop','mode'=>(int)($s['mode']??0),
                'stage'=>0,'minutes'=>0]);
    }

    private static function error(array $s,int $now,string $why):array
    {
        $s['phase']='BACKOFF';
        $s['error_retry_after']=$now+self::ERROR_BACKOFF_SECONDS;
        $s['pending_until']=0;$s['readback_pending_until']=0;
        return self::result($s,$why);
    }

    /** Pure state machine. Caller atomically stores state BEFORE each RPC. */
    public static function step(array $plan,?array $live,
        array $old,int $now):array
    {
        $s=self::initialState($old);
        $phase=(string)($s['phase']??'IDLE');
        $mode=$live['mode']??null;
        $fan=$live['fan']??null;
        if(!is_int($mode)||!is_int($fan)
            ||!in_array($mode,[0,1,2,3,4],true)
            ||!in_array($fan,[0,1,2,3,4],true))
            return self::result($s,'SIMPLE_LIVE_UNAVAILABLE');
        $ownedMode=(int)($s['mode']??0);
        $ownedStage=(int)($s['stage']??0);
        $match=$mode===$ownedMode &&$fan===$ownedStage
            &&in_array($mode,[2,3],true);
        $desired=($plan['execute']??false)===true
            ?(int)($plan['target_stage']??0):0;
        if($desired<1||$desired>4)$desired=0;

        if($phase==='STARTING'){
            if($match){
                $s['phase']='ACTIVE';$s['pending_until']=0;
                $s['readback_pending_until']=0;
                $s['readback_confirmed_at']=$now;
                $phase='ACTIVE';
            } elseif($mode===0
                &&$now<=(int)($s['pending_until']??0))
                return self::result($s,'SIMPLE_WAIT_START_READBACK');
            elseif($mode!==0){
                $s['phase']='IDLE';$s['mode']=0;$s['stage']=0;
                return self::result($s,'SIMPLE_FOREIGN_MODE_KEEP');
            } else return self::error($s,$now,
                    'SIMPLE_START_NOT_CONFIRMED_BACKOFF');
        }
        if($phase==='STOPPING'){
            if($mode===0){
                $s['phase']='IDLE';$s['pending_until']=0;
                $s['readback_pending_until']=0;
                $s['stage']=0;$s['mode']=0;
                $phase='IDLE';
            } elseif($match
                &&$now<=(int)($s['pending_until']??0))
                return self::result($s,'SIMPLE_WAIT_STOP_READBACK');
            else
                return self::error($s,$now,
                    'SIMPLE_STOP_NOT_CONFIRMED_BACKOFF');
        }
        if($phase==='ACTIVE'){
            if(!$match){
                if($mode===0
                    &&$now-(int)($s['sent_at']??0)>=13*60){
                    $s['phase']='IDLE';$s['stage']=0;$s['mode']=0;
                    $phase='IDLE';
                } else return self::error($s,$now,
                    'SIMPLE_OWNED_MODE_INTERRUPTED');
            } else {
                $age=$now-(int)($s['sent_at']??0);
                // The 15 minute hardware timer is anchored at the first
                // START, never any "renewal". STOP -> AUTO -> START is
                // a two-phase handshake with only one device write/tick.
                if($desired===0 ||$desired!==$ownedStage
                    ||$age>=self::REARM_SECONDS
                    ||($ownedStage===1 &&$age>=14*60)){
                    return self::stop($s,$now);
                }
                return self::result($s,'SIMPLE_STAGE_CONFIRMED',
                    null,true);
            }
        }
        if($phase==='BACKOFF' &&$now<(int)($s['error_retry_after']??0))
            return self::result($s,'SIMPLE_ACTUATOR_BACKOFF');
        // Do not take over unrelated human/manual/vacation or
        // manufacturer-owned Party/Whisper. The previous V1's party
        // must return to AUTO before V2 may start.
        if($mode!==0)return self::result($s,'SIMPLE_FOREIGN_MODE_KEEP');
        if($desired===0){
            $s['phase']='IDLE';$s['stage']=0;$s['mode']=0;
            return self::result($s,'SIMPLE_MANUFACTURER_AUTO');
        }
        if($desired===1
            &&$now<(int)($s['quiet_cooldown_until']??0))
            return self::result($s,'SIMPLE_S1_COOLDOWN_KEEP_AUTO');
        // Never abruptly jump from manufacturer S3/S4 to a proposed
        // low-airflow S1. Confirm a manufacturer-timed S2 first.
        return self::start($s,$desired===1 &&$fan>2 ?2:$desired,$now);
    }

    /** Pure post-RPC failure transition: no blind retry on uncertain error. */
    public static function writeFailed(array $state,int $now):array
    {
        return self::error(self::initialState($state),$now,
            'SIMPLE_RPC_FAILED_BACKOFF');
    }

    public static function wakeDue(array $old,int $now):bool
    {
        $s=self::initialState($old);
        $phase=(string)($s['phase']??'IDLE');
        return in_array($phase,['STARTING','STOPPING'],true)
            ||($phase==='ACTIVE'
                &&$now-(int)($s['sent_at']??0)>=9*60);
    }
}
