<?php
/** Read-only, multi-evidence cooking and resident-occupancy interpretation.
 * Never assumes individual phones certify people or that absence certifies an empty house.
 */
final class JVHeliosCookingAdvisor
{
    public static function normalizeResident($raw): string
    {
        if (!is_string($raw) || trim($raw)==='') return 'UNKNOWN';
        $status=strtoupper(trim($raw));
        // WLAN-home still locates a handset; an unconfirmed geofence is not a veto.
        if (str_starts_with($status,'WLAN ZUHAUSE')) return 'HOME_PHONE_HINT';
        if (str_contains($status,'WIDERSPRUCH') || str_contains($status,'UNKLAR')
            || str_contains($status,'NICHT BEST') || str_contains($status,'UNBEST')
            || str_contains($status,'ABWESEND') || str_contains($status,'ABWESENHEIT')
            || str_contains($status,'NICHT ANWESEND')) return 'UNKNOWN';
        if (str_contains($status,'FIRMA') || str_contains($status,'WORK')) return 'WORK_PHONE_HINT';
        if (str_contains($status,'ZUHAUSE') || str_contains($status,'ZU HAUSE')
            || str_contains($status,'ANWESEND') || str_contains($status,'HOME')) return 'HOME_PHONE_HINT';
        return 'UNKNOWN';
    }

    public static function analyze(array $signals, int $now, array $previous=[]): array
    {
        $residents=[];$home=[];$work=[];
        foreach (['Johannes','Carina'] as $name) {
            $r=$signals['residents'][$name]??[];
            $status=self::normalizeResident($r['status']??null);
            // The SHADOW evaluation age, not a non-changing string value, bounds usefulness.
            if (!($r['source_recent']??false)) $status='UNKNOWN';
            $residents[$name]=['phone_hint'=>$status,'raw_status'=>is_string($r['status']??null)
                ?substr($r['status'],0,85):null,'source_recent'=>(bool)($r['source_recent']??false)];
            if ($status==='HOME_PHONE_HINT') $home[]=$name;
            if ($status==='WORK_PHONE_HINT') $work[]=$name;
        }
        $occupancy=[
            'residents'=>$residents, 'home_phone_hints'=>$home,'work_phone_hints'=>$work,
            'known_home_phone_count'=>count($home),
            'house_empty_proven'=>false,'away_person_certified'=>false,
            'weekly_minimum_may_be_reduced'=>false,
            'description'=>count($home)===2?'Beide Handyhinweise zuhause':
                (count($home)===1?$home[0].': Handyhinweis zuhause':
                (count($work)===2?'Beide Handyhinweise in Firma; Hausleerstand NICHT bewiesen':
                'Anwesenheit der Bewohner nicht gesichert')),
        ];
        $light=($signals['light']['usable']??false)===true && ($signals['light']['on']??false)===true;
        $motion=false;$motions=[];
        foreach (['Wohnkueche','EG Flur'] as $n) {
            if (($signals['motions'][$n]['recent']??false)===true) {
                $motion=true; $motions[]=$n;
            }
        }
        $powerUsable=($signals['power']['usable']??false)===true;
        $watts=$powerUsable && is_numeric($signals['power']['watts']??null)
            ?(float)$signals['power']['watts']:null;
        $threshold=(int)($signals['power_threshold_w']??2000);
        $threshold=max(500,min(20000,$threshold));
        $highPower=$watts!==null && $watts>=$threshold;
        $hour=(int)(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $mealTime=($hour>=10 && $hour<15) || ($hour>=17 && $hour<22);
        $homeHint=count($home)>0;
        // Presence and clock time only corroborate physical kitchen evidence.
        $score=($light?2:0)+($motion?2:0)+($highPower?3:0)+($mealTime?1:0)+($homeHint?1:0);
        $independent=$light || $motion;
        $likely=$independent && $highPower && $score>=6
            && (($light && $motion) || ($mealTime && $homeHint && ($light || $motion)));
        $possible=$independent && !$likely && ($light&&$motion&&($mealTime||$homeHint));
        $priorUntil=max(0,(int)($previous['hold_until']??0));
        $validPrevious=$priorUntil>=$now && $priorUntil<=$now+20*60;
        $hold=$likely ? $now+15*60 : ($validPrevious ? $priorUntil : 0);
        $held=!$likely && $hold>$now;
        // The hold is merely a pending ventilation suggestion, never new evidence.
        $kind=$likely?'COOKING_LIKELY':($held?'COOKING_RECENTLY_LIKELY':
            ($possible?'COOKING_POSSIBLE':'NO_CONFIRMED_COOKING'));
        $fan=max(1,min(4,(int)($signals['current_fan_level']??2)));
        $automatic=($signals['helios_automatic_mode']??true)===true;
        $boost=($likely || $held) && $automatic && $fan<4;
        $level=$boost ? min(4,max(3,$fan+1)) : null;
        $summary=$kind==='COOKING_LIKELY'?'Kochen wahrscheinlich (mehrere unabhaengige Indizien)':
            ($held?'Kochen vor kurzem wahrscheinlich; Geruchs-Nachlauf nur als Vorschlag':
            ($possible?'Kochen moeglich, aber noch nicht ausreichend bestaetigt':
            'Kochen derzeit nicht ausreichend belegt'));
        if (($likely || $held) && !$automatic) $summary.='; manueller Helios-Modus hat Vorrang';
        elseif (($likely || $held) && $fan>=4) $summary.='; Stufe 4 bereits aktiv';
        return [
            'advice'=>[
                'classification'=>$kind,'confidence'=>$likely?'HIGH_INDICATIVE':($held?'HISTORICAL':($possible?'LOW':'NONE')),
                'power_w'=>$watts,'power_threshold_w'=>$threshold,
                'power_high'=>$highPower,'helios_automatic_mode'=>$automatic,'kitchen_light_on'=>$light,'recent_ground_floor_motion'=>$motion,
                'motion_zones'=>$motions,'meal_time'=>$mealTime,'score'=>$score,
                'occupancy'=>$occupancy,
                'suggested_fan_level'=>$level,'suggested_minutes'=>$boost?20:0,
                'potential_odor_boost'=>$boost,
                'hold_until'=>$hold?:null,
                'baseline_weekly_program_preserved'=>true,
                'combustion_pressure_safety_not_validated'=>true,
                'airflow_and_odor_removal_not_measured'=>true,
                'hardware_authorized'=>false,'observation_only'=>true,
                'summary'=>$summary,
            ],
            'state'=>['hold_until'=>$hold,'last_likely_at'=>$likely?$now:(int)($previous['last_likely_at']??0)],
        ];
    }
}
