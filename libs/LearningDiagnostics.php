<?php
require_once __DIR__.'/LearningPatterns.php';
require_once __DIR__.'/LearningStore.php';
require_once __DIR__.'/LearningOverheat.php';
require_once __DIR__.'/LearningNightOpportunity.php';
require_once __DIR__.'/LearningStageStudy.php';
/**
 * Passive room-temperature observer. One bounded instance attribute holds
 * original temperature CHANGE/UPDATE evidence; one user-visible diagnostic.
 *
 * Does not compute physical fan cooling capacity, does not write to devices
 * and does not contact ChatGPT or any external service.
 */
final class JVHeliosLearningDiagnostics
{
    /**
     * Descriptive evidence only. Never extrapolates from missing hours,
     * attributes a trend to the fan, or reports a learned thermal mass.
     * A 24h trend needs a recent endpoint, one observed reference 20-28h
     * earlier, and at least eight ORIGINAL two-hour support points.
     */
    public static function describeHistory(array $samples,int $now):array
    {
        $ret=['points'=>count($samples),'span_h'=>0.0,'recent_points'=>0,
            'gaps_gt_3h'=>0,'jumps_gt_3c'=>0,
            'delta_24h_c'=>null,'trend_24h'=>'NOCH_NICHT_MESSBAR',
            'min_7d_c'=>null,'max_7d_c'=>null,'paired_context_points'=>0,
            'evidence'=>'ANLAUF'];
        if(!$samples)return $ret;
        usort($samples,static fn($a,$b)=>($a['at']??0)<=>($b['at']??0));
        $n=count($samples);$first=$samples[0];$last=$samples[$n-1];
        $span=max(0,(int)$last['at']-(int)$first['at']);
        $ret['span_h']=round($span/3600,1);
        $temperatures=array_map(static fn($p)=>(float)$p['c'],$samples);
        $ret['min_7d_c']=round(min($temperatures),1);
        $ret['max_7d_c']=round(max($temperatures),1);
        foreach($samples as $i=>$sample) {
            if((int)$sample['at']>=$now-24*3600)$ret['recent_points']++;
            if(isset($sample['context']) && is_array($sample['context'])
                && ($sample['context']['simultaneous_only']??false)===true)
                $ret['paired_context_points']++;
            if($i===0)continue;
            $elapsed=(int)$sample['at']-(int)$samples[$i-1]['at'];
            if($elapsed>3*3600)$ret['gaps_gt_3h']++;
            if($elapsed>0 && $elapsed<=3*3600
                && abs((float)$sample['c']-(float)$samples[$i-1]['c'])>3.)
                $ret['jumps_gt_3c']++;
        }
        if($span>=48*3600 && $n>=20 && $ret['gaps_gt_3h']<=4
            && $ret['jumps_gt_3c']===0)$ret['evidence']='MEHRTAGES_TREND_BEOBACHTBAR';
        elseif($span>=24*3600 && $n>=10)$ret['evidence']='ERSTE_TAGESVERLAEUFE';
        if($n<8 || $ret['recent_points']<8 || $ret['jumps_gt_3c']>0
            || (int)$last['at']<$now-3*3600)return $ret;
        $reference=null;$referenceDistance=INF;
        foreach($samples as $p) {
            $age=(int)$last['at']-(int)$p['at'];
            if($age<20*3600 || $age>28*3600)continue;
            $distance=abs($age-24*3600);
            if($distance<$referenceDistance) {
                $reference=$p;$referenceDistance=$distance;
            }
        }
        if($reference===null)return $ret;
        $delta=round((float)$last['c']-(float)$reference['c'],2);
        $ret['delta_24h_c']=$delta;
        $ret['trend_24h']=$delta>=0.4?'WAERMER':
            ($delta<=-0.4?'KUEHLER':'NAHEZU_GLEICH');
        return $ret;
    }

    public static function update(array $prior, array $rooms, array $snapshot, int $now):array
    {
        $expanded=JVHeliosLearningStore::decode($prior);
        $state=['rooms'=>[],
            'last_report_at'=>(int)($expanded['last_report_at']??0),
            'last_diag_at'=>(int)($expanded['last_diag_at']??0),
            'seasonal'=>(array)($expanded['seasonal']??[])];
        $priorRooms=(array)($expanded['rooms']??[]);
        // Preserve and migrate historical data on the first update from
        // legacy v1 format. On subsequent v2 runs, never count it twice.
        if (!isset($prior['v']) && !isset($prior['seasonal'])) {
            foreach($priorRooms as $oldName=>$oldRoom) {
                $deduplicated=[];
                foreach((array)($oldRoom['samples']??[]) as $original) {
                    if(!isset($original['at'],$original['c']))continue;
                    $at=(int)$original['at'];
                    $slot=intdiv($at,7200);
                    if(!isset($deduplicated[$slot]) ||
                        $at<(int)$deduplicated[$slot]['at'])
                        $deduplicated[$slot]=$original;
                }
                $state['seasonal']=JVHeliosLearningStore::record(
                    $state['seasonal'],(string)$oldName,
                    array_values($deduplicated),$now);
            }
        }
        $lines=[];
        $changed=0;
        $freshTemperature=0;
        $freshHumidity=0;
        $seen=[];
        $roomStats=[];
        $nightStats=[];
        $overheatStats=[];
        $nightOpportunities=[];
        $stageStudies=[];
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
                    $history[$slot]=array_filter([
                        'at'=>$at,'c'=>round($c,2),
                        'context'=>isset($p['context']) && is_array($p['context'])
                            && ($p['context']['simultaneous_only']??false)===true
                            ?$p['context']:null,
                    ],static fn($value)=>$value!==null);
            }
            ksort($history,SORT_NUMERIC);
            $last=$history?end($history):null;
            $newMonthlyEvidence=[];
            if($temperatureOK && $tStamp>=(int)($last['at']??0)
                && $tStamp>=$now-2*3600) {
                $slot=intdiv($tStamp,7200);
                if(!isset($history[$slot])) {
                    // Timestamp comes directly from the source variable.
                    // Context is a near-time read-only comparison, NOT
                    // evidence of continuous stage or bypass operation.
                    // Weather and supply must each be <=10 min old to avoid
                    // joining a current room update with stale air values.
                    $context=null;
                    $near=(int)abs($now-$tStamp)<=180;
                    $weatherTrusted=in_array((string)($snapshot['weather_quality']??''),
                        ['VERIFIED','DWD_ONLY'],true);
                    $outAge=$snapshot['outside_age_minutes']??null;
                    $supplyAge=$snapshot['supply_age_minutes']??null;
                    if($near && $weatherTrusted && is_numeric($outAge)
                        && (float)$outAge>=0 && (float)$outAge<=10
                        && is_numeric($snapshot['outside_temperature']??null)
                        && is_numeric($snapshot['outside_dewpoint']??null)
                        && is_numeric($supplyAge) && (float)$supplyAge>=0
                        && (float)$supplyAge<=10
                        && is_numeric($snapshot['supply_temperature']??null)) {
                        $context=[
                            'supply_c'=>round((float)$snapshot['supply_temperature'],1),
                            'outside_c'=>round((float)$snapshot['outside_temperature'],1),
                            'outside_dewpoint_c'=>round((float)$snapshot['outside_dewpoint'],1),
                            'fan_level'=>is_numeric($snapshot['fan_level']??null)
                                ?(int)$snapshot['fan_level']:null,
                            'simultaneous_only'=>true,
                        ];
                    }
                    $history[$slot]=array_filter([
                        'at'=>$tStamp,'c'=>round((float)$t,2),
                        'context'=>$context,
                    ],static fn($value)=>$value!==null);
                    ksort($history,SORT_NUMERIC);
                    $newMonthlyEvidence[]=$history[$slot];
                    $changed++;
                }
            }
            if($newMonthlyEvidence)
                $state['seasonal']=JVHeliosLearningStore::record(
                    $state['seasonal'],$name,$newMonthlyEvidence,$now);
            $history=array_slice(array_values($history),-84);
            $state['rooms'][$name]=['samples'=>$history];
            $n=count($history);
            $first=$n>0?$history[0]:null;
            $last=$n>0?$history[$n-1]:null;
            $span=$n>=2?(int)($last['at']-$first['at']):0;
            // Only a descriptive observed difference, never a causal cooling
            // effect or physical thermal-inertia estimate.
            $dT=$n>=2?round((float)$last['c']-(float)$first['c'],2):null;
            $trend=self::describeHistory($history,$now);
            $nights=JVHeliosLearningPatterns::analyze($history,$now);
            $roomStats[$name]=$trend;
            $nightStats[$name]=$nights;
            $recentHeat=JVHeliosLearningOverheat::recent($name,$history,$now);
            $historicHeat=JVHeliosLearningOverheat::summerProfile(
                $name,(array)($state['seasonal'][$name]??[]));
            $overheatStats[$name]=[
                'recent'=>$recentHeat,'historical'=>$historicHeat,
            ];
            $nightReview=JVHeliosLearningNightOpportunity::analyze(
                $name,$recentHeat,$nights,$historicHeat,$now);
            $nightOpportunities[$name]=$nightReview;
            $stageStudy=JVHeliosLearningStageStudy::analyze($history,$now);
            $stageStudies[$name]=$stageStudy;
            $tText=$temperatureOK?number_format((float)$t,1,'.','').'C':'?';
            $rhText=$humidityOK?number_format((float)$rh,0,'.','').'%':'?';
            $lines[]=$name.': T '.$tText.' (Alter '.($tAge===null?'?':$tAge.'m')
                .'), RH '.$rhText.' (Alter '.($hAge===null?'?':$hAge.'m')
                .($hAge!==null && $hAge>45?', nur Indikation':'')
                .'), Punkte '.$n
                .($dT===null?'':($span<7200?', Lernzeitraum noch zu kurz':
                    ', delta '.($dT>=0?'+':'').$dT.'C / '.round($span/3600,1).'h'))
                .($trend['delta_24h_c']===null?'':', 24h '.
                    ($trend['delta_24h_c']>0?'+':'').$trend['delta_24h_c'].'C')
                .($trend['gaps_gt_3h']>0?', Luecken '.$trend['gaps_gt_3h']:'')
                .($trend['jumps_gt_3c']>0?', SPRUNG_PRUEFEN '.$trend['jumps_gt_3c']:'')
                .($nights['valid_nights']>0?', Naechte '.$nights['valid_nights'].
                    ' (Mittel '.($nights['average_night_delta_c']>0?'+':'').
                    $nights['average_night_delta_c'].'C)':'')
                .(in_array($recentHeat['status'],[
                    'WIEDERHOLT_UEBER_REFERENZ',
                    'MEHRERE_AKTUELLE_WARME_MESSWERTE',
                ],true)?', Sommer-Waermehinweis: '.$recentHeat['status']:'')
                .($historicHeat['status']==='MEHRERE_SOMMERMONATE_HOHES_MITTEL'
                    ?', Langzeit: SOMMERMONATSMITTEL_ERHOEHT':'')
                .($nightReview['review_priority']==='HOHE_PRIORITAET_FUER_REINEN_DATENVERGLEICH'
                    ?', Nacht-Lernhinweis: MEHR_BEOBACHTEN':'')
                .($stageStudy['matched_weather_stage_pairs']>0
                    ?', Stufen-Nachtpaare '.$stageStudy['matched_weather_stage_pairs']
                        .' (nur Beobachtung)':'');
        }
        // The 7-day detail buffer may drop absent rooms, but the tiny
        // 12-month long-term summary must survive a temporary missing sensor
        // or a transient configuration read error. Bound total room keys.
        $state['rooms']=array_intersect_key($state['rooms'],$seen);
        $state['seasonal']=array_slice($state['seasonal'],0,20,true);
        if ($changed>0 || $state['last_report_at']<=0) $state['last_report_at']=$now;
        $fmt=static function($value,int $digits=1):string {
            return is_numeric($value)&&is_finite((float)$value)
                ?number_format((float)$value,$digits,'.',''):'?';
        };
        $weather=(string)($snapshot['weather_quality']??'UNBEKANNT');
        $status=in_array(($snapshot['weather_quality']??''),['VERIFIED','DWD_ONLY'],true)
            && $freshTemperature>=3 && $freshHumidity>=3
            ?'BEOBACHTUNG_MIT_AUSREICHEND_AKTUELLEN_MESSWERTEN'
            :'BEOBACHTUNG_DATEN_TEILWEISE_FEHLEN';
        $date=(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i:s');
        $seasonalTotals=JVHeliosLearningStore::totals($state['seasonal']);
        $month=(int)(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('n');
        $seasonalClimate=JVHeliosLearningStore::seasonalSummary($state['seasonal'],$month);
        $seasonCompare=JVHeliosLearningStore::compareSeasons($state['seasonal']);
        $recentHeatRooms=0;
        $seasonHeatRooms=0;
        foreach($overheatStats as $v) {
            if(in_array($v['recent']['status'],[
                'WIEDERHOLT_UEBER_REFERENZ',
                'MEHRERE_AKTUELLE_WARME_MESSWERTE',
            ],true))$recentHeatRooms++;
            if($v['historical']['status']==='MEHRERE_SOMMERMONATE_HOHES_MITTEL')
                $seasonHeatRooms++;
        }
        $nightHighPriority=0;
        $nightNeedsEvidence=0;
        $nightCoolingObserved=0;
        $highNightRooms=[];
        foreach($nightOpportunities as $roomName=>$v) {
            if($v['review_priority']==='HOHE_PRIORITAET_FUER_REINEN_DATENVERGLEICH') {
                $nightHighPriority++;
                $highNightRooms[]=$roomName;
            }
            if(in_array($v['status'],[
                'WARM_ABER_NACHTDATEN_FEHLEN',
                'AUSSENWETTER_NACHTVERGLEICH_FEHLT',
            ],true))$nightNeedsEvidence++;
            if($v['status']==='NACHTABKUEHLUNG_BEREITS_BEOBACHTET')
                $nightCoolingObserved++;
        }
        $stagePairs=0;
        $stageRooms=0;
        $stageTransitions=0;
        $missingStageEvidence=0;
        $stageConflicts=0;
        $stageSupplyWarm=0;
        $stageNextGaps=[];
        foreach($stageStudies as $roomName=>$v) {
            $stagePairs+=(int)$v['matched_weather_stage_pairs'];
            if($v['matched_weather_stage_pairs']>0)$stageRooms++;
            $stageTransitions+=(int)$v['stage_transitions_seen'];
            $missingStageEvidence+=(int)$v['missing_context_nights'];
            $stageSupplyWarm+=(int)$v['supply_not_cooler_nights'];
            if($v['pair_directions_disagree'])$stageConflicts++;
            if($v['status']!=='AUSSERHALB_SOMMERSTUDIE'
                &&$v['data_gap']!=='KAUSALITAET_BYPASS_FBH_FENSTER_NICHT_BELEGT')
                $stageNextGaps[]=$roomName.': '.$v['data_gap'];
        }
        $measuredSpan=0;
        $enoughForDay=0;
        $longerTrend=0;
        $gaps=0;
        $paired=0;
        $nightRooms=0;
        $nightWeatherPairs=0;
        foreach($nightStats as $v) {
            if($v['valid_nights']>=3)$nightRooms++;
            $nightWeatherPairs+=(int)$v['similar_weather_night_pairs'];
        }
        foreach($roomStats as $v) {
            $measuredSpan=max($measuredSpan,(float)$v['span_h']);
            if($v['delta_24h_c']!==null)$enoughForDay++;
            if($v['evidence']==='MEHRTAGES_TREND_BEOBACHTBAR')$longerTrend++;
            $gaps+=(int)$v['gaps_gt_3h'];
            $paired+=(int)$v['paired_context_points'];
        }
        $header=[
            '=== HELIOS SMART – LERNDIAGNOSE FUER CHATGPT ===',
            'Messzeit: '.$date.' Europe/Berlin | Schema 1',
            'Lernmodus: '.$status.' | NUR LESEN | Keine automatische Uebertragung',
            'Lernstand: Temperatur-Stuetzpunkte (alle 2h, originaler Zeitstempel); Gebaeudetraegheit NICHT kalibriert',
            'Messfortschritt: maximal '.number_format($measuredSpan,1,'.','').'h Verlauf'
                .' | 24h-Vergleich in '.$enoughForDay.'/'.count($seen).' Raeumen'
                .' | Mehrtages-Trend in '.$longerTrend.'/'.count($seen).' Raeumen'
                .' | Luecken '.$gaps.' | passende Wetter/Zuluft-Snapshots '.$paired,
            'Nachtmuster: mindestens 3 gueltige Naechte in '.$nightRooms.'/'.count($seen)
                .' Raeumen | vergleichbare Aussenwetter-Nachtpaare '.$nightWeatherPairs,
            'Langzeit-Speicher: '.$seasonalTotals['observations']
                .' echte 2h-Stuetzpunkte zu 12 wiederkehrenden Kalendermonaten'
                .' | letzte 7 Tage im Detail | keine unbegrenzt wachsende Historie',
            'Monats-Wettervergleich: '.($seasonalClimate['mean_indoor_outdoor_gap_k']===null
                ?'NOCH_NICHT_BEWERTBAR ('.$seasonalClimate['paired_samples'].' passende Messwerte)'
                :'Innen minus Aussen '.number_format(
                    $seasonalClimate['mean_indoor_outdoor_gap_k'],1,'.',''). 'K'
                    .' | mittlerer Aussen-Taupunkt '.number_format(
                    $seasonalClimate['mean_outside_dewpoint_c'],1,'.','').'C'
                    .' ('.$seasonalClimate['paired_samples'].' Messwerte)')
                .' | keine Aussage zur Helios-Wirkung',
            'Sommer/Winter-Vergleich: '.($seasonCompare['status']==='SAISONMITTEL_BEOBACHTBAR_NICHT_KAUSAL'
                ?'gleiche '.$seasonCompare['common_rooms'].' Raeume | Innen-Aussen-Abstand Sommer '.
                    number_format($seasonCompare['summer_mean_gap_k'],1,'.','').'K, Winter '.
                    number_format($seasonCompare['winter_mean_gap_k'],1,'.','').'K'
                :'NOCH_ZU_WENIG_WETTERPAARE ('.$seasonCompare['common_rooms'].' gemeinsame Raeume)')
                .' | nur Langzeitbeobachtung, keine Helios-Wirkung',
            'Sommer-Ueberwaermung: aktuelle Messhinweise in '.$recentHeatRooms.' Raeumen'
                .' | erhoehte langjaehrige Sommermonatsmittel in '.$seasonHeatRooms.' Raeumen'
                .' | keine Prognose/keine Schaltung',
            'Nacht-Lernbewertung: '.$nightHighPriority
                .' Raeume mit schwacher beobachteter Nachtabkuehlung trotz kuehler Aussennaechte'
                .' | Daten fehlen bei '.$nightNeedsEvidence
                .' | staerkere Nachtabkuehlung bereits beobachtet: '.$nightCoolingObserved
                .' | NUR VERGLEICHSBEDARF, keine Luefterfreigabe',
            'Stufen-Lernvergleich: '.$stagePairs.' natuerliche Wetter-Nachtpaare in '
                .$stageRooms.' Raeumen | erkennbare Stufenwechsel '.$stageTransitions
                .' | unvollstaendige Quellen '.$missingStageEvidence
                .' | Zuluft nicht kuehler '.$stageSupplyWarm
                .' | widerspruechliche Paare '.$stageConflicts
                .' | STUFEN_NUR_MOMENTAUFNAHMEN, KEIN WIRKUNGSNACHWEIS',
            'Stufen-Messluecken (max. 3 Raeume): '.(count($stageNextGaps)>0
                ?implode('; ',array_slice($stageNextGaps,0,3))
                :'keine aktuelle Sommer-Messluecke ausgewiesen'),
            'Nacht-Lernfokus (max. 3 Raeume): '.(count($highNightRooms)>0
                ?implode(', ',array_slice($highNightRooms,0,3))
                    .(count($highNightRooms)>3?', weitere '.(count($highNightRooms)-3):'')
                :'noch keiner ausreichend belegt'),
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
            '24h-Vergleich nur bei mindestens 8 Original-Messpunkten und einer passenden Messung 20-28h zuvor.',
            'Wetter/Zuluft-Kontext ist nur eine Momentaufnahme, KEIN Nachweis einer konstanten Helios-Stufe.',
            'Nachtabkuehlung: echte Abend-/Morgen-Messpunkte, keine Prognose und kein Beleg fuer Helios-Effekt.',
            'Vergleichbares Aussenwetter ist nicht gleichbedeutend mit gleichem Sonnen-/FBH-/Fensterzustand.',
            'Monatsmittel enthalten Beobachtungen verschiedener Jahre; kein Vergleich einzelner Kalenderjahre.',
            'Sommer-Waermehinweis nur bei echten warmen Originalpunkten, nicht wegen Einzelspitze oder Feuchtraum.',
            'Kein Nachweis einer Ursache durch Helios; FBH, Sonne, geoeffnete Tueren/Fenster koennen mitwirken.',
            'Nacht-Lernbewertung ist KEINE Empfehlung zu S4, Bypass oder Wochenprogrammwechsel.',
            'Auch mehrere gleiche Stufen-Snapshots beweisen KEINE lueckenlose Stufendauer; Solareintrag, FBH und Fenster ungeprueft.',
            'Gleiche Temperatur bedeutet weder Sensorfehler noch fehlenden Update; kein erzwungener Stundenwert.',
            'Bitte diesen gesamten Diagnose-Text in ChatGPT einfuegen; kein automatisches Senden.',
        ];
        $report=implode("\n",array_merge($header,$lines,$footer));
        return [
            'state'=>$state,
            'compact_state'=>JVHeliosLearningStore::encode($state['rooms'],
                $state['seasonal'],$state['last_report_at'],$state['last_diag_at']),
            'seasonal_totals'=>$seasonalTotals,
            'seasonal_climate'=>$seasonalClimate,
            'summer_winter_comparison'=>$seasonCompare,
            'report'=>$report,
            'fresh_temperature_rooms'=>$freshTemperature,
            'fresh_humidity_rooms'=>$freshHumidity,
            'new_distinct_temperature_samples'=>$changed,
            'room_count'=>count($state['rooms']),
            'trends_24h_rooms'=>$enoughForDay,
            'multi_day_rooms'=>$longerTrend,
            'paired_context_points'=>$paired,
            'per_room_observation'=>$roomStats,
            'per_room_night_patterns'=>$nightStats,
            'per_room_overheating_evidence'=>$overheatStats,
            'per_room_night_review'=>$nightOpportunities,
            'per_room_stage_study'=>$stageStudies,
            'observed_stage_study_pairs'=>$stagePairs,
            'stage_study_rooms'=>$stageRooms,
            'stage_transitions_seen'=>$stageTransitions,
            'incomplete_stage_source_nights'=>$missingStageEvidence,
            'stage_observational_contradictions'=>$stageConflicts,
            'stage_nights_without_cooler_supply'=>$stageSupplyWarm,
            'stage_next_data_gaps'=>$stageNextGaps,
            'night_high_review_priority_rooms'=>$nightHighPriority,
            'night_high_review_room_names'=>$highNightRooms,
            'night_missing_evidence_rooms'=>$nightNeedsEvidence,
            'night_already_cooling_rooms'=>$nightCoolingObserved,
            'recent_overheating_rooms'=>$recentHeatRooms,
            'historical_high_summer_means_rooms'=>$seasonHeatRooms,
            'night_pattern_rooms'=>$nightRooms,
            'similar_weather_night_pairs'=>$nightWeatherPairs,
            'learning_calibrated'=>false,
            'control_authorized'=>false,
        ];
    }
}
