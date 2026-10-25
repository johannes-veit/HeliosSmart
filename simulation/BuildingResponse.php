<?php
/**
 * OFFLINE ONLY – single-zone lumped heat-capacity scenario model.
 * Required heat capacity / envelope losses / solar gains are ASSUMPTIONS.
 * Cannot forecast measured room temperatures or confirm air exchange.
 */
final class JVHeliosBuildingResponse
{
    public static function step(float $roomC, float $outsideC, float $supplyC,
        float $flowM3h, float $envelopeWPerK, float $gainsW,
        float $capacityWhPerK, float $hours): ?float
    {
        foreach ([$roomC,$outsideC,$supplyC,$flowM3h,$envelopeWPerK,
                  $gainsW,$capacityWhPerK,$hours] as $v) if (!is_finite($v)) return null;
        if ($roomC<0 || $roomC>50 || $outsideC< -45 || $outsideC>58
            || $supplyC< -20 || $supplyC>50
            || $flowM3h<20 || $flowM3h>800 || $envelopeWPerK<0
            || $envelopeWPerK>1500 || abs($gainsW)>15000
            || $capacityWhPerK<500 || $capacityWhPerK>500000
            || $hours<=0 || $hours>1.0) return null;
        // exact integration for a constant-environment first-order RC state.
        // 0.34 W / (m3/h K) approximates air sensible capacity at 20 C.
        $ventWPerK=0.34*$flowM3h;
        $totalWPerK=$envelopeWPerK+$ventWPerK;
        if ($totalWPerK<=0) return $roomC+$gainsW*$hours/$capacityWhPerK;
        $equilibrium=($envelopeWPerK*$outsideC
            +$ventWPerK*$supplyC+$gainsW)/$totalWPerK;
        $decay=exp(-$totalWPerK*$hours/$capacityWhPerK);
        return $equilibrium+($roomC-$equilibrium)*$decay;
    }

    public static function hour(float $roomC, array $weather, array $profiles,
        array $building, int $baselineStage, ?int $boostStage): ?array
    {
        $baseline=$profiles[$baselineStage]??null;
        if (!is_array($baseline)) return null;
        $required=['flow_m3h','fan_w'];
        foreach ($required as $key)
            if (!isset($baseline[$key])||!is_numeric($baseline[$key])) return null;
        $supplies=$weather['supply_c_by_stage']??null;
        if (!is_array($supplies)||!isset($supplies[$baselineStage])
            ||!is_numeric($supplies[$baselineStage])) return null;
        foreach (['outside_c','solar_w','internal_w'] as $key)
            if (!isset($weather[$key])||!is_numeric($weather[$key])) return null;
        foreach (['capacity_wh_per_k','envelope_w_per_k'] as $key)
            if (!isset($building[$key])||!is_numeric($building[$key])) return null;
        $gain=(float)$weather['solar_w']+(float)$weather['internal_w'];
        $u=(float)$building['envelope_w_per_k'];
        $c=(float)$building['capacity_wh_per_k'];
        $out=(float)$weather['outside_c'];
        $step=function(float $t,int $stage,float $duration)use($profiles,$supplies,$gain,$u,$c,$out):?float {
            $profile=$profiles[$stage]??null;
            if (!is_array($profile)||!isset($profile['flow_m3h'],$profile['fan_w'])
                ||!is_numeric($profile['flow_m3h'])||!is_numeric($profile['fan_w'])
                ||!isset($supplies[$stage])||!is_numeric($supplies[$stage])) return null;
            return self::step($t,$out,(float)$supplies[$stage],
                (float)$profile['flow_m3h'],$u,$gain,$c,$duration);
        };
        $energyWh=(float)$baseline['fan_w'];
        $t=$roomC;
        if ($boostStage!==null) {
            $boost=$profiles[$boostStage]??null;
            if ($boost===null||!isset($boost['fan_w'])||!is_numeric($boost['fan_w'])) return null;
            $t=$step($t,$boostStage,0.5);
            if ($t===null) return null;
            $energyWh=0.5*(float)$boost['fan_w']+0.5*(float)$baseline['fan_w'];
            $t=$step($t,$baselineStage,0.5);
        } else $t=$step($t,$baselineStage,1.0);
        if ($t===null || !is_finite($t)) return null;
        return ['temperature_c'=>$t,'fan_wh'=>$energyWh,
            'incremental_fan_wh'=>max(0.0,$energyWh-(float)$baseline['fan_w'])];
    }
}
