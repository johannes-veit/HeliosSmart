<?php
require_once __DIR__.'/../libs/WetroomEvents.php';
require_once __DIR__.'/../libs/VentilationNeed.php';
$checks=0;
function trendCheck(bool $condition,string $message):void {
    global $checks;
    if (!$condition) {fwrite(STDERR,"FAIL ".$message."\n");exit(1);}
    $checks++;echo "PASS ".$message."\n";
}
$now=1800000000;
$base=['watched'=>[53361,28802]];
$source=static function(int $rh=70,int $age=1,bool $available=true):array {
    return [['room'=>'EG Bad','lcn_humidity_id'=>53361,'available'=>$available,'age_minutes'=>$age,'relative_humidity'=>$rh],
        ['room'=>'OG Bad','lcn_humidity_id'=>28802,'available'=>true,'age_minutes'=>5,'relative_humidity'=>53]];
};
$initial=JVHeliosWetroomEvents::summarize($base,$source(60),$now);
trendCheck(count($initial['rooms'])===2,'Zwei Originalbäder in der neuen Trend-Diagnose');
trendCheck($initial['observed_rises']===[],'Ohne Beobachtung kein Feuchteanstieg behauptet');
trendCheck($initial['not_a_shower_proof']===true,'Trend ist auch ohne Meldung kein Duschbeweis');
trendCheck($initial['hardware_authorized']===false,'Kein Aktor in der Trend-Auswertung');
$x=JVHeliosWetroomEvents::process($base,53361,10603,[63,1,60],$now);
trendCheck($x['changed'] && $x['evaluate'],'Numerisches HasDiff 1 als echte Symcon-Aenderung erkannt');
trendCheck((int)$x['state']['samples'][53361]['rising_steps']===1,'Erster Anstieg bleibt einzelner Hinweis');
trendCheck(JVHeliosWetroomEvents::summarize($x['state'],$source(63),$now)['observed_rises']===[],
    'Nur ein Schritt genügt NICHT fuer Trend');
$y=JVHeliosWetroomEvents::process($x['state'],53361,10603,[68,true,63],$now+3);
trendCheck($y['changed'] && $y['evaluate'],'Wichtiger 2. RH-Anstieg durchbricht Entprellung nur zur Beobachtung');
trendCheck($y['state']['samples'][53361]['rise_pp']===8.0,'RH-Anstieg wird ab tatsaechlichem alten Messwert aggregiert');
$sum=JVHeliosWetroomEvents::summarize($y['state'],$source(68),$now+3);
trendCheck($sum['observed_rises']===['EG Bad'],'Erst zwei passende Anstiege ergeben RH-Trend');
trendCheck($sum['rooms'][0]['rising_steps']===2,'Zwei echte Variablenaenderungen nachgewiesen');
trendCheck($sum['rooms'][0]['is_shower_proof']===false,'Mehrfacher RH-Anstieg ist KEIN beweisbarer Duschvorgang');
trendCheck($sum['rooms'][0]['hardware_authorized']===false,'Ein Trend bewirkt keine Lüfterfreigabe');
trendCheck($sum['rooms'][1]['status']==='NO_RECENT_EVENT','OG Bad bleibt trotz EG-Feuchte unauffaellig');
trendCheck(JVHeliosWetroomEvents::summarize($y['state'],$source(68),$now+20*60+4)['observed_rises']===[],
    'Nach 20 Minuten verliert Trend automatisch Gueltigkeit');
trendCheck(JVHeliosWetroomEvents::summarize($y['state'],$source(68,46),$now+4)['observed_rises']===[],
    'Zu alte Sensoraktualisierung macht RH-Trend ungueltig');
trendCheck(JVHeliosWetroomEvents::summarize($y['state'],$source(68,1,false),$now+4)['observed_rises']===[],
    'Abgemeldeter/fehlender Sensor erzeugt keinen RH-Trend');
trendCheck(JVHeliosWetroomEvents::summarize($y['state'],$source(70),$now+4)['observed_rises']===[],
    'Bei widerspruechlichem Sensor-Istwert wird Eventhistorie nicht geglaubt');
trendCheck(JVHeliosWetroomEvents::summarize($y['state'],$source(68),$now-200)['observed_rises']===[],
    'Nicht plausible zukuenftige Ereignisse nicht als aktuell ausgeben');
$quiet=JVHeliosWetroomEvents::process($x['state'],53361,10603,[64,1,63],$now+1);
trendCheck($quiet['changed'] && !$quiet['evaluate'],'Entprellte RH-Aenderung trotzdem gespeichert');
trendCheck($quiet['state']['samples'][53361]['last_rh']===64.0,'Kein Verlust des geaenderten Wertes');
trendCheck($quiet['state']['samples'][53361]['rising_steps']===2,'Anstiegskette entprellt trotzdem fortgeführt');
$quiet2=JVHeliosWetroomEvents::process($quiet['state'],53361,10603,[68,1,64],$now+2);
trendCheck($quiet2['evaluate'],'Ab Schwelle RH-Trend auch in Entprellzeit sichtbar');
trendCheck(JVHeliosWetroomEvents::summarize($quiet2['state'],$source(68),$now+2)['observed_rises']===['EG Bad'],
    'Keine verlorene Meldung innerhalb von zehn Sekunden');
$fall=JVHeliosWetroomEvents::process($y['state'],53361,10603,[65,true,68],$now+12);
trendCheck(JVHeliosWetroomEvents::summarize($fall['state'],$source(65),$now+12)['observed_rises']===[],
    'Fallende Feuchte beendet aktiven Anstieg sofort');
trendCheck($fall['state']['samples'][53361]['rising_steps']===0,'Nach RH-Rueckgang kein Restzaehler');
$one=JVHeliosWetroomEvents::process($base,53361,10603,[71,1,60],$now);
trendCheck(JVHeliosWetroomEvents::summarize($one['state'],$source(71),$now)['observed_rises']===[],
    'Ein einzelner Sprung 11pp ist kein Trendbeweis');
$wild=JVHeliosWetroomEvents::process($base,53361,10603,[90,true,60],$now);
trendCheck($wild['state']['samples'][53361]['rising_steps']===0,'Ein einzelner grosser 30pp-Sprung wird als unplausibel behandelt');
$wild2=JVHeliosWetroomEvents::process($wild['state'],53361,10603,[94,true,90],$now+60);
trendCheck(JVHeliosWetroomEvents::summarize($wild2['state'],$source(94),$now+60)['observed_rises']===[],
    'Nach Fehlspitze kein gefaelschter mehrstufiger Trend');
$wild3=JVHeliosWetroomEvents::process($wild2['state'],53361,10603,[96,true,94],$now+120);
trendCheck(JVHeliosWetroomEvents::summarize($wild3['state'],$source(96),$now+120)['observed_rises']===[],
    'Auch dritter Schritt nach unplausibler Sprungmeldung baut keinen falschen Anstieg');
trendCheck(JVHeliosWetroomEvents::summarize($y['state'],$source(68),$now+2)['observed_rises']===[],
    'Kuenftiger Ereigniszeitpunkt kann nicht als beobachteter Anstieg gelten');
$gap=JVHeliosWetroomEvents::process($x['state'],53361,10603,[70,true,63],$now+26*60);
trendCheck(JVHeliosWetroomEvents::summarize($gap['state'],$source(70),$now+26*60)['observed_rises']===[],
    'RH-Anstiege ueber mehr als 25 Minuten nicht zusammenspannen');
$badOld=JVHeliosWetroomEvents::process($x['state'],53361,10603,[70,true,60],$now+1);
trendCheck(JVHeliosWetroomEvents::summarize($badOld['state'],$source(70),$now+1)['observed_rises']===[],
    'Nicht passender alter LCN-Wert bricht Ereigniskette');
trendCheck(!JVHeliosWetroomEvents::process($x['state'],53361,10603,[69,1,63],$now-1)['changed'],
    'Rueckdatierte Sendernachricht wird verworfen');
trendCheck(!JVHeliosWetroomEvents::process($base,53361,10603,[100.1,true,63],$now)['changed'],
    'RH-Wert ueber 100% wird verworfen');
trendCheck(!JVHeliosWetroomEvents::process($base,53361,10603,[68,false,63],$now)['changed'],
    'HasDiff false kein Wertwechsel');
trendCheck(!JVHeliosWetroomEvents::process($base,53361,10603,[68,0,63],$now)['changed'],
    'HasDiff 0 kein Wertwechsel');
trendCheck(!JVHeliosWetroomEvents::process($base,53361,10603,[68,'true',63],$now)['changed'],
    'Unbekanntes Protokollformat nicht stillschweigend erlauben');
trendCheck(!JVHeliosWetroomEvents::process($base,53361,10603,[68,1,'abc'],$now)['changed'],
    'Unbrauchbarer Altwert wird abgewiesen');
trendCheck(!JVHeliosWetroomEvents::process($base,53361,10603,[68,1,68],$now)['changed'],
    'Falsches HasDiff true bei gleichem Wert wird ignoriert');
trendCheck(!JVHeliosWetroomEvents::process($base,123,10603,[68,1,63],$now)['changed'],
    'Fremde Quelle nicht abonniert');
$pruned=JVHeliosWetroomEvents::prune($y['state'],[28802]);
trendCheck(array_keys($pruned['samples'])===[],'Abo-Migration loescht alten Feuchtetrend');
trendCheck($pruned['watched']===[28802],'OG Bad allein nach geaenderter Konfiguration');
trendCheck(JVHeliosWetroomEvents::summarize($pruned,$source(68),$now+5)['observed_rises']===[],
    'Nach Sensorentfernung kein alter RH-Trend reaktivierbar');
$decision=['mode'=>'WEEKLY_BASE','weather_usable_for_advice'=>true];
$comfort=['dry_current'=>[],'humid_current'=>[]];
$dryForecast=['drying_windows'=>[['start_at'=>$now]],'cooling_windows'=>[]];
$daily=['bath_humidity_quality'=>['EG Bad'=>'CURRENT','OG Bad'=>'CURRENT']];
$advice=JVHeliosVentilationNeed::analyze($decision,$comfort,$dryForecast,$daily,$sum);
trendCheck($advice['classification']==='WETROOM_RISE_OBSERVED_ONLY',
    'Mehrstufiger Trend ist nur beobachteter Anstieg, kein bestaetigter Lueftungsbedarf');
trendCheck($advice['action']==='KEEP_WEEKLY_PLAN','Trotz guenstigem Trocknungswetter keine Schaltanweisung');
trendCheck($advice['hardware_authorized']===false && !$advice['air_quality_verified'],
    'Weder Luftqualitaet noch Hardwarefreigabe erfunden');
$boost=JVHeliosVentilationNeed::analyze(
    ['mode'=>'HUMIDITY_BOOST','weather_usable_for_advice'=>true],$comfort,$dryForecast,$daily,$sum);
trendCheck($boost['classification']==='CURRENT_BATH_HUMIDITY_DEMAND',
    'Nur separat gepruefter Feuchtegradient kann Bedarfsdiagnose aufwerten');
trendCheck($boost['hardware_authorized']===false,'Auch mit Feuchtegradient nur Beobachtung');
$unavailable=JVHeliosVentilationNeed::analyze(
    ['mode'=>'WEEKLY_BASE','weather_usable_for_advice'=>false],$comfort,$dryForecast,$daily,$sum);
trendCheck($unavailable['classification']==='INSUFFICIENT_WEATHER',
    'Wetterausfall weiterhin wichtiger als bloss beobachteter Feuchteanstieg');
echo "ALLE $checks RH-TREND-REGRESSIONSTESTS BESTANDEN\n";
