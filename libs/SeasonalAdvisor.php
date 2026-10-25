<?php
/** Stable *advisory* for Helios stored programs: 3 normal, 4 hot summer.
 *  No hardware calls, no timers required, no seasonal guessing on forecast outage.
 */
final class JVHeliosSeasonalAdvisor
{
    public static function evaluate(array $decision, array $forecast, int $actualProgram,
        int $now, array $previous): array
    {
        $state=['candidate'=>null,'first_at'=>0,'last_at'=>0];
        $output=['status'=>'INSUFFICIENT','proposed_program'=>null,
            'recommended_program'=>null,'current_program'=>$actualProgram,
            'confirmed_hours'=>0.,'program_changed'=>false,
            'reason'=>'Keine sichere Profilentscheidung aus der 24h-Prognose'];
        if (!in_array($actualProgram,[3,4],true)) {
            $output['status']='NONCUSTOM';
            $output['reason']='Anderes Helios-Wochenprogramm aktiv; nicht ueberschreiben';
            return ['state'=>$state,'advice'=>$output];
        }
        if (!($decision['weather_usable_for_advice']??false)
            || !in_array((string)($forecast['status']??''),['READY','NO_FRESH_INDOOR_HUMIDITY'],true)
            || (int)($forecast['forecast_hours']??0)<18
            || !is_numeric($decision['indoor_temperature']??null)
            || !is_numeric($forecast['temperature_max_c']??null)) {
            return ['state'=>$state,'advice'=>$output];
        }
        $inside=(float)$decision['indoor_temperature'];
        $high=(int)($forecast['hot_hours']??0);
        $max=(float)$forecast['temperature_max_c'];
        $proposed=null;
        if ($high>=4 && $max>=28 && $inside>=23.5) $proposed=4;
        elseif ($high<=1 && $max<=23.0 && $inside<=24.0) $proposed=3;
        $output['proposed_program']=$proposed;
        if ($proposed===null) {
            $output['status']='TRANSITION';
            $output['reason']='Wechselwetter: bisheriges Helios-Profil behalten';
            return ['state'=>$state,'advice'=>$output];
        }
        if ($proposed===$actualProgram) {
            $output['status']='ALREADY_MATCHES';
            $output['recommended_program']=$actualProgram;
            $output['reason']='Bestehendes Helios-Wochenprogramm passt zur 24h-Prognose';
            return ['state'=>$state,'advice'=>$output];
        }
        $oldCandidate=(int)($previous['candidate']??0);
        $last=(int)($previous['last_at']??0);
        $first=(int)($previous['first_at']??0);
        // Preserve the dwell only for consecutive evaluations; stale history is not proof.
        if ($oldCandidate!==$proposed || $first<=0 || $first>$now
            || $last>$now || $now-$last>2*3600) $first=$now;
        $state=['candidate'=>$proposed,'first_at'=>$first,'last_at'=>$now];
        $elapsed=max(0,$now-$first);
        $output['confirmed_hours']=round($elapsed/3600,2);
        if ($elapsed<4*3600) {
            $output['status']='PENDING_STABILITY';
            $output['reason']='Profilwechsel nur vormerken: Prognose muss vier Stunden stabil bleiben';
        } else {
            $output['status']='ADVISORY_READY';
            $output['recommended_program']=$proposed;
            $output['reason']='Profil '.($proposed===4?'4 (heisser Sommer)':'3 (Normal-/Winterprogramm)')
                .' seit vier Stunden prognostisch begruendet; nur Beratung';
        }
        return ['state'=>$state,'advice'=>$output];
    }
}
