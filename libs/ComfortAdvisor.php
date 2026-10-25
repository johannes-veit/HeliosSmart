<?php
/** Room RH context: older change-only LCN values may inform comfort, never trigger a boost. */
final class JVHeliosComfortAdvisor
{
    public static function analyze(array $sources, ?float $outdoorTemperature): array
    {
        $result=[
            'current_rooms'=>0, 'indicative_rooms'=>0, 'stale_rooms'=>0,
            'dry_current'=>[], 'dry_indicative'=>[],
            'humid_current'=>[], 'humid_indicative'=>[],
            'bath_signal_quality'=>[], 'temperature_pair_not_required'=>true,
            'active_boost_authorized'=>false, 'summary'=>'Keine auswertbaren Raumfeuchtewerte',
        ];
        foreach ($sources as $source) {
            if (!is_array($source) || ($source['available']??false)!==true) continue;
            $age=$source['age_minutes']??null;
            $rh=$source['relative_humidity']??null;
            if (!is_numeric($age) || !is_numeric($rh) || $age<0 || $rh<0 || $rh>100) continue;
            $name=(string)($source['room']??'Raum');
            if ($name==='') continue;
            $quality=$age<=45?'CURRENT':($age<=720?'INDICATIVE':'STALE');
            if (in_array($name,['EG Bad','OG Bad'],true)) {
                $result['bath_signal_quality'][$name]=$quality;
            }
            if ($quality==='STALE') { $result['stale_rooms']++; continue; }
            $current=$quality==='CURRENT';
            if ($current) $result['current_rooms']++; else $result['indicative_rooms']++;
            if ($rh<=35) $result[$current?'dry_current':'dry_indicative'][]=$name;
            if ($rh>=65) $result[$current?'humid_current':'humid_indicative'][]=$name;
        }
        if ($result['current_rooms']+$result['indicative_rooms']===0) return $result;
        $text='Feuchte-Komfort: '.$result['current_rooms'].' neue / '.$result['indicative_rooms'].' stabile aeltere Raumwerte';
        if ($result['dry_current']) {
            $text.=' | trockene Raumluft aktuell in '.implode(', ',$result['dry_current']);
            if ($outdoorTemperature!==null && is_finite($outdoorTemperature) && $outdoorTemperature<12) {
                $text.=' (Winter: unnötigen Zusatzluftwechsel vermeiden)';
            }
        } elseif ($result['dry_indicative']) {
            $text.=' | moeglich trockene Raumluft (nur aeltere Werte)';
        }
        if ($result['humid_current']) {
            $text.=' | hohe RH aktuell in '.implode(', ',$result['humid_current']);
        } elseif ($result['humid_indicative']) {
            $text.=' | moeglich hohe RH (nur aeltere Werte)';
        }
        $result['summary']=$text;
        // Pure context. Even a CURRENT humid bathroom may only boost after a
        // separate checked temperature/absolute humidity gradient and device safety approval.
        return $result;
    }
}
