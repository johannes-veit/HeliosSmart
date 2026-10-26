<?php
/**
 * RESEARCH ONLY. Coupled multi-room sensible heat model.
 * No IP-Symcon functions, no ventilation actuator, no actual airflow inference.
 * Solar loads, inter-room conductance, each supply share, heat capacity and
 * stage-specific fan values are assumed until real commissioning.
 *
 * Formula per zone i, in Watts:
 * C_i dT_i/dt = UA_i(Tout-T_i)
 *   + 0.34 V_stage share_i(Tsupply_stage-T_i)
 *   + solar_share_i Psolar + internal_share_i Pinternal
 *   + sum_j G_ij(T_j-T_i).
 *
 * Symmetric coupling conserves energy between rooms. All zones are stepped
 * SIMULTANEOUSLY in five-minute increments, never sequentially, and reject
 * a numerically unstable timestep.
 */
final class JVHeliosMultiZoneResponse
{
    public static function validate(array $zones, array $links, array $stages):?string
    {
        if (count($zones)<2 || count($zones)>14 || count($stages)<2
            || count($stages)>4 || count($links)>60) return 'INVALID_SIZE';
        $ventShare=0.;$solarShare=0.;$internalShare=0.;
        foreach ($zones as $name=>$z) {
            if (!is_string($name) || $name==='' || strlen($name)>100 || !is_array($z))
                return 'INVALID_ZONE_NAME';
            foreach (['initial_c','capacity_wh_per_k','envelope_w_per_k',
                'supply_share','solar_share','internal_share','floor_c','target_c'] as $k)
                if (!isset($z[$k])||!is_numeric($z[$k])
                    ||!is_finite((float)$z[$k])) return 'MISSING_ZONE_PARAMETER';
            if ($z['initial_c']<10 || $z['initial_c']>38
                || $z['capacity_wh_per_k']<500 || $z['capacity_wh_per_k']>200000
                || $z['envelope_w_per_k']<0 || $z['envelope_w_per_k']>600
                || $z['floor_c']<15 || $z['floor_c']>25
                || $z['target_c']<21 || $z['target_c']>30
                || $z['floor_c']>$z['target_c']
                || $z['supply_share']<0 || $z['supply_share']>1
                || $z['solar_share']<0 || $z['solar_share']>1
                || $z['internal_share']<0 || $z['internal_share']>1) return 'BAD_ZONE_RANGE';
            $ventShare+=(float)$z['supply_share'];
            $solarShare+=(float)$z['solar_share'];
            $internalShare+=(float)$z['internal_share'];
        }
        if (abs($ventShare-1)>0.0001 || abs($solarShare-1)>0.0001
            || abs($internalShare-1)>0.0001) return 'SHARES_MUST_SUM_TO_ONE';
        foreach ($stages as $idx=>$p) {
            if (!is_numeric($idx) || (int)$idx<1 || (int)$idx>4 || !is_array($p)
                || !isset($p['flow_m3h'],$p['fan_w'])
                || !is_numeric($p['flow_m3h']) || !is_numeric($p['fan_w'])
                || !is_finite((float)$p['flow_m3h'])||!is_finite((float)$p['fan_w'])
                || $p['flow_m3h']<20 || $p['flow_m3h']>800
                || $p['fan_w']<0 || $p['fan_w']>1000) return 'INVALID_STAGE_ASSUMPTION';
        }
        $pairs=[];
        foreach ($links as $edge) {
            if (!is_array($edge) || !isset($edge['a'],$edge['b'],$edge['g_w_per_k'])
                || !is_string($edge['a']) || !is_string($edge['b'])
                || !array_key_exists($edge['a'],$zones)
                || !array_key_exists($edge['b'],$zones)
                || $edge['a']===$edge['b']
                || !is_numeric($edge['g_w_per_k'])
                || !is_finite((float)$edge['g_w_per_k'])
                || $edge['g_w_per_k']<0 || $edge['g_w_per_k']>600)
                return 'INVALID_COUPLING';
            $names=[$edge['a'],$edge['b']]; sort($names,SORT_STRING);
            $id=implode('|',$names);
            if(isset($pairs[$id])) return 'DUPLICATE_COUPLING';
            $pairs[$id]=true;
        }
        return null;
    }

    /**
     * Simulate exactly 1 h: up to 30min additional ventilation then baseline.
     * $variant is an OFFLINE sensitivity vector, not sensor observations.
     */
    public static function hour(array $temperatures,array $zones,array $links,
        array $stages,array $weather,int $baselineStage,?int $boostStage=null,
        array $variant=[]):?array
    {
        if (self::validate($zones,$links,$stages)!==null
            || !isset($stages[$baselineStage])
            || ($boostStage!==null && (!isset($stages[$boostStage])
                ||$boostStage<=$baselineStage))) return null;
        foreach (['outside_c','solar_w','internal_w'] as $k)
            if (!isset($weather[$k]) || !is_numeric($weather[$k])
                || !is_finite((float)$weather[$k])) return null;
        if (!is_array($weather['supply_c_by_stage']??null)
            || $weather['outside_c']< -45 || $weather['outside_c']>55
            || $weather['solar_w']<0 || $weather['solar_w']>12000
            || $weather['internal_w']<0 || $weather['internal_w']>6000) return null;
        foreach ($stages as $stage=>$_) {
            if (!isset($weather['supply_c_by_stage'][$stage])
                || !is_numeric($weather['supply_c_by_stage'][$stage])
                || !is_finite((float)$weather['supply_c_by_stage'][$stage])
                || $weather['supply_c_by_stage'][$stage]< -20
                || $weather['supply_c_by_stage'][$stage]>50) return null;
        }
        if (count($temperatures)!==count($zones)) return null;
        $t=[];
        foreach ($zones as $name=>$z) {
            if (!isset($temperatures[$name]) || !is_numeric($temperatures[$name])
                || !is_finite((float)$temperatures[$name])
                || $temperatures[$name]<5 || $temperatures[$name]>45) return null;
            $t[$name]=(float)$temperatures[$name];
        }
        $outside=(float)$weather['outside_c'];
        $solar=(float)$weather['solar_w']*(float)($variant['solar_mult']??1.);
        $internal=(float)$weather['internal_w']*(float)($variant['internal_mult']??1.);
        $supplyOffset=(float)($variant['supply_offset_k']??0.);
        $flowMult=(float)($variant['flow_mult']??1.);
        $capacityMult=(float)($variant['capacity_mult']??1.);
        $conductMult=(float)($variant['conductance_mult']??1.);
        foreach ([$solar,$internal,$supplyOffset,$flowMult,$capacityMult,$conductMult] as $v)
            if (!is_finite($v)) return null;
        if ($flowMult<0.5||$flowMult>1.5||$capacityMult<0.6||$capacityMult>1.5
            ||$conductMult<0.5||$conductMult>1.5
            ||abs($supplyOffset)>5||$solar<0||$internal<0) return null;
        $dt=1./12.;$minima=$t;$maxima=$t;
        $fanWh=0.;$baseFan=(float)$stages[$baselineStage]['fan_w'];
        for ($step=0;$step<12;$step++) {
            $stage=($boostStage!==null && $step<6)?$boostStage:$baselineStage;
            $flow=(float)$stages[$stage]['flow_m3h']*$flowMult;
            $supply=(float)$weather['supply_c_by_stage'][$stage]+$supplyOffset;
            $power=[];
            foreach ($zones as $name=>$z) {
                $here=$t[$name];
                $c=(float)$z['capacity_wh_per_k']*$capacityMult;
                $ua=(float)$z['envelope_w_per_k'];
                $vent=0.34*$flow*(float)$z['supply_share'];
                $power[$name]=$ua*($outside-$here)+$vent*($supply-$here)
                    +$solar*(float)$z['solar_share']
                    +$internal*(float)$z['internal_share'];
                $conductanceSum=$ua+$vent;
                foreach ($links as $edge) {
                    $g=(float)$edge['g_w_per_k']*$conductMult;
                    if ($edge['a']===$name) {
                        $power[$name]+=$g*($t[$edge['b']]-$here);
                        $conductanceSum+=$g;
                    } elseif ($edge['b']===$name) {
                        $power[$name]+=$g*($t[$edge['a']]-$here);
                        $conductanceSum+=$g;
                    }
                }
                if ($conductanceSum*$dt/$c>0.25) return null;
            }
            foreach ($zones as $name=>$z) {
                $t[$name]+=$power[$name]*$dt/((float)$z['capacity_wh_per_k']*$capacityMult);
                if (!is_finite($t[$name]) || $t[$name]<0 || $t[$name]>50) return null;
                $minima[$name]=min($minima[$name],$t[$name]);
                $maxima[$name]=max($maxima[$name],$t[$name]);
            }
            $fanWh+=(float)$stages[$stage]['fan_w']*$dt;
        }
        return [
            'rooms_c'=>$t,'lowest_c'=>$minima,'highest_c'=>$maxima,
            'fan_wh'=>round($fanWh,3),
            'incremental_fan_wh'=>round(max(0,$fanWh-$baseFan),3),
            'all_quantities_hypothetical'=>true,'offline_only'=>true,
            'hardware_authorized'=>false,
        ];
    }
}
