<?php
/**
 * Passive room-temperature observer. One bounded instance attribute holds
 * original temperature CHANGE/UPDATE evidence; one user-visible diagnostic.
 *
 * Does not compute physical fan cooling capacity, does not write to devices
 * and does not contact ChatGPT or any external service.
 */
final class JVHeliosLearningDiagnostics
{
    public static function update(array $prior, array $rooms, array $snapshot, int $now):array
    {
        $state=['rooms'=>[],'last_report_at'=>(int)($prior['last_report_at']??0)];
        $priorRooms=is_array($prior['rooms']??null)?$prior['rooms']:[];
        $lines=[];
        $changed=0;
        $freshTemperature=0;
        $freshHumidity=0;
        $seen=[];
        foreach($rooms as $room) {
            if(!is_array($room))continue;
            $name=trim((string)($room['name']??''));
            if($name==='' || isset($seen[$name]) || count($seen)>=20)continue;
            $seen[$name]=true;
            $t=$room['temperature']??null;
            $rh=$room['rh']??null;
            $tStamp=(int)($room['temperature_observed_at']??0);
            $hStamp=(int)($room['humidity_observed_at']??0);
            $temperatureOK=is_numeric($t) && is_finite((float)$t)
                && (float)$t>=8 && (float)$t<=40
                && $tStamp>0 && $tStamp<=$now+120
                && $tStamp>=$now-48*3600;
            $humidityOK=is_numeric($rh) && is_finite((float)$rh)
                && (float)$rh>=0 && (float)$rh<=100
                && $hStamp>0 && $hStamp<=$now+120;
            $tAge=$temperatureOK?(int)floor(max(0,$now-$tStamp)/60):null;
            $hAge=$humidityOK?(int)floor(max(0,$now-$hStamp)/60):null;
            if($tAge!==null && $tAge<=120)$freshTemperature++;
            if($hAge!==null && $hAge<=45)$freshHumidity++;
            // Temperature values arrive every 1-2 minutes. Keeping each one
            // exhausted the former 72-point history in only ~2 hours.
            // ONE ORIGINAL sample per 2h bucket spans a full seven days with
            // 84 bounded entries/room. Never create a fabricated sample.
            $history=[];
            foreach((array)($priorRooms[$name]['samples']??[]) as $p) {
                if(!is_array($p)||!is_numeric($p['at']??null)
                    ||!is_numeric($p['c']??null))continue;
                $at=(int)$p['at'];$c=(float)$p['c'];
                if($at<$now-7*86400 || $at>$now+120 ||$c<8||$c>40)continue;
                $slot=intdiv($at,7200);
                if(!isset($history[$slot]) ||$at<$history[$slot]['at'])
                    $history[$slot]=['at'=>$at,'c'=>round($c,2)];
            }
            ksort($history,SORT_NUMERIC);
            $last=$history?end($history):null;
            if($temperatureOK && $tStamp>=(int)($last['at']??0)
                && $tStamp>=$now-2*3600) {
                $slot=intdiv($tStamp,7200);
                if(!isset($history[$slot])) {
                    // Timestamp comes directly from the source variable.
                    $history[$slot]=['at'=>$tStamp,'c'=>round((float)$t,2)];
                    ksort($history,SORT_NUMERIC);
                    $changed++;
                }
            }
            $history=array_slice(array_values($history),-84);
            $state['rooms'][$name]=['samples'=>$history];
            $n=count($history);
            $first=$n>0?$history[0]:null;
            $last=$n>0?$history[$n-1]:null;
            $span=$n>=2?(int)($last['at']-$first['at']):0;
            // Only a descriptive observed difference, never a causal cooling
            // effect or physical thermal-inertia estimate.
            $dT=$n>=2?round((float)$last['c']-(float)$first['c'],2):null;
            $tText=$temperatureOK?number_format((float)$t,1,'.','').'C':'?';
            $rhText=$humidityOK?number_format((float)$rh,0,'.','').'%':'?';
            $lines[]=$name.': T '.$tText.' (Alter '.($tAge===null?'?':$tAge.'m')
                .'), RH '.$rhText.' (Alter '.($hAge===null?'?':$hAge.'m')
                .'), Punkte '.$n
                .($dT===null?'':($span<7200?', Lernzeitraum noch zu kurz':
                    ', delta '.($dT>=0?'+':'').$dT.'C / '.round($span/3600,1).'h'));
        }
        $state['rooms']=array_intersect_key($state['rooms'],$seen);
        if ($changed>0 || $state['last_report_at']<=0) $state['last_report_at']=$now;
        $fmt=static function($value,int $digits=1):string {
            return is_numeric($value)&&is_finite((float)$value)
                ?number_format((float)$value,$digits,'.',''):'?';
        };
        $weather=(string)($snapshot['weather_quality']??'UNBEKANNT');
        $status=($snapshot['weather_quality']??'')==='VERIFIED'
            && $freshTemperature>=3 && $freshHumidity>=3
            ?'BEOBACHTUNG_MIT_AUSREICHEND_AKTUELLEN_MESSWERTEN'
            :'BEOBACHTUNG_DATEN_TEILWEISE_FEHLEN';
        $date=(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i:s');
        $header=[
            '=== HELIOS SMART – LERNDIAGNOSE FUER CHATGPT ===',
            'Messzeit: '.$date.' Europe/Berlin | Schema 1',
            'Lernmodus: '.$status.' | NUR LESEN | Keine automatische Uebertragung',
            'Lernstand: Temperatur-Stuetzpunkte (alle 2h, originaler Zeitstempel); Gebaeudetraegheit NICHT kalibriert',
            'Grund: Luftmengen, solare/interne Lasten und Zuluftfeuchte nicht gemessen',
            'Helios: Stufe '.($snapshot['fan_level']??'?')
                .' | Modus '.($snapshot['mode']??'?')
                .' | Wochenprogramm '.($snapshot['program']??'?')
                .' | Anlage unveraendert',
            'Zuluft nach WT: '.$fmt($snapshot['supply_temperature']??null).'C'
                .' | Original-Messalter '.(
                    is_numeric($snapshot['supply_age_minutes']??null)
                        ?(int)$snapshot['supply_age_minutes'].'m':'?'),
            'Sole-EWT Eintritt: '.$fmt($snapshot['intake_after_ewt']??null).'C',
            'Wetter: '.$weather.' | Aussen '. $fmt($snapshot['outside_temperature']??null).'C'
                .' | Aussen-Taupunkt '.$fmt($snapshot['outside_dewpoint']??null).'C',
            'Raumtemperaturen <=120m: '.$freshTemperature
                .' | Raumfeuchten <=45m: '.$freshHumidity
                .' | neue 2h-Stuetzpunkte dieser Auswertung: '.$changed,
            'Behaglichkeit: '.(string)($snapshot['comfort_classification']??'UNBEKANNT')
                .' | Zusatzlueftung: '.(string)($snapshot['candidate_kind']??'UNBEKANNT')
                .' | Hardwareausfuehrung aus Lernmodul: NEIN',
            '-- Raeume: 2h-Stuetzpunkte mit originaler Messzeit (max. 84/Raum) --',
        ];
        $footer=[
            '-- Auswertungshinweise --',
            'delta beschreibt nur beobachtete T-Aenderung zwischen erstem und letztem gespeicherten Stuetzpunkt.',
            'Kein Nachweis einer Ursache durch Helios; FBH, Sonne, geoeffnete Tueren/Fenster koennen mitwirken.',
            'Gleiche Temperatur bedeutet weder Sensorfehler noch fehlenden Update; kein erzwungener Stundenwert.',
            'Bitte diesen gesamten Diagnose-Text in ChatGPT einfuegen; kein automatisches Senden.',
        ];
        $report=implode("\n",array_merge($header,$lines,$footer));
        return [
            'state'=>$state,
            'report'=>$report,
            'fresh_temperature_rooms'=>$freshTemperature,
            'fresh_humidity_rooms'=>$freshHumidity,
            'new_distinct_temperature_samples'=>$changed,
            'room_count'=>count($state['rooms']),
            'learning_calibrated'=>false,
            'control_authorized'=>false,
        ];
    }
}
