<?php
require_once __DIR__.'/../libs/CookingAdvisor.php';
$checks=0;
function ok(bool $yes,string $message):void { global $checks; if(!$yes){fwrite(STDERR,"FAIL $message\n");exit(1);} echo "PASS $message\n";$checks++; }
$cases=[
 'ABWESEND'=>'AWAY_REPORTED',
 'Johannes abwesend'=>'AWAY_REPORTED',
 'ABWESENDE Person'=>'AWAY_REPORTED',
 'Abwesenheit erkannt'=>'AWAY_REPORTED',
 'NICHT ANWESEND'=>'AWAY_REPORTED',
 'NICHT ZUHAUSE'=>'AWAY_REPORTED',
 'AUSSER HAUS'=>'AWAY_REPORTED',
 'AWAY'=>'AWAY_REPORTED',
 'ANWESEND (Handyhinweis)'=>'HOME_PHONE_HINT',
 'WLAN ZUHAUSE (Geofence veraltet)'=>'HOME_PHONE_HINT',
 'WORK (Firmen-WLAN-Handy)'=>'WORK_PHONE_HINT',
 'NICHT BESTÄTIGT'=>'UNKNOWN',
 'Unklar'=>'UNKNOWN',
 'WIDERSPRUCH (WLAN / Geofence)'=>'UNKNOWN',
 ''=>'UNKNOWN',
];
foreach($cases as $raw=>$expect) ok(JVHeliosCookingAdvisor::normalizeResident($raw)===$expect,'Klassifizierung '.($raw?:'[leer]'));
$now=strtotime('2026-10-25T12:00:00Z');
$base=[
 'light'=>['usable'=>true,'on'=>false],
 'motions'=>['Wohnkueche'=>['recent'=>false],'EG Flur'=>['recent'=>false]],
 'power'=>['usable'=>true,'watts'=>700],
 'current_fan_level'=>2,'helios_automatic_mode'=>true,
 'residents'=>[
  'Johannes'=>['status'=>'ABWESEND','source_recent'=>true],
  'Carina'=>['status'=>'ANWESEND (Handyhinweis)','source_recent'=>true],
 ],
];
$r=JVHeliosCookingAdvisor::analyze($base,$now)['advice'];
$a=$r['occupancy'];
ok($a['away_status_reports']===['Johannes'],'Abwesender Johannes explizit erfasst');
ok($a['home_phone_hints']===['Carina'],'Anwesende Carina separat erfasst');
ok($a['residents']['Johannes']['state']==='AWAY_REPORTED','Johannes wird nicht UNKNOWN');
ok($a['status']==='ONE_HOME_HINT','Ein Bewohner zuhause, ein anderer abwesend');
ok(str_contains($a['description'],'abwesend'),'Lesbare Bewohneranzeige mit abwesend');
ok($r['potential_odor_boost']===false,'Nur Bewohnerstatus ohne Kochindizien kein Boost');
$both=$base;$both['residents']['Carina']['status']='Abwesend';
$b=JVHeliosCookingAdvisor::analyze($both,$now)['advice'];
ok($b['occupancy']['both_residents_away_reported']===true,'Beide abwesend gesondert erfasst');
ok($b['occupancy']['known_away_status_count']===2,'Zwei Abwesenheitsmeldungen, nicht null');
ok($b['occupancy']['status']==='BOTH_AWAY_REPORTED','Kombination beide abwesend');
ok($b['occupancy']['house_empty_proven']===false,'Abwesenheit beider Bewohner ist kein Leerstandbeweis');
ok($b['occupancy']['away_person_certified']===false,'Personenabwesenheit nicht physisch zertifiziert');
ok($b['occupancy']['weekly_minimum_may_be_reduced']===false,'Kein Wochenplan-Absenken bei Abwesenheit');
ok($b['potential_odor_boost']===false,'Kein Geruchsboost bei ruhigem Haus und beiden abwesend');
$work=$both;$work['residents']['Johannes']['status']='WORK (Firmen-WLAN-Handy)';
$w=JVHeliosCookingAdvisor::analyze($work,$now)['advice']['occupancy'];
ok($w['known_away_status_count']===1,'Arbeitsort unterscheidet sich von Abwesenheitsstatus');
ok($w['both_residents_nonhome_reported']===true,'Abwesend und Firma zusammen erkennbar');
ok($w['both_residents_away_reported']===false,'Abwesend plus Firma nicht als beide abwesend labeln');
$unc=$both;$unc['residents']['Carina']['status']='NICHT BESTAETIGT';
$u=JVHeliosCookingAdvisor::analyze($unc,$now)['advice']['occupancy'];
ok($u['known_away_status_count']===1 && $u['unknown_residents']===['Carina'],'Unklar bleibt unklar');
$old=$both;$old['residents']['Johannes']['source_recent']=false;
$o=JVHeliosCookingAdvisor::analyze($old,$now)['advice']['occupancy'];
ok($o['residents']['Johannes']['state']==='UNKNOWN','Stale Abwesenheit wird nicht als aktuell gezählt');
ok($o['known_away_status_count']===1,'Veraltete Abwesenheit nicht doppelt gezählt');
$cooking=$both;
$cooking['light']['on']=true;
$cooking['motions']['Wohnkueche']['recent']=true;
$cooking['power']['watts']=2750;
$previous=['high_power_since'=>$now-100,'last_high_sample_at'=>$now-90,'high_power_samples'=>1];
$c=JVHeliosCookingAdvisor::analyze($cooking,$now,$previous)['advice'];
ok($c['classification']==='COOKING_LIKELY','Kochindizien werden nicht ignoriert trotz beide abwesend');
ok($c['occupancy']['kitchen_activity_conflicts_with_nonhome_reports']===true,'Abwesenheit gegen Küchenaktivität als Widerspruch');
ok(str_contains($c['summary'],'Bewohner abwesend'),'Widerspruch sichtbar');
ok($c['hardware_authorized']===false && $c['observation_only']===true,'Auch widersprechende Quellen erlauben keine Steuerung');
$zero=$both;$zero['power']['watts']=2750;
$z=JVHeliosCookingAdvisor::analyze($zero,$now,$previous)['advice'];
ok($z['classification']!=='COOKING_LIKELY','Hochlast ohne Küchenbewegung kein Kochnachweis');
ok($z['potential_odor_boost']===false,'Abwesenheitskontext und einzelne Leistung ohne Boost');
echo "ALLE $checks ABWESEND-STATUS-TESTS BESTANDEN\n";
