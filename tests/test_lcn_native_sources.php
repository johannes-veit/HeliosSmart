<?php
require_once __DIR__.'/../libs/LCNRoomSources.php';
function checked(bool $ok, string $description): void {
    if (!$ok) {fwrite(STDERR,"FAIL $description\n");exit(1);}
    echo "PASS $description\n";
}
$defaults=JVHeliosLCNRoomSources::SOURCES;
checked(count($defaults)===12,'12 LCN-Originalfeuchtequellen dokumentiert');
$raw=[53361,19020,54341,16107,52026,58531,46360,43385,28802,13394,37079,22436];
checked(array_column($defaults,'humidity')===$raw,'Feuchtevariablen entsprechen SymBox-Backup, keine Info-Links');
checked(count(array_unique($raw))===12,'Keine doppelt verwendeten Feuchtequellen');
checked(count(array_filter($defaults,static fn($x)=>$x['temperature']>0))===11,'11 bekannte Temperatur-Feuchte-Paare');
checked(($defaults[2]['name']==='EG Flur' && $defaults[2]['temperature']===0),'EG Flur bleibt bewusst Feuchte-only ohne geratene Temperatur');
checked(count(array_filter($defaults,static fn($x)=>$x['wetroom']))===2,'Nur Duschbaeder als aktive Feuchte-Boostquellen');
foreach ($defaults as $room) checked(JVHeliosLCNRoomSources::isKnownRawHumidity($room['humidity']),'LCN-Originalwert '.$room['name']);
$stored=array_values(array_filter($defaults,static fn($r)=>in_array($r['name'],['EG Bad','OG Bad','EG Küche','EG Wohnen','EG Schlafen','OG Schlafen 1','OG Schlafen 2'],true)));
$merged=JVHeliosLCNRoomSources::merge($stored);
checked(count($merged)===12,'Migration ergaenzt alte Sieben-Raum-Konfiguration automatisch');
checked(array_column($merged,'humidity')===$raw,'Alte Instanz erhaelt alle 12 Original-IDs');
$stored[0]['humidity']=28274;
$repair=JVHeliosLCNRoomSources::merge($stored);
checked($repair[0]['humidity']===53361,'Info-Link wird auf Original-Feuchtevariable umgesetzt');
$stored[0]['temperature']=48888;
$repair=JVHeliosLCNRoomSources::merge($stored);
checked($repair[0]['temperature']===48888,'Individuell konfigurierte Isttemperatur bleibt erhalten');
$stored[]=['name'=>'Zusatzraum','temperature'=>60001,'humidity'=>60002,'wetroom'=>false];
$merged=JVHeliosLCNRoomSources::merge($stored);
checked(count($merged)===13,'Eigene zusaetzliche Raumkonfiguration bleibt erhalten');
checked($merged[12]['name']==='Zusatzraum','Keine fremden Raumdefinitionen überschrieben');
echo "ALLE LCN-QUELLENPRUEFUNGEN BESTANDEN\n";
