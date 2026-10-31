<?php
require_once __DIR__ . '/WeatherMath.php';
/** Kein Abstimmen per Mehrheitsentscheid: PWS/DWD sind Messungen, Modelle sind Prognosen. */
final class JVWeatherLogic
{
    public static function chooseObservation(?array $pws, ?array $dwd, int $now, int $fresh = 2700): array
    {
        $p = self::isFresh($pws, $now, $fresh) ? $pws : null;
        $d = self::isFresh($dwd, $now, $fresh) ? $dwd : null;
        if (!$p && !$d) {
            return ['quality' => 'UNAVAILABLE', 'current' => null, 'reason' => 'Keine frischen unabhaengigen Messdaten'];
        }
        if (!$p) {
            return ['quality' => 'DWD_ONLY', 'current' => $d, 'reason' => 'Nur DWD-Messstation plausibel'];
        }
        if (!$d) {
            return ['quality' => 'PWS_ONLY', 'current' => $p, 'reason' => 'Eigene Station ohne DWD-Kontrolle'];
        }
        $difference = abs($p['temperature'] - $d['temperature']);
        $dewDifference = abs($p['dewpoint'] - $d['dewpoint']);
        if ($difference > 4.0 || $dewDifference > 4.0) {
            return ['quality' => 'CONFLICT', 'current' => null, 'reason' => 'PWS/DWD widersprechen sich; keine Wetterfreigabe'];
        }
        // Gemessene lokale Bedingungen als lokale Referenz, DWD dient als unabhängige Kontrolle.
        return ['quality' => 'VERIFIED', 'current' => $p, 'reason' => 'Lokale Messung stimmt mit DWD ueberein'];
    }

    /**
     * A distant/higher SYNOP station is not the local climate in a valley.
     * Forecasts can corroborate a local observation, NEVER impersonate one.
     * The public quality flag deliberately distinguishes measurement from model support.
     */
    public static function assessSources(?array $pws, ?array $dwd, array $forecasts,
        int $now, float $siteElevation = 0.0): array
    {
        $p = self::isFresh($pws, $now, 2700) ? $pws : null;
        $d = self::isFresh($dwd, $now, 2700) ? $dwd : null;
        $ref = self::modelReference($forecasts, $now);
        $supportsPws = $p !== null && $ref !== null
            && abs($p['temperature'] - $ref['temperature']) <= 3.5
            && abs($p['dewpoint'] - $ref['dewpoint']) <= 3.5;
        $near = $d !== null && isset($d['distance_m']) && is_numeric($d['distance_m'])
            && (float)$d['distance_m'] <= 12000;
        $heightOK = $d !== null && ($siteElevation <= 0.0
            || (!empty($d['station_height_m'])
                && abs((float)$d['station_height_m'] - $siteElevation) <= 120));
        if ($p === null && $d === null) return ['quality'=>'UNAVAILABLE', 'current'=>null,
            'reason'=>'Keine frische lokale oder DWD-Messung'];
        if ($p === null) return ['quality'=>($near && $heightOK ? 'DWD_ONLY' : 'DWD_REMOTE'), 'current'=>$d,
            'reason'=>($near && $heightOK ? 'Nur nahe DWD-Messstation, keine lokale Messung' : 'Nur entfernte DWD-Messstation, keine lokale Messung')];
        if ($d === null) {
            if ($supportsPws) return ['quality'=>'MODEL_SUPPORTED', 'current'=>$p,
                'reason'=>'Lokale Messung durch zwei API-Betreiber/Modelle gestuetzt; keine unabhängige Ortsmessung'];
            return ['quality'=>'PWS_ONLY', 'current'=>$p, 'reason'=>'Lokale Station ohne belastbare Kontrolle'];
        }
        $difference = abs($p['temperature'] - $d['temperature']);
        $dewDifference = abs($p['dewpoint'] - $d['dewpoint']);
        if ($difference <= 4.0 && $dewDifference <= 4.0 && $near && $heightOK)
            return ['quality'=>'VERIFIED', 'current'=>$p,
                'reason'=>'Lokale Station und nahe DWD-Messung stimmen ueberein'];
        if ($supportsPws) return ['quality'=>'MODEL_SUPPORTED', 'current'=>$p,
            'reason'=>sprintf('PWS gegen DWD abweichend (T %.1f K/Tau %.1f K); zwei getrennte Wetter-APIs stuetzen lokale Messung', $difference, $dewDifference)];
        if (!$near || !$heightOK)
            return ['quality'=>'PWS_ONLY', 'current'=>$p,
                'reason'=>'DWD-Station geografisch/hoehenmaessig nicht ausreichend vergleichbar; keine Modellbestaetigung'];
        return ['quality'=>'CONFLICT', 'current'=>null,
            'reason'=>sprintf('PWS und nahe DWD-Station widersprechen sich (T %.1f K/Tau %.1f K)', $difference, $dewDifference)];
    }

    /** Two service operators: Open-Meteo (ICON/ECMWF/best-match) and MET Norway.
     * This is model corroboration, NOT two independently observed ground temperatures.
     */
    public static function modelReference(array $bySource, int $now): ?array
    {
        // The paired forecasts must refer to the SAME hour, not merely to "sometime today".
        $pairs=[];
        foreach (['ICON','BEST_MATCH','ECMWF'] as $key) {
            foreach (($bySource[$key] ?? []) as $om) {
                if (abs((int)($om['observed_at']??0)-$now)>75*60) continue;
                foreach (($bySource['MET_NO'] ?? []) as $met) {
                    if (abs((int)($om['observed_at']??0)-(int)($met['observed_at']??0))>30*60) continue;
                    if (abs($om['temperature']-$met['temperature'])>3.0
                        || abs($om['dewpoint']-$met['dewpoint'])>3.5) continue;
                    $pairs[]=['age'=>abs($now-(int)$om['observed_at']),
                        'temperature'=>($om['temperature']+$met['temperature'])/2,
                        'dewpoint'=>($om['dewpoint']+$met['dewpoint'])/2];
                }
            }
            if ($pairs) break;
        }
        if (!$pairs) return null;
        usort($pairs,static fn($a,$b)=>$a['age']<=>$b['age']);
        return ['temperature'=>$pairs[0]['temperature'],'dewpoint'=>$pairs[0]['dewpoint']];
    }

    /** Public, versioned MET Norway Locationforecast 2.0 JSON. */
    public static function parseMetNo(?array $payload, int $now): array
    {
        $steps = $payload['properties']['timeseries'] ?? null;
        if (!is_array($steps)) return [];
        $out=[];
        foreach ($steps as $step) {
            $ts = strtotime((string)($step['time'] ?? ''));
            if (!$ts || $ts<$now-3600 || $ts>$now+50*3600) continue;
            $details = $step['data']['instant']['details'] ?? [];
            $pair=JVWeatherMath::readPair($details['air_temperature']??null,$details['relative_humidity']??null);
            if ($pair === null) continue;
            $out[]=$pair+['observed_at'=>$ts,'source'=>'MET_NO'];
        }
        return $out;
    }

    /**
     * A configuration/module reload must never destroy an otherwise fresh
     * weather quality. Conversely, a cached VERIFIED flag is no license
     * to reuse a stale observation: validate both the snapshot and the
     * underlying measurement age before returning it to consumers.
     * This is read-only: it never executes a new provider query.
     */
    public static function validatedSnapshot(?array $snapshot,int $now): array
    {
        $fallback=[
            'issued_at'=>$now,
            'quality'=>'UNAVAILABLE',
            'current'=>null,
            'forecast'=>[],
            'reason'=>'Kein aktueller verifizierter Wetter-Snapshot',
        ];
        if(!is_array($snapshot))return $fallback;
        $issued=(int)($snapshot['issued_at']??0);
        if($issued<=0 || $issued>$now+120 || $now-$issued>35*60) {
            $fallback['reason']='WetterZentrale-Snapshot aelter als 35 Minuten oder ungueltig';
            return $fallback;
        }
        $quality=(string)($snapshot['quality']??'UNAVAILABLE');
        $known=['VERIFIED','DWD_ONLY','DWD_REMOTE',
            'MODEL_SUPPORTED','PWS_ONLY','CONFLICT','UNAVAILABLE'];
        if(!in_array($quality,$known,true)) {
            $fallback['reason']='Unbekannte Wetterqualitaet im Snapshot';
            return $fallback;
        }
        if(in_array($quality,['VERIFIED','DWD_ONLY','DWD_REMOTE',
            'MODEL_SUPPORTED','PWS_ONLY'],true)) {
            $current=is_array($snapshot['current']??null)
                ?$snapshot['current']:null;
            if(!self::isFresh($current,$now,2700)) {
                $fallback['reason']='Messzeitstempel nicht frisch: Qualitaet UNAVAILABLE';
                return $fallback;
            }
        }
        if($quality==='CONFLICT' || $quality==='UNAVAILABLE')
            $snapshot['current']=null;
        return $snapshot;
    }

    public static function isFresh(?array $v, int $now, int $maxAge): bool
    {
        return $v !== null && isset($v['observed_at'], $v['temperature'], $v['rh'], $v['dewpoint'])
            && is_numeric($v['observed_at']) && (int)$v['observed_at'] <= $now + 120
            && $now - (int)$v['observed_at'] <= $maxAge
            && JVWeatherMath::plausible((float)$v['temperature'], (float)$v['rh']);
    }

    /** /current_weather liefert ein einzelnes DWD-SYNOP-Messobjekt (kein Forecast). */
    public static function parseBrightSkyCurrent(?array $payload, int $now): ?array
    {
        if (!$payload || !is_array($payload['weather'] ?? null)) return null;
        $row = $payload['weather'];
        if (!isset($row['source_id'], $row['timestamp'])) return null;
        $source = null;
        foreach ((array)($payload['sources'] ?? []) as $s) {
            if (is_array($s) && isset($s['id']) && (string)$s['id'] === (string)$row['source_id']
                && ($s['observation_type'] ?? '') === 'synop') {
                $source = $s; break;
            }
        }
        // Fehlende Stations-Metadaten oder Modelle niemals als Messwerte akzeptieren.
        if ($source === null) return null;
        $timestamp = strtotime((string)$row['timestamp']);
        if ($timestamp === false || $timestamp > $now + 120 || $timestamp < $now - 24 * 3600) return null;
        $pair = JVWeatherMath::readPair($row['temperature'] ?? null, $row['relative_humidity'] ?? null);
        if ($pair === null) return null;
        return $pair + [
            'observed_at' => $timestamp,
            'source' => 'DWD_SYNOP',
            'station' => (string)($source['station_name'] ?? $source['dwd_station_id'] ?? $row['source_id']),
            'station_height_m' => isset($source['height']) ? (float)$source['height'] : null,
            'distance_m' => isset($source['distance']) ? (float)$source['distance'] : null,
        ];
    }

    public static function parseBrightSky(?array $payload, int $now, bool $forecast): array
    {
        if (!$payload) return [];
        $sources = [];
        foreach ((array)($payload['sources'] ?? []) as $s) {
            if (isset($s['id'])) $sources[(string)$s['id']] = $s;
        }
        $out = [];
        foreach ((array)($payload['weather'] ?? []) as $r) {
            $timestamp = isset($r['timestamp']) ? strtotime((string)$r['timestamp']) : false;
            if (!$timestamp) continue;
            $meta = $sources[(string)($r['source_id'] ?? '')] ?? [];
            $kind = $meta['observation_type'] ?? '';
            // Wenn Quellenmetadaten fehlen, nicht versehentlich Prognose als Messung behandeln.
            if ($forecast ? $kind !== 'forecast' : !in_array($kind, ['historical','current'], true)) continue;
            if ($forecast && ($timestamp < $now - 3600 || $timestamp > $now + 50 * 3600)) continue;
            if (!$forecast && ($timestamp > $now + 900 || $timestamp < $now - 3 * 3600)) continue;
            $p = JVWeatherMath::readPair($r['temperature'] ?? null, $r['relative_humidity'] ?? null);
            if ($p === null) continue;
            $p['observed_at'] = $timestamp;
            $p['source'] = 'DWD_BRIGHTSKY';
            $p['station'] = (string)($meta['station_name'] ?? $r['source_id'] ?? '');
            $p['distance_m'] = isset($meta['distance']) ? (float)$meta['distance'] : null;
            $p['station_height_m'] = isset($meta['height']) ? (float)$meta['height'] : null;
            $out[] = $p;
        }
        usort($out, static fn($a, $b) => $a['observed_at'] <=> $b['observed_at']);
        return $out;
    }

    public static function parseOpenMeteo(?array $payload, int $now, string $source): array
    {
        if (!$payload) return [];
        $h = $payload['hourly'] ?? null;
        if (!is_array($h)) return [];
        $times = $h['time'] ?? [];
        $temps = $h['temperature_2m'] ?? [];
        $rhs = $h['relative_humidity_2m'] ?? [];
        $out = [];
        foreach ($times as $i => $time) {
            // API Request setzt timeformat=unixtime und timezone=UTC.
            if (!is_int($time) && !ctype_digit((string)$time)) continue;
            $ts = (int)$time;
            if ($ts < $now - 3600 || $ts > $now + 50 * 3600) continue;
            $p = JVWeatherMath::readPair($temps[$i] ?? null, $rhs[$i] ?? null);
            if ($p === null) continue;
            $p['observed_at'] = $ts;
            $p['source'] = $source;
            $out[] = $p;
        }
        return $out;
    }

    public static function combineForecasts(array $bySource, int $now): array
    {
        $primary = $bySource['MOSMIX'] ?? [];
        if (!$primary) $primary = $bySource['ICON'] ?? [];
        if (!$primary) $primary = $bySource['ECMWF'] ?? [];
        if (!$primary) $primary = $bySource['BEST_MATCH'] ?? [];
        if (!$primary) $primary = $bySource['MET_NO'] ?? [];
        $out = [];
        foreach ($primary as $p) {
            $ts = (int)$p['observed_at'];
            if ($ts < $now - 3600 || $ts > $now + 48 * 3600) continue;
            $temps = [$p['temperature']];
            foreach (['ICON', 'ECMWF', 'BEST_MATCH', 'MET_NO'] as $s) {
                if ($s === $p['source']) continue;
                foreach (($bySource[$s] ?? []) as $q) {
                    if (abs((int)$q['observed_at'] - $ts) <= 1800) {
                        $temps[] = $q['temperature'];break;
                    }
                }
            }
            $p['model_spread_c'] = round(max($temps) - min($temps), 2);
            $p['confidence'] = count($temps) >= 2 && $p['model_spread_c'] <= 4 ? 'OK' : 'LOW';
            // MODEL SPREAD ist nur Unsicherheitsangabe; KEIN Mittelwert unabhängiger Stimmen.
            $out[] = $p;
        }
        return array_slice($out, 0, 50);
    }
}
