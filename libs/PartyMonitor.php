<?php
/** Read-only ownership monitoring. Never cancels Party or restores a week plan. */
final class JVHeliosPartyMonitor
{
    public static function inspect(array $owned, ?array $live, int $now):array
    {
        $issued=(int)($owned['last_command_at']??0);
        $end=(int)($owned['expires_at']??0);
        $level=(int)($owned['level']??0);
        $program=(int)($owned['original_program']??0);
        $base=['status'=>'NO_OWNED_PARTY','since_at'=>$issued?:null,
            'reserved_until'=>$end?:null,'requested_level'=>$level?:null,
            'weekly_program_at_start'=>$program?:null,
            'does_not_cancel_or_reset_device'=>true,'cooldown_minutes'=>30];
        if ($issued<=0 || $end<=0 || $level<=0) return $base;
        if ($now<$issued || $end<$issued || $end>$issued+17*60) {
            $base['status']='CORRUPTED_OWNERSHIP';return $base;
        }
        if ($live===null || !JVHeliosPartyExecutor::validLive($live)) {
            $base['status']='DEVICE_READBACK_UNAVAILABLE';return $base;
        }
        if ($program>0 && $live['program']!==$program) {
            $base['status']='WEEK_PROGRAM_CHANGED_EXTERNALLY';return $base;
        }
        if ($now<=$end) {
            if ($live['mode']===2 && $live['fan']===$level) $base['status']='PARTY_ACTIVE_CONFIRMED';
            elseif ($live['mode']===2) $base['status']='DIFFERENT_PARTY_ACTIVE';
            elseif ($live['mode']===0) $base['status']='AUTOMATIC_OR_RETURNED_EARLY';
            else $base['status']='MANUAL_OR_SPECIAL_OVERRIDE';
        } else {
            if ($live['mode']===0) $base['status']='WEEK_PROGRAM_RESUMED';
            elseif ($live['mode']===2) $base['status']='PARTY_AFTER_EXPECTED_EXPIRY';
            else $base['status']='NON_AUTOMATIC_AFTER_EXPIRY';
        }
        return $base;
    }
}
