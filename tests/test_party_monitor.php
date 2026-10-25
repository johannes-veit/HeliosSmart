<?php
require_once __DIR__.'/../libs/PartyExecutor.php';
require_once __DIR__.'/../libs/PartyMonitor.php';
$checks=0;
function mon($v,$text){global $checks;$checks++;if(!$v){fwrite(STDERR,"FAIL $text\n");exit(1);}echo "PASS $text\n";}
$now=1792940000;
$owned=['last_command_at'=>$now-300,'expires_at'=>$now+720,'level'=>3,'original_program'=>3];
$auto=['mode'=>0,'fan'=>2,'program'=>3];
$active=['mode'=>2,'fan'=>3,'program'=>3];
mon(JVHeliosPartyMonitor::inspect([],null,$now)['status']==='NO_OWNED_PARTY','Kein Auftrag: keine Eigentumsbehauptung');
mon(JVHeliosPartyMonitor::inspect($owned,$active,$now)['status']==='PARTY_ACTIVE_CONFIRMED','Laufende eigene Party korrekt lesend erkannt');
mon(JVHeliosPartyMonitor::inspect($owned,$auto,$now)['status']==='AUTOMATIC_OR_RETURNED_EARLY','Vorzeitige Automatik wird nicht ueberschrieben');
mon(JVHeliosPartyMonitor::inspect($owned,$auto,$now+900)['status']==='WEEK_PROGRAM_RESUMED','Herstellerwochenschema nach Ablauf erkannt');
mon(JVHeliosPartyMonitor::inspect($owned,['mode'=>1,'fan'=>3,'program'=>3],$now)['status']==='MANUAL_OR_SPECIAL_OVERRIDE','Manuelle Bedienung nicht ausgleichen');
mon(JVHeliosPartyMonitor::inspect($owned,['mode'=>2,'fan'=>4,'program'=>3],$now)['status']==='DIFFERENT_PARTY_ACTIVE','Fremdparty mit anderer Stufe nicht als eigene beanspruchen');
mon(JVHeliosPartyMonitor::inspect($owned,['mode'=>2,'fan'=>3,'program'=>3],$now+900)['status']==='PARTY_AFTER_EXPECTED_EXPIRY','Fremde weiterlaufende Party nicht abbrechen');
mon(JVHeliosPartyMonitor::inspect($owned,['mode'=>0,'fan'=>2,'program'=>4],$now)['status']==='WEEK_PROGRAM_CHANGED_EXTERNALLY','Ungeplanter Wechsel des Wochenprogramms sichtbar');
mon(JVHeliosPartyMonitor::inspect($owned,null,$now)['status']==='DEVICE_READBACK_UNAVAILABLE','Geraeteoffline explizit sichtbar');
mon(JVHeliosPartyMonitor::inspect($owned,['mode'=>8,'fan'=>6,'program'=>3],$now)['status']==='DEVICE_READBACK_UNAVAILABLE','Unplausible Geraeteantwort nicht uebernommen');
$corrupt=$owned;$corrupt['expires_at']=$now+3600;
mon(JVHeliosPartyMonitor::inspect($corrupt,$auto,$now)['status']==='CORRUPTED_OWNERSHIP','Fehlerhafte Zeitreserve erkannt');
mon(JVHeliosPartyMonitor::inspect($owned,$active,$now)['does_not_cancel_or_reset_device']===true,'Nie selbsttaetiger Abbruch oder Wochenprogrammwechsel');
echo "ALLE $checks PARTY-MONITORPRUEFUNGEN BESTANDEN\n";
