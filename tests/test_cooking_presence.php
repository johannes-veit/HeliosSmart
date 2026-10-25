<?php
require_once __DIR__.'/../libs/CookingAdvisor.php';
$checks=0;
function ck($ok,$why):void {global $checks; if(!$ok){fwrite(STDERR,"FAIL $why\n");exit(1);} $checks++;echo "PASS $why\n";}
$now=strtotime('2026-10-25T12:00:00Z'); // 13:00 Europe/Berlin
$input=[
 'light'=>['usable'=>true,'on'=>true],
 'motions'=>['Wohnkueche'=>['recent'=>true],'EG Flur'=>['recent'=>false]],
 'power'=>['usable'=>true,'watts'=>2650], 'power_threshold_w'=>2000,
 'current_fan_level'=>2,
 'residents'=>['Johannes'=>['status'=>'WORK (Firmen-WLAN-Handy)','source_recent'=>true],
     'Carina'=>['status'=>'ANWESEND (Handyhinweis)','source_recent'=>true]],
];
$firstSample=JVHeliosCookingAdvisor::analyze($input,$now-90);
ck($firstSample['advice']['classification']==='COOKING_POSSIBLE',
   'Ein einzelner hoher Leistungswert ist trotz Kuechenlicht und Bewegung nicht ausreichend');
ck($firstSample['advice']['power_multiple_samples']===false,'Erste Hochlastmessung nicht als bestaetigt ausgeben');
$seed=$firstSample['state'];
$early=JVHeliosCookingAdvisor::analyze($input,$now-70,$seed);
ck($early['advice']['power_multiple_samples']===false,'Zwischenbewertung nach 20 Sekunden ist keine zweite unabhaengige Leistungsprobe');
ck($early['state']['last_high_sample_at']===$seed['last_high_sample_at'],
   'Zwischenbewertung verschiebt die naechste qualifizierte Leistungsprobe nicht');
$one=JVHeliosCookingAdvisor::analyze($input,$now,$early['state']);
$ad=$one['advice'];
ck($ad['classification']==='COOKING_LIKELY','Kochhinweis aus Licht Bewegung Leistung und Bewohnerkontext');
ck($ad['suggested_fan_level']===3,'Stufe 2 erzeugt unverbindlichen Vorschlag Stufe 3');
ck($ad['suggested_minutes']===20,'Kochvorschlag dauert 20 min');
ck($ad['potential_odor_boost']===true,'Mehrfachindikatoren erlauben nur potenziellen Geruchslueftungsvorschlag');
ck($ad['hardware_authorized']===false && $ad['observation_only']===true,'Nie Aktorfreigabe');
ck($ad['occupancy']['home_phone_hints']===['Carina'],'Carina Anwesenheit nur Handyhinweis');
ck($ad['occupancy']['work_phone_hints']===['Johannes'],'Johannes Firmen-WLAN Kontext');
ck($ad['occupancy']['house_empty_proven']===false,'Keine Behauptung Haus leer');
ck($ad['occupancy']['weekly_minimum_may_be_reduced']===false,'Nie Wochenplan-Mindestwert durch Anwesenheit absenken');
ck($ad['combustion_pressure_safety_not_validated']===true,'Kamin und Unterdruck-Schutz im Ergebnis ausgewiesen');
$solo=$input;$solo['light']['on']=false;$solo['motions']['Wohnkueche']['recent']=false;$solo['residents']['Carina']['status']='Zuhause';
$r=JVHeliosCookingAdvisor::analyze($solo,$now);
ck(!$r['advice']['potential_odor_boost'],'Leistung allein nicht ausreichend');
$only=$input;$only['power']['watts']=850;$only['residents']['Carina']['status']='Unklar';
$r=JVHeliosCookingAdvisor::analyze($only,$now);
ck($r['advice']['classification']==='COOKING_POSSIBLE','Licht und Bewegung koennen ohne Leistung nur moegliches Kochen bedeuten');
ck($r['advice']['suggested_fan_level']===null,'Unbestaetigtes Kochen erzeugt keinen Boost');
$night=strtotime('2026-10-25T23:30:00Z');
$late=JVHeliosCookingAdvisor::analyze($input,$night,['high_power_since'=>$night-90,'last_high_sample_at'=>$night-90,'high_power_samples'=>1]);
ck($late['advice']['classification']==='COOKING_LIKELY','Auch spaetes Kochen wird erkannt');
$noCarina=$input;$noCarina['residents']['Carina']['status']='Nicht bestaetigt';
$noCarina['residents']['Johannes']['status']='Abwesend (vermutet)';
$r=JVHeliosCookingAdvisor::analyze($noCarina,$now,$seed);
ck($r['advice']['classification']==='COOKING_LIKELY','Kochen trotz fehlender persoenlicher Anwesenheitsindizien');
ck($r['advice']['occupancy']['house_empty_proven']===false,'Nicht bestaetigt ist keine verifizierte Abwesenheit');
$bothWork=$input;$bothWork['residents']['Carina']['status']='WORK (Firmen-WLAN-Handy)';
$r=JVHeliosCookingAdvisor::analyze($bothWork,$now,$seed);
ck($r['advice']['occupancy']['known_home_phone_count']===0,'Beide Arbeits-Handys liefern keine Zuhause-Indikation');
ck($r['advice']['potential_odor_boost']===true,'Beide in Firma schliessen dritten Menschen im Haus nicht aus');
ck($r['advice']['occupancy']['house_empty_proven']===false,'Auch zwei Arbeits-Handys sind kein Leerstandbeweis');
$old=$input;$old['residents']['Carina']['source_recent']=false;
$r=JVHeliosCookingAdvisor::analyze($old,$now);
ck($r['advice']['occupancy']['home_phone_hints']===[],'Veralteter Bewohnerstatus wird nicht als aktuell gezaehlt');
$stalePower=$input;$stalePower['power']['usable']=false;
$r=JVHeliosCookingAdvisor::analyze($stalePower,$now);
ck(!$r['advice']['potential_odor_boost'],'Alte Strommessung kein Kochbeweis');
$staleMotion=$input;$staleMotion['motions']['Wohnkueche']['recent']=false;
$staleMotion['residents']['Carina']['status']='NICHT BESTAETIGT';
$r=JVHeliosCookingAdvisor::analyze($staleMotion,$now);
ck(!$r['advice']['potential_odor_boost'],'Kuechenlicht und Leistung allein ohne zeitlichen oder Personenbezug nicht genug');
$r=JVHeliosCookingAdvisor::analyze($input,$now+12*60,$one['state']);
ck($r['advice']['potential_odor_boost'],'Nachlauf erhaelt den Vorschlag auch bei kurz ausgebliebener erneuter Hochlastprobe');
$quiet=$input;$quiet['light']['on']=false;$quiet['power']['watts']=750;
$quiet['motions']['Wohnkueche']['recent']=false;
$r=JVHeliosCookingAdvisor::analyze($quiet,$now+10*60,$one['state']);
ck($r['advice']['classification']==='COOKING_RECENTLY_LIKELY','Kurzer Geruchs-Nachlauf nur Hinweis');
ck($r['advice']['hardware_authorized']===false,'Auch Nachlauf nie automatischer Befehl');
$r=JVHeliosCookingAdvisor::analyze($quiet,$now+16*60,$one['state']);
ck(!$r['advice']['potential_odor_boost'],'Nach 15 Minuten ohne Kochindikatoren erlischt Vorschlag');
$suspicious=['Carina'=>'WIDERSPRUCH (WLAN / Geofence)','Johannes'=>'GEOFENCE ZUHAUSE (WLAN unbestaetigt)'];
ck(JVHeliosCookingAdvisor::normalizeResident($suspicious['Carina'])==='UNKNOWN','Widersprueche nicht als Zuhause interpretieren');
ck(JVHeliosCookingAdvisor::normalizeResident($suspicious['Johannes'])==='UNKNOWN','Unbestaetigte Geofence nur unsicher');
ck(JVHeliosCookingAdvisor::normalizeResident('WLAN ZUHAUSE (Geofence veraltet)')==='HOME_PHONE_HINT','Aktuelles WLAN Handy zuhause als schwacher Kontext');
ck(JVHeliosCookingAdvisor::normalizeResident('WLAN ZUHAUSE (Geofence unbestaetigt)')==='HOME_PHONE_HINT', 'WLAN Zuhause trotz ungeprueften Geofence bleibt reiner Handyhinweis');
$stage=$input;$stage['current_fan_level']=3;
$r=JVHeliosCookingAdvisor::analyze($stage,$now,$seed);
ck($r['advice']['suggested_fan_level']===4,'Stufe 3 ergibt nur Vorschlag Stufe 4');
$stage['current_fan_level']=4;
$r=JVHeliosCookingAdvisor::analyze($stage,$now,$seed);
ck($r['advice']['suggested_fan_level']===null && !$r['advice']['potential_odor_boost'],
    'Bereits Stufe 4 nicht weiter steigern, nur Kochhinweis');
$manual=$input;$manual['helios_automatic_mode']=false;
$r=JVHeliosCookingAdvisor::analyze($manual,$now,$seed);
ck(!$r['advice']['potential_odor_boost'],'Manuelle Helios-Bedienung hat Vorrang vor Kochvorschlag');
$noSignals=$input;$noSignals['light']=['usable'=>false];$noSignals['motions']=[];
$r=JVHeliosCookingAdvisor::analyze($noSignals,$now);
ck(!$r['advice']['potential_odor_boost'],'Keine echten Wohn-/Kuechensignale, keine Kochfolgerung');
$unknown=JVHeliosCookingAdvisor::analyze([], $now);
ck($unknown['advice']['occupancy']['house_empty_proven']===false,'Ausfall aller Quellen meldet kein leeres Haus');
ck($unknown['advice']['suggested_fan_level']===null,'Ausfall aller Quellen: Wochenplan beibehalten');
echo "ALLE $checks KOCH- UND BEWOHNERTESTS BESTANDEN\n";
