<?php
require_once __DIR__.'/../libs/ComfortAdvisor.php';
require_once __DIR__.'/../libs/SeasonalAdvisor.php';
$checks=0;
function cs(bool $value,string $description):void{
 global $checks;
 if(!$value){fwrite(STDERR,"FAIL $description\n");exit(1);}
 $checks++;echo "PASS $description\n";
}
$src=[
 ['room'=>'EG Bad','available'=>true,'age_minutes'=>900,'relative_humidity'=>75],
 ['room'=>'OG Bad','available'=>true,'age_minutes'=>101,'relative_humidity'=>70],
 ['room'=>'EG Büro','available'=>true,'age_minutes'=>14,'relative_humidity'=>32],
 ['room'=>'EG Flur','available'=>true,'age_minutes'=>106,'relative_humidity'=>66,'humidity_only'=>true],
 ['room'=>'EG Gäste WC','available'=>true,'age_minutes'=>4,'relative_humidity'=>52],
 ['room'=>'EG Küche','available'=>true,'age_minutes'=>114,'relative_humidity'=>56],
 ['room'=>'EG Schlafen','available'=>true,'age_minutes'=>37,'relative_humidity'=>42],
 ['room'=>'EG Wohnen','available'=>true,'age_minutes'=>141,'relative_humidity'=>50],
 ['room'=>'OG Ankleide','available'=>true,'age_minutes'=>416,'relative_humidity'=>47],
 ['room'=>'OG Flur','available'=>true,'age_minutes'=>68,'relative_humidity'=>48],
 ['room'=>'OG Schlafen 1','available'=>true,'age_minutes'=>164,'relative_humidity'=>53],
 ['room'=>'OG Schlafen 2','available'=>true,'age_minutes'=>160,'relative_humidity'=>55],
];
$x=JVHeliosComfortAdvisor::analyze($src,10);
cs($x['current_rooms']===3,'Drei frische RH-Werte im Beispielsatz');
cs($x['indicative_rooms']===8,'Acht aeltere aber brauchbare RH-Werte');
cs($x['stale_rooms']===1,'Ein RH-Wert ueber zwoelf Stunden ausgeschlossen');
cs($x['bath_signal_quality']['EG Bad']==='STALE','15 Stunden alter Badwert ist nicht aktuell');
cs($x['bath_signal_quality']['OG Bad']==='INDICATIVE','OG Bad RH 101 Minuten alt ist nur Hintergrund');
cs($x['dry_current']===['EG Büro'],'Nur frisches trockenes Buero als Hinweis');
cs(in_array('OG Bad',$x['humid_indicative'],true),'Aelterer nasser Badwert ist blosser Kontext');
cs(in_array('EG Flur',$x['humid_indicative'],true),'RH-only-Flur darf ohne Temperatur fuer relative Feuchte ausgewertet werden');
cs(!in_array('EG Bad',$x['humid_indicative'],true),'Uralt-Badwert nicht in indikative Risikoklasse');
cs($x['active_boost_authorized']===false,'Weder hoher noch trockener RH-Wert schaltet Boost frei');
cs(str_contains($x['summary'],'unnötigen Zusatzluftwechsel vermeiden'),'Winterwarnung bei frischer trockener Luft');
$bad=$src;$bad[2]['relative_humidity']=105;
$xBad=JVHeliosComfortAdvisor::analyze($bad,10);
cs(!in_array('EG Büro',$xBad['dry_current'],true),'Unmoegliche 105 Prozent RH aussortiert');
$missing=$src;$missing[1]['available']=false;
$xM=JVHeliosComfortAdvisor::analyze($missing,10);
cs(!isset($xM['bath_signal_quality']['OG Bad']),'Fehlende Badmessung nicht als frisch deklariert');
cs(JVHeliosComfortAdvisor::analyze([],null)['current_rooms']===0,'Keine erfundenen Raumwerte');
$hot=['status'=>'READY','forecast_hours'=>24,'hot_hours'=>8,'temperature_max_c'=>32.0];
$cold=['status'=>'READY','forecast_hours'=>24,'hot_hours'=>0,'temperature_max_c'=>14.0];
$warmInside=['weather_usable_for_advice'=>true,'indoor_temperature'=>25.5];
$coolInside=['weather_usable_for_advice'=>true,'indoor_temperature'=>22.5];
$now=1_800_000_000;
$first=JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,3,$now,[]);
cs($first['advice']['status']==='PENDING_STABILITY','Sommerprofil nicht schon beim ersten Durchlauf empfehlen');
cs($first['advice']['proposed_program']===4,'Heisse Prognose erzeugt nur Kandidat Profil 4');
cs($first['advice']['recommended_program']===null,'Noch keine umschaltreife Empfehlung');
cs($first['advice']['program_changed']===false,'Saisonalgorithmus hat keinen Aktor');
$inter=JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,3,$now+3*3600,$first['state']);
cs($inter['advice']['status']==='PENDING_STABILITY','Erster dreistuendiger Nachweis reicht nicht');
$steady=JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,3,$now+4*3600,$first['state']);
cs($steady['advice']['status']==='INSUFFICIENT' || $steady['advice']['status']==='PENDING_STABILITY',
 'Vier Stunden alte letzte Auswertung nicht ohne laufende Zwischensignale uebernehmen');
$previous=$first['state'];
for($h=1;$h<=4;$h++){$last=JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,3,$now+$h*3600,$previous);$previous=$last['state'];}
cs($last['advice']['status']==='ADVISORY_READY','Vier Stunden kontinuierliche Prognosestabilitaet belegt');
cs($last['advice']['recommended_program']===4,'Sommerprofil nur als Empfehlung ausgewiesen');
cs($last['advice']['program_changed']===false,'Sogar nach stabiler Prognose kein Schaltbefehl');
$match=JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,4,$now,[]);
cs($match['advice']['status']==='ALREADY_MATCHES','Vorhandenes Sommerprofil wird belassen');
$coldFirst=JVHeliosSeasonalAdvisor::evaluate($coolInside,$cold,4,$now,[]);
cs($coldFirst['advice']['proposed_program']===3,'Kuehler Tagesverlauf spricht fuer Normalprofil');
$state=$coldFirst['state'];
for($h=1;$h<=4;$h++){$r=JVHeliosSeasonalAdvisor::evaluate($coolInside,$cold,4,$now+$h*3600,$state);$state=$r['state'];}
cs($r['advice']['recommended_program']===3,'Normalprofil erst nach Stabilitaet vorschlagen');
$uncertain=JVHeliosSeasonalAdvisor::evaluate(['weather_usable_for_advice'=>false,'indoor_temperature'=>25],$hot,3,$now,[]);
cs($uncertain['advice']['recommended_program']===null,'Wetterausfall verhindert Saisonempfehlung');
cs($uncertain['state']['candidate']===null,'Wetterausfall setzt Kandidaten zurueck');
$thin=$hot;$thin['forecast_hours']=7;
cs(JVHeliosSeasonalAdvisor::evaluate($warmInside,$thin,3,$now,[])['advice']['status']==='INSUFFICIENT','Zu wenig Prognosestunden');
cs(JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,2,$now,[])['advice']['status']==='NONCUSTOM','Fremder Helios-Wochenplan unberuehrt');
$transition=$hot;$transition['hot_hours']=2;$transition['temperature_max_c']=26;
cs(JVHeliosSeasonalAdvisor::evaluate($warmInside,$transition,3,$now,$last['state'])['advice']['status']==='TRANSITION','Wechselwetter loescht Kandidat');
$gap=JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,3,$now+12*3600,$last['state']);
cs($gap['advice']['status']==='PENDING_STABILITY','Langer Auswertungsabbruch fordert neue Stabilitaetsdauer');
$future=['candidate'=>4,'first_at'=>$now+7000,'last_at'=>$now+9000];
cs(JVHeliosSeasonalAdvisor::evaluate($warmInside,$hot,3,$now,$future)['advice']['confirmed_hours']===0.,'Zeitstempel aus Zukunft nicht akzeptieren');
$brief=['status'=>'READY','forecast_hours'=>24,'hot_hours'=>4,'temperature_max_c'=>29];
cs(JVHeliosSeasonalAdvisor::evaluate(['weather_usable_for_advice'=>true,'indoor_temperature'=>22],$brief,3,$now,[])['advice']['status']==='TRANSITION',
 'Nur warme Aussenprognose ohne sommerlich warme Innenraeume bewirkt keinen Umschaltvorschlag');
echo "ALLE $checks KOMFORT- UND PROFILTESTS BESTANDEN\n";
