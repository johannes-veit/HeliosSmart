<?php
/** Regression: 25.10.2026 – 24h drying potential, only two fresh LCN RH sensors. */
require_once __DIR__.'/../libs/HeliosLogic.php';
require_once __DIR__.'/../libs/ForecastAdvisor.php';
require_once __DIR__.'/../libs/DailyStrategy.php';
function bcheck(bool $ok,string $msg):void {if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}echo "PASS $msg\n";}
$now=strtotime('2026-10-25T12:00:00Z');
$wxPair=JVWeatherMath::readPair(13.0,80.0);
$weather=['quality'=>'VERIFIED','current'=>$wxPair+['observed_at'=>$now-90,'source'=>'PWS'],'forecast'=>[]];
$roomNames=['EG Bad','EG Büro','EG Gäste WC','EG Küche','EG Schlafen','EG Wohnen',
'OG Ankleide','OG Bad','OG Flur','OG Schlafen 1','OG Schlafen 2'];
$ages=[900,14,4,114,60,141,416,101,68,164,160];
$rooms=[];
foreach($roomNames as $i=>$name) {
 $age=$ages[$i];
 $rooms[]=['name'=>$name,'temperature'=>22.39,'rh'=>58.0,'wetroom'=>in_array($name,['EG Bad','OG Bad'],true),
 'temperature_observed_at'=>$now-70,'humidity_observed_at'=>$now-$age*60,'observed_at'=>$now-$age*60];
}
$decision=JVHeliosLogic::decide(['now'=>$now,'weather'=>$weather,'rooms'=>$rooms,
 'baseline_level'=>3,'supply_temperature'=>20.5,'supply_fresh'=>true]);
bcheck($decision['mode']==='WEEKLY_BASE','Kein erdachter Dusch-Bedarf bei alten Badwerten');
bcheck($decision['fresh_room_count']===11,'11 aktuelle Temperaturwerte');
bcheck($decision['fresh_humidity_room_count']===2,'Nur 2 aktuelle Feuchtewerte trotz 11 Quellen');
$forecast=[];for($j=1;$j<=24;$j++)$forecast[]=['observed_at'=>$now+$j*3600,'temperature'=>9.,'rh'=>75.,'confidence'=>'OK'];
$f=JVHeliosForecastAdvisor::analyze($forecast,$now,22.39,11.11,'VERIFIED',20.5,15.0,13.0,2,false);
bcheck($f['forecast_hours']===23,'23 Prognosestunden werden gelesen');
bcheck(count($f['drying_windows'])===1&&$f['drying_windows'][0]['hours']===23,'23h Trocknungspotenzial bleibt erkennbar');
bcheck($f['current_humidity_demand']===false,'Ohne frisches Badereignis kein erkannter Feuchtebedarf');
bcheck($f['drying_opportunity_only']===true,'Trocknungspotenzial als potenziell markiert');
bcheck($f['moisture_reference_rooms']===2,'Anzahl frischer Vergleichsraeume angezeigt');
bcheck(str_contains($f['summary'],'keine Zusatzlueftung ableiten'),'24h Trockenheit kein 24h Boost-Befehl');
bcheck(!str_contains($f['summary'],'Moegliches Entfeuchtungsfenster'),'Keine falsche ganztags Entfeuchtungsempfehlung');
$fc=JVHeliosForecastAdvisor::analyze($forecast,$now,22.39,11.11,'VERIFIED',20.5,15.0,13.0,2,true);
bcheck($fc['current_humidity_demand']===true,'Nur bestaetigter aktueller Feuchtebedarf wird gekennzeichnet');
bcheck(str_contains($fc['summary'],'Feuchtebedarf mit frischem Badwert pruefen'),'Selbst bei Feuchtebedarf bleibt manuelle Ist-Pruefung sichtbar');
$sources=[];
foreach($rooms as $i=>$r) $sources[]=['room'=>$r['name'],'available'=>true,'age_minutes'=>$ages[$i]];
$sources[]=['room'=>'EG Flur','available'=>true,'age_minutes'=>106];
$d=JVHeliosDailyStrategy::analyze($decision,$f,15,13,$sources,3);
bcheck($d['humidity_counts']===['current'=>2,'indicative'=>9,'old'=>1,'missing'=>0],'Aktualitaet 2 frisch, 9 indikativ, 1 alt erkannt');
bcheck($d['bath_humidity_quality']['EG Bad']==='STALE','EG Bad 15 h alt: keine Ereignisbehauptung');
bcheck($d['bath_humidity_quality']['OG Bad']==='INDICATIVE','OG Bad 101 min alt: nicht frisch');
bcheck(str_contains($d['summary'],'Badfeuchte nicht aktuell'),'Warnung fuer fehlende aktuelle Badfeuchte');
bcheck($d['airflow_action']==='KEEP_WEEKLY_PLAN'&&$d['safety']==='ADVISORY_ONLY','Kein automatischer Eingriff');
$bad=$sources;foreach($bad as &$x){if($x['room']==='OG Bad')$x['age_minutes']=5;}unset($x);
$d2=JVHeliosDailyStrategy::analyze($decision,$f,15,13,$bad,3);
bcheck(!str_contains($d2['summary'],'Badfeuchte nicht aktuell'),'Aktuelles Bad vermeidet pauschale Badwarnung');
print "ALLE FEUCHTE-INTERPRETATIONS-TESTS BESTANDEN\n";
