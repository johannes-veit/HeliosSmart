<?php
require_once __DIR__ . '/../libs/HeliosLogic.php';
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
        $this->RegisterPropertyString('Rooms',json_encode([
            ['name'=>'EG Bad','temperature'=>12812,'humidity'=>53361,'wetroom'=>true],
            ['name'=>'OG Bad','temperature'=>36518,'humidity'=>28802,'wetroom'=>true],
            ['name'=>'EG Küche','temperature'=>21585,'humidity'=>52026,'wetroom'=>false],
            ['name'=>'EG Wohnen','temperature'=>29782,'humidity'=>46360,'wetroom'=>false],
            ['name'=>'EG Schlafen','temperature'=>25557,'humidity'=>58531,'wetroom'=>false],
            ['name'=>'OG Schlafen 1','temperature'=>30970,'humidity'=>37079,'wetroom'=>false],
            ['name'=>'OG Schlafen 2','temperature'=>25330,'humidity'=>22436,'wetroom'=>false],
        ],JSON_UNESCAPED_UNICODE));
        $this->RegisterPropertyBoolean('EnableControl',false);
        $this->RegisterPropertyBoolean('MinimumVentilationValidated',false);
        $this->RegisterPropertyBoolean('CombustionSafetyValidated',false);
        $this->RegisterPropertyBoolean('DevicePartyReturnValidated',false);
        $this->RegisterPropertyBoolean('EnableProgramSwitch',false);
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
                ['type'=>'Label','caption'=>'V0.1: Beobachtungsmodus. Aktivsteuerung wird erst nach expliziter Sicherheitsfreigabe möglich.'],
                ['type'=>'NumberSpinner','name'=>'HeliosInstance','caption'=>'Helios-Instanz'],
                ['type'=>'NumberSpinner','name'=>'WeatherCenterInstance','caption'=>'WetterZentrale-Instanz'],
                ['type'=>'NumberSpinner','name'=>'ClimateControlInstance','caption'=>'LCNClimateControl (nur lesend)'],
                ['type'=>'NumberSpinner','name'=>'HeliosFanVariable','caption'=>'Helios Lüfterstufe'],
                ['type'=>'NumberSpinner','name'=>'HeliosModeVariable','caption'=>'Helios Betriebsart'],
                ['type'=>'NumberSpinner','name'=>'HeliosProgramVariable','caption'=>'Helios Wochenprogramm'],
                ['type'=>'NumberSpinner','name'=>'HeliosSupplyTemperature','caption'=>'Helios Zulufttemperatur nach EWT und WRG'],
                ['type'=>'NumberSpinner','name'=>'HeliosIntakeAfterEWT','caption'=>'Helios-Geräteeintritt NACH Sole-EWT'],
                ['type'=>'ValidationTextBox','name'=>'Rooms','caption'=>'Raumsensoren JSON'],
                ['type'=>'CheckBox','name'=>'EnableControl','caption'=>'Aktive Helios-Befehle erlauben'],
                ['type'=>'CheckBox','name'=>'MinimumVentilationValidated','caption'=>'Luftmengen/Mindestlüftung fachlich bestätigt'],
                ['type'=>'CheckBox','name'=>'CombustionSafetyValidated','caption'=>'Ofen-/Unterdrucksicherheit geklärt'],
                ['type'=>'CheckBox','name'=>'DevicePartyReturnValidated','caption'=>'Geräteinternen Party-Rücksprung nachgewiesen'],
                ['type'=>'CheckBox','name'=>'EnableProgramSwitch','caption'=>'Automatische Wochenplanwahl erlauben'],
            ],
            'actions'=>[['type'=>'Button','caption'=>'Nur lesen: Empfehlung berechnen','onClick'=>'JVHS_Evaluate($id);']]
        ],JSON_UNESCAPED_UNICODE);
    }

    public function Evaluate(): void
    {
        $now=time();
        $weather=['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[]];
        $id=$this->ReadPropertyInteger('WeatherCenterInstance');
        if ($id>0 && IPS_InstanceExists($id)) {
            try {
                $payload=JVWC_Snapshot($id);
                $w=json_decode((string)$payload,true);
                if (is_array($w)) $weather=$w;
            } catch (Throwable $e) { $this->SendDebug('Weather',$e->getMessage(),0); }
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
        $rooms=[];
        $config=json_decode($this->ReadPropertyString('Rooms'),true);
        foreach (is_array($config)?$config:[] as $r) {
            $t=(int)($r['temperature']??0);$h=(int)($r['humidity']??0);
            if (!IPS_VariableExists($t)||!IPS_VariableExists($h)) continue;
            $rooms[]=[
                'name'=>(string)($r['name']??'Raum'),
                'temperature'=>GetValue($t),'rh'=>GetValue($h),
                'observed_at'=>min((int)IPS_GetVariable($t)['VariableUpdated'],(int)IPS_GetVariable($h)['VariableUpdated']),
                'wetroom'=>(bool)($r['wetroom']??false)
            ];
        }
        $decision=JVHeliosLogic::decide([
            'now'=>$now,'weather'=>$weather,'rooms'=>$rooms,
            'baseline_level'=>(int)GetValue($fanID),
            'supply_temperature'=>$supply,'supply_fresh'=>$supplyFresh,
        ]);
        $decision['ewt_effect_kelvin']=null;
        $ewtFresh=IPS_VariableExists($intakeID) && $now-(int)IPS_GetVariable($intakeID)['VariableUpdated']<=20*60;
        if ($decision['trusted_weather'] && $ewtFresh && is_array($weather['current']??null)) {
            // Positiv: Sole-EWT wärmt, negativ: Sole-EWT kühlt gegenüber realer Außenluft.
            $decision['ewt_effect_kelvin']=round((float)GetValue($intakeID)-(float)$weather['current']['temperature'],2);
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
        $decision['program_actual']=$program;
        $decision['program_recommended']=JVHeliosLogic::recommendProgram($weather,$decision,$now);
        $decision['helios_mode']=$mode;
        $decision['observation_only']=true;
        $this->SetValue('DecisionJSON',json_encode($decision,JSON_UNESCAPED_UNICODE));
        $canWrite=$this->ReadPropertyBoolean('EnableControl')
            && $this->ReadPropertyBoolean('MinimumVentilationValidated')
            && $this->ReadPropertyBoolean('CombustionSafetyValidated')
            && $this->ReadPropertyBoolean('DevicePartyReturnValidated');
        $this->SetValue('Monitoring',!$canWrite);
        if (!$canWrite) {
            $this->SetValue('Status','BEOBACHTUNG | '.$decision['mode'].' | Stufe '.$decision['requested_level'].' | '.$decision['reason']);
            return;
        }
        $helios=$this->ReadPropertyInteger('HeliosInstance');
        if (!IPS_InstanceExists($helios)) {
            $this->SetValue('Status','FEHLER: Helios-Instanz nicht vorhanden; nur Wochenplan');return;
        }
        // Niemals manuelle Eingriffe des Benutzers überschreiben. Party darf nur von uns stammen.
        $ownedExpires=$this->ReadAttributeInteger('OwnedPartyExpiresAt');
        if ($mode!==0 && !($mode===2 && $now<$ownedExpires)) {
            $this->SetValue('Status','Manueller/Gerätebetrieb hat Vorrang; keine Automatikbefehle');return;
        }
        if (!$decision['trusted_weather']) {
            // Nur eigene Party beenden; keine fremde Party/Urlaubsteuerung ändern.
            if ($mode===2 && $now<$ownedExpires) {
                HELIOS_OperatingMode_Party_Set($helios,false,0,5);
                $this->WriteAttributeInteger('OwnedPartyExpiresAt',0);
            }
            $this->SetValue('Status','Wetter gestört: Rückkehr zum vorhandenen Helios-Wochenplan; keine Zusatzlüftung');return;
        }
        // Normalprogramm-Switch ist optional und höchstens alle 24 Stunden zulässig.
        // Der Sommerplan ist im Backup nachweislich 4, der andere Plan 3.
        if ($mode===0 && $this->ReadPropertyBoolean('EnableProgramSwitch')
            && $decision['program_recommended']!==null
            && $decision['program_recommended']!==$program
            && $now-$this->ReadAttributeInteger('LastProgramAt')>86400) {
            if (HELIOS_WeekProgram_Set($helios,$decision['program_recommended'])===true) {
                $this->WriteAttributeInteger('LastProgramAt',$now);
            }
        }
        // Zeitbegrenzte Boosts nur bei ausfallsicherem, zuvor bestätigtem Geräte-Rücksprung.
        if ($mode===0 && $decision['duration_minutes']>=5 && $decision['requested_level']>(int)GetValue($fanID)
            && $now-$this->ReadAttributeInteger('LastCommandAt')>=30*60) {
            if (HELIOS_OperatingMode_Party_Set($helios,true,(int)$decision['requested_level'],(int)$decision['duration_minutes'])===true) {
                $this->WriteAttributeInteger('LastCommandAt',$now);
                $this->WriteAttributeInteger('OwnedPartyExpiresAt',$now+$decision['duration_minutes']*60+60);
                $this->SetValue('Status','GERÄTE-PARTY AKTIV | '.$decision['mode'].' | '.$decision['duration_minutes'].' min');return;
            }
            $this->SetValue('Status','Befehl nicht bestätigt; Woche bleibt Fallback');return;
        }
        $this->SetValue('Status','Wochenprogramm aktiv | '.$decision['mode'].' | keine zusätzliche Umschaltung erforderlich');
    }
}
