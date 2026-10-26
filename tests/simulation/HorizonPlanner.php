<?php
require_once __DIR__.'/BuildingResponse.php';
/**
 * Strictly offline, non-actuating rolling-horizon cooling window research.
 * 24-48 h supply temperature by stage, fan curves, solar gains, thermal mass
 * and heat losses are all EXPLICIT ASSUMPTIONS until independently measured.
 * Greedy whole-horizon cost search, not a certified global optimum/MPC.
 */
final class JVHeliosHorizonPlanner
{
    private static function invalid(string $why):array
    {
        return [
            'status'=>$why,'offline_only'=>true,'hardware_authorized'=>false,
            'weekly_program_modified'=>false,'bypass_command_issued'=>false,
            'supply_forecast_is_real'=>false,'optimality_certified'=>false,
            'schedule'=>[],'candidate_schedule'=>[],
            'note'=>'Research scenario only; no Helios command or measured air volume',
        ];
    }

    private static function weatherValid(array $hours,int $now,int $count,array $stages):bool
    {
        if (count($hours)!==$count) return false;
        foreach ($hours as $i=>$h) {
            if (!is_array($h) || (int)($h['at']??0)!==$now+$i*3600
                || ($h['confidence']??'')!=='OK') return false;
            foreach (['outside_c','outside_dewpoint_c','solar_w','internal_w'] as $key) {
                if (!isset($h[$key])||!is_numeric($h[$key])
                    ||!is_finite((float)$h[$key])) return false;
            }
            if ((float)$h['outside_c']< -45||(float)$h['outside_c']>55
                ||(float)$h['outside_dewpoint_c']< -45
                ||(float)$h['outside_dewpoint_c']>(float)$h['outside_c']+0.4
                ||(float)$h['solar_w']<0||(float)$h['solar_w']>12000
                ||(float)$h['internal_w']<0||(float)$h['internal_w']>6000
                ||!is_array($h['supply_c_by_stage']??null)) return false;
            foreach ($stages as $stage=>$profile) {
                if (!isset($h['supply_c_by_stage'][$stage])
                    ||!is_numeric($h['supply_c_by_stage'][$stage])
                    ||(float)$h['supply_c_by_stage'][$stage]< -20
                    ||(float)$h['supply_c_by_stage'][$stage]>50) return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed>|null */
    private static function run(array $hours,array $schedule,array $input,array $variant=[]):?array
    {
        $profiles=$input['stages'];
        $building=$input['building'];
        $t=(float)$input['current_room_c'];
        $first=$t;
        $coolest=(float)$input['coolest_room_c'];
        $floor=(float)$input['coolest_room_floor_c'];
        $target=(float)$input['target_c'];
        $base=(int)$input['baseline_stage'];
        $cost=0.;$degreeHours=0.;$fanWh=0.;$increment=0.;$peak=$t;
        foreach ($hours as $i=>$original) {
            $h=$original;
            $h['solar_w']=(float)$h['solar_w']*(float)($variant['solar_multiplier']??1.);
            $h['internal_w']=(float)$h['internal_w']*(float)($variant['internal_multiplier']??1.);
            $building['capacity_wh_per_k']=(float)$input['building']['capacity_wh_per_k']
                *(float)($variant['capacity_multiplier']??1.);
            $building['envelope_w_per_k']=(float)$input['building']['envelope_w_per_k']
                *(float)($variant['envelope_multiplier']??1.);
            if (isset($schedule[$i])) {
                // Forecasted humidity is upstream of the enthalpy exchanger;
                // do NOT claim an exact supply humidity or dry-air exchange.
                $outDp=(float)$h['outside_dewpoint_c']
                    +(float)($variant['dewpoint_offset_c']??0);
                $inDp=(float)$input['indoor_dewpoint_c'];
                $stage=(int)$schedule[$i];
                $supply=(float)$h['supply_c_by_stage'][$stage]
                    +(float)($variant['supply_offset_c']??0);
                $cool=$t-$supply;
                if ($outDp>16 || $outDp>$inDp-1.0 || $cool<2.2
                    || $supply<17 || $coolest+($t-$first)<$floor+0.3)
                    return null;
            }
            $profilesModified=$profiles;
            if (isset($variant['flow_multiplier'])) {
                foreach ($profilesModified as &$prof)
                    $prof['flow_m3h']*=(float)$variant['flow_multiplier'];
                unset($prof);
            }
            if (isset($variant['supply_offset_c'])) {
                foreach ($h['supply_c_by_stage'] as &$supplyC)
                    $supplyC+=(float)$variant['supply_offset_c'];
                unset($supplyC);
            }
            $result=JVHeliosBuildingResponse::hour($t,$h,$profilesModified,
                $building,$base,$schedule[$i]??null);
            if ($result===null) return null;
            $t=$result['temperature_c'];
            if (!is_finite($t) || $t<10 || $t>43) return null;
            if (isset($schedule[$i]) && $coolest+($t-$first)<$floor) return null;
            $fanWh+=$result['fan_wh'];
            $increment+=$result['incremental_fan_wh'];
            $over=max(0.,$t-$target);
            $degreeHours+=$over;
            $cost+=100.*$over*$over;
            // Actuation model avoids heating/cooling below comfortable air floor.
            $under=max(0.,$floor+2.-$t);
            $cost+=200.*$under*$under;
            $cost+=0.12*$result['incremental_fan_wh'];
            if (isset($schedule[$i])) $cost+=1.5;
            $peak=max($peak,$t);
        }
        return ['cost'=>$cost,'ending_c'=>$t,'max_c'=>$peak,
            'over_target_degree_hours'=>$degreeHours,
            'fan_wh'=>$fanWh,'additional_fan_wh'=>$increment];
    }

    public static function plan(array $input):array
    {
        $now=(int)($input['now']??0);
        $hours=(array)($input['hours']??[]);
        $count=count($hours);
        $base=(int)($input['baseline_stage']??0);
        $stages=(array)($input['stages']??[]);
        $building=(array)($input['building']??[]);
        if ($now<=0 || $now%3600!==0 || $count<24 || $count>48
            || ($input['weather_verified']??false)!==true
            || ($input['source_quality']??'')!=='SYNTHETIC_ASSUMPTIONS'
            || ($input['room_sensors_fresh']??false)!==true
            || (int)($input['fresh_living_rooms']??0)<3
            || (int)($input['fresh_humidity_rooms']??0)<5
            || $base<1 || $base>3) return self::invalid('INSUFFICIENT_OR_UNCALIBRATED_INPUT');
        foreach (['current_room_c','coolest_room_c','coolest_room_floor_c',
            'indoor_dewpoint_c','target_c'] as $key)
            if (!isset($input[$key])||!is_numeric($input[$key])
                ||!is_finite((float)$input[$key])) return self::invalid('INVALID_CLIMATE');
        foreach (['capacity_wh_per_k','envelope_w_per_k'] as $key)
            if (!isset($building[$key])||!is_numeric($building[$key])
                ||!is_finite((float)$building[$key])) return self::invalid('INVALID_BUILDING');
        if ((float)$building['capacity_wh_per_k']<500
            ||(float)$building['capacity_wh_per_k']>500000
            ||(float)$building['envelope_w_per_k']<0
            ||(float)$building['envelope_w_per_k']>1500
            ||(float)$input['current_room_c']<18
            ||(float)$input['current_room_c']>37
            ||(float)$input['target_c']<22
            ||(float)$input['target_c']>28
            ||(float)$input['coolest_room_c']<(float)$input['coolest_room_floor_c'])
            return self::invalid('BUILDING_OR_ROOM_GUARD');
        if (count($stages)<2 || !isset($stages[$base]))
            return self::invalid('UNMEASURED_STAGE_CURVES');
        foreach ($stages as $index=>$p) {
            if (!is_numeric($index)||(int)$index<1||(int)$index>4
                ||!is_array($p)||!isset($p['flow_m3h'],$p['fan_w'])
                ||!is_numeric($p['flow_m3h'])||!is_numeric($p['fan_w'])
                ||(float)$p['flow_m3h']<20||(float)$p['flow_m3h']>800
                ||(float)$p['fan_w']<0||(float)$p['fan_w']>3000)
                return self::invalid('INVALID_ASSUMED_FAN_PROFILE');
        }
        if (!self::weatherValid($hours,$now,$count,$stages))
            return self::invalid('HOURLY_FORECAST_INCOMPLETE');
        $baseline=self::run($hours,[],$input);
        if ($baseline===null) return self::invalid('INVALID_BASELINE_SIMULATION');
        $maxTemp=max(array_column($hours,'outside_c'));
        $hotDay=$maxTemp>=30;
        $maxEpisodes=min(3,max(0,(int)($input['max_episodes']??3)));
        $minGap=max(3,min(12,(int)($input['min_gap_hours']??3)));
        $selected=[];$best=$baseline;
        for ($e=0;$e<$maxEpisodes;$e++) {
            $candidate=null;$candidateResult=null;$bestGain=0.5;
            foreach ($hours as $i=>$h) {
                $localHour=(int)(new DateTimeImmutable('@'.(int)$h['at']))
                    ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
                $window=$localHour>=19 || $localHour<10;
                // For daytime EWT cooling: allow only when estimated house
                // is already substantially overheated, not all afternoon.
                $dayBenefit=($best['max_c']>=(float)$input['target_c']+1.5);
                if (!$window && !$dayBenefit) continue;
                if (!$hotDay && !$dayBenefit) continue;
                if (isset($selected[$i])) continue;
                foreach ($selected as $j=>$_) if (abs($i-$j)<$minGap) continue 2;
                foreach ($stages as $stage=>$p) {
                    $stage=(int)$stage;
                    if ($stage<=$base) continue;
                    $schedule=$selected;$schedule[$i]=$stage;
                    ksort($schedule,SORT_NUMERIC);
                    $r=self::run($hours,$schedule,$input);
                    if ($r===null) continue;
                    $benefit=$best['cost']-$r['cost'];
                    if ($benefit>$bestGain) {
                        $bestGain=$benefit;
                        $candidate=$schedule;
                        $candidateResult=$r;
                    }
                }
            }
            if ($candidate===null) break;
            $selected=$candidate;$best=$candidateResult;
        }
        $pessimistic=[
            'solar_multiplier'=>1.25,'internal_multiplier'=>1.2,
            'capacity_multiplier'=>1.3,'envelope_multiplier'=>1.15,
            'flow_multiplier'=>0.8,'supply_offset_c'=>1.5,
            'dewpoint_offset_c'=>1.5,
        ];
        $sensBase=self::run($hours,[],$input,$pessimistic);
        $sensPlan=self::run($hours,$selected,$input,$pessimistic);
        $robust=$sensBase!==null && $sensPlan!==null
            && $sensPlan['over_target_degree_hours']<=$sensBase['over_target_degree_hours']+0.01;
        $items=[];
        foreach ($selected as $idx=>$stage) {
            $items[]=['at'=>(int)$hours[$idx]['at'],
                'local'=>(new DateTimeImmutable('@'.(int)$hours[$idx]['at']))
                    ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i'),
                'hypothetical_stage'=>(int)$stage,'duration_minutes'=>30,
                'estimated_supply_c'=>(float)$hours[$idx]['supply_c_by_stage'][$stage],
                'outside_dewpoint_c'=>(float)$hours[$idx]['outside_dewpoint_c'],
                'requires_live_recheck'=>true];
        }
        $ret=self::invalid('SCENARIO_PLANNED_NOT_VALIDATED');
        $ret['horizon_hours']=$count;
        $ret['forecast_outdoor_peak_c']=round($maxTemp,1);
        $ret['candidate_schedule']=$items;
        $ret['schedule']=$robust?$items:[];
        $ret['uncertainty_test_passed']=$robust;
        $ret['nominal']=[
            'baseline_over_target_degree_hours'=>round($baseline['over_target_degree_hours'],3),
            'candidate_over_target_degree_hours'=>round($best['over_target_degree_hours'],3),
            'baseline_peak_c'=>round($baseline['max_c'],2),
            'candidate_peak_c'=>round($best['max_c'],2),
            'extra_fan_wh'=>round($best['additional_fan_wh']-$baseline['additional_fan_wh'],1),
            'episode_count'=>count($items),
            'model_objective_improvement'=>round($baseline['cost']-$best['cost'],2),
        ];
        $ret['uncertainty']=[
            'scenario'=>'hot solar load +25%, internal +20%, effective mass +30%, envelope +15%, flow -20%, supply +1.5 K, dewpoint +1.5 K',
            'outcome'=>$sensPlan===null?'PROTECTION_NOT_CONFIRMED':
                ($robust?'DIRECTIONAL_ADVANTAGE_IN_STRESS_SCENARIO':'MODEL_SENSITIVE'),
            'temperatures_not_guaranteed'=>true,
            'latent_humidity_transport_unmodelled'=>true,
        ];
        $ret['method']='GREEDY_WHOLE_HORIZON_SCENARIO_OPTIMIZATION';
        $ret['baseline_always_operates']=true;
        $ret['reason']=$robust?'Hypothetical schedule survives chosen stress case; measurements still missing'
            :'Hypothetical optimum not stable enough to publish as a recommended schedule';
        return $ret;
    }
}
