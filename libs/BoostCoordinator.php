<?php
/**
 * Pure actuator planner. Its output is inert until the independent safety gates pass.
 * Priorities: actual wetroom humidity > plausible cooking > actual supply cooling.
 * A weather forecast / home-away status alone cannot order ventilation.
 */
final class JVHeliosBoostCoordinator
{
    public static function propose(array $decision, array $cooking, int $fan): array
    {
        $fan=max(0,min(4,$fan));
        $choices=[];
        $mode=(string)($decision['mode']??'WEEKLY_BASE');
        $requested=(int)($decision['requested_level']??0);
        $wetrooms=(array)($decision['wetrooms']??[]);
        $bathConfirmed=count(array_filter($wetrooms, static fn($r)=>is_array($r)&&($r['boost_recommended']??false)===true))>0;
        if ($mode==='HUMIDITY_BOOST' && $bathConfirmed && ($decision['weather_usable_for_advice']??false)) {
            $choices[]=['source'=>'BATH_HUMIDITY','rank'=>3,'level'=>max(3,$requested),'minutes'=>15];
        }
        if (($cooking['potential_odor_boost']??false)===true
            && in_array(($cooking['classification']??''),['COOKING_LIKELY','COOKING_RECENTLY_LIKELY'],true)
            && is_int($cooking['suggested_fan_level']??null)) {
            $choices[]=['source'=>'COOKING','rank'=>2,'level'=>(int)$cooking['suggested_fan_level'],'minutes'=>20];
        }
        if ($mode==='PASSIVE_COOLING' && ($decision['weather_usable_for_advice']??false)
            && ($decision['supply_temperature']??null)!==null
            && ($decision['indoor_temperature']??null)!==null
            && (float)$decision['indoor_temperature']-(float)$decision['supply_temperature']>=2) {
            $choices[]=['source'=>'COOLING','rank'=>1,'level'=>max(3,$requested),'minutes'=>20];
        }
        usort($choices,static fn($a,$b)=>($b['rank']<=>$a['rank']));
        $pick=$choices[0]??null;
        $level=$pick===null?null:min(3,max(1,(int)$pick['level']));
        $minutes=$pick===null?0:min(15,max(5,(int)$pick['minutes']));
        $useful=$pick!==null && $level>$fan;
        return [
            'kind'=>$useful?'PARTY_CANDIDATE':'KEEP_WEEKLY',
            'source'=>$useful?$pick['source']:null,
            'suggested_level'=>$useful?$level:null,
            'suggested_minutes'=>$useful?$minutes:0,
            'candidate_sources'=>array_column($choices,'source'),
            'multiple_demands'=>count($choices)>1,
            'fan_actual'=>$fan,'never_reduce_weekly'=>true,
            'weekly_program_change'=>false,
            'hardware_authorized'=>false,
            'reason'=>$pick===null?'Kein ausreichend belegter Zusatzbedarf'
                :($useful?'Zeitbegrenzte Party als Kandidat; separater Sicherheitsentscheid erforderlich'
                    :'Aktuelle Lüfterstufe erreicht oder uebertrifft die Zusatzempfehlung'),
        ];
    }
}
