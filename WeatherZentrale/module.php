<?php
require_once __DIR__ . '/../libs/WeatherLogic.php';
require_once __DIR__ . '/../libs/SoilClimatology.php';
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
        $this->RegisterAttributeString('SoilHistory', '{}');
        $this->RegisterTimer('SoilHistoryTick', 0, 'JVWC_RefreshSoilHistory($_IPS[\'TARGET\']);');
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
        $this->SetValue('Quality', 'UNAVAILABLE');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('WeatherTick', 5 * 60 * 1000);
        $this->SetTimerInterval('SoilHistoryTick', 10 * 60 * 1000);
        // ApplyChanges führt bewusst keine HTTP-Abfragen aus.
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
            ],
            'actions' => [
                ['type'=>'Button','caption'=>'Wetter jetzt aktualisieren','onClick'=>'JVWC_Refresh($id);'],
                ['type'=>'Button','caption'=>'Bodenhistorie nächsten Jahrgang abrufen','onClick'=>'JVWC_RefreshSoilHistory($id);']
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
                'best_match'=>[60*60,'https://api.open-meteo.com/v1/forecast?'.http_build_query($query)],
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
            'forecast'=>$forecast, 'reason'=>$chosen['reason'], 'providers'=>array_map(
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
    public function RefreshSoilHistory(): void
    {
        if (!$this->ReadPropertyBoolean('EnableExternal')
            || !$this->ReadPropertyBoolean('EnableSoilHistory')) {
            $this->SetValue('SoilHistoryStatus','Bodenhistorie passiv – externe Abfragen oder Bodenhistorie deaktiviert');
            return;
        }
        $loc=$this->coordinates();
        if($loc===null){
            $this->SetValue('SoilHistoryStatus','Bodenhistorie: Hauskoordinaten unbekannt');
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
                $http=$this->httpJSON($url);
                $annual=is_array($http['body']??null)
                    ?JVHeliosSoilClimatology::annual($http['body'],$year):null;
                if($annual!==null){
                    $store['years'][$year]=$annual;
                    $store['error']='';
                    $store['retry_after']=0;
                    $store['updated_at']=$now;
                }else{
                    $store['error']='ERA5-Land Jahr '.$year.' HTTP/Datensatz unbrauchbar';
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
        $this->WriteAttributeString('SoilHistory',json_encode($store,JSON_UNESCAPED_UNICODE));
        $status='ERA5-Land 28–100 / 100–255 cm (Modell, kein Solefühler)'
            .' | Jahre '.count($store['years']).'/'.count($wanted)
            .' | Qualität '.$summary['quality']
            .' | Tiefenphasenversatz '.($summary['lag_months']===null?'unbekannt':$summary['lag_months'].' Monate');
        if(($store['error']??'')!=='')$status.=' | '.$store['error'];
        $this->SetValue('SoilHistoryStatus',$status);
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
            $soil=json_decode((string)$this->GetValue('SoilClimatologyJSON'),true);
            $site=$this->coordinates();
            $key=$site===null?'':sprintf('%.4f,%.4f',$site[0],$site[1]);
            if(is_array($soil) && ($soil['location']??null)===$key)
                $snapshot['soil_climatology']=$soil;
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
