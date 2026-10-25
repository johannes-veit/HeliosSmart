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

    public static function isFresh(?array $v, int $now, int $maxAge): bool
    {
        return $v !== null && isset($v['observed_at'], $v['temperature'], $v['rh'], $v['dewpoint'])
            && is_numeric($v['observed_at']) && (int)$v['observed_at'] <= $now + 120
            && $now - (int)$v['observed_at'] <= $maxAge
            && JVWeatherMath::plausible((float)$v['temperature'], (float)$v['rh']);
    }

    public static function parseBrightSky(?array $payload, int $now, bool $forecast): array
    {
        if (!$payload) return [];
        $sources = [];
        foreach ((array)($payload['sources'] ?? []) as $s) {
            if (isset($s['id'])) $sources[(string)$s['id']] = (string)($s['observation_type'] ?? '');
        }
        $out = [];
        foreach ((array)($payload['weather'] ?? []) as $r) {
            $timestamp = isset($r['timestamp']) ? strtotime((string)$r['timestamp']) : false;
            if (!$timestamp) continue;
            $kind = $sources[(string)($r['source_id'] ?? '')] ?? '';
            // Wenn Quellenmetadaten fehlen, nicht versehentlich Prognose als Messung behandeln.
            if ($forecast ? $kind !== 'forecast' : !in_array($kind, ['historical','current'], true)) continue;
            if ($forecast && ($timestamp < $now - 3600 || $timestamp > $now + 50 * 3600)) continue;
            if (!$forecast && ($timestamp > $now + 900 || $timestamp < $now - 3 * 3600)) continue;
            $p = JVWeatherMath::readPair($r['temperature'] ?? null, $r['relative_humidity'] ?? null);
            if ($p === null) continue;
            $p['observed_at'] = $timestamp;
            $p['source'] = 'DWD_BRIGHTSKY';
            $p['station'] = (string)($r['source_id'] ?? '');
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
        $out = [];
        foreach ($primary as $p) {
            $ts = (int)$p['observed_at'];
            if ($ts < $now - 3600 || $ts > $now + 48 * 3600) continue;
            $temps = [$p['temperature']];
            foreach (['ICON', 'ECMWF'] as $s) {
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
