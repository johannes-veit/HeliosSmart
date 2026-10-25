<?php
/** Reine, ohne IP-Symcon testbare Wetter- und Feuchteberechnung. */
final class JVWeatherMath
{
    public static function plausible(float $c, float $rh): bool
    {
        return is_finite($c) && is_finite($rh) && $c >= -45.0 && $c <= 55.0 && $rh >= 1.0 && $rh <= 100.0;
    }

    public static function vaporPressure(float $c, float $rh): float
    {
        return 6.112 * exp((17.62 * $c) / (243.12 + $c)) * $rh / 100.0;
    }

    public static function absoluteHumidity(float $c, float $rh): float
    {
        return 216.7 * self::vaporPressure($c, $rh) / ($c + 273.15);
    }

    public static function dewPoint(float $c, float $rh): float
    {
        $gamma = log($rh / 100.0) + 17.62 * $c / (243.12 + $c);
        return 243.12 * $gamma / (17.62 - $gamma);
    }

    public static function readPair($t, $rh): ?array
    {
        if (!is_numeric($t) || !is_numeric($rh)) {
            return null;
        }
        $t = (float)$t;
        $rh = (float)$rh;
        if (!self::plausible($t, $rh)) {
            return null;
        }
        return [
            'temperature' => round($t, 2), 'rh' => round($rh, 2),
            'dewpoint' => round(self::dewPoint($t, $rh), 2),
            'absolute_humidity' => round(self::absoluteHumidity($t, $rh), 2),
        ];
    }
}
