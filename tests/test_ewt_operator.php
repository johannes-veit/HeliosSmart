<?php
require_once __DIR__.'/../libs/EWTPumpOperator.php';
function opCheck($ok,$why){if(!$ok){fwrite(STDERR,"FAIL EWT OPERATOR: ".$why."\n");exit(1);}}
$auto=[
    'desired_on'=>false,'frost_priority'=>false,
    'reason'=>'NEUTRAL_NO_BENEFIT'
];
$autoResolved=JVHeliosEWTPumpOperator::apply($auto,false,true);
opCheck($autoResolved['desired_on']===false,'automatic recommendation must win in auto');
opCheck($autoResolved['operator_mode']==='AUTOMATIK','automatic mode is explicit');
$on=JVHeliosEWTPumpOperator::apply($auto,true,true);
opCheck($on['desired_on']===true && $on['reason']==='MANUAL_ON',
    'manual on commands true');
$off=JVHeliosEWTPumpOperator::apply($auto,true,false);
opCheck($off['desired_on']===false && $off['reason']==='MANUAL_OFF',
    'manual off commands false');
$frost=JVHeliosEWTPumpOperator::apply(
    ['desired_on'=>true,'frost_priority'=>true,'reason'=>'FROST_PREHEAT'],true,false);
opCheck($frost['desired_on']===true,'frost priority always overrides manual off');
opCheck($frost['reason']==='MANUAL_OFF_BLOCKED_BY_FROST',
    'manual off visibly blocked by frost');
opCheck(JVHeliosEWTPumpOperator::manualOffPermitted($frost)===false,
    'manual off not permitted in frost');
opCheck(JVHeliosEWTPumpOperator::manualOffPermitted($auto)===true,
    'manual off permitted in reliable warm weather');
echo "EWT operator mode & frost priority pure tests OK\n";
