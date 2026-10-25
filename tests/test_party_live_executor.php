<?php
require_once __DIR__.'/../libs/PartyExecutor.php';
require_once __DIR__.'/../libs/PartySafety.php';
require_once __DIR__.'/../libs/AirflowModel.php';
$checks=0;
function liveCheck($ok,$text){global $checks;$checks++;if(!$ok){fwrite(STDERR,"FAIL ".$text."\n");exit(1);}echo "PASS $text\n";}
$now=1792938000;
$p=['kind'=>'PARTY_CANDIDATE','source'=>'COOKING','suggested_level'=>3,'suggested_minutes'=>15];
$d=['instance_exists'=>true,'module_verified'=>true,'mode'=>0,'fan'=>2,'program'=>3,
'mode_fresh'=>true,'fan_fresh'=>true,'program_fresh'=>true];
$f=['enable_control'=>true,'minimum_airflow'=>false,'estimated_flow_trial'=>true,
 'combustion_safety'=>true,'party_return'=>true];
$owned=['last_command_at'=>0,'expires_at'=>0];
$g=JVHeliosPartySafety::inspect($p,$d,$f,$owned,$now,true);
liveCheck($g['command_allowed'],'Bewusste beschraenkte Modell-Option kann schalten');
liveCheck($g['airflow_basis']==='ESTIMATED_ONLY_OPERATOR_OPT_IN','Modell niemals als Messung ausgegeben');
liveCheck($g['airflow_estimate_is_measurement']===false,'Falsche Messzertifizierung ausdruecklich verboten');
liveCheck($g['limited_operation']===true,'Modellbetrieb ist eingeschraenkt');
$estimate=JVHeliosAirflowModel::report();
liveCheck($estimate['measured_volumes_m3h']===null,'Kein erfundener Messwert');
liveCheck($estimate['stages'][3]['exhaust_v']===7.1&&$estimate['stages'][3]['supply_v']===7.5,'Nutzer-Spannungen S3 exakt');
foreach(['enable_control','estimated_flow_trial','combustion_safety','party_return'] as $k){
 $changed=$f;$changed[$k]=false;
 liveCheck(!JVHeliosPartySafety::inspect($p,$d,$changed,$owned,$now,true)['command_allowed'],
    'Fehlende Einzelbedingung '.$k.' verhindert Schaltung');
}
foreach([['suggested_level'=>4],['suggested_minutes'=>16],['source'=>'COOLING']] as $change){
 liveCheck(!JVHeliosPartySafety::inspect(array_replace($p,$change),$d,$f,$owned,$now,true)['command_allowed'],
  'Strikte Modellgrenze '.json_encode($change));
}
foreach (['COOLING','FORECAST','PRESENCE',null,''] as $source) {
 $bad=$p;$bad['source']=$source;
 liveCheck(!JVHeliosPartySafety::inspect($bad,$d,$f,$owned,$now,true)['command_allowed'],
  'Nicht autorisierte Quelle blockiert '.json_encode($source));
}
foreach ([1,2,4] as $stage) {
 $bad=$p;$bad['suggested_level']=$stage;
 liveCheck(!JVHeliosPartySafety::inspect($bad,$d,$f,$owned,$now,true)['command_allowed'],
  'Erststufen-Freigabe nur S3, nicht S'.$stage);
}
$bath=$p;$bath['source']='BATH_HUMIDITY';
liveCheck(JVHeliosPartySafety::inspect($bath,$d,$f,$owned,$now,true)['command_allowed'],
 'Bestaetigter Badfeuchte-Bedarf bleibt zulaessig');
foreach([0,1,2,3,4] as $mode){
 $v=$d;$v['mode']=$mode;
 liveCheck(JVHeliosPartySafety::inspect($p,$v,$f,$owned,$now,true)['command_allowed']===($mode===0),
     'Betriebsart '.$mode.' prueft Bedienhoheit');
}
$live=['mode'=>0,'fan'=>2,'program'=>3];
$read=0;$writes=0;
$tx=JVHeliosPartyExecutor::execute($p,$g,$now,static function()use(&$read,$live){$read++;return $live;},
 static function()use(&$writes){$writes++;return true;},static fn()=>[]);
liveCheck($tx['status']==='RESERVED' && $tx['attempted'],'Vollstaendiger Live-Prefight reserviert zuerst');
liveCheck($tx['reserved_until']===$now+15*60+120,'Ausfallreserve beruecksichtigt Geraete-Timer und Toleranz');
liveCheck($writes===0&&$read===1,'Keine Sendung vor persistierter Reservierung');
$r=JVHeliosPartyExecutor::dispatch($tx,3,15,static function($level,$minutes)use(&$writes){$writes++;return true;},
  static fn()=>['mode'=>2,'fan'=>3,'program'=>3,'remaining_minutes'=>14]);
liveCheck($writes===1 && $r['status']==='PARTY_CONFIRMED' && $r['verified'],
    'Hersteller-Geraeteantwort nach S3 Party verifiziert');
liveCheck($r['never_cancels_party']===true && $r['no_retry_after_uncertain_result']===true,
    'Nie fremde Party abbrechen oder unklare Bestaetigung wiederholen');
$gBlocked=$g;$gBlocked['command_allowed']=false;
$before=0;
$refused=JVHeliosPartyExecutor::execute($p,$gBlocked,$now,static function()use(&$before){$before++;return null;},static fn()=>true,static fn()=>[]);
liveCheck($refused['attempted']===false && $before===0,'Gesperrte Auftraege verursachen keine Herstelleraufrufe');
foreach([
 ['mode'=>1,'fan'=>2,'program'=>3],
 ['mode'=>2,'fan'=>2,'program'=>3],
 ['mode'=>0,'fan'=>3,'program'=>3],
 ['mode'=>0,'fan'=>2,'program'=>5],
] as $bad){
 $o=JVHeliosPartyExecutor::execute($p,$g,$now,static fn()=>$bad,static fn()=>true,static fn()=>[]);
 liveCheck($o['status']==='PREFLIGHT_STATE_CHANGED' && !$o['attempted'],
    'Stunden- oder Fremdmoduswechsel blockiert: '.json_encode($bad));
}
$programChanged=$p;$programChanged['expected_weekly_program']=3;
$preflightProgramChanged=JVHeliosPartyExecutor::execute($programChanged,$g,$now,
 static fn()=>['mode'=>0,'fan'=>2,'program'=>4],static fn()=>true,static fn()=>[]);
liveCheck($preflightProgramChanged['status']==='PREFLIGHT_STATE_CHANGED' && !$preflightProgramChanged['attempted'],
 'Wochenprogrammwechsel direkt vor Party darf nicht ueberholt werden');
$missing=JVHeliosPartyExecutor::execute($p,$g,$now,static fn()=>null,static fn()=>true,static fn()=>[]);
liveCheck($missing['status']==='PREFLIGHT_UNAVAILABLE','Fehlende Netzwerkrueckgabe verhindert Befehl');
$exception=JVHeliosPartyExecutor::execute($p,$g,$now,static function(){throw new Exception('LAN offline');},static fn()=>true,static fn()=>[]);
liveCheck($exception['status']==='PREFLIGHT_ERROR','Netzwerkausfall wird abgefangen');
$ambiguous=JVHeliosPartyExecutor::dispatch($tx,3,15,static fn()=>false,static fn()=>[]);
liveCheck($ambiguous['status']==='WRITE_NOT_CONFIRMED'&& !$ambiguous['verified'],
    'Falsche Rueckgabe nach Netzwerkkommando nicht als sichere Wiederholung');
$unknown=JVHeliosPartyExecutor::dispatch($tx,3,15,static function(){throw new RuntimeException('Timeout');},static fn()=>[]);
liveCheck($unknown['status']==='IO_UNCERTAIN'&& !$unknown['verified'],
    'Schreibtimeout erhaelt Sperr-Reserve');
$noRead=JVHeliosPartyExecutor::dispatch($tx,3,15,static fn()=>true,static fn()=>null);
liveCheck($noRead['status']==='READBACK_UNAVAILABLE'&& !$noRead['verified'],
    'Verlorenes Readback ist kein bestaetigter Betrieb');
$foreign=JVHeliosPartyExecutor::dispatch($tx,3,15,static fn()=>true,static fn()=>['mode'=>1,'fan'=>3,'program'=>3]);
liveCheck($foreign['status']==='READBACK_PENDING_OR_MISMATCH','Manueller Override bei Readback nicht ueberschreiben');
$wrongFan=JVHeliosPartyExecutor::dispatch($tx,3,15,static fn()=>true,static fn()=>['mode'=>2,'fan'=>2,'program'=>3,'remaining_minutes'=>10]);
liveCheck($wrongFan['status']==='READBACK_PENDING_OR_MISMATCH','Ruecklese widerspricht Ziel, kein neuer Befehl');
$wrongTime=JVHeliosPartyExecutor::dispatch($tx,3,15,static fn()=>true,static fn()=>['mode'=>2,'fan'=>3,'program'=>3,'remaining_minutes'=>90]);
liveCheck($wrongTime['status']==='READBACK_PENDING_OR_MISMATCH','Lange fremde Party nicht als eigene bestaetigt');
$second=JVHeliosPartySafety::inspect($p,$d,$f,['last_command_at'=>$now,'expires_at'=>$tx['reserved_until']],$now+300,true);
liveCheck(!$second['command_allowed'] && in_array('PARTY_WINDOW_RESERVED',$second['blocking_reasons'],true),
    'Persistierter Party-Zeitraum blockiert weitere Befehle');
$end=JVHeliosPartySafety::inspect($p,$d,$f,['last_command_at'=>$now,'expires_at'=>$tx['reserved_until']],$now+16*60,true);
liveCheck(!$end['command_allowed'] && in_array('COOLDOWN_30_MIN',$end['blocking_reasons'],true),
    'Ablauf begruendet keinen sofortigen Neustart');
liveCheck(JVHeliosPartySafety::inspect($p,$d,$f,['last_command_at'=>$now,'expires_at'=>$tx['reserved_until']],$now+31*60,true)['command_allowed'],
    'Erst nach Cooldown erneuter begruendeter Partyauftrag erlaubt');
echo "ALLE $checks LIVE-PARTY-PRUEFUNGEN BESTANDEN\n";
