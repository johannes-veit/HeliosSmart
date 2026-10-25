<?php
require_once __DIR__.'/../libs/WetroomEvents.php';
require_once __DIR__.'/../libs/LCNRoomSources.php';
$checks=0;
function we(bool $ok,string $reason):void {global $checks; if(!$ok){fwrite(STDERR,"FAIL $reason\n");exit(1);} $checks++;echo "PASS $reason\n";}
$defs=JVHeliosLCNRoomSources::SOURCES;
$ids=JVHeliosWetroomEvents::ids($defs,static fn($id)=>true,static fn($id)=>false);
we($ids===[53361,28802],'Nur die zwei Bad-Originalfeuchtequellen abonniert');
$missing=JVHeliosWetroomEvents::ids($defs,static fn($id)=>$id!==53361,static fn($id)=>false);
we($missing===[28802],'Nicht existierender Sensor wird nicht abonniert');
$link=JVHeliosWetroomEvents::ids($defs,static fn($id)=>true,static fn($id)=>$id===28802);
we($link===[53361],'Linkobjekt nicht abonniert');
$modified=$defs;$modified[0]['humidity']=12345;$modified[8]['wetroom']=false;
we(JVHeliosWetroomEvents::ids($modified,static fn($id)=>true,static fn($id)=>false)===[12345],
    'Umkonfiguration aktualisiert Abo-Liste');
$changed=$defs;$changed[0]['humidity']=0;
we(JVHeliosWetroomEvents::ids($changed,static fn($id)=>true,static fn($id)=>false)===[28802],
    'Fehlende ID wird nicht abonniert');
$state=['watched'=>[53361,28802]];$now=1800000000;
we(!JVHeliosWetroomEvents::process($state,53361,10603,[65.,false,65.],$now)['evaluate'],
    'Wert unveraendert: kein Sofortlauf');
we(!JVHeliosWetroomEvents::process($state,52026,10603,[66.,true,55.],$now)['evaluate'],
    'Kuechenfeuchte loest keinen Sofortlauf aus');
we(!JVHeliosWetroomEvents::process($state,53361,10604,[66.,true,55.],$now)['evaluate'],
    'Fremde Nachricht nicht bearbeiten');
we(!JVHeliosWetroomEvents::process($state,53361,10603,[], $now)['evaluate'],
    'Fehlendes Aenderungskennzeichen nicht vertrauen');
$go=JVHeliosWetroomEvents::process($state,53361,10603,[68.,true,64.],$now);
we($go['evaluate'],'EG Bad: echte Aenderung loest sofortige Bewertung aus');
we($go['state']['last_sender']===53361 && $go['state']['last_event_at']===$now,
    'Originalvariable und Ereigniszeit sind gespeichert');
$short=JVHeliosWetroomEvents::process($go['state'],28802,10603,[69.,true,65.],$now+4);
we(!$short['evaluate'],'Sekunden-Sturm unter 10s wird entprellt');
$next=JVHeliosWetroomEvents::process($go['state'],28802,10603,[69.,true,65.],$now+11);
we($next['evaluate'] && $next['state']['last_sender']===28802,'Nach zehn Sekunden erneut ansprechbar');
$old=JVHeliosWetroomEvents::process($next['state'],53361,10603,[69.,true,65.],$now+1);
we(!$old['evaluate'],'Zurueckdatierte Ereignisse duerfen nicht entprellt werden');
we(!isset($next['state']['hardware_authorized']),'Ereignislogik vergibt keine Hardwarefreigaben');
echo "ALLE $checks WETROOM-EVENT-TESTS BESTANDEN\n";
