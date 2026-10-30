<?php
/**
 * Heating-period room-air and sensible-energy OBSERVER.
 *
 * Uses 11 original room temperatures independently from often-stale native
 * LCN RH. Only fresh RH is allowed to identify CURRENT dryness/moisture.
 * It cannot prove hygiene without CO2/volumetric airflow measurements and
 * never issues or authorizes a device command.
 */
final class JVHeliosWinterRoomAir
{
    public static function analyze(array $rooms,array $context,int $now):array
    {
        $tz=new DateTimeZone('Europe/Berlin');
        $month=(int)(new DateTimeImmutable('@'.$now))->setTimezone($tz)->format('n');
        $season=(string)($context['season_signal']??'UNKNOWN');
        $quality=(string)($context['weather_quality']??'UNAVAILABLE');
        $external=$context['outside_temperature']??null;
        $weatherAge=$context['outside_age_minutes']??null;
        $verified=in_array($quality,['VERIFIED','DWD_ONLY'],true)
            && is_numeric($external) && is_finite((float)$external)
            && (float)$external>=-40 && (float)$external<=55
            && is_numeric($weatherAge) && (float)$weatherAge>=0
            && (float)$weatherAge<=45;
        // Oct-Apr plus actual measured cold outdoor air is only a provisional
        // heating indicator; never confuse an October 12C night with proof that
        // any specific FBH actuator is energized.
        $confirmed=$season==='HEATING_SEASON';
        $likely=$verified && in_array($month,[10,11,12,1,2,3,4],true)
            && (float)$external<=12.0 && $season!=='SUMMER_HEAT';
        $heating=$confirmed || $likely;
        $temp=[];$currentRh=[];$indicative=0;$stale=0;$wet=[];$dry=[];
        $validNames=[];
        foreach($rooms as $r) {
            if(!is_array($r))continue;
            $name=trim((string)($r['name']??''));
            if($name==='' || isset($validNames[$name]) || count($validNames)>=20)continue;
            $validNames[$name]=true;
            $at=(int)($r['temperature_observed_at']??$r['observed_at']??0);
            $raw=$r['temperature']??null;
            $t=is_numeric($raw)?(float)$raw:NAN;
            $tempOK=is_finite($t)&&$t>=8&&$t<=40
                && $at>0&&$at<=$now+120&&$now-$at<=7200;
            if($tempOK)$temp[]=['name'=>$name,'c'=>$t];
            $hAt=(int)($r['humidity_observed_at']??$r['observed_at']??0);
            $h=$r['rh']??null;
            if(!$tempOK||!is_numeric($h)||!is_finite((float)$h)
                ||(float)$h<0||(float)$h>100
                ||$hAt<=0||$hAt>$now+120){$stale++;continue;}
            $age=$now-$hAt;
            if($age>45*60) {
                if($age<=12*3600)$indicative++;
                else $stale++;
                continue;
            }
            $currentRh[]=(float)$h;
            if((float)$h<35)$dry[]=$name;
            if((float)$h>=60)$wet[]=$name;
        }
        $count=count($temp);$rhCount=count($currentRh);
        $mean=$count?array_sum(array_column($temp,'c'))/$count:null;
        $min=$count?min(array_column($temp,'c')):null;
        $max=$count?max(array_column($temp,'c')):null;
        $supply=$context['supply_temperature']??null;
        $sAge=$context['supply_age_minutes']??null;
        $supplyOK=is_numeric($supply)&&is_finite((float)$supply)
            && (float)$supply>=-20 && (float)$supply<=50
            && is_numeric($sAge)&&(float)$sAge>=0&&(float)$sAge<=20;
        $impact=$mean!==null && $count>=6 && $supplyOK
            ?round(34.0*((float)$supply-$mean),1):null;
        $state=!$heating?'NO_HEATING_EVIDENCE':($count<6?'INSUFFICIENT_ROOM_T':
            ($rhCount<3?'HEATING_HUMIDITY_PARTIAL':'HEATING_ROOM_AIR_OBSERVED'));
        if($heating && count($wet)>0)$need='CHECK_FRESH_LOCAL_MOISTURE_EVENT';
        elseif($heating && count($dry)>=2)$need='AVOID_UNNECESSARY_DRYING';
        elseif($heating && $rhCount<3)$need='HUMIDITY_EVIDENCE_INCOMPLETE';
        elseif($heating)$need='NO_EXTRA_DEMAND_PROVEN';
        else $need='SEASON_NOT_HEATING';
        $note=$heating?'Heizbetrieb: Herstellerwochenplan als Mindestlueftung; keine wetterbedingte Zusatzstufe ohne belegten Bedarf':
            'Keine belastbare Heizbetriebs-Situation; Wochenprogramm unveraendert';
        return [
            'status'=>$state,'heating_active'=>$heating,
            'heating_basis'=>$confirmed?'FORECAST_HEATING_SEASON':
                ($likely?'MEASURED_COLD_OCT_APR':'NOT_CONFIRMED'),
            'temperature_rooms_current'=>$count,'temperature_min_c'=>$min,
            'temperature_max_c'=>$max,
            'temperature_average_c'=>$mean!==null?round($mean,2):null,
            'humidity_rooms_current'=>$rhCount,
            'humidity_rooms_indicative'=>$indicative,
            'humidity_rooms_old_or_missing'=>$stale,
            'current_dry_rooms'=>$dry,'current_humid_rooms'=>$wet,
            'room_air_need'=>$need,
            'supply_is_current'=>$supplyOK,
            'sensible_heat_w_per_100m3h'=>$impact,
            'sensible_heat_basis'=>'Theoretische fuehlbare Raumluftwirkung bei 100 m3/h; weder reale Heizleistung noch Waermerueckgewinnungsgrad',
            'discretionary_weather_boost_allowed'=>!$heating,
            'hygienic_minimum_preserved'=>true,
            'existing_party_or_manual_untouched'=>true,
            'co2_or_airquality_verified'=>false,
            'actual_supply_airflow_measured'=>false,
            'learning_directly_selects_fan'=>false,
            'summary'=>$note.' | Raum-T '.$count
                .' (Mittel '.($mean!==null?number_format($mean,1,'.',''):'?').'C)'
                .' | RH frisch '.$rhCount.'/'.count($validNames)
                .' | RH indikativ '.$indicative
                .' | lokale Feuchtehinweise '.count($wet)
                .' | Zuluftwirkung '.($impact!==null?($impact>0?'+':'').$impact.' W/100m3h':'unbekannt'),
        ];
    }
}
