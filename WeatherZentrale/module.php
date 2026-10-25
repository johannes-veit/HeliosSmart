<?php
require_once __DIR__ . '/../libs/WeatherLogic.php';
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
        $this->RegisterPropertyBoolean('EnableExternal', false); // Opt-in, keine HTTP-Requests bei Installation
        $this->RegisterAttributeString('Cache', '{}');
        $this->RegisterTimer('WeatherTick', 0, 'JVWC_Refresh($_IPS[\'TARGET\']);');
        $this->RegisterVariableFloat('OutdoorTemperature', 'Außentemperatur geprüft', '~Temperature', 10);
        $this->RegisterVariableFloat('OutdoorHumidity', 'Außenfeuchte geprüft', '~Humidity', 20);
        $this->RegisterVariableFloat('OutdoorDewPoint', 'Außentaupunkt geprüft', '~Temperature', 30);
        $this->RegisterVariableFloat('OutdoorAbsHumidity', 'Außenfeuchte absolut g/m³', '', 40);
        $this->RegisterVariableString('Quality', 'Wetterqualität', '', 50);
        $this->RegisterVariableString('ForecastJSON', 'Stundenprognose JSON', '', 60);
        $this->RegisterVariableString('Diagnostic', 'Wetterdiagnose', '', 70);
        $this->SetValue('Quality', 'UNAVAILABLE');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('WeatherTick', 15 * 60 * 1000);
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
                ['type'=>'CheckBox','name'=>'EnableExternal','caption'=>'HTTPS-Abrufe extern aktivieren'],
            ],
            'actions' => [['type'=>'Button','caption'=>'Wetter jetzt aktualisieren','onClick'=>'JVWC_Refresh($id);']]
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
            $this->refreshProvider($cache, 'dwd_observation', 15 * 60,
                $this->brightSkyURL($lat,$lon,$now-3*3600,$now), $now, $diag);
            $this->refreshProvider($cache, 'mosmix', 60 * 60,
                $this->brightSkyURL($lat,$lon,$now,$now+48*3600), $now, $diag);
            $query = [
                'latitude'=>$lat, 'longitude'=>$lon,
                'hourly'=>'temperature_2m,relative_humidity_2m,dew_point_2m',
                'timezone'=>'UTC', 'timeformat'=>'unixtime', 'forecast_days'=>3,
            ];
            $this->refreshProvider($cache, 'icon', 3*3600,
                'https://api.open-meteo.com/v1/dwd-icon?'.http_build_query($query+['models'=>'icon_d2']), $now, $diag);
            $this->refreshProvider($cache, 'ecmwf', 6*3600,
                'https://api.open-meteo.com/v1/ecmwf?'.http_build_query($query+['models'=>'ecmwf_ifs']), $now, $diag);
        }
        $externalActive=$this->ReadPropertyBoolean('EnableExternal') && $location!==null;
        $dwdRows = JVWeatherLogic::parseBrightSky($externalActive?($cache['dwd_observation']['body'] ?? null):null, $now, false);
        $dwd = $dwdRows ? end($dwdRows) : null;
        $chosen = JVWeatherLogic::chooseObservation($pws, $dwd, $now);
        $this->SetValue('Quality', $chosen['quality']);
        if ($chosen['current'] !== null) {
            $c = $chosen['current'];
            $this->SetValue('OutdoorTemperature', $c['temperature']);
            $this->SetValue('OutdoorHumidity', $c['rh']);
            $this->SetValue('OutdoorDewPoint', $c['dewpoint']);
            $this->SetValue('OutdoorAbsHumidity', $c['absolute_humidity']);
        }
        $bySource = [
            'MOSMIX' => JVWeatherLogic::parseBrightSky($externalActive?($cache['mosmix']['body'] ?? null):null,$now,true),
            'ICON' => JVWeatherLogic::parseOpenMeteo($externalActive?($cache['icon']['body'] ?? null):null,$now,'ICON'),
            'ECMWF' => JVWeatherLogic::parseOpenMeteo($externalActive?($cache['ecmwf']['body'] ?? null):null,$now,'ECMWF'),
        ];
        $forecast = JVWeatherLogic::combineForecasts($bySource, $now);
        $this->SetValue('ForecastJSON', json_encode($forecast, JSON_UNESCAPED_UNICODE));
        $this->SetValue('Diagnostic', $chosen['reason'].' | '.implode(' | ',$diag));
        // Nur tatsächlich frische Quellen werden vom Konsumenten als gültig betrachtet.
        $cache['snapshot'] = [
            'issued_at'=>$now, 'quality'=>$chosen['quality'], 'current'=>$chosen['current'],
            'forecast'=>$forecast, 'reason'=>$chosen['reason'], 'providers'=>array_map(
                static function($v){return ['fetched_at'=>$v['fetched_at']??0,'error'=>$v['error']??''];},
                array_intersect_key($cache,array_flip(['dwd_observation','mosmix','icon','ecmwf']))
            )
        ];
        $this->WriteAttributeString('Cache', json_encode($cache, JSON_UNESCAPED_UNICODE));
    }

    /** Einheitliche Ausgabeschnittstelle für weitere Module, ohne neue Einzelvariablen. */
    public function Snapshot(): string
    {
        $cache = json_decode($this->ReadAttributeString('Cache'), true);
        $snapshot = $cache['snapshot'] ?? null;
        if (!is_array($snapshot) || time() - (int)($snapshot['issued_at']??0)>35*60) {
            return json_encode(['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[], 'reason'=>'WetterZentrale nicht aktuell']);
        }
        // Auch bei gültigem Snapshot darf PWS_ONLY nie als unabhängige Bestätigung gelten.
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

    private function refreshProvider(array &$cache,string $key,int $period,string $url,int $now,array &$diag): void
    {
        $last=(int)($cache[$key]['fetched_at']??0);
        $failed=(int)($cache[$key]['failed_at']??0);
        // Backoff bei Störung, nicht bei jedem WetterTick vier fehlschlagende Requests.
        if ($now-$last<$period || ($failed && $now-$failed<15*60)) return;
        $body=$this->httpJSON($url);
        if ($body === null) {
            $cache[$key]['failed_at']=$now;
            $cache[$key]['error']='HTTP/JSON nicht verfügbar';
            $diag[]=$key.' derzeit nicht erreichbar';
            return;
        }
        $cache[$key]=['fetched_at'=>$now,'failed_at'=>0,'body'=>$body,'error'=>''];
    }

    private function httpJSON(string $url): ?array
    {
        $curl=curl_init($url);
        curl_setopt_array($curl,[
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>9,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_USERAGENT=>'Symcon-WetterZentrale/0.1',
        ]);
        $result=curl_exec($curl);
        $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($result)||$status!==200||strlen($result)>2_000_000) return null;
        $data=json_decode($result,true);
        return is_array($data)?$data:null;
    }
}
