<?php
require_once __DIR__ . '/WeatherMath.php';
/**
 * Forecast-only decision support. Not an actuator; outdoor weather predictions
 * are NOT forecasts of air temperature after the brine EWT or the ERV.
 * Windows represent potential and must be revalidated with live data.
 */
final class JVHeliosForecastAdvisor
{
    /** @param array<int,array<string,mixed>> $forecast */
    public static function analyze(array $forecast, int $now, ?float $indoorTemperature,
        ?float $indoorAbsoluteHumidity, string $weatherQuality = 'UNAVAILABLE',
        ?float $supplyTemperature = null, ?float $ewtTemperature = null,
        ?float $outdoorTemperature = null): array
    {
        $result = [
            'status' => 'INSUFFICIENT',
            'forecast_hours' => 0,
            'high_confidence_hours' => 0,
            'earliest_at' => null, 'latest_at' => null,
            'temperature_min_c' => null, 'temperature_max_c' => null,
            'cooling_windows' => [], 'drying_windows' => [],
            'humid_hours' => 0, 'hot_hours' => 0,
            'ewt_cooling_now_k' => null, 'supply_cooling_now_k' => null,
            'next_window' => null,
            'summary' => 'Noch keine ausreichend dichte Stundenprognose',
            'notice' => 'Nur Planung. Reale Zuluft und Feuchte vor einem Eingriff neu pruefen.',
        ];
        if (!in_array($weatherQuality, ['VERIFIED','DWD_ONLY','MODEL_SUPPORTED'], true)) {
            $result['status']='WEATHER_UNCERTAIN';
            $result['summary']='Keine unabhaengig gestuetzte Wetterlage fuer Planung';
            return $result;
        }
        if ($indoorTemperature===null || !is_finite($indoorTemperature)
            || $indoorTemperature<10 || $indoorTemperature>38) {
            $result['status']='NO_INDOOR_TEMPERATURE';
            $result['summary']='Keine belastbare Raumtemperatur fuer Planung';
            return $result;
        }
        if ($ewtTemperature!==null && $outdoorTemperature!==null
            && is_finite($ewtTemperature) && is_finite($outdoorTemperature)
            && $ewtTemperature>=-20 && $ewtTemperature<=50
            && $outdoorTemperature>=-40 && $outdoorTemperature<=55) {
            $result['ewt_cooling_now_k']=round($outdoorTemperature-$ewtTemperature,2);
        }
        if ($supplyTemperature!==null && is_finite($supplyTemperature)
            && $supplyTemperature>=-20 && $supplyTemperature<=50) {
            $result['supply_cooling_now_k']=round($indoorTemperature-$supplyTemperature,2);
        }
        // A future model's dewpoint cannot predict what the ERV will transfer.
        $hasMoisture=$indoorAbsoluteHumidity!==null && is_finite($indoorAbsoluteHumidity)
            && $indoorAbsoluteHumidity>=2 && $indoorAbsoluteHumidity<=25;
        $hours=[];
        foreach ($forecast as $r) {
            if (!is_array($r) || !isset($r['observed_at'], $r['temperature'], $r['rh'])) continue;
            $ts=(int)$r['observed_at'];
            // Cut off stale/too distant forecasts and an implicit null timestamp.
            if ($ts<$now || $ts>$now+24*3600+1800) continue;
            $q=(string)($r['confidence']??'LOW');
            if ($q!=='OK') continue;
            $pair=JVWeatherMath::readPair($r['temperature'],$r['rh']);
            if ($pair===null) continue;
            // Forecast timestamps may differ by a few minutes; group only true hourly points.
            $hour=intdiv($ts,3600);
            if (isset($hours[$hour])) continue;
            $hours[$hour]=['at'=>$ts,'t'=>$pair['temperature'],'ah'=>$pair['absolute_humidity'],
                'dewpoint'=>$pair['dewpoint'],'rh'=>$pair['rh']];
        }
        ksort($hours,SORT_NUMERIC);
        $result['high_confidence_hours']=count($hours);
        $result['forecast_hours']=count($hours);
        if (count($hours)<8) {
            $result['summary']='Weniger als acht vertrauenswuerdige Prognosestunden';
            return $result;
        }
        $timestamps=array_column(array_values($hours),'at');
        $result['earliest_at']=min($timestamps);
        $result['latest_at']=max($timestamps);
        $temps=array_column(array_values($hours),'t');
        $result['temperature_min_c']=round(min($temps),1);
        $result['temperature_max_c']=round(max($temps),1);
        $states=[];
        foreach ($hours as $h=>$p) {
            $result['hot_hours']+=($p['t']>=27.0)?1:0;
            $result['humid_hours']+=($p['dewpoint']>=16.0)?1:0;
            // 'Potential' means cool outdoor air, not guaranteed supply cooling.
            // Do not optimize moisture without at least two *fresh* indoor RH values.
            $cool=($indoorTemperature>=23.5 && $p['t']<=$indoorTemperature-2.5)
                && $hasMoisture && $p['ah']<=$indoorAbsoluteHumidity+0.3
                && $p['dewpoint']<=16.0;
            $dry=$hasMoisture && ($indoorAbsoluteHumidity-$p['ah']>=1.5);
            $states[$h]=['at'=>$p['at'],'cool'=>$cool,'dry'=>$dry,
                'dewpoint'=>$p['dewpoint'],'temperature'=>$p['t']];
        }
        $result['cooling_windows']=self::windows($states,'cool',2);
        $result['drying_windows']=self::windows($states,'dry',2);
        $result['status']=$hasMoisture?'READY':'NO_FRESH_INDOOR_HUMIDITY';
        $result['next_window']=$result['cooling_windows'][0]??null;
        if (!$hasMoisture) {
            $result['summary']='Prognose verfuegbar, aber Feuchtevergleich mangels frischer Raummessungen offen';
        } elseif ($result['cooling_windows']) {
            $first=$result['cooling_windows'][0];
            $result['summary']='Moegliches Kuehlfenster ab '.self::localTime($first['start_at'])
                .' ('.$first['hours'].' h), vor Ort mit Zuluft pruefen';
        } elseif ($result['drying_windows']) {
            $first=$result['drying_windows'][0];
            $result['summary']='Moegliches Entfeuchtungsfenster ab '.self::localTime($first['start_at'])
                .' ('.$first['hours'].' h), bei Bedarfsfall nutzen';
        } else {
            $result['summary']='Kein hinreichend sicheres Zusatzfenster innerhalb der Prognose';
        }
        return $result;
    }

    /** Consecutive forecast hours, with DST safe UTC arithmetic. */
    private static function windows(array $states, string $flag, int $minHours): array
    {
        $windows=[];$first=null;$last=null;$count=0;
        $flush=static function() use (&$windows,&$first,&$last,&$count,$minHours): void {
            if ($first!==null && $count>=$minHours) {
                $windows[]=['start_at'=>$first,'end_at'=>$last+3600,
                    'local_start'=>self::localTime($first),
                    'local_end'=>self::localTime($last+3600),
                    'hours'=>$count];
            }
            $first=null;$last=null;$count=0;
        };
        foreach ($states as $state) {
            $at=(int)$state['at'];
            if (!$state[$flag]) { $flush(); continue; }
            if ($last!==null && abs(($at-$last)-3600)>300) $flush();
            if ($first===null) $first=$at;
            $last=$at;$count++;
        }
        $flush();
        return array_slice($windows,0,4);
    }

    private static function localTime(int $ts): string
    {
        return (new DateTimeImmutable('@'.$ts))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i');
    }
}
