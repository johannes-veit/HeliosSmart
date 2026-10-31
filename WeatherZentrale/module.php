<?php
require_once __DIR__ . '/../libs/WeatherLogic.php';
require_once __DIR__ . '/../libs/SoilClimatology.php';
require_once __DIR__ . '/../libs/SoilHistorySchedule.php';
require_once __DIR__ . '/../libs/CurrentSoilModel.php';
require_once __DIR__ . '/../libs/EWTEnvironment.php';
/** Symcon 9: Zentrale, geteilte und rein lesende Wetterversorgung. */
class WetterZentrale extends IPSModule
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('LocationInstance', 11842);
        $this->RegisterPropertyFloat('Latitude', 0.0);
        $this->RegisterPropertyFloat('Longitude', 0.0);
        $this->RegisterPropertyInteger('PWSTemperature', 31433);
        $this->RegisterPropertyInteger('PWSHumidity', 12304);
        // Optional real ground altitude; 0 = unknown (never assume altitude).
        $this->RegisterPropertyFloat('SiteElevation', 0.0);
        $this->RegisterPropertyBoolean('EnableExternal', false); // Opt-in, keine HTTP-Requests bei Installation
        // Read-only historical model follows already approved external access.
        // One past calendar year per timer cycle, compact 12-value summary only.
        $this->RegisterPropertyBoolean('EnableSoilHistory', true);
        $this->RegisterPropertyInteger('SoilHistoryYears', 5);
        $this->RegisterPropertyBoolean('EnableSoilCurrent', true);
        $this->RegisterAttributeString('SoilCurrentState', '{}');
        $this->RegisterTimer('SoilCurrentTick', 0,
            'JVWC_RefreshCurrentSoil($_IPS[\'TARGET\']);');
        $this->RegisterAttributeString('SoilHistory', '{}');
        $this->RegisterAttributeString('SoilHistorySnapshot', '{}');
        $this->RegisterTimer('SoilHistoryTick', 0, 'JVWC_RefreshSoilHistoryScheduled($_IPS[\'TARGET\']);');
        $this->RegisterAttributeString('Cache', '{}');
        $this->RegisterTimer('WeatherTick', 0, 'JVWC_Refresh($_IPS[\'TARGET\']);');
        $this->RegisterVariableFloat('OutdoorTemperature', 'Außentemperatur geprüft', '~Temperature', 10);
        $this->RegisterVariableFloat('OutdoorHumidity', 'Außenfeuchte geprüft', '~Humidity', 20);
        $this->RegisterVariableFloat('OutdoorDewPoint', 'Außentaupunkt geprüft', '~Temperature', 30);
        $this->RegisterVariableFloat('OutdoorAbsHumidity', 'Außenfeuchte absolut g/m³', '', 40);
        $this->RegisterVariableString('Quality', 'Wetterqualität', '', 50);
        $this->RegisterVariableString('ForecastJSON', 'Stundenprognose JSON', '', 60);
        $this->RegisterVariableString('Diagnostic', 'Wetterdiagnose', '', 70);
        $this->RegisterVariableString('SoilClimatologyJSON', 'EWT Bodenkennlinie ERA5-Land – Modell', '', 80);
        $this->RegisterVariableString('SoilHistoryStatus', 'EWT Bodenhistorie – Datenqualität', '', 90);
        $this->RegisterVariableString('SoilHistoryRunStatus',
            'EWT Bodenhistorie – Import/Timer', '', 100);
        $this->RegisterVariableFloat('SoilCurrentShallowC',
            'Bodenmodell aktuell 28–100 cm (kein Messwert)', '~Temperature', 110);
        $this->RegisterVariableFloat('SoilCurrentDeepC',
            'Bodenmodell aktuell 100–255 cm (kein Messwert)', '~Temperature', 120);
        $this->RegisterVariableString('SoilCurrentStatus',
            'Bodenmodell aktuell – 6-Stunden-Status', '', 130);
        $this->RegisterVariableString('SoilCurrentJSON',
            'Bodenmodell aktuell – Datenquelle und Zeitpunkt JSON', '', 140);
        $this->SetValue('Quality', 'UNAVAILABLE');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('WeatherTick', 5 * 60 * 1000);
        if (!$this->ReadPropertyBoolean('EnableExternal')
            || !$this->ReadPropertyBoolean('EnableSoilHistory')) {
            $this->SetTimerInterval('SoilHistoryTick',0);
            $this->SetValue('SoilHistoryRunStatus',
                'AUTOMATIK AUS: HTTPS oder Bodenhistorie deaktiviert');
        } else {
            $store=json_decode($this->ReadAttributeString('SoilHistory'),true);
            if(!is_array($store))$store=[];
            $loc=$this->coordinates();
            if($loc!==null && ($store['location']??'')!==sprintf('%.4f,%.4f',$loc[0],$loc[1]))
                $store=[]; // old coordinates never justify deferring a new site
            $now=time();
            $delay=JVHeliosSoilHistorySchedule::delaySeconds($store,
                $now,$this->ReadPropertyInteger('SoilHistoryYears'),true);
            $this->SetTimerInterval('SoilHistoryTick',$delay*1000);
            $progress=JVHeliosSoilHistorySchedule::progress($store,$now,
                $this->ReadPropertyInteger('SoilHistoryYears'));
            $last=(int)($store['last_run_at']??0);
            $source=(string)($store['last_run_source']??'');
            $this->SetValue('SoilHistoryRunStatus',
                'AUTO-TIMER AKTIV | naechster Versuch in '.$delay.' s'
                .' | Jahre '.count($progress['completed']).'/'.$progress['total']
                .' | letzter Lauf '.($last>0
                    ?date('d.m.Y H:i:s',$last).' ('.$source.')':'NOCH NIE'));
        }
        if($this->ReadPropertyBoolean('EnableExternal')
            && $this->ReadPropertyBoolean('EnableSoilCurrent')){
            $stored=json_decode($this->ReadAttributeString('SoilCurrentState'),true);
            if(!is_array($stored))$stored=[];
            $loc=$this->coordinates();
            $key=$loc===null?'':sprintf('%.4f,%.4f',$loc[0],$loc[1]);
            $last=($stored['location']??'')===$key
                ?(int)($stored['last_attempt_at']??0):0;
            $delay=$last>0?max(60,6*3600-(time()-$last)):60;
            $this->SetTimerInterval('SoilCurrentTick',$delay*1000);
            $this->SetValue('SoilCurrentStatus',
                'BODENMODELL AUTO AKTIV | 6-h-Intervall'
                .' | naechster Abruf in '.$delay.' s'
                .' | bisheriger Modellstand '
                .((int)($stored['last_success_at']??0)>0
                    ?date('d.m.Y H:i:s',(int)$stored['last_success_at'])
                    :'NOCH NIE'));
        }else{
            $this->SetTimerInterval('SoilCurrentTick',0);
            $this->SetValue('SoilCurrentStatus',
                'BODENMODELL AUS – externe Abfragen oder aktuelles Bodenmodell deaktiviert');
        }
        // Kein HTTP in ApplyChanges: historische Quelle und aktuelle Werte getrennt.
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements' => [
                ['type'=>'Label','caption'=>'Zentraler Wetterdienst – Installation startet passiv, nur lesend'],
                ['type'=>'NumberSpinner','name'=>'LocationInstance','caption'=>'Symcon Location-Control-ID (Backup: 11842)'],
                ['type'=>'NumberSpinner','name'=>'Latitude','caption'=>'Breitengrad (0 = aus Location Control)'],
                ['type'=>'NumberSpinner','name'=>'Longitude','caption'=>'Längengrad (0 = aus Location Control)'],
                ['type'=>'NumberSpinner','name'=>'PWSTemperature','caption'=>'Eigene Wetterstation Temperatur-ID'],
                ['type'=>'NumberSpinner','name'=>'PWSHumidity','caption'=>'Eigene Wetterstation Feuchte-ID'],
                ['type'=>'NumberSpinner','name'=>'SiteElevation','caption'=>'Haus-Hoehenlage m ue.NN (0 = nicht bekannt)'],
                ['type'=>'CheckBox','name'=>'EnableExternal','caption'=>'HTTPS-Abrufe extern aktivieren'],
                ['type'=>'CheckBox','name'=>'EnableSoilHistory','caption'=>'Zusatz: 5 Jahre historische ERA5-Land Bodentemperaturen (nur Modell, ein Jahr je 10 Minuten, nur wenn HTTPS aktiv)'],
                ['type'=>'NumberSpinner','name'=>'SoilHistoryYears','caption'=>'Vollständige historische Jahre (3–10, Standard 5)'],
                ['type'=>'CheckBox','name'=>'EnableSoilCurrent',
                    'caption'=>'Aktuelle MODELL-Bodentemperatur 28–100/100–255 cm alle 6 h abrufen (ECMWF IFS, KEIN DWD-Messwert)'],
                ['type'=>'Label','caption'=>'ERA5-Land-Historie nach Erstbefuellung nur einmal jaehrlich (ab 10. Januar), aktuelle Bodenmodell-Werte viermal taeglich. DWD-Stationsmessungen in 1 m sind eine separate, derzeit nicht angebundene Messquelle.'],
            ],
            'actions' => [
                ['type'=>'Button','caption'=>'Wetter jetzt aktualisieren','onClick'=>'JVWC_Refresh($id);'],
                ['type'=>'Button','caption'=>'Bodenhistorie nächsten Jahrgang abrufen','onClick'=>'JVWC_RefreshSoilHistory($id);'],
                ['type'=>'Button','caption'=>'Bodenmodell jetzt einmal abrufen','onClick'=>'JVWC_RefreshCurrentSoil($id);']
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    public function Refresh(): void
    {
        $now = time();
        $cache = json_decode($this->ReadAttributeString('Cache'), true);
        if (!is_array($cache)) $cache = [];
        $diag = [];
        $location = $this->coordinates();
        $pws = $this->pws($now);
        if ($location === null) {
            $diag[] = 'Keine gültigen Hauskoordinaten';
        } elseif (!$this->ReadPropertyBoolean('EnableExternal')) {
            $diag[] = 'Externe HTTP-Abrufe noch deaktiviert';
        } else {
            [$lat,$lon] = $location;
            // Maximal 2 HTTP-Aufrufe je Durchlauf: SymBox wird nicht durch
            // 7 serielle Timeouts blockiert. Alle Quellen werden zyklisch aktualisiert.
            $query = [
                'latitude'=>$lat, 'longitude'=>$lon,
                'hourly'=>'temperature_2m,relative_humidity_2m,dew_point_2m',
                'timezone'=>'UTC', 'timeformat'=>'unixtime', 'forecast_days'=>3,
            ];
            $requests = [
                'met_no'=>[2*3600,'https://api.met.no/weatherapi/locationforecast/2.0/compact?'
                    .http_build_query(['lat'=>round($lat,4),'lon'=>round($lon,4)])],
                'best_match'=>[60*60,'https://api.open-meteo.com/v1/forecast?'.http_build_query(
                    array_replace($query,['hourly'=>
                        'temperature_2m,relative_humidity_2m,dew_point_2m,wind_speed_10m,precipitation,shortwave_radiation']))],
                'dwd_synop'=>[15*60,'https://api.brightsky.dev/current_weather?'.http_build_query(['lat'=>$lat,'lon'=>$lon])],
                'icon'=>[3*3600,'https://api.open-meteo.com/v1/dwd-icon?'.http_build_query($query+['models'=>'icon_d2'])],
                'ecmwf'=>[6*3600,'https://api.open-meteo.com/v1/ecmwf?'.http_build_query($query+['models'=>'ecmwf_ifs'])],
                'dwd_observation'=>[60*60,$this->brightSkyURL($lat,$lon,$now-3*3600,$now)],
                // MOSMIX ist optional: unvollstaendige Bright-Sky-Daten blockieren niemals die WetterZentrale.
                'mosmix'=>[6*3600,$this->brightSkyURL($lat,$lon,$now,$now+48*3600)],
            ];
            $done=0;
            foreach ($requests as $provider => $request) {
                if ($done>=2) break;
                if ($this->refreshProvider($cache,$provider,$request[0],$request[1],$now,$diag)) $done++;
            }
        }
        $externalActive=$this->ReadPropertyBoolean('EnableExternal') && $location!==null;
        $dwdLive = JVWeatherLogic::parseBrightSkyCurrent($externalActive?($cache['dwd_synop']['body'] ?? null):null, $now);
        $dwdRows = JVWeatherLogic::parseBrightSky($externalActive?($cache['dwd_observation']['body'] ?? null):null, $now, false);
        $dwdHistory = $dwdRows ? end($dwdRows) : null;
        // Kein Forecast als unabhaengige Messung: SYNOP vor echter /weather-Historie.
        $dwd = JVWeatherLogic::isFresh($dwdLive, $now, 2700) ? $dwdLive : $dwdHistory;
        // Nur ausreichend aktuelle Abrufe zulassen; alte Cache-Daten niemals als aktuelle Prognose ausgeben.
        $validBody = static function (string $key, int $maxAge) use ($cache, $externalActive, $now): ?array {
            if (!$externalActive || $now-(int)($cache[$key]['fetched_at']??0)>$maxAge) return null;
            return is_array($cache[$key]['body']??null) ? $cache[$key]['body'] : null;
        };
        $bySource = [
            'MOSMIX'=>JVWeatherLogic::parseBrightSky($validBody('mosmix',6*3600),$now,true),
            'ICON'=>JVWeatherLogic::parseOpenMeteo($validBody('icon',8*3600),$now,'ICON'),
            'ECMWF'=>JVWeatherLogic::parseOpenMeteo($validBody('ecmwf',12*3600),$now,'ECMWF'),
            'BEST_MATCH'=>JVWeatherLogic::parseOpenMeteo($validBody('best_match',4*3600),$now,'BEST_MATCH'),
            'MET_NO'=>JVWeatherLogic::parseMetNo($validBody('met_no',6*3600),$now),
        ];
        $chosen=JVWeatherLogic::assessSources($pws,$dwd,$bySource,$now,$this->ReadPropertyFloat('SiteElevation'));
        if ($externalActive) {
            if (!JVWeatherLogic::isFresh($dwd, $now, 2700)) {
                $diag[] = 'DWD: kein verwendbarer Messwert unter 45 Minuten ('
                    . ($dwdLive ? 'SYNOP zu alt' : 'SYNOP fehlt/ungueltig') . ')';
            } else {
                $diag[] = 'DWD Messquelle: '.($dwd === $dwdLive ? 'SYNOP' : 'historische Station')
                    .' | Zeitstempel '.gmdate('H:i', (int)$dwd['observed_at']).' UTC'
                    .' | Distanz '.(isset($dwd['distance_m'])?round((float)$dwd['distance_m']/1000,1).' km':'unbekannt')
                    .' | Stationshoehe '.(isset($dwd['station_height_m'])?round((float)$dwd['station_height_m']).' m':'unbekannt');
            }
        }
        $this->SetValue('Quality', $chosen['quality']);
        if ($chosen['current'] !== null) {
            $c = $chosen['current'];
            $this->SetValue('OutdoorTemperature', $c['temperature']);
            $this->SetValue('OutdoorHumidity', $c['rh']);
            $this->SetValue('OutdoorDewPoint', $c['dewpoint']);
            $this->SetValue('OutdoorAbsHumidity', $c['absolute_humidity']);
        }
        $environment=JVHeliosEWTEnvironment::parse(
            $validBody('best_match',4*3600),$now);
        $forecast = JVWeatherLogic::combineForecasts($bySource, $now);
        $this->SetValue('ForecastJSON', json_encode($forecast, JSON_UNESCAPED_UNICODE));
        if ($externalActive) {
            $diag[] = 'Prognosen MOSMIX '.count($bySource['MOSMIX'])
                .'/ICON '.count($bySource['ICON']).'/ECMWF '.count($bySource['ECMWF'])
                .'/BestMatch '.count($bySource['BEST_MATCH']).'/MET.no '.count($bySource['MET_NO']);
            foreach (['dwd_synop','dwd_observation','mosmix','icon','ecmwf','best_match','met_no'] as $provider) {
                if (($cache[$provider]['error'] ?? '') !== '') $diag[] = $provider.': '.$cache[$provider]['error'];
            }
        }
        $this->SetValue('Diagnostic', $chosen['reason'].' | '.implode(' | ',$diag));
        // Nur tatsächlich frische Quellen werden vom Konsumenten als gültig betrachtet.
        $cache['snapshot'] = [
            'issued_at'=>$now, 'quality'=>$chosen['quality'], 'current'=>$chosen['current'],
            'forecast'=>$forecast, 'reason'=>$chosen['reason'],
            'environment_context'=>$environment,'providers'=>array_map(
                static function($v){return ['fetched_at'=>$v['fetched_at']??0,'error'=>$v['error']??''];},
                array_intersect_key($cache,array_flip(['dwd_synop','dwd_observation','mosmix','icon','ecmwf','best_match','met_no']))
            )
        ];
        $this->WriteAttributeString('Cache', json_encode($cache, JSON_UNESCAPED_UNICODE));
    }


    /**
     * Historical ERA5-Land grid model (100-255 cm and 28-100 cm).
     * Uses the same coordinates as weather forecasts; never writes actuators.
     * Keeps at most 10 x 12 monthly means in a Symcon attribute.
     * Does NOT reinterpret the model as a current 2.5-m soil sensor.
     */
    /** Button: a single immediate year, while the timer continues itself. */
    public function RefreshSoilHistory(): void
    {
        $this->ExecuteSoilHistory('MANUELL');
    }

    /** Own timer callback: shown separately from manual requests. */
    public function RefreshSoilHistoryScheduled(): void
    {
        $this->ExecuteSoilHistory('TIMER');
    }

    private function ExecuteSoilHistory(string $trigger): void
    {
        $lock='JVWC_SOIL_HISTORY_'.$this->InstanceID;
        $locked=false;
        if(function_exists('IPS_SemaphoreEnter')) {
            $locked=IPS_SemaphoreEnter($lock,1000);
            if(!$locked) {
                $this->SetValue('SoilHistoryRunStatus',
                    'Bodenimport bereits aktiv – kein paralleler HTTP-Aufruf');
                return;
            }
        }
        try {
            $this->RefreshSoilHistoryInternal($trigger);
        } finally {
            if($locked && function_exists('IPS_SemaphoreLeave'))
                IPS_SemaphoreLeave($lock);
        }
    }

    private function RefreshSoilHistoryInternal(string $trigger): void
    {
        if (!$this->ReadPropertyBoolean('EnableExternal')
            || !$this->ReadPropertyBoolean('EnableSoilHistory')) {
            $this->SetTimerInterval('SoilHistoryTick',0);
            $this->SetValue('SoilHistoryStatus','Bodenhistorie passiv – externe Abfragen oder Bodenhistorie deaktiviert');
            $this->SetValue('SoilHistoryRunStatus',
                'AUTO-TIMER AUS – externe Abfragen oder Bodenhistorie deaktiviert');
            return;
        }
        $loc=$this->coordinates();
        if($loc===null){
            $this->SetValue('SoilHistoryStatus','Bodenhistorie: Hauskoordinaten unbekannt');
            $this->SetValue('SoilHistoryRunStatus',
                'Bodenimport blockiert: Hauskoordinaten fehlen');
            $this->SetTimerInterval('SoilHistoryTick',6*3600*1000);
            return;
        }
        [$lat,$lon]=$loc;
        $now=time();
        $key=sprintf('%.4f,%.4f',$lat,$lon);
        $store=json_decode($this->ReadAttributeString('SoilHistory'),true);
        if(!is_array($store)||($store['location']??'')!==$key){
            // Never silently reuse a climatology from a different site.
            $store=['location'=>$key,'years'=>[],'error'=>''];
        }
        if(!is_array($store['years']??null))$store['years']=[];
        $store['last_run_at']=$now;
        $store['last_run_source']=$trigger;
        $store['last_attempt_result']='NO_REQUEST_NEEDED';
        $num=max(3,min(10,$this->ReadPropertyInteger('SoilHistoryYears')));
        $end=(int)gmdate('Y',$now)-1;
        $wanted=range($end-$num+1,$end);
        $wantedKeys=array_fill_keys($wanted,true);
        $store['years']=array_intersect_key($store['years'],$wantedKeys);
        if($now>=(int)($store['retry_after']??0)){
            foreach($wanted as $year){
                if(isset($store['years'][$year]))continue;
                $store['last_attempt_at']=$now;
                $store['last_attempt_year']=$year;
                $url=JVHeliosSoilClimatology::apiUrl($lat,$lon,$year);
                try {
                    $http=$this->httpJSON($url);
                    $annual=is_array($http['body']??null)
                        ?JVHeliosSoilClimatology::annual($http['body'],$year):null;
                } catch(Throwable $e) {
                    $annual=null;
                    $this->SendDebug('SoilHistoryFetch',
                        substr($e->getMessage(),0,200),0);
                }
                if($annual!==null){
                    $store['years'][$year]=$annual;
                    $store['error']='';
                    $store['retry_after']=0;
                    $store['updated_at']=$now;
                    $store['last_attempt_result']='OK_YEAR_'.$year;
                }else{
                    $store['error']='ERA5-Land Jahr '.$year.' HTTP/Datensatz unbrauchbar';
                    $store['last_attempt_result']='FAILED_YEAR_'.$year;
                    // Failing endpoints are not polled every 10 minutes.
                    $store['retry_after']=$now+6*3600;
                }
                // ONE request per 10-min timer tick, never a burst of 5-10 years.
                break;
            }
        }
        $summary=JVHeliosSoilClimatology::aggregate($store['years'],$now);
        $summary['location']=$key;
        $summary['lat']=round($lat,4);
        $summary['lon']=round($lon,4);
        $summary['generated_at']=$now;
        $summary['last_data_at']=$store['updated_at']??null;
        $this->SetValue('SoilClimatologyJSON',json_encode($summary,JSON_UNESCAPED_UNICODE));
        $this->WriteAttributeString('SoilHistorySnapshot',json_encode($summary,JSON_UNESCAPED_UNICODE));
        $this->WriteAttributeString('SoilHistory',json_encode($store,JSON_UNESCAPED_UNICODE));
        $status='ERA5-Land 28–100 / 100–255 cm (Modell, kein Solefühler)'
            .' | Jahre '.count($store['years']).'/'.count($wanted)
            .' | Qualität '.$summary['quality']
            .' | Tiefenphasenversatz '.($summary['lag_months']===null?'unbekannt':$summary['lag_months'].' Monate');
        if(($store['error']??'')!=='')$status.=' | '.$store['error'];
        $this->SetValue('SoilHistoryStatus',$status);
        $progress=JVHeliosSoilHistorySchedule::progress($store,$now,$num);
        $delay=JVHeliosSoilHistorySchedule::delaySeconds($store,$now,$num);
        $this->SetTimerInterval('SoilHistoryTick',$delay*1000);
        $this->SetValue('SoilHistoryRunStatus',
            'AUTO-TIMER AKTIV | zuletzt '.$trigger.' um '.date('d.m.Y H:i:s',$now)
            .' | '.$store['last_attempt_result']
            .' | Jahre '.count($progress['completed']).'/'.$progress['total']
            .' | naechster Timer in '.$delay.' s'
            .(($store['error']??'')!==''?' | '.$store['error']:''));

    }

    /**
     * Four context observations per day. ECMWF model output is NOT a soil
     * thermometer or DWD station value, and never overrides frost detection.
     * Only one external HTTP request per six-hour interval.
     */
    public function RefreshCurrentSoil(): void
    {
        if(!$this->ReadPropertyBoolean('EnableExternal')
            || !$this->ReadPropertyBoolean('EnableSoilCurrent')){
            $this->SetTimerInterval('SoilCurrentTick',0);
            $this->SetValue('SoilCurrentStatus',
                'BODENMODELL AUS – externe Daten nicht freigegeben');
            return;
        }
        $loc=$this->coordinates();
        if($loc===null){
            $this->SetValue('SoilCurrentStatus',
                'BODENMODELL – HAUSKOORDINATEN FEHLEN');
            $this->SetTimerInterval('SoilCurrentTick',6*3600*1000);
            return;
        }
        $now=time();
        $key=sprintf('%.4f,%.4f',$loc[0],$loc[1]);
        $previous=json_decode($this->ReadAttributeString('SoilCurrentState'),true);
        if(!is_array($previous)||($previous['location']??'')!==$key)
            $previous=['location'=>$key];
        // Even a manual request cannot hammer the provider at a higher rate.
        $elapsed=$now-(int)($previous['last_attempt_at']??0);
        if($elapsed>=0 && $elapsed<6*3600){
            $this->SetTimerInterval('SoilCurrentTick',max(60,6*3600-$elapsed)*1000);
            $this->SetValue('SoilCurrentStatus',
                'BODENMODELL RATE-LIMIT 6 h | letzte Anfrage '
                .date('d.m.Y H:i:s',(int)$previous['last_attempt_at'])
                .' | Quelle ECMWF IFS / Open-Meteo (Modell, nicht Messung)');
            return;
        }
        $lock='JVWC_SOIL_CURRENT_'.$this->InstanceID;
        $locked=false;
        if(function_exists('IPS_SemaphoreEnter')){
            if(!IPS_SemaphoreEnter($lock,1000))return;
            $locked=true;
        }
        try{
            // Re-check inside critical section before issuing HTTP.
            $state=json_decode($this->ReadAttributeString('SoilCurrentState'),true);
            if(!is_array($state)||($state['location']??'')!==$key)
                $state=['location'=>$key];
            if($now-(int)($state['last_attempt_at']??0)<6*3600){
                $this->SetTimerInterval('SoilCurrentTick',6*3600*1000);
                return;
            }
            $state['last_attempt_at']=$now;
            $state['location']=$key;
            $model=null;
            try{
                $http=$this->httpJSON(JVHeliosCurrentSoilModel::url($loc[0],$loc[1]));
                $model=JVHeliosCurrentSoilModel::latest(
                    is_array($http['body']??null)?$http['body']:null,$now);
            }catch(Throwable $e){
                $this->SendDebug('SoilCurrent',
                    substr($e->getMessage(),0,200),0);
            }
            if($model!==null){
                $model['location']=$key;
                $model['lat']=round($loc[0],4);
                $model['lon']=round($loc[1],4);
                $model['retrieved_at']=$now;
                $state['snapshot']=$model;
                $state['last_success_at']=$now;
                $state['error']='';
                $this->SetValue('SoilCurrentShallowC',$model['shallow_c']);
                $this->SetValue('SoilCurrentDeepC',$model['deep_c']);
                $this->SetValue('SoilCurrentJSON',json_encode($model,JSON_UNESCAPED_UNICODE));
            } else {
                $state['error']='Aktuelle ECMWF-IFS-Bodenschichten nicht verfuegbar';
            }
            $this->WriteAttributeString('SoilCurrentState',
                json_encode($state,JSON_UNESCAPED_UNICODE));
            $this->SetValue('SoilCurrentStatus',
                ($model!==null?'BODENMODELL OK':'BODENMODELL NICHT VERFUEGBAR')
                .' | Quelle ECMWF IFS / Open-Meteo – kein DWD-Messwert'
                .' | letzter Versuch '.date('d.m.Y H:i:s',$now)
                .' | nächster Abruf in 6 h'
                .(($state['error']??'')!==''?' | '.$state['error']:''));
        } finally {
            $this->SetTimerInterval('SoilCurrentTick',6*3600*1000);
            if($locked && function_exists('IPS_SemaphoreLeave'))
                IPS_SemaphoreLeave($lock);
        }
    }

    /** Einheitliche Ausgabeschnittstelle für weitere Module, ohne neue Einzelvariablen. */
    public function Snapshot(): string
    {
        $cache = json_decode($this->ReadAttributeString('Cache'), true);
        $snapshot = $cache['snapshot'] ?? null;
        if (!is_array($snapshot) || time() - (int)($snapshot['issued_at']??0)>35*60) {
            return json_encode(['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[], 'reason'=>'WetterZentrale nicht aktuell']);
        }
        // Soil climatology is independent archival information, not current weather.
        // Never upgrade weather quality because the model exists.
        if($this->ReadPropertyBoolean('EnableExternal')
            && $this->ReadPropertyBoolean('EnableSoilHistory')){
            $soil=json_decode($this->ReadAttributeString('SoilHistorySnapshot'),true);
            $site=$this->coordinates();
            $key=$site===null?'':sprintf('%.4f,%.4f',$site[0],$site[1]);
            if(is_array($soil) && ($soil['location']??null)===$key)
                $snapshot['soil_climatology']=$soil;
        }
        if($this->ReadPropertyBoolean('EnableExternal')
            && $this->ReadPropertyBoolean('EnableSoilCurrent')){
            $currentState=json_decode($this->ReadAttributeString('SoilCurrentState'),true);
            $site=$this->coordinates();
            $siteKey=$site===null?'':sprintf('%.4f,%.4f',$site[0],$site[1]);
            $model=is_array($currentState['snapshot']??null)
                ?$currentState['snapshot']:null;
            if(is_array($model) && ($model['location']??'')===$siteKey
                && time()-(int)($model['retrieved_at']??0)<=12*3600
                && time()>=(int)($model['retrieved_at']??0)){
                $snapshot['soil_current_model']=$model;
            }
        }
        // Auch bei gültigem Snapshot darf PWS_ONLY nie als unabhaengige Bestaetigung gelten.
        return json_encode($snapshot, JSON_UNESCAPED_UNICODE);
    }

    private function coordinates(): ?array
    {
        $lat = $this->ReadPropertyFloat('Latitude');
        $lon = $this->ReadPropertyFloat('Longitude');
        if ($lat == 0.0 && $lon == 0.0) {
            $id = $this->ReadPropertyInteger('LocationInstance');
            if ($id > 0 && IPS_InstanceExists($id)) {
                $config = json_decode(IPS_GetConfiguration($id),true);
                $loc = $config['Location'] ?? null;
                if (is_string($loc)) $loc = json_decode($loc,true);
                if (is_array($loc)) {
                    $lat = (float)($loc['latitude']??0.0);
                    $lon = (float)($loc['longitude']??0.0);
                }
            }
        }
        return abs($lat) <= 90 && abs($lon) <= 180 && !($lat==0.0 && $lon==0.0) ? [$lat,$lon] : null;
    }

    private function pws(int $now): ?array
    {
        $tID=$this->ReadPropertyInteger('PWSTemperature');
        $hID=$this->ReadPropertyInteger('PWSHumidity');
        if (!IPS_VariableExists($tID) || !IPS_VariableExists($hID)) return null;
        $t=IPS_GetVariable($tID); $h=IPS_GetVariable($hID);
        $age = min((int)$t['VariableUpdated'], (int)$h['VariableUpdated']);
        if ($age < $now-2700 || $age > $now+120) return null;
        $pair=JVWeatherMath::readPair(GetValue($tID),GetValue($hID));
        if ($pair === null) return null;
        return $pair + ['observed_at'=>$age,'source'=>'PWS'];
    }

    private function brightSkyURL(float $lat,float $lon,int $start,int $end): string
    {
        return 'https://api.brightsky.dev/weather?'.http_build_query([
            'lat'=>$lat,'lon'=>$lon,'date'=>gmdate('Y-m-d\TH:i:s\Z',$start),
            'last_date'=>gmdate('Y-m-d\TH:i:s\Z',$end),
        ]);
    }

    private function refreshProvider(array &$cache,string $key,int $period,string $url,int $now,array &$diag): bool
    {
        $last=(int)($cache[$key]['fetched_at']??0);
        $failed=(int)($cache[$key]['failed_at']??0);
        $expires=(int)($cache[$key]['expires_at']??0);
        if ($now < $expires || $now-$last<$period || ($failed && $now-$failed<15*60)) return false;
        $conditional=$key==='met_no' ? ($cache[$key]['last_modified']??null) : null;
        $response=$this->httpJSON($url,is_string($conditional)?$conditional:null);
        if ($response===null) {
            $cache[$key]['failed_at']=$now;
            $cache[$key]['error']='HTTP/JSON nicht verfuegbar';
            $diag[]=$key.' derzeit nicht erreichbar';
            return true;
        }
        $entry=$cache[$key]??[];
        if (!empty($response['not_modified'])) {
            if (!is_array($entry['body']??null)) {
                $cache[$key]['failed_at']=$now;
                $cache[$key]['error']='304 aber kein lokaler Cache vorhanden';
                return true;
            }
        } else {
            $entry['body']=$response['body'];
        }
        $entry['fetched_at']=$now;
        $entry['failed_at']=0;
        $entry['error']='';
        if (!empty($response['last_modified'])) $entry['last_modified']=$response['last_modified'];
        $entry['expires_at']=$key==='met_no' ? max($now,min($now+6*3600,(int)($response['expires_at']??0))) : 0;
        $cache[$key]=$entry;
        return true;
    }

    private function httpJSON(string $url,?string $lastModified=null): ?array
    {
        $curl=curl_init($url);
        if ($curl===false) return null;
        $headers=[];
        if ($lastModified!==null && $lastModified!=='') $headers[]='If-Modified-Since: '.$lastModified;
        curl_setopt_array($curl,[
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true,
            CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>12,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_USERAGENT=>'HeliosSmartWeather/0.1.2 (+https://github.com/johannes-veit/HeliosSmart)',
        ]);
        $response=curl_exec($curl);
        $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $headerSize=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);
        curl_close($curl);
        if (!is_string($response) || $headerSize<0 || $headerSize>strlen($response)) return null;
        $headerText=substr($response,0,$headerSize);
        $body=substr($response,$headerSize);
        $modified=null;$expires=0;
        if (preg_match('/^Last-Modified:\s*(.+)$/mi',$headerText,$matches)) $modified=trim($matches[1]);
        if (preg_match('/^Expires:\s*(.+)$/mi',$headerText,$matches)) $expires=strtotime(trim($matches[1]))?:0;
        if ($status===304) return ['not_modified'=>true,'last_modified'=>$modified,'expires_at'=>$expires];
        if ($status!==200 || strlen($body)>2_000_000) return null;
        $data=json_decode($body,true);
        if (!is_array($data)) return null;
        return ['body'=>$data,'last_modified'=>$modified,'expires_at'=>$expires,'not_modified'=>false];
    }
}
