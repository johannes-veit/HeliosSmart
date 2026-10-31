<?php
/**
 * A SINGLE copyable, read-only operator report for HELIOS SMART.
 * Inputs MUST be explicit, whitelisted diagnostics, not whole IPS settings.
 * Never include native configuration, URLs, network credentials or tokens.
 */
final class JVHeliosChatGPTDiagnostic
{
    public static function render(array $f, int $copiedAt):string
    {
        $at=(int)($f['sampled_at']??0);
        $age=$at>0&&$at<=$copiedAt?($copiedAt-$at):null;
        $fmt=static function($value):string {
            if($value===null)return 'UNBEKANNT';
            if(is_bool($value))return $value?'JA':'NEIN';
            if(is_array($value)){
                $json=json_encode($value,JSON_UNESCAPED_UNICODE
                    |JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
                return is_string($json)?$json:'DATEN_NICHT_KODIERBAR';
            }
            return (string)$value;
        };
        $add=static function(array &$lines,string $name,$value)use($fmt):void{
            $lines[]=$name.': '.$fmt($value);
        };
        $lines=[
            '=== HELIOS SMART – DIAGNOSE SENDEN AN CHATGPT ===',
            'Schema: JVHS_DIAG_1 | Automatisch alle 5 min; manueller Refresh nur LESEN.',
            'WICHTIG: Diagnosekopie ist KEINE neue Geraeteabfrage/Schaltfreigabe.',
            'Quelle: '.(string)($f['source_mode']??'NICHT_DEKLARIERT')
                .' | Bericht erstellt: '
                .gmdate('Y-m-d\\TH:i:s\\Z',(int)($f['report_created_at']??$copiedAt)),
            'Zeit Bezug: '.($at>0?gmdate('Y-m-d\TH:i:s\Z',$at):'NOCH_KEINE_AUSWERTUNG')
              .' UTC | jetzt: '.gmdate('Y-m-d\TH:i:s\Z',$copiedAt)
              .' | Auswertungsalter: '.($age===null?'UNBEKANNT':$age.' s'),
        ];
        if($age===null||$age>900)
            $lines[]='WARNUNG: Daten alt/fehlend; keine Aussage ueber den aktuellen Geraetezustand!';
        foreach((array)($f['warnings']??[]) as $warning)
            $lines[]='HINWEIS: '.$fmt($warning);
        $sections=[
            'STEUERUNG / HERSTELLER'=>[
                'Instanzen'=>$f['instances']??null,
                'Freigaben (keine Nachweise fingiert)'=>$f['flags']??null,
                'Aktueller Helios-Iststatus'=>$f['device']??null,
                'Original-Helios Integrationsstatus'=>$f['native']??null,
                'Stufen-Ursache'=>$f['attribution']??null,
                'Original-Helios CO2/VOC/Feuchte/Enteisung'=>$f['native_air_sensors']??null,
            ],
            'SMART-ENTSCHEIDUNG / SCHALTUNGEN'=>[
                'Plan und Begruendung'=>$f['smart_plan']??null,
                'Schaltbestaetigung / Fallback'=>$f['smart_execution']??null,
                'Effektiver Zustand'=>$f['smart_effective']??null,
                'Besitz-/Zeitfenster'=>$f['smart_ownership']??null,
                'Rueckfall-/Saisonprogramm'=>$f['season']??null,
                'Legacy Party/Ruhe'=>$f['legacy']??null,
            ],
            'WETTER / ENERGIE / EWT-LUFT'=>[
                'Wetterdaten inkl. Messalter'=>$f['weather']??null,
                'Aussenluft VOR EWT gegen Helios NACH EWT'=>$f['air_ewt']??null,
                'Bodenmodell/Referenz'=>$f['soil']??null,
                'Kuehlung und Winterenergie'=>$f['energy']??null,
            ],
            'RAUMKLIMA / BEDARF / ANWESENHEIT'=>[
                'Raumdaten Guete'=>$f['room_summary']??null,
                'Raumdetails T/RH und Alter'=>$f['rooms']??null,
                'Fehlende Sensoren'=>$f['missing_rooms']??null,
                'Zusatz-RH ohne Temperatur'=>$f['humidity_only']??null,
                'Behaglichkeit und Lueftungsbedarf'=>$f['ventilation']??null,
                'Bad / Kochen / Anwesenheit (ohne Personendaten)'=>$f['events']??null,
            ],
            'SOLE-EWT PUMPE / SICHERHEIT'=>[
                'Schaltplan / Frostschutz'=>$f['ewt_plan']??null,
                'Schalt- und Erreichbarkeitsnachweis'=>$f['ewt_health']??null,
                'Steuerzustand'=>$f['ewt_state']??null,
            ],
            'LERNEN / PROGNOSEN'=>[
                'EWT Lernstand'=>$f['ewt_learning']??null,
                'HELIOS Lernfortschritt'=>$f['learning']??null,
                'Prognose/Nachtmuster'=>$f['prediction']??null,
            ],
        ];
        foreach($sections as $header=>$rows){
            $lines[]='';
            $lines[]='--- '.$header.' ---';
            foreach($rows as $label=>$value)$add($lines,$label,$value);
        }
        $lines[]='';
        $lines[]='=== ENDE – VOLLSTAENDIGEN TEXT AN CHATGPT SENDEN ===';
        return implode("\n",$lines);
    }

    /** Use only whitelisted report keys. No arbitrary native/IPS settings. */
    public static function pick(array $source,array $keys):array
    {
        $result=[];
        foreach($keys as $key){
            if(array_key_exists($key,$source))$result[$key]=$source[$key];
        }
        return $result;
    }

    /**
     * Count actual bounded learning observations, never outer JSON keys.
     * These are hints only: phone observations do not prove occupancy.
     */
    public static function learningMetrics(array $occupancy,array $calendar):array
    {
        $samples=array_values(array_filter((array)($occupancy['p']??[]),
            'is_array'));
        $bins=array_values(array_filter((array)($calendar['bins']??[]),
            'is_array'));
        $counts=[];
        foreach($samples as $sample){
            $status=(string)($sample['o']??'MIXED_OR_UNKNOWN');
            $counts[$status]=($counts[$status]??0)+1;
        }
        return [
            'occupancy_samples'=>count($samples),
            'occupancy_status_counts'=>$counts,
            'calendar_patterns'=>count($bins),
            'calendar_patterns_with_fresh_status'=>count(array_filter(
                $bins,static fn(array $b):bool=>(int)($b['valid']??0)>0)),
            'no_presence_proof'=>true,
        ];
    }

    public static function roomRow(array $r,int $now):array
    {
        $tAt=(int)($r['temperature_observed_at']??0);
        $rhAt=(int)($r['humidity_observed_at']??0);
        $age=static fn(int $when)=>$when>0&&$when<=$now+120
            ?max(0,(int)ceil(($now-$when)/60)):null;
        $t=$r['temperature']??null;$rh=$r['rh']??null;
        return [
            'raum'=>substr((string)($r['name']??'UNBEKANNT'),0,80),
            'c'=>is_numeric($t)?round((float)$t,2):null,
            'rh_pct'=>is_numeric($rh)?round((float)$rh,1):null,
            't_alter_min'=>$age($tAt),
            'rh_alter_min'=>$age($rhAt),
            'bad'=>(bool)($r['wetroom']??false),
        ];
    }
}
