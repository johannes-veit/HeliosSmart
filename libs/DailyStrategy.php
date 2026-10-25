<?php
/** Pure advisory only: no hardware calls, energy figures normalized to 100 m3/h. */
final class JVHeliosDailyStrategy
{
    public static function analyze(array $decision, array $forecast, ?float $ewtInlet,
        ?float $actualOutside, array $humiditySources, ?int $programActual = null): array
    {
        $inside = is_numeric($decision['indoor_temperature'] ?? null)
            ? (float)$decision['indoor_temperature'] : null;
        $supply = is_numeric($decision['supply_temperature'] ?? null)
            ? (float)$decision['supply_temperature'] : null;
        $weatherOK = ($decision['weather_usable_for_advice'] ?? false) === true;
        $readings=['current'=>0,'indicative'=>0,'old'=>0,'missing'=>0];
        foreach ($humiditySources as $source) {
            if (($source['available'] ?? false)!==true) { $readings['missing']++; continue; }
            $age=$source['age_minutes'] ?? null;
            if (!is_numeric($age) || $age < 0) { $readings['missing']++; continue; }
            if ($age <= 45) $readings['current']++;
            elseif ($age <= 720) $readings['indicative']++;
            else $readings['old']++;
        }
        // This is an approximation for 100 m3/h of *actual supply air*.
        // A negative sign denotes sensible cooling of the rooms, not heat recovery efficiency.
        $per100 = null;
        if ($inside !== null && is_finite($inside) && $inside>=10 && $inside<=38
            && $supply !== null && is_finite($supply) && $supply>=-20 && $supply<=50) {
            $per100=round(0.34*100*($supply-$inside),1);
        }
        $ewtDelta = null;
        if ($weatherOK && $ewtInlet !== null && is_finite($ewtInlet)
            && $ewtInlet>=-20 && $ewtInlet<=50
            && $actualOutside !== null && is_finite($actualOutside)
            && $actualOutside>=-40 && $actualOutside<=55) {
            $ewtDelta=round($ewtInlet-$actualOutside,2);
        }
        $hours=(int)($forecast['forecast_hours']??0);
        $high=(int)($forecast['hot_hours']??0);
        $minT = is_numeric($forecast['temperature_min_c']??null)?(float)$forecast['temperature_min_c']:null;
        $maxT = is_numeric($forecast['temperature_max_c']??null)?(float)$forecast['temperature_max_c']:null;
        $forecastReady = in_array($forecast['status']??'', ['READY','NO_FRESH_INDOOR_HUMIDITY'], true)
            && $hours >= 8 && $weatherOK && $minT !== null && $maxT !== null;
        $season='UNKNOWN';
        if ($forecastReady) {
            if ($high>=3 && $inside!==null && $inside>=23.5) $season='SUMMER_HEAT';
            elseif ($maxT<=18.0 && $high===0) $season='HEATING_SEASON';
            else $season='TRANSITION';
        } elseif ($weatherOK && $actualOutside !== null && is_finite($actualOutside)) {
            // A local current sample alone is only a provisional season hint.
            $season=$actualOutside<=12?'POSSIBLE_HEATING':'UNDETERMINED';
        }
        $mode=(string)($decision['mode']??'WEEKLY_BASE');
        $action='KEEP_WEEKLY_PLAN';
        $reason='Kein belegter Vorteil fuer zusaetzliche Lueftung; Herstellerwochenplan belassen';
        if ($mode==='HUMIDITY_BOOST') {
            $action='CONSIDER_HUMIDITY_BOOST';
            $reason='Feuchteereignis nur mit aktueller Aussen- und Badmessung bewertet';
        } elseif ($mode==='PASSIVE_COOLING') {
            $action='CONSIDER_COOLING_BOOST';
            $reason='Nur bei bestaetigter kuehler Zuluft und passendem Feuchtegefaelle';
        } elseif ($mode==='SUMMER_HUMIDITY_PROTECTION') {
            $action='AVOID_ADDITIONAL_VENTILATION';
            $reason='Hohe Feuchtelast: nur erforderliche Grundlueftung';
        }
        $days = $forecastReady ? ($season==='HEATING_SEASON'?'Winter/Waermeerhalt':
             ($season==='SUMMER_HEAT'?'Sommer/Hitzeschutz':'Uebergangsstrategie')) : 'Tagesstrategie vorlaeufig';
        $thermal = $per100===null?'Zuluftwirkung nicht bestimmbar':
            'Zuluftwirkung '.($per100>=0?'+':'').number_format($per100,0,'.','')
            .' W je 100 m3/h';
        $desc=$days.' | '.$thermal.' | Feuchte '.$readings['current'].' frisch/'
            .$readings['indicative'].' indikativ/'.$readings['old'].' alt';
        return [
            'season_signal'=>$season,
            'season_signal_basis'=>$forecastReady?'24H_FORECAST':'CURRENT_ONLY_OR_NONE',
            'airflow_action'=>$action,
            'airflow_reason'=>$reason,
            'program_actual'=>$programActual,
            'program_change_executed'=>false,
            'sensible_heat_w_per_100m3h'=>$per100,
            'normalized_heat_note'=>'Nur theoretische fuehlbare Raumluftwirkung bei 100 m3/h; keine reale Watt-Leistung ohne gemessenen Volumenstrom',
            'ewt_preconditioning_k'=>$ewtDelta,
            'humidity_counts'=>$readings,
            'temperature_sensor_count'=>(int)($decision['fresh_room_count']??0),
            'safety'=>'ADVISORY_ONLY',
            'summary'=>$desc,
        ];
    }
}
