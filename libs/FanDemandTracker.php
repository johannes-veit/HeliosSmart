<?php
/**
 * Bounded, read-only sampling of HELIOS fan stages and conservative away
 * eligibility. Observations are NOT exact elapsed run-time or occupancy proof.
 * No device command and no ventilation-off permission is produced here.
 */
final class JVHeliosFanDemandTracker
{
    public static function update(array $old,array $x,int $now):array
    {
        $rows=[];
        foreach((array)($old['samples']??[]) as $row){
            if(!is_array($row) || !is_int($row['t']??null)
                ||$row['t']<$now-30*3600 || $row['t']>$now+120)continue;
            $rows[intdiv($row['t'],300)]=$row;
        }
        $fan=$x['fan']??null;
        $validFan=is_int($fan)&&$fan>=0&&$fan<=4;
        $mode=is_int($x['mode']??null)?(int)$x['mode']:-1;
        $presence=(array)($x['occupancy']??[]);
        $people=(array)($presence['residents']??[]);
        $bothFresh=($people['Johannes']['source_recent']??false)===true
            &&($people['Carina']['source_recent']??false)===true;
        // User-selected meaning: 'no fresh HOME hint' includes in-firma,
        // abwesend and unklar. Treat it as an ECO opportunity only,
        // never assert physical absence or authorize device OFF.
        $noHomeHints=$bothFresh &&
            ($presence['both_no_home_hints_fresh']??false)===true
            &&($people['Johannes']['state']??'')!=='HOME_PHONE_HINT'
            &&($people['Carina']['state']??'')!=='HOME_PHONE_HINT';
        $activityVeto=($x['recent_house_activity']??true)!==false;
        $away=$noHomeHints && !$activityVeto;
        // A stale controller response cannot be counted as a real fan sample.
        if(($x['fan_fresh']??false)===true && $validFan
            &&in_array($mode,[0,1,2,3,4],true))
            $rows[intdiv($now,300)]=[
                't'=>$now,'f'=>$fan,'m'=>$mode,'away'=>$away,
                // Only source-backed context, never a physical occupancy
                // claim. Old 5-minute records intentionally lack this
                // context and will be counted as UNKNOWN.
                'no_home'=>$noHomeHints,'presence_fresh'=>$bothFresh,
                'wet'=>($x['wet_need']??false)===true,
                'cook'=>($x['cooking_confirmed']??false)===true,
                'cool'=>($x['cooling_confirmed']??false)===true,
            ];
        ksort($rows,SORT_NUMERIC);
        $rows=array_slice(array_values($rows),-360);
        $today=(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        $day=$today->format('Y-m-d');
        $hour=(int)$today->format('G');
        $morning=[];
        $stage4=0;$stage4Auto=0;$stage4Timed=0;$last24=0;
        $stage0=0;$stage0NoHome=0;$stage0WithHome=0;$stage0Unknown=0;
        $largeJumps=[];$last24row=null;
        $stage4AutoNoNeed=0;$stage4AutoEvidenceMissing=0;
        foreach($rows as $row){
            $t=(int)$row['t'];$f=(int)$row['f'];$m=(int)$row['m'];
            if($t>=$now-24*3600){
                $last24++;
                if($f===0) {
                    $stage0++;
                    if(($row['presence_fresh']??null)!==true)
                        $stage0Unknown++;
                    elseif(($row['no_home']??null)===true)
                        $stage0NoHome++;
                    else $stage0WithHome++;
                }
                if($last24row!==null){
                    $gap=$t-(int)$last24row['t'];
                    if($gap>0 && $gap<=8*60
                        &&abs($f-(int)$last24row['f'])>=2){
                        $need=is_bool($row['cook']??null)
                            &&is_bool($last24row['cook']??null)
                            &&is_bool($row['wet']??null)
                            &&is_bool($last24row['wet']??null);
                        $largeJumps[]=[
                            'at'=>$t,
                            'from_stage'=>(int)$last24row['f'],
                            'to_stage'=>$f,
                            'from_mode'=>(int)$last24row['m'],
                            'to_mode'=>$m,
                            'cooking_or_wet_evidence_near_transition'=>$need
                                ?(($row['cook']||$row['wet'])
                                    ||($last24row['cook']||$last24row['wet'])):null,
                            'cooling_evidence_near_transition'=>$need
                                ?(($row['cool']??false)===true
                                    ||($last24row['cool']??false)===true):null,
                            'cause_confirmed'=>false,
                        ];
                    }
                }
                $last24row=$row;
                if($f===4){
                    $stage4++;
                    if($m===0){
                        $stage4Auto++;
                        if(!is_bool($row['cook']??null)
                            ||!is_bool($row['wet']??null))
                            $stage4AutoEvidenceMissing++;
                        elseif(!$row['cook']&&!$row['wet'])
                            $stage4AutoNoNeed++;
                    } elseif($m===2||$m===3)$stage4Timed++;
                }
            }
            $local=(new DateTimeImmutable('@'.$t))
                ->setTimezone(new DateTimeZone('Europe/Berlin'));
            $h=(int)$local->format('G');
            if($local->format('Y-m-d')===$day
                &&$h>=7&&$h<12 &&$f>=3)
                $morning[]=$t;
        }
        // Three 5-minute sampling buckets with no gap > 8 minutes:
        // at least a documented morning ventilation sequence, not inferred
        // from a schedule that might never have executed.
        $run=0;$best=0;$prior=0;
        foreach($morning as $t){
            $run=$prior>0 && $t-$prior<=8*60?$run+1:1;
            $best=max($best,$run);
            $prior=$t;
        }
        $morningCompleted=$best>=3;
        // A resident may leave before the traditional 07-12 flush or
        // remain away overnight. Permit low-speed economy after an
        // ACTUALLY OBSERVED fresh ventilation quota, not a scheduled
        // event and not a phone status alone. Three consecutive 5min
        // S3+ samples OR six consecutive 5min S2+ samples within 6h.
        // After many hours of S1 the quota expires until S2/S3 has
        // run again; this avoids indefinite reduced ventilation.
        $freshVent3=0;$freshVent2=0;$lastVent3=0;$lastVent2=0;
        $bestVent3=0;$bestVent2=0;$lastAdequateVentAt=0;
        foreach($rows as $row){
            $t=(int)$row['t'];
            if($t<$now-6*3600 ||$t>$now)continue;
            $level=(int)$row['f'];
            if($level>=3){
                $freshVent3=$lastVent3>0&&$t-$lastVent3<=8*60
                    ?$freshVent3+1:1;
                $bestVent3=max($bestVent3,$freshVent3);
                $lastVent3=$t;
                if($freshVent3>=3)$lastAdequateVentAt=$t;
            }else $freshVent3=0;
            if($level>=2){
                $freshVent2=$lastVent2>0&&$t-$lastVent2<=8*60
                    ?$freshVent2+1:1;
                $bestVent2=max($bestVent2,$freshVent2);
                $lastVent2=$t;
                if($freshVent2>=6)$lastAdequateVentAt=$t;
            }else $freshVent2=0;
        }
        $recentVentilation=$lastAdequateVentAt>0
            &&$now-$lastAdequateVentAt<=4*3600;
        $awayStart=0;$awayLast=0;
        foreach(array_reverse($rows) as $row){
            if(($row['away']??false)!==true)break;
            $t=(int)$row['t'];
            if($awayLast>0 && $awayLast-$t>8*60)break;
            $awayStart=$t;$awayLast=$t;
        }
        $awayMinutes=$away && $awayStart>0
            ?max(0,(int)floor(($now-$awayStart)/60)):0;
        $rh=$x['max_room_rh']??null;
        $extract=$x['native_extract_rh']??null;
        $rhSafe=is_numeric($rh)&&(float)$rh<=65.0
            &&is_numeric($extract)&&(float)$extract<60.0
            &&(int)($x['room_humidity_count']??0)>=8;
        $validConditions=$rhSafe
            &&($x['wet_need']??false)!==true
            &&($x['cooking_confirmed']??false)!==true
            &&($x['cooling_confirmed']??false)!==true;
        // SHADOW away has no artificial daily time cutoff. Nighttime
        // absence may save electricity too. Native frost, room RH,
        // cooking, shower and actual cooling remain hard vetoes.
        // Even an earlier morning S3 cannot license unlimited S1 into
        // the night; actual ventilation evidence must remain recent.
        $safeAway=$away && $awayMinutes>=30
            &&$recentVentilation &&$validConditions;
        if(!$bothFresh)$reason='RESIDENT_SOURCE_STALE';
        elseif(!$noHomeHints)$reason='ONE_OR_BOTH_HOME_HINTS_PRESENT';
        elseif($activityVeto)$reason='IN_HOUSE_ACTIVITY_VETO';
        elseif($awayMinutes<30)$reason='NO_HOME_HINTS_NOT_STABLE_30MIN';
        elseif(!$recentVentilation)
            $reason='RECENT_VENTILATION_NOT_OBSERVED';
        elseif(!$validConditions)$reason='ROOM_HUMIDITY_OR_AIR_DEMAND_VETO';
        else $reason='NO_HOME_HINTS_LOW_DEMAND_WINDOW_OBSERVED';
        return [
            'state'=>['v'=>1,'samples'=>$rows],
            'advice'=>[
                'status'=>$reason,
                'away_low_demand_eligible'=>$safeAway,
                'both_nonhome_phone_hints_fresh'=>($presence['both_residents_nonhome_reported']??false)===true
                    &&$bothFresh,
                'both_no_home_hints_fresh'=>$noHomeHints,
                'home_hint_missing_is_not_absence_proof'=>true,
                'in_house_activity_veto'=>$activityVeto,
                'away_hint_contiguous_minutes'=>$awayMinutes,
                'morning_ventilation_observed'=>$morningCompleted,
                'morning_ventilation_samples_5min'=>$best,
                'recent_ventilation_observed'=>$recentVentilation,
                'last_adequate_ventilation_at'=>$lastAdequateVentAt?:null,
                'recent_ventilation_stage3_samples'=>$bestVent3,
                'recent_ventilation_stage2_samples'=>$bestVent2,
                'away_applies_all_day_including_night'=>true,
                'reduced_stage_authorization_requires_independent_gates'=>true,
                'off_authorized'=>false,
                'off_reason'=>'HELIOS_AND_MOISTURE_PROTECTION_REQUIRE_PROVEN_VENTILATION_PLAN',
                'stage0_samples_24h'=>$stage0,
                'stage0_no_home_hint_samples_24h'=>$stage0NoHome,
                'stage0_home_hint_samples_24h'=>$stage0WithHome,
                'stage0_presence_context_unknown_samples_24h'=>$stage0Unknown,
                'large_stage_jumps_ge2_24h'=>count($largeJumps),
                'recent_large_stage_jump_events'=>array_slice($largeJumps,-6),
                'large_jump_detection'=>'5_MIN_SAMPLES_ADJACENT_LE_8MIN',
                'zero_and_jump_counts_not_exact_runtime'=>true,
                'stage4_auto_no_wet_or_cooking_signal_samples_24h'=>$stage4AutoNoNeed,
                'stage4_auto_without_saved_demand_context_samples_24h'=>$stage4AutoEvidenceMissing,
                'stage4_auto_without_signals_is_not_proof_of_unnecessary_fan'=>true,
                'stage4_samples_24h'=>$stage4,
                'stage4_auto_samples_24h'=>$stage4Auto,
                'stage4_timed_samples_24h'=>$stage4Timed,
                'all_samples_24h'=>$last24,
                'stage4_minutes_are_not_measured'=>true,
                'sampling_period_nominal_minutes'=>5,
                'phone_hints_are_not_house_empty_proof'=>true,
            ],
        ];
    }
}
