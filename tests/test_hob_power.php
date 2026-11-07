<?php
require_once __DIR__.'/../libs/HobPowerDemand.php';
function hobAssert(bool $v,string $why):void {
    if(!$v){fwrite(STDERR,"FAIL HOB POWER: ".$why."\n");exit(1);}
}
$at=1794070000;
$sample=static fn($watts,$fresh=true,$online=true):array=>[
    'id'=>47825,'watts'=>$watts,'configured'=>true,
    'online'=>$online,'age_seconds'=>$fresh?3:500,
];
$s=JVHeliosHobPowerDemand::assess([],$sample(0),$at);
hobAssert(!$s['advice']['boost_requested'],'0 watt standby never starts cooktop boost');
$s=JVHeliosHobPowerDemand::assess($s['state'],$sample(5),$at+10);
hobAssert(!$s['advice']['boost_requested'],'exactly 5W is OFF, only >5W is active');
$on=JVHeliosHobPowerDemand::assess($s['state'],$sample(5.1),$at+20);
hobAssert($on['advice']['electrically_active']&&$on['advice']['boost_requested']
    &&$on['advice']['phase']==='ON','single fresh >5W direct three-phase power qualifies');
$stable=JVHeliosHobPowerDemand::assess($on['state'],$sample(45),$at+80);
hobAssert($stable['advice']['electrically_active']
    &&$stable['state']['started_at']===$at+20,
    'ongoing cooktop power retains initial start');
$off=JVHeliosHobPowerDemand::assess($stable['state'],$sample(0),$at+100);
hobAssert($off['advice']['phase']==='AFTER_RUN'
    &&$off['advice']['afterrun_remaining_seconds']===300,
    'first observed OFF starts exactly 300 seconds afterrun');
$held=JVHeliosHobPowerDemand::assess($off['state'],$sample(0),$at+399);
hobAssert($held['advice']['boost_requested']&&$held['advice']['afterrun_remaining_seconds']===1,
    'stay active through last second of five minute afterrun');
$expired=JVHeliosHobPowerDemand::assess($held['state'],$sample(0),$at+400);
hobAssert(!$expired['advice']['boost_requested']
    &&$expired['advice']['phase']==='OFF',
    'after 300 seconds power-off, S4 demand is gone');
$restart=JVHeliosHobPowerDemand::assess($off['state'],$sample(180),$at+150);
hobAssert($restart['advice']['phase']==='ON'
    &&$restart['advice']['boost_requested'],
    'induction power returning during afterrun resets OFF timer');
$newOff=JVHeliosHobPowerDemand::assess($restart['state'],$sample(0),$at+190);
hobAssert($newOff['state']['off_at']===$at+190,
    'next OFF starts a new five minute afterrun');
$stale=JVHeliosHobPowerDemand::assess($on['state'],$sample(400,false),$at+350);
hobAssert(!$stale['advice']['boost_requested']
    &&!$stale['advice']['source_fresh'],
    'stale positive watts cannot run S4 indefinitely');
$offline=JVHeliosHobPowerDemand::assess($on['state'],$sample(400,true,false),$at+200);
hobAssert(!$offline['advice']['boost_requested'],
    'Shelly offline always withdraws cooktop authority');
$phantom=JVHeliosHobPowerDemand::assess($stale['state'],$sample(0),$at+360);
hobAssert(!$phantom['advice']['boost_requested'],
    'sensor recovery with 0 watt never invents a phantom afterrun');
$missing=JVHeliosHobPowerDemand::assess($on['state'],[
    'id'=>47825,'configured'=>false,'online'=>false,'watts'=>null,
    'age_seconds'=>null],$at+360);
hobAssert(!$missing['advice']['boost_requested'],
    'missing real source never borrows household power readings');
echo "HELIOS direct Shelly cooktop >5W / 5-minute OFF afterrun / offline tests OK\n";
