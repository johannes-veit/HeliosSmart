<?php
define('VM_UPDATE',10603);
/** Minimale Offline-Harness: es existiert keine echte SymBox, Hardwarebefehle sind verboten. */
$now=time();
$GLOBALS['values']=[
 31433=>14.0, 12304=>76.0, 30441=>2,58762=>0,12877=>3,
 41272=>18.0, 12812=>23.0,53361=>71,36518=>23.0,28802=>59,
 21585=>22.0,52026=>55,29782=>23.0,46360=>56,25557=>22.0,58531=>54,
 30970=>22.0,37079=>55,25330=>22.0,22436=>54,
];
$GLOBALS['variableTime']=time()-100;
$GLOBALS['calls']=[];
function IPS_SemaphoreEnter($name,$timeout){return true;}
function IPS_SemaphoreLeave($name){return true;}
function IPS_InstanceExists($id){return in_array($id,[11842,16987,19285,17597,42,32969],true);}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>$id===32969?'{D94700DB-96B0-490C-9F23-E6AE39677384}':($id===42?'{685106B4-47EC-43C9-9CA9-9BAE53E4B304}':'{OTHER-INSTANCE}')]];}
function IPS_GetObjectIDByIdent($ident,$instance){return $instance===32969?(['JohannesPresence'=>60001,'CarinaPresence'=>60002,'EvidenceJSON'=>60003][$ident]??0):0;}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function IPS_GetVariable($id){return ['VariableUpdated'=>$GLOBALS['variableTime'],'VariableChanged'=>$GLOBALS['changedAt'][$id]??0];}
function GetValue($id){return $GLOBALS['values'][$id];}
function IPS_GetConfiguration($id){return json_encode(['Location'=>json_encode(['latitude'=>50,'longitude'=>7])]);}
function JVWC_Snapshot($id){return json_encode($GLOBALS['weatherForTest']??['quality'=>'VERIFIED','current'=>JVWeatherMath::readPair(5,100)+['observed_at'=>time()-120], 'forecast'=>[]]);}
function HELIOS_WeekProgram_Set(...$args){$GLOBALS['calls'][]=['week',$args];return true;}
function HELIOS_OperatingMode_Party_Set(...$args){$GLOBALS['calls'][]=['party',$args];return true;}
class IPSModule {
    public int $InstanceID=52187;
    private array $props=[],$attributes=[],$values=[],$timers=[];
    public function Create():void{} public function ApplyChanges():void{}
    public function RegisterPropertyInteger($key,$value){$this->props[$key]=$value;}
    public function RegisterPropertyBoolean($key,$value){$this->props[$key]=$value;}
    public function RegisterPropertyFloat($key,$value){$this->props[$key]=$value;}
    public function RegisterPropertyString($key,$value){$this->props[$key]=$value;}
    public function ReadPropertyInteger($key){return $this->props[$key];}
    public function ReadPropertyBoolean($key){return $this->props[$key];}
    public function ReadPropertyFloat($key){return $this->props[$key];}
    public function ReadPropertyString($key){return $this->props[$key];}
    public function RegisterAttributeInteger($key,$value){$this->attributes[$key]=$value;}
    public function RegisterAttributeString($key,$value){$this->attributes[$key]=$value;}
    public function ReadAttributeString($key){return $this->attributes[$key];}
    public function WriteAttributeString($key,$value){$this->attributes[$key]=$value;}
    public function ReadAttributeInteger($key){return $this->attributes[$key];}
    public function WriteAttributeInteger($key,$value){$this->attributes[$key]=$value;}
    public function RegisterTimer($key,$period,$script){$this->timers[$key]=$period;}
    public function SetTimerInterval($key,$period){$this->timers[$key]=$period;}
    public function RegisterVariableFloat($key,$name,$profile='',$pos=0){$this->values[$key]=null;}
    public function RegisterVariableBoolean($key,$name,$profile='',$pos=0){$this->values[$key]=null;}
    public function RegisterVariableString($key,$name,$profile='',$pos=0){$this->values[$key]=null;}
    public function SetValue($key,$value){$this->values[$key]=$value;}
    public function value($key){return $this->values[$key]??null;}
    public function setProperty($key,$value){$this->props[$key]=$value;}
    public function RegisterMessage($id,$msg){$this->watch[(int)$id]=(int)$msg;return true;}
    public function UnregisterMessage($id,$msg){unset($this->watch[(int)$id]);return true;}
    public function getWatches(){return $this->watch??[];}
    public function SendDebug(...$args){}
}
require_once __DIR__.'/../WeatherZentrale/module.php';
require_once __DIR__.'/../HeliosSmart/module.php';
function a($x,$why){if(!$x){fwrite(STDERR,"FAIL $why\n");exit(1);}echo "PASS $why\n";}
$wc=new WetterZentrale();$wc->Create();$wc->ApplyChanges();$wc->Refresh();
a($wc->value('Quality')==='PWS_ONLY','Ohne externe Daten PWS nicht verifiziert');
$hs=new HeliosSmart();$hs->Create();$hs->ApplyChanges();$hs->setProperty('WeatherCenterInstance',42);
a(str_contains((string)$hs->value('LernDiagnose'),'LERNDIAGNOSE FUER CHATGPT'),
  'Neue Lerndiagnose ist sofort nach ApplyChanges gefuellt und zeigt nicht mehr Nie');
a(count($GLOBALS['calls'])===0,'ApplyChanges Lerndiagnose hat keinerlei Aktorbefehle');
$form=json_decode($hs->GetConfigurationForm(),true);
a(str_contains(json_encode($form),'JVHS_RefreshLearningReport'),
  'Modul-Formular hat eigenen Schaltbefehls-freien Diagnose-Aktualisieren-Knopf');

a(array_keys($hs->getWatches())===[53361,28802],'Nur EG/OG Bad registrieren VM_UPDATE');
a($hs->getWatches()[53361]===VM_UPDATE && $hs->getWatches()[28802]===VM_UPDATE,'Abonnements reagieren ausschliesslich auf VM_UPDATE');

$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Standard Beobachtung: Null Gerätebefehle');
$learningText=(string)$hs->value('LernDiagnose');
a(str_contains($learningText,'HELIOS SMART')
  && str_contains($learningText,'LERNDIAGNOSE FUER CHATGPT')
  && str_contains($learningText,'nicht gemessen'),
  'Eine Lerndiagnose-Textvariable mit Quellen und Grenzen vorhanden');
a(str_contains($learningText,'Keine automatische Uebertragung'),
  'Kein ungewollter externer ChatGPT-Versand durch Lernprogramm');
$sampled=json_decode($hs->ReadAttributeString('LearningObservationState'),true);
a(is_array($sampled) && is_array($sampled['rooms']??null),
  'Lernmesspunkte intern im Attribut statt weiteren sichtbaren Variablen');
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,
  'Wiederholte Lernbeobachtung verursacht keinen zusaetzlichen Helios-Befehl');
// Manually requested diagnosis must consult verified local WeatherZentrale
// but must never refresh it or issue a Helios actuation.
$GLOBALS['weatherForTest']=[
  'quality'=>'VERIFIED',
  'current'=>JVWeatherMath::readPair(8.0,80.0)+['observed_at'=>time()-100],
  'forecast'=>[]
];
$oldCommandCount=count($GLOBALS['calls']);
$hs->RefreshLearningReport();
a(str_contains((string)$hs->value('LernDiagnose'),'Wetter: VERIFIED')
  && str_contains((string)$hs->value('LernDiagnose'),'Aussen 8.0C')
  && str_contains((string)$hs->value('LernDiagnose'),'Taupunkt'),
  'Manual diagnosis includes independently verified true outdoor climate');
a(count($GLOBALS['calls'])===$oldCommandCount,
  'Weather learning diagnosis stays purely read-only');
$GLOBALS['weatherForTest']['current']['observed_at']=time()-7200;
$hs->RefreshLearningReport();
a(str_contains((string)$hs->value('LernDiagnose'),'NICHT_AKTUELL_ODER_UNBESTAETIGT')
 && str_contains((string)$hs->value('LernDiagnose'),'Aussen ?C'),
 'Stale weather source not misrepresented as fresh readings');
unset($GLOBALS['weatherForTest']);


a(str_contains($hs->value('Status'),'BEOBACHTUNG'),'Status signalisiert Beobachtung');
$nowStrategy=json_decode($hs->value('DecisionJSON'),true);
a(isset($nowStrategy['forecast_advice']) && isset($nowStrategy['daily_strategy']),
  'Tagesstrategie und 24-h-Auswertung werden gemeinsam im bestehenden JSON veroeffentlicht');
a(($nowStrategy['daily_strategy']['safety']??'')==='ADVISORY_ONLY',
  'Neue Tagesstrategie gibt keine Geraetefreigabe');
a(($nowStrategy['daily_strategy']['program_change_executed']??null)===false,
  'Kein Wochenplanwechsel durch Tagesstrategie');
$hs->setProperty('EnableControl',true);
$beforeLearningButtons=count($GLOBALS['calls']);
$hs->RefreshLearningReport();
a(count($GLOBALS['calls'])===$beforeLearningButtons
  && str_contains((string)$hs->value('LernDiagnose'),'LERNDIAGNOSE FUER CHATGPT'),
  'Separater Lernbericht schaltet auch bei aktivierter Automatik keine Helios-Party');
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Freigabesperren blockieren einzelne Aktivierung');
$hs->setProperty('MinimumVentilationValidated',true);
$hs->setProperty('CombustionSafetyValidated',true);
$hs->setProperty('DevicePartyReturnValidated',true);
$GLOBALS['values'][58762]=1; $hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Manuelle Helios-Bedienung hat Vorrang');
$GLOBALS['values'][58762]=0;
$GLOBALS['values'][12812]=20.0;
$GLOBALS['values'][53361]=45;
$GLOBALS['values'][36518]=20.0;
$GLOBALS['values'][28802]=45;
$GLOBALS['values'][41272]=23;
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Keine Zusatzlüftung ohne Bedarf');
a(str_contains($hs->value('Status'),'Aktuelle Stufe') && !str_contains($hs->value('Status'),'Vorschlag Stufe'), 'Keine ausgelesene Ist-Stufe als optimierten Sollwert ausgeben');
a(is_array(json_decode($hs->value('DecisionJSON'),true)),'Gültige Entscheidungsdiagnose');
$GLOBALS['weatherForTest']=['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[]];
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Wetterausfall lässt Geräte-Wochenplan unverändert');
a(str_contains($hs->value('Status'),'UNAVAILABLE'),'Expliziter Wetter-Störungsstatus im Beobachtungsmodus');
// Critical regression: even legacy TRUE approval flags cannot cause a hardware write.
$GLOBALS['weatherForTest']=['quality'=>'MODEL_SUPPORTED',
 'current'=>JVWeatherMath::readPair(6,95)+['observed_at'=>time()-120,'source'=>'PWS'], 'forecast'=>[]];
$GLOBALS['values'][12812]=24.0;$GLOBALS['values'][53361]=74;
$GLOBALS['values'][36518]=23.0;$GLOBALS['values'][28802]=52;
$GLOBALS['values'][41272]=18.0;
$hs->setProperty('EnableProgramSwitch',true);
$hs->Evaluate();
$decision=json_decode($hs->value('DecisionJSON'),true);
a($decision['mode']==='HUMIDITY_BOOST','MODEL_SUPPORTED berechnet Bad-Boost im Beobachtungsmodus');
a($decision['safe_to_execute']===false && $decision['observation_only']===false,'Keine Ausfuehrungsfreigabe im Ergebnis');
a($hs->value('Monitoring')===false,'Steuerungswunsch erkennbar; ohne live Hersteller-Lesewerte kein Versand');
a(count($GLOBALS['calls'])===0,'Null Helios-Befehle trotz aller Freigaben und erkannter Feuchtespitze');
$GLOBALS['weatherForTest']=['quality'=>'VERIFIED',
 'current'=>JVWeatherMath::readPair(6,95)+['observed_at'=>time()-120,'source'=>'DWD_SYNOP'], 'forecast'=>[]];
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Null Helios-Befehle selbst mit VERIFIED und aktiven Alt-Freigaben');

// v0.3.0: forecasting only enriches status and JSON; it cannot actuate the Helios.
$GLOBALS['weatherForTest']=['quality'=>'VERIFIED',
 'current'=>JVWeatherMath::readPair(15,60)+['observed_at'=>time()-120,'source'=>'DWD_SYNOP'],
 'forecast'=>[]];
for ($j=1;$j<=20;$j++) {
    $GLOBALS['weatherForTest']['forecast'][]=[
      'observed_at'=>time()+$j*3600,
      'temperature'=>16.0,'rh'=>55.0,'confidence'=>'OK','source'=>'ICON'];
}
$GLOBALS['values'][12812]=25.0;$GLOBALS['values'][53361]=46;
$GLOBALS['values'][36518]=25.0;$GLOBALS['values'][28802]=45;
foreach ([12812,36518,21585,29782,25557,30970,25330] as $roomTempID) $GLOBALS['values'][$roomTempID]=25.0;
$GLOBALS['values'][41272]=20.0;
$hs->Evaluate();
$withForecast=json_decode($hs->value('DecisionJSON'),true);
a(isset($withForecast['forecast_advice']) && is_array($withForecast['forecast_advice']),
 '24h-Prognose im vorhandenen Entscheidungs-JSON enthalten');
a(($withForecast['forecast_advice']['status']??null)==='READY',
 'Gueltige Prognosen und frische LCN-Sensoren ergeben Planungsfenster');
a(count($withForecast['forecast_advice']['cooling_windows']??[])>=1,
 'Kuehlfenster in Beobachtungsdiagnose vorhanden');
a(str_contains($hs->value('Status'),'24h:'),
 'Status enthaelt kurze 24h-Zusammenfassung');
a(count($GLOBALS['calls'])===0,'Prognose erzeugt auch bei alten Freigaben null Helios-Befehle');

$lastDecision=json_decode($hs->value('DecisionJSON'),true);
a(isset($lastDecision['comfort_advice']['active_boost_authorized'])
    && $lastDecision['comfort_advice']['active_boost_authorized']===false,
    'V0.5 Komfortkontext kann keinen Boost aktivieren');
a(isset($lastDecision['seasonal_advice']['program_changed'])
    && $lastDecision['seasonal_advice']['program_changed']===false,
    'V0.5 Saisonprofil bleibt eine Leseberechnung');
a(count($GLOBALS['calls'])===0,'V0.5 weiterhin garantiert null Aktoraufrufe');
a(is_array($lastDecision['ventilation_need']??null), 'v0.6 Bedarf separat in vorhandenem JSON');
a(($lastDecision['ventilation_need']['hardware_authorized']??null)===false,
  'Bedarfsklassifikation hat keinen Aktor');
a(($lastDecision['ventilation_need']['air_quality_verified']??null)===false,
  'CO2-Luftqualitaet ohne Sensorik nicht behauptet');
a(str_contains($hs->value('Status'),'Bedarf:'),
  'Status enthaelt Bedarf getrennt von 24h-Wetterchance');
a(count($GLOBALS['calls'])===0,'v0.6 alle historischen Freigaben blockiert');
echo "ALLE SYMCON-SCHUTZTESTS BESTANDEN\n";

// LCN-Statusmeldungen duerfen nie einen Helios-Schaltbefehl ausloesen.
$hs->MessageSink(1,53361,VM_UPDATE,[73.0,false,73.0]);
$audit=json_decode($hs->value('DecisionJSON'),true);
a(($audit['wetroom_event_monitor']['last_changed_at']??0)===0,'Unveraenderte RH-Meldung triggert keinen neuen Duschbeweis');
$hs->MessageSink(2,53361,VM_UPDATE,[73.0,true,68.0]);
$audit=json_decode($hs->value('DecisionJSON'),true);
a(($audit['wetroom_event_monitor']['last_changed_id']??0)===53361,'Aenderung im EG Bad aktualisiert Beobachtungs-JSON');
a(($audit['wetroom_event_monitor']['last_changed_at']??0)>0,'Ereigniszeitpunkt sichtbar');
a(count($GLOBALS['calls'])===0,'VM_UPDATE aus EG Bad sendet keinen HELIOS-Befehl');
$prior=$audit['wetroom_event_monitor']['last_changed_id'];
$hs->MessageSink(3,28802,VM_UPDATE,[79.0,true,73.0]);
$audit2=json_decode($hs->value('DecisionJSON'),true);
a($audit2['wetroom_event_monitor']['last_changed_id']===$prior,'10s Entprellung verhindert Doppelberechnung');
a(count($GLOBALS['calls'])===0,'Ereignis-Sturm bleibt vollstaendig lesend');

// Migration der Abonnements: altes Bad-Event darf nach Konfigurationswechsel nicht mehr wecken.
$oldSub=$hs->getWatches();
$hs->setProperty('Rooms',json_encode([['name'=>'EG Bad','temperature'=>12812,'humidity'=>53361,'wetroom'=>false]]));
$hs->ApplyChanges();
a(array_keys($hs->getWatches())===[28802],'Bei geaenderter Raumkonfiguration EG Bad sauber abgemeldet');
a(($hs->getWatches()[28802]??null)===VM_UPDATE,'OG Bad bleibt korrekt abonniert');
$hs->MessageSink(5,53361,VM_UPDATE,[95.,true,73.]);
$auditAfter=json_decode($hs->value('DecisionJSON'),true);
a($auditAfter['wetroom_event_monitor']['last_changed_id']===53361,'Abgemeldete Quelle erzwingt keine neue Auswertung');
$hs->setProperty('Rooms',json_encode(JVHeliosLCNRoomSources::SOURCES));
$hs->ApplyChanges();
a(array_keys($hs->getWatches())===[28802,53361],'Original-Zuordnung ist nach Rueckstellung wieder vollstaendig');
a(count($GLOBALS['calls'])===0,'Konfigurationsmigration ohne Helios-Schaltbefehle');

// v0.8.0: vollstaendige Symcon-Migration und eingehende LCN-Meldungen.
$trendModule=new HeliosSmart();$trendModule->Create();$trendModule->ApplyChanges();
$trendModule->setProperty('WeatherCenterInstance',42);
$GLOBALS['values'][12812]=23.0;
$GLOBALS['values'][53361]=63.0;
$trendModule->MessageSink(100,53361,VM_UPDATE,[63.0,1,60.0]);
$firstTrend=json_decode($trendModule->value('DecisionJSON'),true);
a(($firstTrend['wetroom_trend']['observed_rises']??[])===[],
    'Erste RH-Meldung ist kein bestaetigter Anstieg im Integrationstest');
$GLOBALS['values'][53361]=68.0;
$trendModule->MessageSink(101,53361,VM_UPDATE,[68.0,1,63.0]);
$secondTrend=json_decode($trendModule->value('DecisionJSON'),true);
a(($secondTrend['wetroom_trend']['observed_rises']??[])===['EG Bad'],
    'Mehrfacher Anstieg aus numerischem HasDiff erreicht reales Modul-JSON');
a(($secondTrend['wetroom_trend']['rooms'][0]['is_shower_proof']??null)===false,
    'Integration deutet RH-Anstieg nicht als sichere Dusch-Erkennung');
a(($secondTrend['ventilation_need']['hardware_authorized']??null)===false,
    'Auch mehrstufiger Bad-Anstieg laesst Helios-Schaltsperre aktiv');
a(count($GLOBALS['calls'])===0,'Kein HELIOS-Aktor nach mehrfacher sensorbasierter Neubewertung');
$trendModule->setProperty('Rooms',json_encode([['name'=>'EG Bad','temperature'=>12812,'humidity'=>53361,'wetroom'=>false]]));
$trendModule->ApplyChanges();
$trendModule->Evaluate();
$afterUnregister=json_decode($trendModule->value('DecisionJSON'),true);
a(($afterUnregister['wetroom_trend']['observed_rises']??[])===[],
    'Beim Umkonfigurieren abgemeldeter Feuchtetrend verschwindet auch aus Diagnose');
a(count($GLOBALS['calls'])===0,'Keine Hardwarebefehle auch nach Konfigurationsmigration');

// v0.9.0: read-only Kochhinweis aus den bestaetigten Original-IDs plus SHADOW Idents.
foreach ([12812,36518,21585,29782,25557,30970,25330] as $roomTempID) $GLOBALS['values'][$roomTempID]=22.3;
$GLOBALS['values'][53361]=47.0; $GLOBALS['values'][28802]=49.0;
$GLOBALS['values'][51009]=true;
$GLOBALS['values'][51040]=true;
$GLOBALS['values'][39155]=false;
$GLOBALS['values'][27700]=2600;
$GLOBALS['values'][60001]='WORK (Firmen-WLAN-Handy)';
$GLOBALS['values'][60002]='ANWESEND (Handyhinweis)';
$GLOBALS['values'][60003]='{}';
$GLOBALS['changedAt'][51040]=time()-60;
$GLOBALS['variableTime']=time()-90;
// Zwei getrennte Leistungsproben statt ein einziges 2-kW-Ereignis.
$hs->WriteAttributeString('CookingAdviceState',json_encode([
  'high_power_since'=>time()-100,'last_high_sample_at'=>time()-90,'high_power_samples'=>1]));
$hs->Evaluate();
$withCook=json_decode($hs->value('DecisionJSON'),true);
a(($withCook['cooking_advice']['classification']??'')==='COOKING_LIKELY',
  'Kuechenlicht, Bewegung und Hausleistung koennen gemeinsames Kochen nahelegen');
a(($withCook['cooking_advice']['suggested_fan_level']??0)===3,
  'Bestehende Stufe 2: nur unverbindlicher Vorschlag Stufe 3');
a(($withCook['occupancy_advice']['home_phone_hints']??[])===['Carina'],
  'Carina aus vorhandener Anwesenheitserkennung per Ident eingebunden');
a(($withCook['occupancy_advice']['work_phone_hints']??[])===['Johannes'],
  'Johannes Firmenhinweis eingebunden, ohne Hausleerstand anzunehmen');
a(($withCook['occupancy_advice']['house_empty_proven']??null)===false,
  'Auch bei gemischtem Bewohnerstatus kein Beweis fuer Hausleerstand');
a(($withCook['mode']??null)==='COOKING_BOOST',
  'Kochbetrieb als separate Beobachtungsstrategie gekennzeichnet');
a(!str_contains($withCook['reason']??'','Kein zusaetzlicher Lueftungsvorteil belegt'),
  'Bei erkanntem Kochen keine widerspruechliche Wochenplanbegruendung');
a(($withCook['cooking_advice']['hardware_authorized']??null)===false,
  'Kochhinweis hat unter keinen Bedingungen eine Hardwarefreigabe');
a(count($GLOBALS['calls'])===0,'Auch mit Carina zuhause + 2600W + Bewegung: keine Helios-Befehle');
$GLOBALS['values'][51009]=false;
$GLOBALS['values'][51040]=false;
$GLOBALS['values'][27700]=1400;
$GLOBALS['changedAt'][51040]=time()-2400;
$hs->WriteAttributeString('CookingAdviceState','{}');
$hs->Evaluate();
$withoutCook=json_decode($hs->value('DecisionJSON'),true);
a(($withoutCook['cooking_advice']['potential_odor_boost']??null)===false,
  'Ausgeschaltetes Licht und keine frische Bewegung / Leistung: kein Boost');
a(count($GLOBALS['calls'])===0,'Bewohnerstatus veraendert niemals den Helios-Wochenplan');

// v0.10: genuine kitchen VM_UPDATE subscriptions; one pulse must not be lost.
$hs->ApplyChanges();
$watch=$hs->getWatches();
foreach ([51009,51040,39155,27700] as $id) {
 a(($watch[$id]??null)===VM_UPDATE,'Kochbeobachter hat originale Variable #'.$id.' abonniert');
}
$GLOBALS['values'][51009]=true;
$GLOBALS['values'][51040]=false;
$GLOBALS['values'][39155]=false;
$GLOBALS['values'][27700]=2750;
$GLOBALS['changedAt'][51040]=time()-40; // FALSE edge is NOT movement.
$hs->WriteAttributeString('CookingAdviceState',json_encode([
 'high_power_since'=>time()-100,'last_high_sample_at'=>time()-85,'high_power_samples'=>1]));
$hs->Evaluate();
$cookFalse=json_decode($hs->value('DecisionJSON'),true);
a(($cookFalse['cooking_advice']['kitchen_motion_recent']??true)===false,
 'LCN-FALSE-Flanke darf nicht als frische Kuechenbewegung gelten');
a(($cookFalse['cooking_advice']['classification']??'')!=='COOKING_LIKELY',
 'Hohe Hausleistung und Licht ohne Kuechenbewegung kein Kochnachweis');
$hs->MessageSink(7,51040,VM_UPDATE,[true,true,false]);
$hs->Evaluate();
$cookPulse=json_decode($hs->value('DecisionJSON'),true);
a(($cookPulse['cooking_advice']['kitchen_motion_recent']??false)===true,
 'Kurzer LCN-TRUE-Puls bleibt trotz inzwischen FALSE bei Auswertung vorhanden');
a(($cookPulse['cooking_advice']['hardware_authorized']??null)===false,
 'Auch Kochnachweis aus Puls hat keine Aktorfreigabe');
a(count($GLOBALS['calls'])===0,'VM_UPDATE Kochen: garantiert keine HELIOS-Geraetebefehle');
$hs->setProperty('KitchenMotionVariable',99999);
$hs->ApplyChanges();
a(!isset($hs->getWatches()[51040]),'Alte Kuechenbewegung bei Sensorumzug abgemeldet');
a(!isset(json_decode($hs->ReadAttributeString('CookingEventState'),true)['motion_positive_at']['Wohnkueche']),
 'Alter Kuechen-Bewegungsimpuls nach Sensorumzug geloescht');
a(count($GLOBALS['calls'])===0,'Koch-Sensorumzug ohne Schaltbefehle');

// v0.10.1: SHADOW-Bewohnerstatus ABWESEND explizit im Produktionsmodul lesen.
$hs->setProperty('KitchenMotionVariable',51040);
$hs->ApplyChanges();
$GLOBALS['values'][60001]='ABWESEND';
$GLOBALS['values'][60002]='ANWESEND (Handyhinweis)';
$GLOBALS['values'][51009]=false;
$GLOBALS['values'][51040]=false;
$GLOBALS['values'][27700]=720;
$GLOBALS['changedAt'][51040]=time()-3600;
$hs->WriteAttributeString('CookingAdviceState','{}');
$hs->Evaluate();
$awayResult=json_decode($hs->value('DecisionJSON'),true);
a(($awayResult['occupancy_advice']['away_status_reports']??[])===['Johannes'],
  'Echte SHADOW-Idents: Johannes abwesend explizit erfasst');
a(($awayResult['occupancy_advice']['home_phone_hints']??[])===['Carina'],
  'Echte SHADOW-Idents: Carina weiterhin anwesend');
a(str_contains($hs->value('Status'),'Bewohner: Johannes abwesend'),
  'ABWESEND erscheint im normalen kompakten Beobachtungsstatus');
a(($awayResult['cooking_advice']['potential_odor_boost']??null)===false,
  'Bei keiner Kuechenaktivitaet keine Kochanhebung');
$GLOBALS['values'][60002]='ABWESEND';
$hs->Evaluate();
$bothAwayResult=json_decode($hs->value('DecisionJSON'),true);
a(($bothAwayResult['occupancy_advice']['both_residents_away_reported']??null)===true,
  'Beide Bewohner abwesend separat diagnostiziert');
a(($bothAwayResult['occupancy_advice']['house_empty_proven']??null)===false,
  'Beide Bewohner abwesend bedeutet keinen gesicherten Hausleerstand');
a(($bothAwayResult['occupancy_advice']['weekly_minimum_may_be_reduced']??null)===false,
  'Auch bei beide abwesend Wochenplan niemals unterbieten');
a(count($GLOBALS['calls'])===0,'Keine Helios-Befehle nach neuer ABWESEND-Integration');

// v0.11: the commissioning architecture must stay hard-locked regardless of
// old checkbox values, real cooking evidence, and a plausible Boost candidate.
$hs->setProperty('EnableControl',true);
$hs->setProperty('MinimumVentilationValidated',true);
$hs->setProperty('CombustionSafetyValidated',true);
$hs->setProperty('DevicePartyReturnValidated',true);
$hs->setProperty('EnableProgramSwitch',true);
$hs->Evaluate();
$pre=json_decode($hs->value('DecisionJSON'),true);
a(($pre['execution_status']??'')==='GATE_BLOCKED',
  'Ohne reale Hersteller-Ruecklesedaten bleibt die Schaltlogik blockiert');
a(($pre['hardware_command_attempts']??-1)===0,
  'Nicht einmal ein Helios-Befehlsversuch');
a(in_array('HELIOS_DEVICE_STATUS_NOT_FRESH',(array)($pre['party_safety']['blocking_reasons']??[]),true),
  'Kein fiktiver Livestatus aus alten Variablen');
a(($pre['party_protocol_draft']['transmitted']??null)===false,
  'Hersteller-Party-Protokoll wird nur simuliert');
a(($pre['party_candidate']['weekly_program_change']??null)===false,
  'Keine Wochenprogramm-Uebernahme in Stufe 1');
a(($pre['device_status_check']['program']??null)===(int)$GLOBALS['values'][12877],
  'Nur bestehendes Helios-Wochenprogramm eingelesen');
a(isset($pre['cooking_signal_health']['power_status']),
  'Diagnose der Leistungsquelle #27700 sichtbar');
a(($pre['party_safety']['command_allowed']??null)===false,
  'Trotz Haken ohne echte Hersteller-Rueckmeldung kein Aktor');
a(count($GLOBALS['calls'])===0,
  'Auch nach gesamten Inbetriebnahme-Gates keinerlei Helios-Schaltaufrufe');
