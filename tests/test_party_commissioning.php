<?php
require_once __DIR__.'/../libs/BoostCoordinator.php';
require_once __DIR__.'/../libs/PartySafety.php';
require_once __DIR__.'/../libs/PartyProtocol.php';
require_once __DIR__.'/../libs/CookingSignalHealth.php';
require_once __DIR__.'/../libs/CookingAdvisor.php';
$checks=0;
function pc(bool $cond,string $why):void {global $checks;$checks++;if(!$cond){fwrite(STDERR,"FAIL $why\n");exit(1);}echo "PASS $why\n";}
function blocked(array $g,string $reason):bool {return in_array($reason,$g['blocking_reasons'],true);}
$now=1792937700;
$weekly=['mode'=>'WEEKLY_BASE','requested_level'=>2,'weather_usable_for_advice'=>true,
    'wetrooms'=>[], 'indoor_temperature'=>22.48,'supply_temperature'=>20.7];
$none=['classification'=>'NO_CONFIRMED_COOKING','potential_odor_boost'=>false];
$q=JVHeliosBoostCoordinator::propose($weekly,$none,2);
pc($q['kind']==='KEEP_WEEKLY','Korrekt: Nutzer-Istzustand belässt Stufe 2');
pc($q['suggested_level']===null,'Wetter alleine keine Party');
pc($q['weekly_program_change']===false,'Nie Wochenplan ersetzen');
pc($q['hardware_authorized']===false,'Vorschlag besitzt keine Hardwarefreigabe');
$cook=['classification'=>'COOKING_LIKELY','potential_odor_boost'=>true,
       'suggested_fan_level'=>3];
$p=JVHeliosBoostCoordinator::propose($weekly,$cook,2);
pc($p['kind']==='PARTY_CANDIDATE','Geruchs-Party als Kandidat');
pc($p['source']==='COOKING','Kochquelle identifiziert');
pc($p['suggested_level']===3,'Stufe 2 auf 3');
pc($p['suggested_minutes']===15,'Erstes Produktivrelease beschraenkt Party auf 15 min');
pc(JVHeliosBoostCoordinator::propose($weekly,$cook,3)['kind']==='KEEP_WEEKLY',
    'Kein zusätzlicher Boost wenn aktuelle Stufe ausreichend');
pc(JVHeliosBoostCoordinator::propose($weekly,$cook,4)['kind']==='KEEP_WEEKLY',
    'Keine Party ueber Stufe 4');
$wet=$weekly;$wet['mode']='HUMIDITY_BOOST';$wet['requested_level']=3;
$wet['wetrooms']=[['boost_recommended'=>true]];
$bw=JVHeliosBoostCoordinator::propose($wet,$cook,2);
pc($bw['source']==='BATH_HUMIDITY','Bad hat Vorrang vor Kochen');
pc($bw['multiple_demands']===true,'Gleichzeitige Bedarfe werden protokolliert');
pc($bw['suggested_minutes']===15,'Keine Summierung von 15 und 20 Minuten');
pc(count($bw['candidate_sources'])===2,'Zwei Bedarfsquellen erhalten');
$wet['wetrooms']=[['boost_recommended'=>false]];
pc(JVHeliosBoostCoordinator::propose($wet,$none,2)['kind']==='KEEP_WEEKLY',
    'Alte Badfeuchte ohne explizite Eignung genügt nicht');
$cool=$weekly;$cool['mode']='PASSIVE_COOLING';$cool['requested_level']=3;
$cool['indoor_temperature']=26;$cool['supply_temperature']=21;
pc(JVHeliosBoostCoordinator::propose($cool,$none,2)['source']==='COOLING','Echte kühlere Zuluft als Kandidat');
// Integrated S4/30 weather flush, enabled only by verified real climate measurements.
$weatherGood=$weekly;
$weatherGood['weather_quality']='VERIFIED';$weatherGood['trusted_weather']=true;
$weatherGood['fresh_room_count']=11;$weatherGood['fresh_humidity_room_count']=11;
$weatherGood['indoor_temperature']=26;$weatherGood['indoor_absolute_humidity']=11;
$weatherGood['outside_absolute_humidity']=8;$weatherGood['outside_dewpoint']=8;
$weatherGood['outside_temperature']=17;$weatherGood['supply_temperature']=21;
$weatherGood['mode']='PASSIVE_COOLING';$weatherGood['requested_level']=3;
$weatherParty=JVHeliosBoostCoordinator::propose($weatherGood,$none,2,true);
pc($weatherParty['source']==='WEATHER_OPTIMAL' && $weatherParty['suggested_level']===4
 && $weatherParty['suggested_minutes']===30,'Combined live weather permits S4 30m');
$weatherFlags=['enable_control'=>true,'minimum_airflow'=>true,'combustion_safety'=>true,
 'party_return'=>true,'estimated_flow_trial'=>false,
 'high_power_opt_in'=>true,'high_power_safety'=>true];
$weatherDevice=['instance_exists'=>true,'module_verified'=>true,'mode'=>0,'fan'=>2,'program'=>3,
 'mode_fresh'=>true,'fan_fresh'=>true,'program_fresh'=>true];
pc(JVHeliosPartySafety::inspect($weatherParty,$weatherDevice,$weatherFlags,
 ['last_command_at'=>0,'expires_at'=>0],$now,true)['command_allowed'],
 'S4 weather requires separate installation approval');
$weatherGood['now']=strtotime('2026-10-26 14:00:00 UTC');
$dayWeather=JVHeliosBoostCoordinator::propose($weatherGood,$none,2,true);
pc($dayWeather['weather_flush']['daypart']==='DAY','Day-time recognized for ventilation optimization');
$nightWeather=$weatherGood;$nightWeather['now']=strtotime('2026-10-26 22:30:00 UTC');
$nightWeather['indoor_temperature']=24.5;$nightWeather['supply_temperature']=21;
pc(JVHeliosBoostCoordinator::propose($nightWeather,$none,2,true)['source']!=='WEATHER_OPTIMAL',
 'Moderate weather cooling is not enough for noisy overnight S4');
$nightStrong=$nightWeather;$nightStrong['indoor_temperature']=27;$nightStrong['supply_temperature']=20;
pc(JVHeliosBoostCoordinator::propose($nightStrong,$none,2,true)['source']==='WEATHER_OPTIMAL',
 'Strong night thermal relief still permits S4');
$weatherDamp=$weatherGood;$weatherDamp['outside_absolute_humidity']=13;
pc(JVHeliosBoostCoordinator::propose($weatherDamp,$none,2,true)['source']!=='WEATHER_OPTIMAL',
 'Damp outside air cannot trigger S4 flush');
$weatherCold=$weatherGood;$weatherCold['supply_temperature']=15;
pc(JVHeliosBoostCoordinator::propose($weatherCold,$none,2,true)['source']!=='WEATHER_OPTIMAL',
 'EWT supply too cold disables S4 flush');
$weatherUnverified=$weatherGood;$weatherUnverified['weather_quality']='MODEL_SUPPORTED';
pc(JVHeliosBoostCoordinator::propose($weatherUnverified,$none,2,true)['source']!=='WEATHER_OPTIMAL',
 'Weather API models alone do not qualify for S4');
$weatherMissing=$weatherGood;unset($weatherMissing['outside_absolute_humidity']);
pc(JVHeliosBoostCoordinator::propose($weatherMissing,$none,2,true)['source']!=='WEATHER_OPTIMAL',
 'Missing outdoor humidity fails closed');
$weatherMoisture=$weatherGood;$weatherMoisture['mode']='WEEKLY_BASE';
$weatherMoisture['indoor_temperature']=22;$weatherMoisture['supply_temperature']=20;
$weatherMoisture['indoor_absolute_humidity']=12;
pc(JVHeliosBoostCoordinator::propose($weatherMoisture,$none,2,true)['source']==='WEATHER_OPTIMAL',
 'Indoor moisture load with dry comfortable supply qualifies for flush');
$weatherNormal=$weatherGood;$weatherNormal['mode']='WEEKLY_BASE';
$weatherNormal['indoor_temperature']=22;$weatherNormal['indoor_absolute_humidity']=9;
pc(JVHeliosBoostCoordinator::propose($weatherNormal,$none,2,true)['kind']==='KEEP_WEEKLY',
 'Dry comfortable home does not over-ventilate merely due to forecast');
$weatherCooldown=JVHeliosPartySafety::inspect($weatherParty,$weatherDevice,$weatherFlags,
 ['last_command_at'=>$now-60*60,'expires_at'=>$now-40*60],$now,true);
pc(!$weatherCooldown['command_allowed'],'Weather-only S4 observes 3h anti-cycling interval');

$cool['supply_temperature']=25;
pc(JVHeliosBoostCoordinator::propose($cool,$none,2)['kind']==='KEEP_WEEKLY',
    'Kleine Temperaturdifferenz erzeugt keine Party');
$cool['supply_temperature']=20;$cool['weather_usable_for_advice']=false;
pc(JVHeliosBoostCoordinator::propose($cool,$none,2)['kind']==='KEEP_WEEKLY',
    'Wetterausfall verhindert Kühl-Party');
$device=['instance_exists'=>true,'module_verified'=>true,'mode'=>0,'fan'=>2,'program'=>3,
    'mode_fresh'=>true,'fan_fresh'=>true,'program_fresh'=>true];
$flags=['enable_control'=>true,'minimum_airflow'=>true,'combustion_safety'=>true,'party_return'=>true,'estimated_flow_trial'=>false];
$history=['last_command_at'=>0,'expires_at'=>0];
$lock=JVHeliosPartySafety::inspect($p,$device,$flags,$history,$now,false);
pc(!$lock['command_allowed'],'Compile-lock trotz aller Freigabe-Haken');
pc(blocked($lock,'OUTPUT_NOT_COMPILED'),'Eindeutiger Compile-Lock-Grund');
pc($lock['no_symcon_timer_as_only_recovery']===true,'Geraeteeigene Laufzeit als Rückfall');
pc($lock['no_automatic_week_program_changes']===true,'Keine automatische Profilsteuerung');
pc($lock['no_party_extension']===true,'Bestehenden Partybetrieb nicht verlängern');
// The following tests prove the decision gate itself works if a future,
// explicitly commissioned release ever enables its output constant.
$future=JVHeliosPartySafety::inspect($p,$device,$flags,$history,$now,true);
pc($future['command_allowed']===true,'Reine Entscheidungslogik bei hypothetischer freigegebener Hardware');
foreach (['enable_control'=>'CONTROL_OPT_IN_MISSING','minimum_airflow'=>'NO_AIRFLOW_BASIS_ACCEPTED',
  'combustion_safety'=>'FIREPLACE_APPROVAL_NOT_DOCUMENTED',
  'party_return'=>'DEVICE_PARTY_RETURN_UNVERIFIED'] as $flag=>$reason) {
    $f=$flags;$f[$flag]=false;
    pc(blocked(JVHeliosPartySafety::inspect($p,$device,$f,$history,$now,true),$reason),
        'Einzelfreigabe '.$flag.' fehlt');
}
$mutations=[
 'HELIOS_INSTANCE_MISSING'=>['instance_exists'=>false],
 'HELIOS_MODULE_ID_NOT_VERIFIED'=>['module_verified'=>false],
 'HELIOS_DEVICE_STATUS_NOT_FRESH'=>['fan_fresh'=>false],
 'MANUAL_OR_SPECIAL_MODE'=>['mode'=>1],
 'PARTY_EXISTING_OR_FOREIGN'=>['mode'=>2],
 'NONCUSTOM_WEEKLY_PROGRAM'=>['program'=>5],
 'NO_SAFE_FAN_INCREASE'=>['fan'=>4],
];
foreach($mutations as $code=>$changes){
 $d=array_replace($device,$changes);
 pc(blocked(JVHeliosPartySafety::inspect($p,$d,$flags,$history,$now,true),$code),
    'Störzustand sperrt: '.$code);
}
foreach ([3,4] as $mode) {
 $d=$device;$d['mode']=$mode;
 pc(blocked(JVHeliosPartySafety::inspect($p,$d,$flags,$history,$now,true),'MANUAL_OR_SPECIAL_MODE'),
    'Sondermodus '.$mode.' hat Vorrang');
}
foreach (['mode_fresh','program_fresh'] as $k) {
 $d=$device;$d[$k]=false;
 pc(blocked(JVHeliosPartySafety::inspect($p,$d,$flags,$history,$now,true),'HELIOS_DEVICE_STATUS_NOT_FRESH'),
    'Veralteter Geraetezustand '.$k.' sperrt');
}
foreach ([1,2,0,-1,31,181] as $minutes) {
 $m=$p;$m['suggested_minutes']=$minutes;
 pc(blocked(JVHeliosPartySafety::inspect($m,$device,$flags,$history,$now,true),'INVALID_PARTY_DURATION'),
    'Unplausible Partyzeit '.$minutes.' gesperrt');
}
$reserved=['last_command_at'=>$now-90,'expires_at'=>$now+500];
$rr=JVHeliosPartySafety::inspect($p,$device,$flags,$reserved,$now,true);
pc(blocked($rr,'PARTY_WINDOW_RESERVED'),'Eigene vergangene Partyfenster nicht übernehmen');
pc(blocked($rr,'COOLDOWN_30_MIN'),'Häufige Parteibefehle durch Sperrzeit blockiert');
$recent=['last_command_at'=>$now-900,'expires_at'=>$now-10];
pc(blocked(JVHeliosPartySafety::inspect($p,$device,$flags,$recent,$now,true),'COOLDOWN_30_MIN'),
 'Erneuter Booster nach 15 Minuten gesperrt');
$old=['last_command_at'=>$now-31*60,'expires_at'=>$now-11*60];
pc(JVHeliosPartySafety::inspect($p,$device,$flags,$old,$now,true)['command_allowed']===true,
 'Hypothetischer neuer Fall erst nach Mindest-Sperrzeit');
$futureOwned=['last_command_at'=>$now+500,'expires_at'=>0];
pc(blocked(JVHeliosPartySafety::inspect($p,$device,$flags,$futureOwned,$now,true),'INVALID_COMMAND_STATE'),
 'Uhrfehler sperrt Party');
$protocol=JVHeliosPartyProtocol::draft($p,$lock,19285);
pc($protocol['method']==='HELIOS_OperatingMode_Party_Set','Exakte Helios-API vorgemerkt');
pc($protocol['transmitted']===false,'Geplanter Befehl nicht übertragen');
pc($protocol['proposed_parameters']['duration_min']===15,'Helios-Geräte-Minuten korrekt');
pc($protocol['device_expected_mode_during_party']===2,'Party-Betriebsart 2');
pc($protocol['device_expected_mode_after_duration']===0,'Erwartete Rueckkehr zu Automatik');
pc($protocol['return_behavior_real_device_verified']===false,'Kein erfundener Geräte-Abnahmenachweis');
pc($protocol['must_not_cancel_foreign_party']===true,'Fremde Party wird nie abgebrochen');
pc($protocol['weekly_program_must_remain_unchanged']===true,'Wochenplan durch Kurzprogramm nicht überschrieben');
pc(JVHeliosPartyProtocol::draft($q,$lock,19285)['method']===null,
 'Ohne Bedarf nicht einmal Befehlsskizze erzeugen');
$healthy=['power'=>['id'=>27700,'exists'=>true,'usable'=>true,'raw_value'=>2300,'age_seconds'=>7,'value_type'=>'int']];
$hx=JVHeliosCookingSignalHealth::analyze($healthy);
pc($hx['power_status']==='FRESH','Aktuelle Hichi-Werte korrekt');
pc($hx['power_raw_value']===2300.,'Rohleistung bleibt sichtbar');
pc(JVHeliosCookingSignalHealth::analyze(['power'=>['id'=>27700,'exists'=>true,'usable'=>false,
    'raw_value'=>2300,'age_seconds'=>385,'value_type'=>'int']])['power_status']==='VALUE_NOT_UPDATED_6_MIN',
    'Gültiger alter Wert ist keine aktuelle Lastprobe');
pc(JVHeliosCookingSignalHealth::analyze(['power'=>['id'=>27700,'exists'=>false]])['power_status']==='SOURCE_NOT_FOUND',
    'Fehlende Quelle eigener Diagnosegrund');
pc(JVHeliosCookingSignalHealth::analyze(['power'=>['id'=>27700,'exists'=>true,'raw_value'=>'error','age_seconds'=>5]])['power_status']==='VALUE_NON_NUMERIC',
    'Defekter Rohwert nicht als Leistung');
pc(JVHeliosCookingSignalHealth::analyze(['power'=>['id'=>27700,'exists'=>true,'raw_value'=>-5,'age_seconds'=>5,'usable'=>false]])['power_status']==='VALUE_OUT_OF_RANGE',
    'Negative Hausleistung im Eingang unbrauchbar');
// End-to-end cooking: two *new* source timestamps, not the same cached value.
$signals=[
 'light'=>['usable'=>true,'on'=>true],
 'motions'=>['Wohnkueche'=>['recent'=>true],'EG Flur'=>['recent'=>true]],
 'power'=>['usable'=>true,'watts'=>2600,'sample_updated_at'=>$now-90],
 'power_threshold_w'=>2000,'current_fan_level'=>2,'residents'=>[
   'Carina'=>['status'=>'ANWESEND','source_recent'=>true],
   'Johannes'=>['status'=>'ABWESEND','source_recent'=>true]],
];
$first=JVHeliosCookingAdvisor::analyze($signals,$now-90);
pc($first['advice']['classification']==='COOKING_POSSIBLE','Erste Hochlastprobe noch kein Kochen');
$one=JVHeliosCookingAdvisor::analyze($signals,$now,$first['state']);
pc($one['advice']['classification']!=='COOKING_LIKELY',
    'Identischer Tasmota-Eingangszeitstempel nicht als zweite Probe');
$signals['power']['sample_updated_at']=$now;
$two=JVHeliosCookingAdvisor::analyze($signals,$now,$one['state']);
pc($two['advice']['classification']==='COOKING_LIKELY',
    'Zweite unabhängige neue Leistungsmeldung plus Küchenaktivität erkannt');
pc(JVHeliosBoostCoordinator::propose($weekly,$two['advice'],2)['source']==='COOKING',
    'Kochdetektor liefert Party-Kandidaten');
pc(!JVHeliosPartySafety::inspect(JVHeliosBoostCoordinator::propose($weekly,$two['advice'],2),
 $device,$flags,$history,$now,false)['command_allowed'], 'Komplette Kette hardwareseitig gesperrt');
echo "ALLE $checks PARTY-INBETRIEBNAHMEPRUEFUNGEN BESTANDEN\n";
