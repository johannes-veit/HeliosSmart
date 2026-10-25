<?php
require_once __DIR__.'/WeatherMath.php';
require_once __DIR__.'/MoistureComfort.php';
/**
 * Sensor-supported thermal and moisture comfort observer.
 * Heuristic air-temperature comfort signal, NOT ASHRAE PMV/PPD compliance:
 * operative/radiant temperature, clothing, local velocity and metabolism
 * are not measurable using the existing sensors.
 */
final class JVHeliosComfortController
{
    public static function analyze(array $decision, array $roomData, int $now): array
    {
        $base=[
            'status'=>'INSUFFICIENT','classification'=>'UNKNOWN',
            'comfort_index'=>null,'comfort_index_kind'=>'HEURISTIC_NOT_PMV',
            'rooms_usable'=>0,'rooms'=>[],
            'living_average_temperature_c'=>null,'living_average_absolute_humidity_gm3'=>null,
            'hottest_room'=>null,'most_humid_room'=>null,'coolest_room'=>null,
            'cooling_floor_rule'=>'Schlafraeume >=19 C, sonst >=20.5 C; nur Wetter-S4',
            'rooms_below_cooling_floor'=>[],
            'night_precooling_safe'=>null,
            'temperature_target_summer_c'=>'23-26 (orientation, measured air only)',
            'humidity_target_rh_pct'=>'40-55 (operator preference, not a standard)',
            'heat_discomfort_rooms'=>0,'humidity_discomfort_rooms'=>0,'dry_rooms'=>0,
            'outside_humidity_compatible'=>null,'weather_boost_compatible'=>false,
            'requested_improvement'=>'MEASURE_ROOMS',
            'summary'=>'Nicht genug frische Wohnraummessungen',
            'recommendations'=>[],
            'limitations'=>'Kein PMV: operative/Strahlungstemperatur, Luftgeschwindigkeit, Kleidung und Aktivitaet fehlen.',
        ];
        if ($now<=0) return $base;
        $items=[];$scores=[];$hot=0;$humid=0;$dry=0;
        foreach ($roomData as $room) {
            if (!is_array($room)) continue;
            $name=(string)($room['name']??'');
            // Wetrooms are monitored independently for shower humidity spikes.
            if ($name==='' || preg_match('/bad|gaeste.?wc|gäste.?wc|flur/ui',$name)) continue;
            $tt=(int)($room['temperature_observed_at']??$room['observed_at']??0);
            $ht=(int)($room['humidity_observed_at']??$room['observed_at']??0);
            if ($tt<=0 || $ht<=0 || $tt>$now+120 || $ht>$now+120
                || $now-$tt>2*3600 || $now-$ht>45*60) continue;
            $pair=JVWeatherMath::readPair($room['temperature']??null,$room['rh']??null);
            if ($pair===null || $pair['temperature']<12 || $pair['temperature']>37) continue;
            $t=(float)$pair['temperature'];$rh=(float)$pair['rh'];$dp=(float)$pair['dewpoint'];
            $floor=preg_match('/schlaf/ui',$name)?19.0:20.5;
            $hotRoom=$t>=25.5;$humidRoom=$rh>=60 || $dp>=17.5;
            $dryRoom=$rh<35;
            $heatScore=max(0.0,($t-25.0)*8.0, ($floor-$t)*7.0);
            // RH and dewpoint describe correlated vapor pressure. Do not double
            // count them as two independent discomfort mechanisms.
            $moistScore=max(0.0,($rh-55.0)*1.2,($dp-16.5)*5.0,(35.0-$rh)*0.8);
            $score=(int)max(0,min(100,round(100-$heatScore-$moistScore)));
            $kind=$hotRoom && $humidRoom?'ZU_WARM_UND_SCHWUEL':
                ($humidRoom?'ZU_FEUCHT':($hotRoom?'ZU_WARM':
                ($dryRoom?'ZU_TROCKEN':($t<$floor?'ZU_KUEHL':'ANGENEHM'))));
            $items[]=['room'=>$name,'temperature_c'=>$t,'rh_pct'=>$rh,
                'dewpoint_c'=>$dp,'absolute_humidity_gm3'=>(float)$pair['absolute_humidity'],
                'classification'=>$kind,'comfort_index'=>$score,
                'minimum_cooling_temperature_c'=>$floor,
                'age_temperature_min'=>(int)floor(($now-$tt)/60),
                'age_humidity_min'=>(int)floor(($now-$ht)/60)];
            $scores[]=$score;
            if ($hotRoom) $hot++;
            if ($humidRoom) $humid++;
            if ($dryRoom) $dry++;
        }
        $base['rooms_usable']=count($items);
        $base['rooms']=$items;
        $base['heat_discomfort_rooms']=$hot;
        $base['humidity_discomfort_rooms']=$humid;
        $base['dry_rooms']=$dry;
        if (count($items)<3) return $base;
        $base['status']='READY';
        $livingT=array_sum(array_column($items,'temperature_c'))/count($items);
        $livingAh=array_sum(array_column($items,'absolute_humidity_gm3'))/count($items);
        $base['living_average_temperature_c']=round($livingT,2);
        $base['living_average_absolute_humidity_gm3']=round($livingAh,2);
        $base['comfort_index']=(int)round(array_sum($scores)/count($scores));
        $sortedHot=$items;$sortedWet=$items;
        usort($sortedHot,static fn($a,$b)=>$b['temperature_c']<=>$a['temperature_c']);
        usort($sortedWet,static fn($a,$b)=>$b['dewpoint_c']<=>$a['dewpoint_c']);
        $base['hottest_room']=$sortedHot[0];
        $base['most_humid_room']=$sortedWet[0];
        $sortedCool=$items;
        usort($sortedCool,static fn($a,$b)=>$a['temperature_c']<=>$b['temperature_c']);
        $base['coolest_room']=$sortedCool[0];
        $belowFloor=array_values(array_map(static fn($r)=>$r['room'],
            array_filter($items,static fn($r)=>$r['temperature_c']<$r['minimum_cooling_temperature_c'])));
        $base['rooms_below_cooling_floor']=$belowFloor;
        $base['night_precooling_safe']=count($belowFloor)===0;

        // Bath and kitchen spikes must not make comfortable living rooms
        // appear humidity-loaded merely through the global mean.
        $localDecision=$decision;
        $localDecision['indoor_temperature']=$livingT;
        $localDecision['indoor_absolute_humidity']=$livingAh;
        $moisture=JVHeliosMoistureComfort::analyze($localDecision);
        $compatible=($moisture['status']??'')==='READY'
            && ($moisture['weather_cooling_boost_allowed']??false)===true;
        $base['outside_humidity_compatible']=$moisture['status']==='READY'?$compatible:null;
        $needsCool=$hot>0;
        $needsDry=$humid>0;
        $dryConcern=$dry>0;
        $fc=(array)($decision['forecast_advice']??[]);
        $heatwave=($fc['status']??'')==='READY'
            && (int)($fc['high_confidence_hours']??0)>=12
            && (int)($fc['longest_contiguous_hours']??0)>=8
            && is_numeric($fc['temperature_max_c']??null)
            && (float)$fc['temperature_max_c']>=30;
        $temperature=$livingT;
        $supply=$decision['supply_temperature']??null;
        $realCooling=is_numeric($supply) && $temperature-(float)$supply>=2.2;
        $summer=($decision['season_signal']??null)!=='HEATING_SEASON';
        // Whole-house S4 cools EVERY supplied room. A comfortable house
        // average can conceal an already-cold bedroom: never request extra
        // *weather-only* cooling when a measured room is below its use-based floor.
        $safeToCool=count($belowFloor)===0;

        if ($needsDry && !$compatible) {
            $base['classification']=$needsCool?'WARM_UND_SCHWUEL':'ZU_FEUCHT';
            $base['requested_improvement']='MOISTURE_PROTECTION_NO_WEATHER_BOOST';
            $base['recommendations']=[
                'Aussenluft ist zu feucht: keine zusaetzliche wetterbedingte Stosslueftung.',
                'Geräteeigenen Enthalpiewaermetauscher und hygienische Grundlueftung beibehalten.',
                'Feuchtequellen reduzieren; passive KWL kann Innenluft nicht aktiv entfeuchten.',
            ];
        } elseif ($dryConcern && !$needsDry && !$needsCool) {
            $base['classification']='ZU_TROCKEN';
            $base['requested_improvement']='AVOID_EXCESS_DRYING';
            $base['recommendations']=[
                'Keine unnoetige zusaetzliche Trocknung; erforderliche Grundlueftung erhalten.',
                'Keine Feuchteanhebung durch die KWL behaupten.',
            ];
        } elseif ($needsCool || ($summer && $heatwave && $temperature>=23)) {
            $base['classification']=$needsDry?'WARM_UND_FEUCHT':($needsCool?'ZU_WARM':'HITZEVORSORGE');
            $base['requested_improvement']=$compatible?'COOL_WITH_MOISTURE_GUARD':'PROTECT_FROM_HUMID_AIR';
            $base['weather_boost_compatible']=$compatible && $realCooling && $summer && !$dryConcern
                && $safeToCool;
            $base['recommendations']=$compatible
                ?['Nur bei tatsaechlich kuehler Zuluft und geeignetem Aussen-Taupunkt vorkuehlen.',
                  'Wetter-Partybetrieb zeitlich begrenzen und Grundlueftung erhalten.']
                :['Aussenluftfeuchte hat Vorrang: keine hohe wetterbedingte Luefterstufe.',
                  'Bei Hitze Verschattung und geraeteeigene Kuehlrueckgewinnung nutzen.'];
        } elseif ($needsDry) {
            $base['classification']='ZU_FEUCHT';
            $base['requested_improvement']=$compatible?'DRY_IF_OUTSIDE_DRIER':'MOISTURE_PROTECTION_NO_WEATHER_BOOST';
            $base['weather_boost_compatible']=$compatible && $summer && !$dryConcern
                && $safeToCool;
            $base['recommendations']=$compatible
                ?['Bei nachweisbar trockenerer Aussenluft kontrolliert Feuchtigkeit abfuehren.']
                :['Feuchteimport vermeiden; KWL hat keine aktive Entfeuchtung.'];
        } elseif ($dryConcern) {
            $base['classification']='ZU_TROCKEN';
            $base['requested_improvement']='AVOID_EXCESS_DRYING';
            $base['recommendations']=['Nicht zusaetzlich austrocknen; Grundlueftung erhalten.'];
        } else {
            $base['classification']='ANGENEHM';
            $base['requested_improvement']='MAINTAIN_COMFORT';
            $base['weather_boost_compatible']=$compatible && $realCooling && $summer
                && $heatwave && $temperature>=23 && $safeToCool;
            $base['recommendations']=$base['weather_boost_compatible']
                ?['Bei bevorstehender Hitzewelle kurze Vorabkuehlung zulassen, nur bei trockenem Aussenklima.']
                :['Keine zusaetzliche Luefterstufe aus Behaglichkeit ableiten.'];
        }
        if (!$safeToCool && $summer) {
            $base['recommendations'][]='Mindestens ein Raum unter der nutzungsbezogenen Kuehluntergrenze: keine zusaetzliche wetterbedingte Ganzhaus-Kuehllueftung.';
            $base['weather_boost_compatible']=false;
            if (in_array($base['requested_improvement'],['COOL_WITH_MOISTURE_GUARD','DRY_IF_OUTSIDE_DRIER'],true)) {
                $base['requested_improvement']='PROTECT_COOL_ROOMS_WHILE_MAINTAINING_WEEKLY';
            }
        }
        $base['summary']=$base['classification'].' | Wohnraeume '.$base['rooms_usable'].
            ' | Hitze '.$hot.' | Feuchte '.$humid.' | Trocken '.$dry.
            ' | Aussenfeuchte '.($compatible?'guenstig':'nicht fuer Zusatzlueftung freigegeben');
        return $base;
    }
}
