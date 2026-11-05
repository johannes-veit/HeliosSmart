<?php
/**
 * Anti-hunting for discretionary, already-owned SMART stages.
 * Never overrides the safety decision and never takes control of
 * a foreign Party/Whisper. The underlying 15-minute device timer
 * and full native-sensor checks remain mandatory on every renewal.
 */
final class JVHeliosFanSmoothing
{
    public const QUIET_CONFIRM_SECONDS = 20 * 60;
    public const DOWN_CONFIRM_SECONDS = 20 * 60;
    public const COOLING_CONFIRM_SECONDS = 10 * 60;

    public static function decide(array $plan, array $device, array $owned,
        array $history, int $now): array
    {
        $history = [
            'pending_stage'=>(int)($history['pending_stage'] ?? 0),
            'pending_since'=>(int)($history['pending_since'] ?? 0),
        ];
        $out = ['plan'=>$plan, 'state'=>$history, 'status'=>'NO_FILTER',
            'remaining_seconds'=>0];

        // No manufacturer-mode interference, including unknown or missing
        // weather/CO2/RH/frost evidence: respect every original veto.
        if(($plan['execute']??false)!==true) {
            $out['state']=['pending_stage'=>0,'pending_since'=>0];
            $out['status']='SAFETY_OR_FOREIGN_DEVICE_PRIORITY';
            return $out;
        }
        $desired=(int)($plan['target_stage']??0);
        if($desired<1||$desired>4)return $out;
        $mode=$device['mode']??null;
        $fan=$device['fan']??null;
        $sent=(int)($owned['sent_at']??0);
        $until=(int)($owned['until']??0);
        $ownedStage=(int)($owned['stage']??0);
        $ownedMode=(int)($owned['mode']??0);
        $own=is_int($mode)&&is_int($fan)
            && in_array($mode,[2,3],true) && $mode===$ownedMode
            && $fan===$ownedStage && $sent>0 && $sent<=$now
            && $until>=$now && $until-$sent<=16*60;
        if(!$own) {
            $out['state']=['pending_stage'=>0,'pending_since'=>0];
            $out['status']='UNOWNED_OR_AUTO_NO_DELAY';
            return $out;
        }
        if($fan===$desired) {
            $out['state']=['pending_stage'=>0,'pending_since'=>0];
            $out['status']='TARGET_UNCHANGED';
            return $out;
        }
        // Mandatory ventilation recovery from S1 (<=30 min maximum)
        // and independently confirmed real humidity/air need are instant.
        $reason=(string)($plan['reason']??'');
        $priority=in_array($reason,[
            'WETROOM_AIR_NEED','COOKING','NATIVE_CO2_OR_VOC_HIGH',
            'NATIVE_HUMIDITY_HIGH','CONFIRMED_WETROOM_S4_BOOST',
            'CONFIRMED_COOKING_S4_BOOST',
        ],true);
        if($priority || ($fan===1 && $desired>=2)) {
            $out['state']=['pending_stage'=>0,'pending_since'=>0];
            $out['status']=$priority?'URGENT_NEED_IMMEDIATE':'S1_RECOVERY_IMMEDIATE';
            return $out;
        }
        // No S1->S3 jump for a discretionary change. S1 itself is only
        // reached from verified owned S2 after a period of low demand.
        $confirm=$desired===1?self::QUIET_CONFIRM_SECONDS
            :($desired<$fan?self::DOWN_CONFIRM_SECONDS
                :self::COOLING_CONFIRM_SECONDS);
        $pendingStage=$history['pending_stage'];
        $pendingSince=$history['pending_since'];
        if($pendingStage!==$desired || $pendingSince<=0
            || $pendingSince>$now || $now-$pendingSince>24*3600) {
            $pendingSince=$now;
        }
        $out['state']=['pending_stage'=>$desired,'pending_since'=>$pendingSince];
        $elapsed=max(0,$now-$pendingSince);
        $remaining=max(0,$confirm-$elapsed);
        if($remaining===0){
            $out['status']='STABLE_TARGET_CONFIRMED';
            $out['state']=['pending_stage'=>0,'pending_since'=>0];
            return $out;
        }
        // A held stage MUST remain a real, owned, manufacturer-timed mode.
        // We only renew the same stage with the original verified guards.
        $hold=$plan;
        $hold['target_stage']=$fan;
        $hold['route']=$fan===1?'WHISPER':'PARTY';
        $hold['reason']='SMOOTHING_HOLD_'. $reason;
        $hold['smoothing_original_target']=$desired;
        $hold['smoothing_original_reason']=$reason;
        $hold['smoothing_remaining_seconds']=$remaining;
        $out['plan']=$hold;
        $out['status']='HOLD_CURRENT_STAGE_UNTIL_STABLE';
        $out['remaining_seconds']=$remaining;
        return $out;
    }
}
