<?php
require_once __DIR__.'/../libs/HeliosLogic.php';
require_once __DIR__.'/../libs/DailyStrategy.php';
require_once __DIR__.'/../libs/ForecastAdvisor.php';
function tcheck($good,$text){if(!$good){fwrite(STDERR,"FAIL $text\n");exit(1);}echo "PASS $text\n";}
$now=time();
$current=JVWeatherMath::readPair(13,80)+['observed_at'=>$now-120,'source'=>'PWS'];
$weather=['quality'=>'VERIFIED','current'=>$current,'forecast'=>[]];
$temps=[22.1,21.8,22.4,23.3,22.4,22.8,22.2,22.5,22.5,22.6,21.7];
$rooms=[];
foreach($temps as $i=>$temp){$age=[900,14,4,114,37,141,416,101,68,164,160][$i];$rooms[]=[
 'name'=>'Raum '.$i,'temperature'=>$temp,'rh'=>[57,56,65,53,51,50,52,54,53,53,56][$i],
 'temperature_observed_at'=>$now-80,'humidity_observed_at'=>$now-$age*60,
 'observed_at'=>$now-$age*60,'wetroom'=>($i===0||$i===7),
];}
$decision=JVHeliosLogic::decide(['now'=>$now,'weather'=>$weather,'rooms'=>$rooms,
 'baseline_level'=>3,'supply_temperature'=>20.5,'supply_fresh'=>true]);
tcheck($decision['fresh_room_count']===11,'11 Temperatursensoren auswertbar');
tcheck($decision['fresh_humidity_room_count']===3,'Nur 3 aktuelle Feuchtewerte in 11 Sensorpaaren');
tcheck($decision['mode']==='WEEKLY_BASE','Kein falscher Feuchte-Boost aus alten Werten');
$forecast=[];
for($i=1;$i<=24;$i++){$forecast[]=['observed_at'=>$now+$i*3600,'temperature'=>9.5,'rh'=>85.,'confidence'=>'OK'];}
$plan=JVHeliosForecastAdvisor::analyze($forecast,$now,$decision['indoor_temperature'],
 $decision['indoor_absolute_humidity'],'VERIFIED',20.5,15,13);
$sources=[];foreach([900,14,4,114,37,141,416,101,68,164,160,106] as $age){$sources[]=['available'=>true,'age_minutes'=>$age];}
$s=JVHeliosDailyStrategy::analyze($decision,$plan,15.0,13.0,$sources,3);
tcheck($s['season_signal']==='HEATING_SEASON','24-h Kaltprognose als Heizsaison erkannt');
tcheck($s['season_signal_basis']==='24H_FORECAST','Prognosebasiert, nicht nur aktuell');
tcheck($s['airflow_action']==='KEEP_WEEKLY_PLAN','Wochenplan behalten statt Strom und Heizwaerme fuer unnoetigen Boost');
tcheck($s['program_actual']===3,'Helios Profil 3 vermerkt');
tcheck($s['humidity_counts']===['current'=>3,'indicative'=>8,'old'=>1,'missing'=>0],'3/8/1 Alterstufen der 12 LCN Feuchtewerte');
tcheck($s['ewt_preconditioning_k']===2.,'2 K EWT Vorwaermung ohne erfundene zweite Aussentemperatur');
tcheck($s['sensible_heat_w_per_100m3h']<0,'Zuluft kuehler als Raum: kein positiver Waermeeintrag');
tcheck(abs($s['sensible_heat_w_per_100m3h'] - 34*(20.5-$decision['indoor_temperature']))<0.16,'Raumluftwirkung korrekt je 100 m3/h');
tcheck($s['program_change_executed']===false && $s['safety']==='ADVISORY_ONLY','Profil niemals geschaltet');
tcheck(str_contains($s['normalized_heat_note'],'100 m3/h'),'Energiewert explizit normiert');
tcheck(!isset($s['airflow_m3h'])&&!isset($s['heat_watts']),'Keine erfundene reale Luftmenge oder Watt-Leistung');
$bad=JVHeliosDailyStrategy::analyze($decision,$plan,null,13,$sources,3);
tcheck($bad['ewt_preconditioning_k']===null,'EWT-Effekt bei fehlendem Fuehler unbestimmt');
$bad=JVHeliosDailyStrategy::analyze($decision,$plan,15,null,$sources,3);
tcheck($bad['ewt_preconditioning_k']===null,'EWT-Effekt ohne freien Aussentemperaturvergleich unbestimmt');
$unverified=$decision;$unverified['weather_usable_for_advice']=false;
$bad=JVHeliosDailyStrategy::analyze($unverified,$plan,15,13,$sources,3);
tcheck($bad['ewt_preconditioning_k']===null,'Ungesicherte freie Aussentemperatur verbietet EWT-Leistungskennwert');
$empty=JVHeliosDailyStrategy::analyze($decision,[],null,null,[],null);
tcheck($empty['season_signal']!=='HEATING_SEASON','Ohne Prognose kein sicherer Heizsaison-Nachweis');
$hot=$plan;$hot['status']='READY';$hot['forecast_hours']=24;$hot['hot_hours']=7;
$hot['temperature_min_c']=21;$hot['temperature_max_c']=32;
$summer=$decision;$summer['indoor_temperature']=25.5;
$hotResult=JVHeliosDailyStrategy::analyze($summer,$hot,22,30,$sources,4);
tcheck($hotResult['season_signal']==='SUMMER_HEAT','Hitzewelle und warme Zimmer als Sommersignal');
tcheck($hotResult['ewt_preconditioning_k']===-8.,'Sommer Sole-EWT Abkuehlung negativ');
tcheck($hotResult['airflow_action']==='KEEP_WEEKLY_PLAN','Saisonhinweis allein erzwingt keinen Luftwechsel');
$cooling=$summer;$cooling['mode']='PASSIVE_COOLING';
$coolResult=JVHeliosDailyStrategy::analyze($cooling,$hot,22,30,$sources,4);
tcheck($coolResult['airflow_action']==='CONSIDER_COOLING_BOOST','Nur nach gesondertem Live-Bedarf Kuehl-Boost empfehlen');
$humid=$summer;$humid['mode']='HUMIDITY_BOOST';
$humidResult=JVHeliosDailyStrategy::analyze($humid,$hot,22,30,$sources,4);
tcheck($humidResult['airflow_action']==='CONSIDER_HUMIDITY_BOOST','Separater Feuchtebedarf bleibt priorisiert');
$humidProtection=$summer;$humidProtection['mode']='SUMMER_HUMIDITY_PROTECTION';
tcheck(JVHeliosDailyStrategy::analyze($humidProtection,$hot,22,30,$sources,4)['airflow_action']==='AVOID_ADDITIONAL_VENTILATION','Hohe Aussenfeuchte sperrt Extralueftung');
$indoorBad=$decision;$indoorBad['indoor_temperature']=null;$indoorBad['supply_temperature']=20;
tcheck(JVHeliosDailyStrategy::analyze($indoorBad,$hot,22,30,$sources)['sensible_heat_w_per_100m3h']===null,'Kein Heizwaermeindikator ohne Raummitteltemperatur');
echo "ALLE TAGESSTRATEGIE-TESTS BESTANDEN\n";
