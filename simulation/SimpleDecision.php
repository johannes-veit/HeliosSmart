<?php
require_once __DIR__.'/../libs/ComfortController.php';
require_once __DIR__.'/../libs/WeatherFlush.php';

/**
 * Small, read-only research summary for the three questions:
 * 1. Is the living-space climate comfortable?
 * 2. Would TRUE Helios supply and independently verified outside air help?
 * 3. Is any extra weather ventilation worth considering?
 *
 * Does NOT calculate an extra synthetic humidity/temperature threshold:
 * it reuses the existing tested comfort and weather gates. No Symcon APIs,
 * actuator permissions, new variables, calendar writes or self-learning.
 * Cooking / bathroom bursts remain solely with the productive controllers.
 */
final class JVHeliosSimpleDecision
{
    public static function advise(array $snapshot,array $rooms,int $now):array
    {
        $out=[
            'status'=>'WOCHENPLAN',
            'behaglichkeit'=>'UNBEKANNT',
            'zuluftwirkung'=>'NICHT_BEWERTBAR',
            'feuchtewirkung'=>'NICHT_BEWERTBAR',
            'entscheidung'=>'WOCHENPLAN_BELASSEN',
            'verbessern'=>'Aktuelle Raum- und Wetterwerte pruefen',
            'grund'=>'DATEN_FEHLEN',
            'vorteil_k'=>null,'zusatzstufe'=>null,'max_dauer_min'=>0,
            'komfortraeume'=>0,
            'weather_only'=>true,'kochen_und_bad_separat'=>true,
            'read_only'=>true,'offline_only'=>true,
            'device_command_allowed'=>false,'reduce_weekly'=>false,
            'bypass_command_allowed'=>false,
            'forecast_or_energy_model_needed'=>false,
            'supply_humidity_measured'=>false,
            'eventual_real_action_requires_existing_party_safety'=>true,
        ];
        if ($now<=0) return $out;
        $d=$snapshot;
        $d['now']=$now;
        $comfort=JVHeliosComfortController::analyze($d,$rooms,$now);
        $out['behaglichkeit']=$comfort['classification'];
        $out['komfortraeume']=$comfort['rooms_usable'];
        $out['verbessern']=$comfort['recommendations'][0]??'Keine weitere Massnahme ableitbar';
        if ($comfort['status']!=='READY') {
            $out['grund']='ZU_WENIGE_FRISCHE_WOHNRAEUME';
            return $out;
        }

        if (($d['season_signal']??'')==='HEATING_SEASON') {
            $out['status']='WINTER';
            $out['grund']='WINTER_WAERMERUECKGEWINNUNG';
            $out['verbessern']='Waermerueckgewinnung und Herstellerwochenplan beibehalten';
            return $out;
        }
        if (($d['season_signal']??'')!=='SUMMER') {
            $out['grund']='SAISON_UNKLAR';return $out;
        }
        $fan=$d['current_fan_level']??null;
        if (!is_int($fan)||$fan<1||$fan>4
            || ($d['helios_automatic_mode']??false)!==true) {
            $out['grund']='HELIOS_BETRIEBSZUSTAND_UNKLAR';
            return $out;
        }
        $originalSupplyTime=$d['supply_observed_at']??null;
        $outsideTime=$d['outside_observed_at']??null;
        if (($d['supply_source']??'')!=='HELIOS_SUPPLY_AFTER_EXCHANGER'
            || !is_int($originalSupplyTime) || !is_int($outsideTime)
            || $originalSupplyTime>$now+120 || $outsideTime>$now+120
            || $originalSupplyTime<=$now-20*60
            || $outsideTime<=$now-30*60
            || ($d['weather_quality']??'')!=='VERIFIED'
            || ($d['trusted_weather']??false)!==true
            || !is_numeric($d['supply_temperature']??null)
            || !is_finite((float)($d['supply_temperature']??0))) {
            $out['grund']='ZULUFT_ODER_WETTER_NICHT_VERIFIZIERT';
            return $out;
        }

        $mean=$comfort['living_average_temperature_c'];
        $supply=(float)$d['supply_temperature'];
        if ($supply< -10||$supply>45) {
            $out['grund']='ZULUFT_UNPLAUSIBEL';return $out;
        }
        $gap=(float)$mean-$supply;
        $out['vorteil_k']=round($gap,2);
        $out['zuluftwirkung']=$gap>=2.2?'KUEHLT_SPÜRBAR':
            ($gap<=-0.5?'WAERMT':'GERINGER_TEMPERATURVORTEIL');
        // Weather decision MUST use the actual mean of occupied rooms,
        // not a global whole-house value distorted by bathroom moisture.
        $d['indoor_temperature']=$mean;
        $d['indoor_absolute_humidity']=$comfort['living_average_absolute_humidity_gm3'];
        $d['comfort_control']=$comfort;
        $flush=JVHeliosWeatherFlush::analyze($d);
        $moisture=$flush['moisture_comfort']??null;
        $out['feuchtewirkung']=!is_array($moisture)||($moisture['status']??'')!=='READY'
            ?'NICHT_BEWERTBAR':
            (($moisture['humidity_import_risk']??false)===true
                ?'FEUCHTEEINTRAG_RISIKO':
                (($moisture['indoor_outdoor_vapor_pressure_gap_hpa']??0)>=1.3
                    ?'AUSSEN_TROCKENER':'ETWA_GLEICHE_FEUCHTE'));
        if (($moisture['status']??'')!=='READY') {
            $out['grund']='FEUCHTE_NICHT_VERIFIZIERT';
            return $out;
        }

        if (($moisture['humidity_import_risk']??false)===true) {
            $out['grund']='AUSSENLUFT_FEUCHTE_UNGUENSTIG';
            $out['verbessern']='Keine zusätzliche Wetterlüftung: Feuchteeintrag verhindern';
            return $out;
        }
        if (($comfort['weather_boost_compatible']??false)!==true) {
            $out['grund']=$comfort['rooms_below_cooling_floor']
                ?'EIN_RAUM_BEREITS_ZU_KUEHL':'KEIN_BELEGTER_KOMFORTGEWINN';
            return $out;
        }
        if (($flush['eligible']??false)!==true) {
            $out['grund']=$flush['reason']??'KEIN_ZUSATZVORTEIL';
            return $out;
        }
        // Even in very humid rooms: a strong temperature increase should
        // not be hidden under the word "drying". The native basic ventilation
        // always continues; this is solely an extra weather-only evaluation.
        if ($gap< -0.5) {
            $out['grund']='ZULUFT_ERWAERMT_DAS_HAUS';
            return $out;
        }
        if ($fan>=4) {
            $out['grund']='HOECHSTE_STUFE_BEREITS_AKTIV';
            return $out;
        }

        $out['status']='ZUSATZNUTZEN_BELEGT_ABER_NUR_THEORETISCH';
        $out['entscheidung']='ZUSATZLUEFTUNG_NUR_NACH_SICHERHEITSPRUEFUNG';
        $out['grund']=$flush['type']??'GEPRUEFTER_ZUSATZNUTZEN';
        $out['zusatzstufe']=4;
        $out['max_dauer_min']=30;
        $out['verbessern']=($flush['type']??'')==='HIGH_INDOOR_HUMIDITY_RELIEF'
            ?'Feuchteabfuhr mit nachweisbar trockenerer Aussenluft pruefen'
            :'Mit nachweislich kuehler und ausreichend trockener Zuluft begrenzt kuehlen';
        return $out;
    }
}
