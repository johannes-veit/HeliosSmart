<?php
/**
 * Conservative SMART restart after a manufacturer fallback.
 *
 * A transient weather/network/room-data fault must not repeatedly alternate
 * between timed Party and the hardware weekly programme. The manufacturer
 * keeps full authority during recovery. This class only VETOES new SMART
 * overrides: never grants a command, never cancels a foreign programme,
 * never changes the original decision's safety priorities.
 */
final class JVHeliosRecoveryGate
{
    public const GENERAL_STABLE_SECONDS = 10 * 60;
    public const MOISTURE_STABLE_SECONDS = 15 * 60;
    public const MOISTURE_RELEASE_RH = 55.0;

    private static function isMoistureBlock(string $reason): bool
    {
        return in_array($reason, [
            'NATIVE_EXTRACT_HUMIDITY_ELEVATED_KEEP_DEVICE',
            'NATIVE_EXTRACT_HUMIDITY_HIGH_KEEP_DEVICE',
            'NATIVE_HUMIDITY_HIGH_KEEP_DEVICE',
        ], true);
    }

    private static function urgent(array $plan):bool
    {
        return in_array((string)($plan['reason']??''), [
            'WETROOM_AIR_NEED', 'COOKING', 'CONFIRMED_WETROOM_S4_BOOST',
            'CONFIRMED_COOKING_S4_BOOST',
            'COOKTOP_POWER_ACTIVE_S4','COOKTOP_5MIN_AFTER_RUN_S4',
        ], true);
    }

    public static function assess(array $plan, array $prior, array $sensor,
        int $now):array
    {
        $block = substr((string)($prior['block_reason']??''),0,100);
        $since = max(0,(int)($prior['recovery_since']??0));
        $state=['v'=>1,'block_reason'=>$block,
            'recovery_since'=>$since,
            'blocked_at'=>max(0,(int)($prior['blocked_at']??0))];
        $result=['plan'=>$plan, 'state'=>$state,
            'status'=>'READY_OR_NO_SAFETY_INTERVENTION',
            'required_seconds'=>0, 'remaining_seconds'=>0];

        $enabled=($sensor['enabled']??false)===true;
        if(!$enabled) {
            $result['state']=['v'=>1,'block_reason'=>'',
                'recovery_since'=>0,'blocked_at'=>0];
            $result['status']='SMART_DISABLED_NO_LATCH';
            return $result;
        }

        // A new failed prerequisite is acted upon immediately, exactly as
        // before: cancel only a provably owned timed mode in the executor.
        if(($plan['execute']??false)!==true) {
            $reason=substr((string)($plan['reason']??'UNKNOWN'),0,100);
            // Do not unlearn that native RH was too high just because a
            // simultaneous temporary weather fault obscures the next reason.
            $block=self::isMoistureBlock($block)&&!self::isMoistureBlock($reason)
                ?$block:$reason;
            $result['state']=['v'=>1,'block_reason'=>$block,
                'recovery_since'=>0,'blocked_at'=>$now];
            $result['status']='ORIGINAL_FAILSAFE_'. $reason;
            return $result;
        }

        if($block==='')return $result;
        // Confirmed bath/cooking demand can restore proper ventilation
        // immediately; the original planner has *already* checked the
        // live manufacturer sensors, defrost status and opt-ins.
        if(self::urgent($plan)) {
            $result['state']=['v'=>1,'block_reason'=>'',
                'recovery_since'=>0,'blocked_at'=>0];
            $result['status']='CONFIRMED_AIR_NEED_BYPASSES_RECOVERY_DELAY';
            return $result;
        }
        $moisture=self::isMoistureBlock($block);
        $required=$moisture?self::MOISTURE_STABLE_SECONDS
            :self::GENERAL_STABLE_SECONDS;
        $result['required_seconds']=$required;
        $rh=$sensor['native_extract_rh']??null;
        if($moisture && (!is_numeric($rh)
            || !is_finite((float)$rh)
            || (float)$rh>self::MOISTURE_RELEASE_RH)) {
            $result['state']['recovery_since']=0;
            $result['status']='WAIT_MANUFACTURER_HUMIDITY_TO_DROP_BELOW_55';
            $result['remaining_seconds']=$required;
        } else {
            if($since<=0 || $since>$now || $now-$since>24*3600)
                $since=$now;
            $result['state']['recovery_since']=$since;
            $remaining=max(0,$required-($now-$since));
            $result['remaining_seconds']=$remaining;
            if($remaining===0) {
                $result['state']=['v'=>1,'block_reason'=>'',
                    'recovery_since'=>0,'blocked_at'=>0];
                $result['status']='STABLE_SENSORS_RESUME_SMART';
                return $result;
            }
            $result['status']=$moisture?'WAIT_STABLE_NATIVE_HUMIDITY'
                :'WAIT_STABLE_SENSORS_AFTER_FALLBACK';
        }
        // A restart delay is for discretionary operation only; do not
        // mislabel the manufacturer as currently defective after recovery.
        $held=$plan;
        $held['status']='FALLBACK_WEEKLY';
        $held['reason']='STABILIZATION_'.$block;
        $held['target_stage']=null;
        $held['minutes']=0;
        $held['execute']=false;
        $held['fallback_weekly']=true;
        $held['route']=null;
        $held['recovery_wait_seconds']=$result['remaining_seconds'];
        $result['plan']=$held;
        return $result;
    }
}
