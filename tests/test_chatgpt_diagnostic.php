<?php
require_once __DIR__.'/../libs/ChatGPTDiagnostic.php';
function assertDiag(bool $condition,string $message):void{
    if(!$condition){fwrite(STDERR,"FAIL CHATGPT_DIAG: ".$message."\n");exit(1);}
}
$now=1793456000;
$frame=[
    'sampled_at'=>$now-80,
    'instances'=>['HELIOS_SMART'=>52187,'Original_HELIOS'=>19285],
    'flags'=>['EnableSmartFanTakeover'=>true,'MinimumVentilationValidated'=>false],
    'device'=>['ist_luefterstufe'=>1,'ist_betriebsart'=>3,'wochenprogramm'=>3],
    'smart_plan'=>['reason'=>'SHORT_LOW_DEMAND_QUIET','target_stage'=>1,'minutes'=>15],
    'smart_execution'=>['status'=>'SMART_OWNED_ACTIVE_WAIT_RENEWAL',
        'verified'=>true,'attempted'=>false],
    'smart_effective'=>'SMART-STUFE WEITERHIN BESTAETIGT',
    'weather'=>['quality'=>'VERIFIED','messalter_s'=>160,'current'=>['temperature'=>7.0]],
    'ewt_health'=>['online_confirmed'=>true,'reported'=>'ON_REPORTED','watts'=>17],
    'ewt_learning'=>['samples'=>11,'last_result'=>'FAN_LEVEL_NOT_STABLE_10_MIN'],
    'room_summary'=>['temps_frisch'=>11,'rh_frisch'=>11],
    'rooms'=>[
        JVHeliosChatGPTDiagnostic::roomRow([
            'name'=>'EG Bad','temperature'=>22.13,'rh'=>62.2,
            'temperature_observed_at'=>$now-70,'humidity_observed_at'=>$now-320,
            'wetroom'=>true,
        ],$now),
    ],
    'warnings'=>['Modellluftmenge nicht durch Messung nachgewiesen.'],
];
$text=JVHeliosChatGPTDiagnostic::render($frame,$now);
foreach(['Diagnose senden an ChatGPT','SHORT_LOW_DEMAND_QUIET',
    'SMART_OWNED_ACTIVE_WAIT_RENEWAL','VERIFIED','Sole-EWT',
    'Modellluftmenge','EG Bad','62.2','HELIOS SMART','Ende'] as $part){
    assertDiag(stripos($text,$part)!==false,'Report missing '.$part);
}
assertDiag(str_contains($text,'Auswertungsalter: 80 s'),
    'Report must identify evaluation age, never present cached data as freshly measured');
assertDiag(str_contains($text,'Fehlende Sensoren: UNBEKANNT'),
    'Absent optional fields must not be invented');
$old=JVHeliosChatGPTDiagnostic::render($frame,$now+1800);
assertDiag(str_contains($old,'Daten alt/fehlend'),
    'Read-only manual refresh cannot hide stale underlying data');
$row=$frame['rooms'][0];
assertDiag($row['t_alter_min']===2 && $row['rh_alter_min']===6,
    'Temperature and RH ages are distinct and rounded up');
$zero=JVHeliosChatGPTDiagnostic::render([], $now);
assertDiag(str_contains($zero,'NOCH_KEINE_AUSWERTUNG')
    && str_contains($zero,'WARNUNG'),'Uninitialized reports show uncertainty');
$secret='DevicePassword=TOP_SECRET_TOKEN_123';
$frame['native']=['native_instance_status'=>102];
$frame['warnings']=[];
$text=JVHeliosChatGPTDiagnostic::render($frame,$now);
assertDiag(!str_contains($text,$secret),'Unrequested native secret absent from curated report');
$redacted=JVHeliosChatGPTDiagnostic::pick([
  'status_ok'=>true,'devicepassword'=>'VERY_PRIVATE','url'=>'http://private',
],['status_ok','native_instance_status']);
assertDiag(!isset($redacted['devicepassword']) && !isset($redacted['url']),
    'Whitelist blocks accidental app credentials and URLs');
echo "HELIOS SMART single copy/paste ChatGPT diagnosis renderer tests OK\n";
