<?php
require_once __DIR__ . '/../libs/HeliosLogic.php';
require_once __DIR__ . '/../libs/RoomHistory.php';
require_once __DIR__ . '/../libs/LCNRoomSources.php';
require_once __DIR__ . '/../libs/ForecastAdvisor.php';
require_once __DIR__ . '/../libs/DailyStrategy.php';
/** Vorhersagegestützter Beobachter; Aktor nur nach expliziter Freigabe. */
class HeliosSmart extends IPSModule
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('HeliosInstance', 19285);
        $this->RegisterPropertyInteger('WeatherCenterInstance', 0);
        $this->RegisterPropertyInteger('ClimateControlInstance', 17597);
        $this->RegisterPropertyInteger('HeliosFanVariable',30441);
        $this->RegisterPropertyInteger('HeliosModeVariable',58762);
        $this->RegisterPropertyInteger('HeliosProgramVariable',12877);
        $this->RegisterPropertyInteger('HeliosSupplyTemperature',41272);
        $this->RegisterPropertyInteger('HeliosIntakeAfterEWT',28581);
        $this->RegisterPropertyString('Rooms',json_encode(JVHeliosLCNRoomSources::SOURCES, JSON_UNESCAPED_UNICODE));
        $this->RegisterPropertyBoolean('EnableControl',false);
        $this->RegisterPropertyBoolean('MinimumVentilationValidated',false);
        $this->RegisterPropertyBoolean('CombustionSafetyValidated',false);
        $this->RegisterPropertyBoolean('DevicePartyReturnValidated',false);
        $this->RegisterPropertyBoolean('EnableProgramSwitch',false);
        $this->RegisterAttributeString('RoomHistory', '{}');
        $this->RegisterAttributeInteger('LastCommandAt',0);
        $this->RegisterAttributeInteger('LastProgramAt',0);
        $this->RegisterAttributeInteger('OwnedPartyExpiresAt',0);
        $this->RegisterTimer('SmartTick',0,'JVHS_Evaluate($_IPS[\'TARGET\']);');
        $this->RegisterVariableString('Status','Entscheidung / Status','',10);
        $this->RegisterVariableString('DecisionJSON','Letzte Empfehlung JSON','',20);
        $this->RegisterVariableBoolean('Monitoring','Nur beobachten','~Switch',30);
        $this->SetValue('Monitoring',true);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('SmartTick',5*60*1000);
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements'=>[
                ['type'=>'Label','caption'=>'v0.4.1: Tagesstrategie, Vorhersagefenster und zwölf native LCN-Feuchtequellen; Ausschliesslich Beobachtung. Keine Geraetebefehle, auch nicht bei gesetzten alten Freigabe-Eigenschaften.'],
                ['type'=>'NumberSpinner','name'=>'HeliosInstance','caption'=>'Helios-Instanz'],
                ['type'=>'NumberSpinner','name'=>'WeatherCenterInstance','caption'=>'WetterZentrale-Instanz (0 = automatisch erkennen)'],
                ['type'=>'NumberSpinner','name'=>'ClimateControlInstance','caption'=>'LCNClimateControl (nur lesend)'],
                ['type'=>'NumberSpinner','name'=>'HeliosFanVariable','caption'=>'Helios Lüfterstufe'],
                ['type'=>'NumberSpinner','name'=>'HeliosModeVariable','caption'=>'Helios Betriebsart'],
                ['type'=>'NumberSpinner','name'=>'HeliosProgramVariable','caption'=>'Helios Wochenprogramm'],
                ['type'=>'NumberSpinner','name'=>'HeliosSupplyTemperature','caption'=>'Helios Zulufttemperatur nach EWT und WRG'],
                ['type'=>'NumberSpinner','name'=>'HeliosIntakeAfterEWT','caption'=>'Helios-Geräteeintritt NACH Sole-EWT'],
                ['type'=>'ValidationTextBox','name'=>'Rooms','caption'=>'Raumsensoren JSON'],
            ],
            'actions'=>[['type'=>'Button','caption'=>'Nur lesen: Empfehlung berechnen','onClick'=>'JVHS_Evaluate($id);']]
        ],JSON_UNESCAPED_UNICODE);
    }

    /** WeatherCenterInstance=0 wird bewusst als automatische Suche behandelt.
     *  Niemals eine fremde Modulinstanz anhand des Anzeigenamens verwenden. */
    private function FindWeatherCenter(): array
    {
        $configured = $this->ReadPropertyInteger('WeatherCenterInstance');
        $guid = '{685106B4-47EC-43C9-9CA9-9BAE53E4B304}';
        $reason = '';
        if ($configured > 0 && IPS_InstanceExists($configured)) {
            if (!function_exists('IPS_GetInstance') ||
                strcasecmp((string)(IPS_GetInstance($configured)['ModuleInfo']['ModuleID'] ?? ''), $guid) === 0) {
                return [$configured, 'konfiguriert'];
            }
            $reason = 'Konfigurierte Wetter-ID gehört nicht zur WetterZentrale';
        } elseif ($configured > 0) {
            $reason = 'Konfigurierte Wetter-ID existiert nicht';
        }
        if (function_exists('IPS_GetInstanceListByModuleID')) {
            $found = array_values(array_filter((array)IPS_GetInstanceListByModuleID($guid),
                static fn($x): bool => IPS_InstanceExists((int)$x)));
            if (count($found) === 1) return [(int)$found[0], 'automatisch erkannt'];
            if (count($found) > 1) return [0, 'Mehrere WetterZentralen gefunden, Instanz explizit auswählen'];
        }
        return [0, $reason !== '' ? $reason : 'Keine WetterZentrale gefunden – WetterZentrale-Instanz eintragen'];
    }

    /** NUR LESEN. Datengüte der Sensoren bleibt in der Diagnose sichtbar. */
    private function ReadRooms(int $now): array
    {
        $rooms=[];
        $missing=[];
        $config=json_decode($this->ReadPropertyString('Rooms'),true);
        $humiditySources=[];
        foreach (JVHeliosLCNRoomSources::merge(is_array($config)?$config:[]) as $r) {
            $name=(string)($r['name']??'Raum');
            $t=(int)($r['temperature']??0);$h=(int)($r['humidity']??0);
            // Feuchte direkt aus dem LCN-Modul, nie über Info/Link-Objekte.
            $hExists=$h>0 && IPS_VariableExists($h);
            $hAge=$hExists ? max(0,$now-(int)(IPS_GetVariable($h)['VariableUpdated']??0)) : null;
            $sourceState=JVHeliosLCNRoomSources::isKnownRawHumidity($h)?'LCN_ORIGINAL':'KONFIGURIERT';
            if ($hExists && function_exists('IPS_GetObject')) {
                $object=IPS_GetObject($h);
                if ((int)($object['ObjectType']??2)===6) {
                    $hExists=false;
                    $sourceState='LINK_VERBOTEN';
                }
            }
            $humiditySources[]=[
                'room'=>$name,'lcn_humidity_id'=>$h,'source'=>$sourceState,
                'available'=>$hExists,'age_minutes'=>$hAge===null?null:(int)ceil($hAge/60),
                'humidity_only'=>$t<=0,
            ];
            if (!$hExists) {
                $missing[]=$name.' (LCN-Luftfeuchte #'.$h.' nicht vorhanden oder Link)';
                continue;
            }
            if ($t<=0) {
                // EG Flur hat keinen eindeutig zugeordneten Temperatur-Istwert im Backup.
                // Relative Feuchte wird diagnostiziert; absolute Feuchte niemals geraten.
                continue;
            }
            if (!IPS_VariableExists($t)) {
                $missing[]=$name.' (Isttemperatur #'.$t.' nicht vorhanden)';
                continue;
            }
            $tTime=(int)(IPS_GetVariable($t)['VariableUpdated']??0);
            $hTime=(int)(IPS_GetVariable($h)['VariableUpdated']??0);
            $rooms[]=[
                'name'=>$name, 'temperature'=>GetValue($t), 'rh'=>GetValue($h),
                'temperature_observed_at'=>$tTime, 'humidity_observed_at'=>$hTime,
                'observed_at'=>min($tTime,$hTime),
                'wetroom'=>(bool)($r['wetroom']??false),
            ];
        }
        return [$rooms,$missing,$humiditySources];
    }

    public function Evaluate(): void
    {
        $now=time();
        $weather=['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[]];
        [$id, $weatherConnection] = $this->FindWeatherCenter();
        if ($id > 0) {
            try {
                $payload=JVWC_Snapshot($id);
                $w=json_decode((string)$payload,true);
                if (is_array($w)) {
                    $weather=$w;
                    $weatherConnection.=' (#'.$id.')';
                } else {
                    $weatherConnection='Wetter-Snapshot nicht als JSON lesbar';
                }
            } catch (Throwable $e) {
                $weatherConnection='Wetter-Snapshot Fehler: '.substr($e->getMessage(),0,120);
                $this->SendDebug('Weather',$e->getMessage(),0);
            }
        }
        $fanID=$this->ReadPropertyInteger('HeliosFanVariable');
        $modeID=$this->ReadPropertyInteger('HeliosModeVariable');
        $progID=$this->ReadPropertyInteger('HeliosProgramVariable');
        $supplyID=$this->ReadPropertyInteger('HeliosSupplyTemperature');
        $intakeID=$this->ReadPropertyInteger('HeliosIntakeAfterEWT');
        if (!IPS_VariableExists($fanID)||!IPS_VariableExists($modeID)||!IPS_VariableExists($progID)) {
            $this->SetValue('Status','Stopp: fehlende Helios-Basisvariablen; keine Befehle');return;
        }
        $mode=(int)GetValue($modeID);$program=(int)GetValue($progID);
        $supplyFresh=IPS_VariableExists($supplyID) && $now-(int)IPS_GetVariable($supplyID)['VariableUpdated']<=20*60;
        $supply=$supplyFresh?GetValue($supplyID):null;
        [$rooms, $missingRooms, $humiditySources] = $this->ReadRooms($now);
        // Humidity history is an attribute, not seven more user-facing variables.
        $prev=json_decode($this->ReadAttributeString('RoomHistory'),true);
        $tracking=JVHeliosRoomHistory::update(is_array($prev)?$prev:[],$rooms,$now);
        $this->WriteAttributeString('RoomHistory',json_encode($tracking['state'],JSON_UNESCAPED_UNICODE));
        $decision=JVHeliosLogic::decide([
            'now'=>$now,'weather'=>$weather,'rooms'=>$tracking['rooms'],
            'baseline_level'=>(int)GetValue($fanID),
            'supply_temperature'=>$supply,'supply_fresh'=>$supplyFresh,
        ]);
        // Prognoseplanung ist rein lesend: Wettermodell != Zuluft nach Sole-EWT.
        // Frische Innenraumfeuchte ist Voraussetzung fuer die Feuchtebewertung.
        $indoorAH = ($decision['fresh_humidity_room_count']??0) >= 2
            ? ($decision['indoor_absolute_humidity']??null) : null;
        $forecastAdvice = JVHeliosForecastAdvisor::analyze(
            (array)($weather['forecast']??[]), $now,
            isset($decision['indoor_temperature']) ? (float)$decision['indoor_temperature'] : null,
            $indoorAH,
            (string)($decision['weather_quality']??'UNAVAILABLE'),
            $supplyFresh?(float)$supply:null,
            IPS_VariableExists($intakeID) && $now-(int)IPS_GetVariable($intakeID)['VariableUpdated']<=20*60
                ? (float)GetValue($intakeID):null,
            ($decision['weather_usable_for_advice']??false) && isset($weather['current']['temperature'])
                ? (float)$weather['current']['temperature']:null,
            (int)($decision['fresh_humidity_room_count']??0),
            ($decision['mode']??'')==='HUMIDITY_BOOST'
        );
        $decision['forecast_advice']=$forecastAdvice;
        $decision['ewt_effect_kelvin']=null;
        $ewtFresh=IPS_VariableExists($intakeID) && $now-(int)IPS_GetVariable($intakeID)['VariableUpdated']<=20*60;
        if ($decision['weather_usable_for_advice'] && $ewtFresh && is_array($weather['current']??null)) {
            // Positiv: Sole-EWT wärmt, negativ: Sole-EWT kühlt gegenüber realer Außenluft.
            $decision['ewt_effect_kelvin']=round((float)GetValue($intakeID)-(float)$weather['current']['temperature'],2);
            $decision['ewt_reference_quality']=$decision['weather_quality'];
        }
        $decision['climate_mode']=null; // Lesend, keine Änderung an LCNClimateControl.
        $climateID=$this->ReadPropertyInteger('ClimateControlInstance');
        if (function_exists('IPS_GetChildrenIDs') && function_exists('IPS_GetObject')
            && IPS_InstanceExists($climateID)) {
            foreach (IPS_GetChildrenIDs($climateID) as $child) {
                if (IPS_VariableExists($child) && (IPS_GetObject($child)['ObjectIdent']??'')==='Mode') {
                    $decision['climate_mode']=(int)GetValue($child);break;
                }
            }
        }
        $decision['weather_center_id']=$id;
        $decision['weather_connection']=$weatherConnection;
        $decision['weather_provider_reason']=(string)($weather['reason']??'');
        $decision['missing_room_mappings']=$missingRooms;
        $decision['lcn_humidity_sources']=$humiditySources;
        $decision['lcn_humidity_source_count']=count(array_filter($humiditySources,static fn($x)=>$x['available']));
        // Physikalisch interpretierbare Tagesstrategie, ohne fiktive Luftmengen.
        // 'forecast_advice' wird bereits oben berechnet und bleibt separat erhalten.
        $ewtMeasured = $ewtFresh ? (float)GetValue($intakeID) : null;
        $weatherOutside = $decision['weather_usable_for_advice']
            && is_array($weather['current']??null) && is_numeric($weather['current']['temperature']??null)
            ? (float)$weather['current']['temperature'] : null;
        $decision['daily_strategy']=JVHeliosDailyStrategy::analyze(
            $decision, $forecastAdvice, $ewtMeasured, $weatherOutside, $humiditySources, $program);
        $decision['program_actual']=$program;
        $decision['program_recommended']=JVHeliosLogic::recommendProgram($weather,$decision,$now);
        $decision['helios_mode']=$mode;
        $decision['observation_only']=true;
        $this->SetValue('DecisionJSON',json_encode($decision,JSON_UNESCAPED_UNICODE));
        // v0.4.1 is intentionally and unconditionally read-only.
        // Legacy approval properties remain registered to avoid migration issues,
        // but cannot authorize any HELIOS_* function from this version.
        $this->SetValue('Monitoring',true);
        $this->SetValue('Status','BEOBACHTUNG | '.$decision['mode']
            .' | Wetter '.$decision['weather_quality'].' ('.$weatherConnection.') | '
            .($decision['mode']==='WEEKLY_BASE'
                ? 'Aktuelle Stufe '.$decision['requested_level'].' (kein zusaetzlicher Boost)'
                : 'Unverbindlicher Vorschlag Stufe '.$decision['requested_level'])
            .' | '.$decision['reason']
            .' | Tag: '.$decision['daily_strategy']['summary']
            .' | 24h: '.$forecastAdvice['summary']);
    }
}
