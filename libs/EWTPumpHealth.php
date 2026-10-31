<?php
/**
 * Shelly *device* connection and physical output plausibility for the Helios
 * air-intake EWT. Cached output=false does not prove a stopped pump.
 * Local MQTT LWT / online indication is NOT the same as Internet cloud online.
 */
final class JVHeliosEWTPumpHealth
{
    public static function assess(array $i): array
    {
        $now=(int)($i['now']??time());
        $online=$i['online']??null;
        $onlineUpdated=(int)($i['online_updated_at']??0);
        $onlineChanged=(int)($i['online_changed_at']??0);
        $heartbeatUpdated=(int)($i['heartbeat_updated_at']??0);
        $switch=$i['switch']??null;
        $switchUpdated=(int)($i['switch_updated_at']??0);
        $power=$i['power_w']??null;
        $powerUpdated=(int)($i['power_updated_at']??0);
        $readAge=static function(int $timestamp)use($now): ?int {
            if($timestamp<=0 || $timestamp>$now+120)return null;
            return max(0,$now-$timestamp);
        };
        $onlineAge=$readAge($onlineUpdated);
        $heartbeatAge=$readAge($heartbeatUpdated);
        $switchAge=$readAge($switchUpdated);
        $powerAge=$readAge($powerUpdated);
        $seenOnline=($online===true);
        // The local Shelly "online" variable is mandatory; optional periodic
        // device telemetry can keep the heartbeat current after its LWT
        // announcement has remained unchanged for hours.
        $alive=$seenOnline && (
            ($onlineAge!==null && $onlineAge<=240)
            || ($heartbeatAge!==null && $heartbeatAge<=240));
        $link=$online===null?'NOT_CONFIGURED':
            (!$seenOnline?'OFFLINE':
                ($alive?'ONLINE_FRESH':'ONLINE_STALE_NO_HEARTBEAT'));
        // After a new MQTT session, old retained output must be refreshed.
        $postReconnect=$onlineChanged<=0 ||
            ($switchUpdated>0 && $switchUpdated >= $onlineChanged);
        $freshOutput=$switch!==null && is_bool($switch)
            && $switchAge!==null && $switchAge<=900 && $postReconnect;
        $reported=($link==='ONLINE_FRESH' && $freshOutput)
            ? ($switch?'ON_REPORTED':'OFF_REPORTED')
            :'UNKNOWN';
        $watts=$powerAge!==null && $powerAge<=240
            && is_numeric($power) && is_finite((float)$power)
            && (float)$power>=0 && (float)$power<=5000 ? (float)$power : null;
        $contradiction=$reported==='OFF_REPORTED'
            && $watts!==null && $watts>=2.0;
        $confirmed=$reported==='UNKNOWN'?'UNKNOWN':
            ($contradiction?'CONFLICT_SWITCH_OFF_WITH_POWER':
                ($reported==='OFF_REPORTED'
                    ?($watts!==null && $watts<2.0
                        ?'OFF_REPORTED_ZERO_POWER':'OFF_REPORTED_UNVERIFIED_POWER')
                    :($watts!==null && $watts>=2.0
                        ?'ON_REPORTED_POWER_DETECTED':'ON_REPORTED_POWER_UNKNOWN')));
        return [
            'link'=>$link,
            'state'=>$confirmed,
            'reported'=>$reported,
            'online_age_seconds'=>$onlineAge,
            'heartbeat_age_seconds'=>$heartbeatAge,
            'switch_age_seconds'=>$switchAge,
            'switch_updated_at'=>$switchUpdated,
            'power_age_seconds'=>$powerAge,
            'watts'=>$watts,
            'online_confirmed'=>$link==='ONLINE_FRESH',
            'switch_fresh'=>$freshOutput,
            'actuation_allowed'=>$link==='ONLINE_FRESH' && $freshOutput
                && !$contradiction,
            'confirmed_off_reported'=>$reported==='OFF_REPORTED' && !$contradiction,
            'power_conflict'=>$contradiction,
            'warning'=>$link==='OFFLINE'?'DEVICE_OFFLINE_LAST_SWITCH_STATE_UNTRUSTED'
                :($link!=='ONLINE_FRESH'?'NO_RECENT_SHELLY_AVAILABILITY'
                    :(!$freshOutput?'SHELLY_SWITCH_STATE_STALE_OR_PRE_RECONNECT'
                        :($contradiction?'SWITCH_OFF_BUT_POWER_REPORTED':'NONE'))),
        ];
    }

    /** A command is acknowledged only by a NEW status report since sending. */
    public static function ack(array $health,bool $target,int $beforeUpdate): bool
    {
        $current=$health['reported']??'UNKNOWN';
        $age=$health['switch_age_seconds']??null;
        return ($health['online_confirmed']??false)===true
            && ($health['switch_fresh']??false)===true
            && is_int($age)
            && is_int($health['switch_updated_at']??null)
            && $health['switch_updated_at']>$beforeUpdate
            && $current===($target?'ON_REPORTED':'OFF_REPORTED')
            && !($health['power_conflict']??false);
    }
}
