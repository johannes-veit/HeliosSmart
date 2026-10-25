<?php
require_once __DIR__ . '/../libs/ForecastAdvisor.php';
$checks=0;
function tested(bool $ok, string $description): void {
    global $checks;
    if (!$ok) { fwrite(STDERR,"FAIL ".$description."\n"); exit(1); }
    echo "PASS ".$description."\n";
    $checks++;
}
$now=strtotime('2026-10-25T11:30:00Z'); // DST end Europe/Berlin
$forecast=[];
// 24 UTC hours, with double 02:xx local hour at autumn DST transition.
for ($i=1; $i<=24; ++$i) {
    $t=$now+$i*3600;
    $cold=($i>=2 && $i<=7);
    $forecast[]=['observed_at'=>$t,'temperature'=>$cold?17.0:28.0,
        'rh'=>$cold?60.0:80.0,'confidence'=>'OK','source'=>'ICON'];
}
$r=JVHeliosForecastAdvisor::analyze($forecast,$now,25.,12.,'MODEL_SUPPORTED',21.,20.,29.);
tested($r['status']==='READY','Modellgestuetzte Prognose nur als moegliches Fenster');
tested(count($r['cooling_windows'])===1,'Sechs passende Prognosestunden bilden ein Fenster');
tested($r['cooling_windows'][0]['hours']===6,'Fensterlaenge ohne extrapolierte Stunden');
tested($r['cooling_windows'][0]['start_at']===$forecast[1]['observed_at'],'Richtiger Fensterbeginn');
tested($r['cooling_windows'][0]['end_at']===$forecast[7]['observed_at'],'Ende exklusiv am Folgestundenwechsel');
tested($r['ewt_cooling_now_k']===9.,'EWT-Vorkuehlung nur aus real gemessenen Temperaturen');
tested($r['supply_cooling_now_k']===4.,'Zuluft-Kuehlvorteil gegen Innenraum');
tested($r['hot_hours']===18,'Heisse Stunden gezaehlt, keine fehlenden Stunden aufgefuellt');
tested($r['temperature_min_c']===17. && $r['temperature_max_c']===28.,'Prognose Temperaturbereich korrekt');
tested($r['notice']!=='','Hinweis auf notwendige Ist-Pruefung vorhanden');
$bad=$forecast;
foreach($bad as &$x){$x['rh']=100.;}$x=null;
$r2=JVHeliosForecastAdvisor::analyze($bad,$now,25.,8.,'VERIFIED');
tested($r2['cooling_windows']===[],'Sehr feuchte Aussenluft sperrt potentielles Kuehlfenster');
tested($r2['drying_windows']===[],'Kein Entfeuchtungsfenster wenn Aussenluft nasser');
$r3=JVHeliosForecastAdvisor::analyze($forecast,$now,25.,null,'VERIFIED');
tested($r3['status']==='NO_FRESH_INDOOR_HUMIDITY','Fehlende frische Innenfeuchte getrennt ausgewiesen');
tested($r3['cooling_windows']===[],'Kein scheinbar sicheres Kuehlfenster ohne Innenfeuchte');
$r4=JVHeliosForecastAdvisor::analyze($forecast,$now,25.,12.,'PWS_ONLY');
tested($r4['status']==='WEATHER_UNCERTAIN','Nur eigene Wetterstation nicht als freigegeben behandelt');
tested($r4['cooling_windows']===[],'Keine Fenster bei PWS_ONLY');
$r5=JVHeliosForecastAdvisor::analyze($forecast,$now,25.,12.,'CONFLICT');
tested($r5['status']==='WEATHER_UNCERTAIN','Widersprechende Wetterdaten gesperrt');
$r6=JVHeliosForecastAdvisor::analyze($forecast,$now,null,12.,'VERIFIED');
tested($r6['status']==='NO_INDOOR_TEMPERATURE','Keine Temperatur erfinden');
$r7=JVHeliosForecastAdvisor::analyze(array_slice($forecast,0,7),$now,25.,12.,'VERIFIED');
tested($r7['status']==='INSUFFICIENT','Duenne Prognose ohne Entscheidung');
$low=$forecast;
foreach($low as &$x){$x['confidence']='LOW';}$x=null;
$r8=JVHeliosForecastAdvisor::analyze($low,$now,25.,12.,'VERIFIED');
tested($r8['forecast_hours']===0,'Niedrige Modellguete zaehlt nicht als vertrauenswuerdige Stunde');
$gap=$forecast;
$gap[3]['observed_at']+=7200; // Duplicate row timestamp, leaving hole at index 3
$r9=JVHeliosForecastAdvisor::analyze($gap,$now,25.,12.,'VERIFIED');
tested(count($r9['cooling_windows'])===2,'Fehlende Stunden teilen das Prognosefenster');
tested($r9['cooling_windows'][0]['hours']===2,'Erster Fensterabschnitt korrekt');
tested($r9['cooling_windows'][1]['hours']===3,'Zweiter Fensterabschnitt korrekt');
$dup=$forecast;$dup[]=$forecast[2];
$r10=JVHeliosForecastAdvisor::analyze($dup,$now,25.,12.,'VERIFIED');
tested($r10['forecast_hours']===24,'Doppelte Forecast-Timestamps werden nicht doppelt gezaehlt');
// Actual DST transition: 2026-10-25 02:00 happens twice locally. UTC interval remains 3600 s.
$tDst=strtotime('2026-10-25T00:00:00Z');
$dst=[];
for($i=0;$i<10;$i++){$dst[]=['observed_at'=>$tDst+$i*3600,'temperature'=>16.,'rh'=>60.,'confidence'=>'OK'];}
$dstPlan=JVHeliosForecastAdvisor::analyze($dst,$tDst-60,25.,12.,'VERIFIED');
tested(count($dstPlan['cooling_windows'])===1,'Winterzeitumstellung zerschneidet Stundenfenster nicht');
tested($dstPlan['cooling_windows'][0]['hours']===10,'Zehn echte Zeitstunden trotz lokaler Uhrumstellung');
tested($dstPlan['cooling_windows'][0]['end_at']-$dstPlan['cooling_windows'][0]['start_at']===10*3600,'DST: UTC-Stunden bleiben physikalische Stunden');
$r11=JVHeliosForecastAdvisor::analyze($forecast,$now,25.,12.,'VERIFIED',null,null,null);
tested($r11['ewt_cooling_now_k']===null && $r11['supply_cooling_now_k']===null,'Kein erdachter EWT oder Zuluft-Effekt ohne Messung');
$dry=$forecast;
foreach($dry as &$x){$x['temperature']=15.;$x['rh']=50.;}$x=null;
$r12=JVHeliosForecastAdvisor::analyze($dry,$now,22.,12.,'VERIFIED');
tested($r12['cooling_windows']===[],'Keine vorzeitige Nachtkuehlung bei kuehlen Innenraeumen');
tested(count($r12['drying_windows'])===1,'Entfeuchtungszeitfenster auch ohne Kuehlbedarf');
// No physical heat removal in Watts without actual measured volume flow.
tested(!array_key_exists('cooling_watts',$r),'Keine erfundene Kuehlleistung ohne gemessene m3/h');
echo "ALLE $checks PROGNOSETESTS BESTANDEN\n";
