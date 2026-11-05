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
            ];
        ksort($rows,SORT_NUMERIC);
        $rows=array_slice(array_values($rows),-360);
        $today=(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'));
        $day=$today->format('Y-m-d');
        $hour=(int)$today->format('G');
        $morning=[];
        $stage4=0;$stage4Auto=0;$stage4Timed=0;$last24=0;
        foreach($rows as $row){
            $t=(int)$row['t'];$f=(int)$row['f'];$m=(int)$row['m'];
            if($t>=$now-24*3600){
                $last24++;
                if($f===4){
                    $stage4++;
                    if($m===0)$stage4Auto++;
                    elseif($m===2||$m===3)$stage4Timed++;
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
        // Evening non-home hints are meaningful too; keep night quiet
        // policies separate from daytime absence. No overnight OFF.
        $duringDay=$hour>=9 && $hour<21;
        $safeAway=$away && $awayMinutes>=30
            &&$morningCompleted &&$duringDay &&$validConditions;
        if(!$bothFresh)$reason='RESIDENT_SOURCE_STALE';
        elseif(!$noHomeHints)$reason='ONE_OR_BOTH_HOME_HINTS_PRESENT';
        elseif($activityVeto)$reason='IN_HOUSE_ACTIVITY_VETO';
        elseif($awayMinutes<30)$reason='NO_HOME_HINTS_NOT_STABLE_30MIN';
        elseif(!$morningCompleted)$reason='MORNING_FLUSH_NOT_OBSERVED';
        elseif(!$duringDay)$reason='OUTSIDE_DAYTIME_WINDOW';
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
                'reduced_stage_authorization_requires_independent_gates'=>true,
                'off_authorized'=>false,
                'off_reason'=>'HELIOS_AND_MOISTURE_PROTECTION_REQUIRE_PROVEN_VENTILATION_PLAN',
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
