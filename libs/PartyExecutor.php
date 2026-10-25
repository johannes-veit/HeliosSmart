<?php
/** One-shot party transaction. Callbacks are vendor RPCs; no client-side cancellation. */
final class JVHeliosPartyExecutor
{
    public static function execute(array $proposal, array $gate, int $now,
        callable $before, callable $send, callable $after): array
    {
        $result=[
            'attempted'=>false,'sent'=>false,'verified'=>false,'status'=>'BLOCKED',
            'mode_after'=>null,'fan_after'=>null,'remaining_minutes'=>null,
            'error'=>null, 'last_attempt_at'=>null,'reserved_until'=>null,
            'no_retry_after_uncertain_result'=>true,'never_cancels_party'=>true,
        ];
        if (($gate['command_allowed']??false)!==true || ($proposal['kind']??'')!=='PARTY_CANDIDATE') return $result;
        $target=$proposal['suggested_level']??null;
        $duration=$proposal['suggested_minutes']??null;
        if (!is_int($target)||$target<1||$target>4||!is_int($duration)||$duration<5||$duration>30||($target===4 && $duration!==30)||$now<=0) {
            $result['status']='INVALID_TRANSACTION';return $result;
        }
        try {
            $live=$before();
            if (!is_array($live) || !self::validLive($live)) {
                $result['status']='PREFLIGHT_UNAVAILABLE';return $result;
            }
            if ($live['mode']!==0 || !in_array($live['program'],[3,4],true)
                || (isset($proposal['expected_weekly_program'])
                    && $live['program']!==$proposal['expected_weekly_program'])
                || $live['fan']>=$target || $live['fan']<1) {
                $result['status']='PREFLIGHT_STATE_CHANGED';return $result;
            }
        } catch (Throwable $e) {
            $result['status']='PREFLIGHT_ERROR';$result['error']=substr($e->getMessage(),0,120);return $result;
        }
        // The caller must persist this reservation BEFORE sending to avoid retry
        // after a timeout that may have already changed the device.
        $result['status']='RESERVED';
        $result['reserved_until']=$now+$duration*60+120;
        $result['last_attempt_at']=$now;
        $result['attempted']=true;
        return $result;
    }

    public static function dispatch(array $reserved, int $level,int $minutes,
        callable $send, callable $after):array
    {
        if (($reserved['status']??'')!=='RESERVED'||($reserved['attempted']??false)!==true
            || $level<1||$level>4||$minutes<5||$minutes>30||($level===4 && $minutes!==30)) {
            $reserved['status']='NOT_RESERVED';return $reserved;
        }
        try {
            $reply=$send($level,$minutes);
            if ($reply===false || $reply===null) {
                $reserved['status']='WRITE_NOT_CONFIRMED';
                // Null/false may reflect a timeout after command reception.
                return $reserved;
            }
            $reserved['sent']=true;
            $read=$after();
            if (!is_array($read) || !self::validLive($read)) {
                $reserved['status']='READBACK_UNAVAILABLE';return $reserved;
            }
            $reserved['mode_after']=$read['mode'];
            $reserved['fan_after']=$read['fan'];
            $remaining=$read['remaining_minutes']??null;
            $reserved['remaining_minutes']=is_int($remaining)?$remaining:null;
            if ($read['mode']===2 && $read['fan']===$level && is_int($remaining)
                && $remaining>=1 && $remaining<=$minutes) {
                $reserved['verified']=true;$reserved['status']='PARTY_CONFIRMED';
            } else $reserved['status']='READBACK_PENDING_OR_MISMATCH';
        } catch (Throwable $e) {
            $reserved['status']='IO_UNCERTAIN';$reserved['error']=substr($e->getMessage(),0,120);
        }
        return $reserved;
    }

    public static function validLive(array $v):bool
    {
        return is_int($v['mode']??null)&&in_array($v['mode'],[0,1,2,3,4],true)
            &&is_int($v['fan']??null)&&$v['fan']>=0&&$v['fan']<=4
            &&is_int($v['program']??null)&&$v['program']>=0&&$v['program']<=5;
    }
}
