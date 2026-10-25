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
            || str_contains($status,'NICHT BEST') || str_contains($status,'UNBEST')) return 'UNKNOWN';
        // 'Abwesend' ist ein eigener angezeigter SHADOW-Zustand, nicht UNKNOWN.
        // Ein solcher Meldestatus ist KEIN physischer Nachweis eines leeren Hauses.
        if (str_contains($status,'FIRMA') || str_contains($status,'WORK')) return 'WORK_PHONE_HINT';
        if (str_contains($status,'ABWESEND') || str_contains($status,'ABWESENHEIT')
            || str_contains($status,'NICHT ANWESEND') || str_contains($status,'NICHT ZUHAUSE')
            || str_contains($status,'AUSSER HAUS') || $status==='AWAY') return 'AWAY_REPORTED';
        if (str_contains($status,'ZUHAUSE') || str_contains($status,'ZU HAUSE')
            || str_contains($status,'ANWESEND') || str_contains($status,'HOME')) return 'HOME_PHONE_HINT';
        return 'UNKNOWN';
    }

    public static function analyze(array $signals, int $now, array $previous=[]): array
    {
        $residents=[];$home=[];$work=[];$away=[];$unknown=[];
        $stateLabels=[
            'HOME_PHONE_HINT'=>'zuhause (Handyhinweis)',
            'WORK_PHONE_HINT'=>'in Firma (Handyhinweis)',
            'AWAY_REPORTED'=>'abwesend (gemeldet)',
            'UNKNOWN'=>'unklar',
        ];
        foreach (['Johannes','Carina'] as $name) {
            $r=$signals['residents'][$name]??[];
            $status=self::normalizeResident($r['status']??null);
            // Never trust a previously reported state if source is no longer recent.
            if (!($r['source_recent']??false)) $status='UNKNOWN';
            $residents[$name]=['phone_hint'=>$status,'state'=>$status,
                'display'=>$stateLabels[$status],
                'raw_status'=>is_string($r['status']??null)
                ?substr($r['status'],0,85):null,
                'source_recent'=>(bool)($r['source_recent']??false)];
            if ($status==='HOME_PHONE_HINT') $home[]=$name;
            elseif ($status==='WORK_PHONE_HINT') $work[]=$name;
            elseif ($status==='AWAY_REPORTED') $away[]=$name;
            else $unknown[]=$name;
        }
        $bothAway=count($away)===2;
        $bothNotHome=count($away)+count($work)===2;
        $short='Johannes '.$stateLabels[$residents['Johannes']['state']]
            .' / Carina '.$stateLabels[$residents['Carina']['state']];
        $occupancy=[
            'residents'=>$residents, 'home_phone_hints'=>$home,
            'work_phone_hints'=>$work, 'away_status_reports'=>$away,
            'unknown_residents'=>$unknown,
            'known_home_phone_count'=>count($home),
            'known_away_status_count'=>count($away),
            'both_residents_away_reported'=>$bothAway,
            'both_residents_nonhome_reported'=>$bothNotHome,
            'home_presence_hint'=>count($home)>0,
            // 'ABWESEND' differs from UNKNOWN, but does not prove no other person is home.
            'house_empty_proven'=>false,'away_person_certified'=>false,
            'weekly_minimum_may_be_reduced'=>false,
            'description'=>$short,
            'status'=>$bothAway?'BOTH_AWAY_REPORTED':
                (count($home)===2?'BOTH_HOME_HINT':
                (count($home)===1?'ONE_HOME_HINT':
                ($bothNotHome?'BOTH_NONHOME_REPORTED':'MIXED_OR_UNKNOWN'))),
        ];
        $light=($signals['light']['usable']??false)===true && ($signals['light']['on']??false)===true;
        $motion=false;$motions=[];
        foreach (['Wohnkueche','EG Flur'] as $n) {
            if (($signals['motions'][$n]['recent']??false)===true) {
                $motion=true; $motions[]=$n;
            }
        }
        $kitchenMotion=in_array('Wohnkueche',$motions,true);
        $powerUsable=($signals['power']['usable']??false)===true;
        $watts=$powerUsable && is_numeric($signals['power']['watts']??null)
            ?(float)$signals['power']['watts']:null;
        $threshold=(int)($signals['power_threshold_w']??2000);
        $threshold=max(500,min(20000,$threshold));
        $highPower=$watts!==null && $watts>=$threshold;
        // Household power >2 kW may equally be a water heater, dryer or EV charger.
        // Require at least two distinct high samples >=60 s apart, <=8 min apart.
        // This does not claim the power was continuous between samples.
        $lastHigh=(int)($previous['last_high_sample_at']??0);
        // On the real SymBox every qualifying sample must use a NEW input
        // update, not a reread of the very same stale power register.
        $powerUpdated=$signals['power']['sample_updated_at']??null;
        $hasSampleTime=is_int($powerUpdated) && $powerUpdated>0;
        $previousUpdate=(int)($previous['last_high_updated_at']??0);
        $distinctUpdate=!$hasSampleTime || ($powerUpdated>$previousUpdate
            && $powerUpdated<=$now+120 && $now-$powerUpdated<=360);

        $since=(int)($previous['high_power_since']??0);
        $samples=max(0,min(50,(int)($previous['high_power_samples']??0)));
        $reusable=$highPower && $lastHigh>0 && $since>0 && $lastHigh<=$now
            && $since<=$lastHigh && $now-$lastHigh<=8*60;
        $newHighSampleAt=0;
        if ($highPower) {
            if (!$reusable) {
                $since=$now; $samples=1; $newHighSampleAt=$now;
            } elseif ($now-$lastHigh>=60 && $distinctUpdate) {
                $samples=min(50,$samples+1); $newHighSampleAt=$now;
            } else {
                // Early re-evaluations may not postpone the next independent sample.
                $newHighSampleAt=$lastHigh;
            }
        } else {
            // Induction hobs and thermostatic ovens cycle below the threshold.
            // A brief low sample must not discard earlier REAL high-power
            // source updates while a kitchen event is still underway.
            if (!$kitchenMotion || $lastHigh<=0 || $now-$lastHigh>12*60) {
                $since=0; $samples=0;
            } else {
                // Preserve the original source-backed high event across the
                // trough between induction hob cycles.
                $newHighSampleAt=$lastHigh;
            }
        }
        $powerSustained=$highPower && $samples>=2 && $now-$since>=60;
        $baseline=$previous['baseline_power_w']??null;
        $baselineAt=(int)($previous['baseline_at']??0);
        $baselineValid=is_numeric($baseline) && $baselineAt>0 && $baselineAt<=$now
            && $now-$baselineAt<=3*3600;
        $delta=$baselineValid && $watts!==null?(float)$watts-(float)$baseline:null;
        $powerRisePlausible= !$baselineValid || ($delta!==null && $delta>=700);
        // Learn true idle power only outside an active kitchen episode. In the
        // old logic every 1.5 kW reading during cooking raised the baseline,
        // making later genuine cooking spikes fail the +700 W condition.
        if ($watts!==null && $watts<=$threshold-300 && !$light && !$kitchenMotion) {
            // A low-consumption value is the ONLY safe time to update baseline.
            $baseline=(float)$watts;
            $baselineAt=$now;
            $baselineValid=true;
        }
        // Alternative for moderate, intermittent cooking (oven, low induction):
        // TWO original distinct Tasmota updates, meaningful increase above a
        // learned idle baseline, extended kitchen light and real motion.
        // No power sample timestamp => no moderate-load proof.
        $lightMinutes=max(0,min(1440,(int)($signals['light']['on_minutes']??0)));
        $moderateThreshold=max(1400,$threshold-400);
        $moderate=$watts!==null && $watts>=$moderateThreshold;
        $moderateUpdate=(int)($previous['last_moderate_updated_at']??0);
        $moderateLast=(int)($previous['last_moderate_sample_at']??0);
        $moderateSince=(int)($previous['moderate_since']??0);
        $moderateCount=max(0,min(10,(int)($previous['moderate_samples']??0)));
        $distinctModerate=$hasSampleTime && $powerUpdated>$moderateUpdate
            && $powerUpdated<=$now+120 && $now-$powerUpdated<=360;
        if ($moderate && $distinctModerate
            && ($moderateLast<=0 || $now-$moderateLast>=60)) {
            if ($moderateLast<=0 || $moderateLast>$now || $now-$moderateLast>20*60
                || $moderateSince<=0 || $now-$moderateSince>20*60) {
                $moderateCount=1;$moderateSince=$now;
            } else $moderateCount=min(10,$moderateCount+1);
            $moderateLast=$now;
            $moderateUpdate=(int)$powerUpdated;
        }
        $moderateMultiple=$moderate && $moderateCount>=2
            && $moderateSince>0 && $now-$moderateSince>=60
            && $now-$moderateLast<=6*60 && $now-$moderateSince<=20*60;
        $hour=(int)(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $mealTime=($hour>=10 && $hour<15) || ($hour>=17 && $hour<22);
        $homeHint=count($home)>0;
        // Presence and clock time only corroborate physical kitchen evidence.
        $hallMotion=in_array('EG Flur',$motions,true);
        $score=($light?2:0)+($kitchenMotion?3:0)+($hallMotion?1:0)
            +($powerSustained?3:($highPower?1:0))+($mealTime?1:0)+($homeHint?1:0);
        // A kitchen light + high total power, or corridor-only motion,
        // cannot be sufficient: at least a real kitchen-area motion is required.
        $strongLikely=$powerSustained && $powerRisePlausible && $kitchenMotion
            && ($light || ($mealTime && $homeHint));
        $moderateLikely=!$strongLikely && $moderateMultiple && $baselineValid
            && $delta!==null && $delta>=250 && $light && $lightMinutes>=8
            && $kitchenMotion && $mealTime;
        $likely=$strongLikely || $moderateLikely;
        $possible=!$likely && (($kitchenMotion && $light)
            || ($highPower && ($kitchenMotion || ($hallMotion && $light))));
        $priorUntil=max(0,(int)($previous['hold_until']??0));
        $validPrevious=$priorUntil>=$now && $priorUntil<=$now+20*60;
        $hold=$likely ? $now+15*60 : ($validPrevious ? $priorUntil : 0);
        $held=!$likely && $hold>$now;
        // The hold is merely a pending ventilation suggestion, never new evidence.
        $previousKind=(string)($previous['last_likely_kind']??'');
        $activityKind=$moderateLikely
            || ($held && $previousKind==='COOKING_ACTIVITY_LIKELY');
        $kind=$moderateLikely?'COOKING_ACTIVITY_LIKELY':
            ($strongLikely?'COOKING_LIKELY':
            ($held?($activityKind?'COOKING_ACTIVITY_RECENT':'COOKING_RECENTLY_LIKELY'):
            ($possible?'COOKING_POSSIBLE':'NO_CONFIRMED_COOKING')));
        $fan=max(1,min(4,(int)($signals['current_fan_level']??2)));
        $automatic=($signals['helios_automatic_mode']??true)===true;
        $residentMismatch=$bothNotHome && $likely;
        $occupancy['kitchen_activity_conflicts_with_nonhome_reports']=$residentMismatch;
        $occupancy['independent_kitchen_motion_can_indicate_guests']=true;
        $boost=($likely || $held) && $automatic && $fan<($activityKind?3:4);
        $level=$boost ? ($activityKind?3:min(4,max(3,$fan+1))) : null;
        $summary=$activityKind && $likely
            ?'Kochen wahrscheinlich: wiederholte moderate Leistung und laengere Kuechenaktivitaet'
            :($kind==='COOKING_LIKELY'?'Kochen wahrscheinlich (mehrere unabhaengige Indizien)':
            ($held?'Kochen vor kurzem wahrscheinlich; Geruchs-Nachlauf nur als Vorschlag':
            ($possible?'Kochen moeglich, aber noch nicht ausreichend bestaetigt':
            'Kochen derzeit nicht ausreichend belegt')));
        if ($residentMismatch) $summary.='; Bewohner abwesend/in Firma gemeldet, dennoch Kuechenaktivitaet erkannt';
        if (($likely || $held) && !$automatic) $summary.='; manueller Helios-Modus hat Vorrang';
        elseif (($likely || $held) && $fan>=4) $summary.='; Stufe 4 bereits aktiv';
        return [
            'advice'=>[
                'classification'=>$kind,'confidence'=>$likely?'HIGH_INDICATIVE':($held?'HISTORICAL':($possible?'LOW':'NONE')),
                'power_w'=>$watts,'power_threshold_w'=>$threshold,
                 'power_high'=>$highPower, 'power_multiple_samples'=>$powerSustained,
                'power_high_samples'=>$samples,
                'power_moderate_threshold_w'=>$moderateThreshold,
                'power_moderate_samples'=>$moderateCount,
                'power_moderate_distinct_updates'=>$moderateMultiple,
                'kitchen_light_on_minutes'=>$lightMinutes,
                'moderate_activity_based'=>$activityKind,
                'missing_evidence'=>$likely?[]:array_values(array_filter([
                    !$kitchenMotion?'NO_RECENT_KITCHEN_MOTION':null,
                    !$light?'KITCHEN_LIGHT_OFF':null,
                    $light && $lightMinutes<8?'LIGHT_DURATION_TOO_SHORT_FOR_MODERATE':null,
                    !$powerUsable?'POWER_SOURCE_STALE_OR_MISSING':null,
                    !$powerSustained?'LESS_THAN_TWO_HIGH_POWER_SAMPLES':null,
                    !$moderateMultiple?'LESS_THAN_TWO_MODERATE_POWER_SAMPLES':null,
                    $baselineValid && $delta!==null && $delta<250?'LOW_POWER_DELTA':null,
                    !$baselineValid?'NO_IDLE_BASELINE_FOR_MODERATE':null,
                ])),
                'power_baseline_w'=>$baselineValid?round((float)$baseline,1):null,
                'power_delta_from_baseline_w'=>$delta===null?null:round($delta,1),
                'power_rise_supported'=>$powerRisePlausible,
                'helios_automatic_mode'=>$automatic,'kitchen_light_on'=>$light,'recent_ground_floor_motion'=>$motion,
                'motion_zones'=>$motions,'kitchen_motion_recent'=>$kitchenMotion,
                'meal_time'=>$mealTime,'score'=>$score,
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
            'state'=>['hold_until'=>$hold,'last_likely_at'=>$likely?$now:(int)($previous['last_likely_at']??0),
                'high_power_since'=>$since,
                'last_high_sample_at'=>$newHighSampleAt,
                'last_high_updated_at'=>$highPower && $hasSampleTime
                    ? ($newHighSampleAt===$now ? (int)$powerUpdated : $previousUpdate):$previousUpdate,
                'high_power_samples'=>$samples,
                'moderate_since'=>$moderateSince,
                'last_moderate_sample_at'=>$moderateLast,
                'last_moderate_updated_at'=>$moderateUpdate,
                'moderate_samples'=>$moderateCount,
                'last_likely_kind'=>$likely
                    ?($moderateLikely?'COOKING_ACTIVITY_LIKELY':'COOKING_LIKELY')
                    :$previousKind,
                'baseline_power_w'=>$baselineValid?$baseline:null,
                'baseline_at'=>$baselineValid?$baselineAt:0],
        ];
    }
}
