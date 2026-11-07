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
    // One 30-minute native S2 window replaces three 10-minute cycles.
    // Short demand, S1 and separately validated OFF stay device-timed.
    public const BASE_DURATION_MINUTES=30;
    public const SHORT_DURATION_MINUTES=15;
    public const OFF_DURATION_MINUTES=5;
    public const REARM_MARGIN_SECONDS=4*60;
    public const ACK_SECONDS=90;
    public const ERROR_BACKOFF_SECONDS=15*60;
    public const QUIET_COOLDOWN_SECONDS=60*60;
    public const OFF_COOLDOWN_SECONDS=2*3600;

    /**
     * RH hysteresis: enter vendor protection at 60% extract RH; require
     * <=56% continuously for ten minutes before normal savings resume.
     * A confirmed cooktop/shower still follows the independent S4 gates.
     */
    public static function humidityLatch(array $old,$rh,int $now):array
    {
        // This is a separate persisted attribute, not the actuator FSM.
        $state=$old;
        $hold=($state['humidity_hold']??false)===true;
        if(!is_numeric($rh) ||!is_finite((float)$rh)){
            return ['state'=>$state,'hold'=>true,'reason'=>'UNKNOWN_RH'];
        }
        $value=(float)$rh;
        if($value>=60){
            $hold=true;$state['humidity_clear_since']=0;
        } elseif($hold &&$value<=56){
            $since=(int)($state['humidity_clear_since']??0);
            if($since<=0 ||$since>$now){
                $state['humidity_clear_since']=$now;
            } elseif($now-$since>=10*60){
                $hold=false;$state['humidity_clear_since']=0;
            }
        } elseif($hold) {
            $state['humidity_clear_since']=0;
        }
        $state['humidity_hold']=$hold;
        return ['state'=>$state,'hold'=>$hold,
            'reason'=>$hold?'HUMIDITY_WAIT_STABLE_BELOW_56'
                :'HUMIDITY_CLEAR'];
    }

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
        $ownOff=($d['own_timed_off_active']??false)===true
            &&($d['mode']??null)===3;
        if(!in_array($d['actual_fan']??null,[1,2,3,4],true)
            && !(($d['actual_fan']??null)===0 &&$ownOff))
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
            ||(((float)$rh>=60.0
                ||($d['native_humidity_hold']??false)===true)
                &&!$highEvidence))
            return $deny('EXTRACT_HUMIDITY_KEEP_MANUFACTURER');
        if((is_numeric($d['max_co2_ppm']??null)
                &&(float)$d['max_co2_ppm']>=1100)
            ||(is_numeric($d['max_voc_ppm']??null)
                &&(float)$d['max_voc_ppm']>=1000))
            return $deny('AIR_QUALITY_KEEP_MANUFACTURER');
        // The direct cooktop/humidity burst has independent physical
        // evidence. Discretionary S1/S2/S3 need reliable room telemetry,
        // not a network estimate or a learned seasonal recommendation.
        $roomReliable=(int)($d['room_temp_count']??0)>=9
            &&(int)($d['room_humidity_count']??0)>=8;
        if(!$roomReliable &&!$highEvidence)
            return $deny('ROOM_TELEMETRY_INCOMPLETE');

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
            ||($d['cooking_confirmed']??false)===true
            ||(is_numeric($d['max_room_rh']??null)
                &&(float)$d['max_room_rh']>=65.0)){
            $level=3;
            $reason='HUMIDITY_OR_INFERRED_COOKING';
        } elseif(($d['cooling_confirmed']??false)===true
            &&in_array($d['weather_quality']??'',['VERIFIED','DWD_ONLY'],true)
            &&($d['weather_age_seconds']??PHP_INT_MAX)<=900
            &&($d['weather_age_seconds']??-1)>=0){
            $level=3;
            $reason='VERIFIED_PASSIVE_COOLING';
        } elseif(($d['morning_flush_requested']??false)===true){
            // Exactly one bounded daily flush where outside absolute
            // humidity has already been validated against the rooms.
            // Never a forecast-only, fog-driven or open-ended boost.
            $level=3;
            $reason='MORNING_15MIN_FLUSH';
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
            // A five-minute hardware-timed OFF is an opt-in feature
            // ONLY after real hygienic airflow/moisture verification.
            // Unknown SHADOW or a model-only S1/S2 release cannot
            // authorize it; a physical Helios min-stage 0 is required.
            $offAllowed=$level<=2
                &&($d['timed_off_enabled']??false)===true
                &&($d['off_ventilation_plan_validated']??false)===true
                &&($d['minimum_airflow_validated']??false)===true
                &&($d['native_min_stage']??null)===0
                &&($d['away_daytime_low_demand']??false)===true
                &&(int)($d['away_no_home_minutes']??0)>=60
                &&(float)$rh<50.0
                &&is_numeric($d['max_room_rh']??null)
                &&(float)$d['max_room_rh']<=55.0
                &&(int)($d['off_next_allowed_at']??0)<=(int)($d['now']??0);
            if($offAllowed){
                $level=0;
                $reason='VERIFIED_AWAY_FIVE_MINUTE_NATIVE_OFF';
            }
        }
        $o['status']='SMART_OVERRIDE_READY';
        $o['reason']=$reason;
        $o['execute']=true;
        $o['target_stage']=$level;
        $o['route']=$level<=1?'WHISPER':'PARTY';
        $o['minutes']=$level===0?self::OFF_DURATION_MINUTES:
            ($level===2?self::BASE_DURATION_MINUTES:
                self::SHORT_DURATION_MINUTES);
        $o['fallback_weekly']=false;
        $o['evidence']=[
            'hob_active_or_afterrun'=>$hob,
            'shower_high_confidence'=>$shower,
            'native_rh'=>(float)$rh,
            'airflow_real_validated'=>(bool)($d['minimum_airflow_validated']??false),
            'airflow_model_only'=>(bool)($d['model_airflow_accepted']??false),
            'off_requires_real_minimum_airflow_and_moisture_plan'=>true,
            'timed_off_authorized'=>$level===0,
            'off_hardware_autoreturn_minutes'=>self::OFF_DURATION_MINUTES,
            'weather_quality'=>$d['weather_quality']??'UNKNOWN',
        ];
        return $o;
    }

    /**
     * A single fifteen-minute morning S3 flush. Use actual WEATHER
     * outdoor absolute humidity, never the HELIOS sensor after EWT.
     * All conditions are auditable and independent of inferred phone
     * occupancy. A full morning already observed by the tracker wins.
     */
    public static function morningFlush(array $x,array $state,int $now):array
    {
        $local=(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        $day=$local->format('Y-m-d');
        $hour=(int)$local->format('G');
        $last=(int)($state['morning_flush_started_at']??0);
        $sameDay=(string)($state['morning_flush_day']??'')===$day;
        $elapsed=$sameDay &&$last>0?max(0,$now-$last):0;
        $outside=$x['outside_ah']??null;
        $inside=$x['indoor_ah']??null;
        $dry=is_numeric($outside)&&is_numeric($inside)
            &&is_finite((float)$outside)&&is_finite((float)$inside)
            &&(float)$outside>=0&&(float)$outside<=25
            &&(float)$inside>=0&&(float)$inside<=25
            &&(float)$outside<=(float)$inside-0.2;
        $age=$x['weather_age_seconds']??PHP_INT_MAX;
        $goodWeather=in_array($x['weather_quality']??'',
            ['VERIFIED','DWD_ONLY'],true)
            &&is_int($age)&&$age>=0&&$age<=900;
        $requested=($x['enabled']??false)===true
            &&$hour>=7 &&$hour<10
            &&($x['observed_morning_ventilation']??false)!==true
            &&(!$sameDay||$elapsed<15*60)
            &&$dry&&$goodWeather;
        return [
            'requested'=>$requested,
            'outside_air_suitable'=>$dry,
            'day'=>$day,
            'started_duration_seconds'=>$elapsed,
            'historical_flush_seen'=>
                ($x['observed_morning_ventilation']??false)===true,
            'daily_target_minutes'=>15,
            'reason'=>!$goodWeather?'WEATHER_UNVERIFIED'
                :(!$dry?'OUTSIDE_NOT_DRYER'
                :($hour<7||$hour>=10?'OUTSIDE_MORNING_WINDOW'
                :($sameDay &&$elapsed>=900?'ALREADY_REQUESTED_15MIN'
                :(($x['observed_morning_ventilation']??false)
                    ?'ALREADY_VENTILATED'
                    :($requested?'MORNING_FLUSH_READY':'DISABLED'))))),
        ];
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

    private static function start(array $s,int $stage,int $now,
        string $reason=''):array
    {
        $mode=$stage<=1?3:2;
        $duration=$stage===0?self::OFF_DURATION_MINUTES
            :($stage===2?self::BASE_DURATION_MINUTES:
                self::SHORT_DURATION_MINUTES);
        $s['phase']='STARTING';
        $s['mode']=$mode;$s['stage']=$stage;
        $s['sent_at']=$now;$s['native_timer_anchor_at']=$now;
        $s['duration_minutes']=$duration;
        $s['until']=$now+$duration*60;
        $s['pending_until']=$now+self::ACK_SECONDS;
        $s['readback_pending_until']=$s['pending_until'];
        $s['last_command_at']=$now;
        $s['quiet_started_at']=$stage===1?$now:0;
        $s['hob_boost']=$stage===4;
        if($stage===0){
            $s['off_next_allowed_at']=$now+self::OFF_COOLDOWN_SECONDS;
            $s['off_started_at']=$now;
        }
        if($reason==='MORNING_15MIN_FLUSH'){
            $day=(new DateTimeImmutable('@'.$now))
                ->setTimezone(new DateTimeZone('Europe/Berlin'))
                ->format('Y-m-d');
            if((string)($s['morning_flush_day']??'')!==$day){
                $s['morning_flush_day']=$day;
                $s['morning_flush_started_at']=$now;
            }
        }
        if($stage===1)$s['quiet_baseline_confirmed_at']=0;
        return self::result($s,'SIMPLE_START_REQUESTED',
            ['type'=>'start','mode'=>$mode,'stage'=>$stage,
                'minutes'=>$duration]);
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
        if((int)($s['stage']??0)===1)
            $s['quiet_cooldown_until']=max(
                (int)($s['quiet_cooldown_until']??0),
                $now+self::QUIET_COOLDOWN_SECONDS);
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
            &&array_key_exists('target_stage',$plan)
            &&$plan['target_stage']!==null
                ?(int)$plan['target_stage']:-1;
        if($desired<0||$desired>4)$desired=-1;

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
                $expected=(int)($s['duration_minutes']??15)*60;
                if($mode===0
                    &&$now-(int)($s['sent_at']??0)>=$expected-120){
                    if($ownedStage===1)
                        $s['quiet_cooldown_until']=max(
                            (int)($s['quiet_cooldown_until']??0),
                            $now+self::QUIET_COOLDOWN_SECONDS);
                    $s['phase']='IDLE';$s['stage']=0;$s['mode']=0;
                    $phase='IDLE';
                } else return self::error($s,$now,
                    'SIMPLE_OWNED_MODE_INTERRUPTED');
            } else {
                $age=$now-(int)($s['sent_at']??0);
                if($ownedStage===2 &&$desired===1)
                    $s['quiet_baseline_confirmed_at']=$now;
                // The 15 minute hardware timer is anchored at the first
                // START, never any "renewal". STOP -> AUTO -> START is
                // a two-phase handshake with only one device write/tick.
                $shortMorning=$ownedStage===3
                    &&($plan['reason']??'')==='MORNING_15MIN_FLUSH';
                $shortWhisper=$ownedStage===1;
                $shortOff=$ownedStage===0;
                $duration=(int)($s['duration_minutes']
                    ??self::SHORT_DURATION_MINUTES)*60;
                $rearm=$age>=max(60,
                    $duration-self::REARM_MARGIN_SECONDS)
                    &&!$shortMorning &&!$shortWhisper &&!$shortOff;
                // Short morning ventilation and quiet S1 must not
                // unnecessarily create a second timer period.
                // At 14min whisper explicitly ends and sets 8h cooldown.
                if($desired===-1 ||$desired!==$ownedStage
                    ||$rearm ||($shortWhisper &&$age>=14*60)
                    ||($shortOff &&$age>=4*60)){
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
        if($desired===-1){
            $s['phase']='IDLE';$s['stage']=0;$s['mode']=0;
            return self::result($s,'SIMPLE_MANUFACTURER_AUTO');
        }
        if($desired===1
            &&$now<(int)($s['quiet_cooldown_until']??0))
            return self::result($s,'SIMPLE_S1_COOLDOWN_KEEP_AUTO');
        // Never abruptly jump from manufacturer S3/S4 to a proposed
        // low-airflow S1. After a *freshly confirmed* S2 of our own,
        // the next AUTO handoff may start S1 without getting stuck
        // in an endless S2 -> AUTO -> S2 loop.
        $baseline=(int)($s['quiet_baseline_confirmed_at']??0);
        $baselineFresh=$baseline>0 &&$baseline<=$now
            &&$now-$baseline<=3*60;
        $entry=($desired===1 &&$fan>2 &&!$baselineFresh)?2:$desired;
        return self::start($s,$entry,$now,
            (string)($plan['reason']??''));
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
        if(in_array($phase,['STARTING','STOPPING'],true))return true;
        if($phase!=='ACTIVE')return false;
        $elapsed=$now-(int)($s['sent_at']??0);
        $stage=(int)($s['stage']??0);
        $minutes=(int)($s['duration_minutes']??self::SHORT_DURATION_MINUTES);
        $lead=$stage===0?60:($stage===1?2*60:
            self::REARM_MARGIN_SECONDS+60);
        return $elapsed>=max(0,$minutes*60-$lead);
    }
}
