<?php
/**
 * Bounded, read-only evidence trail for the 15-minute manufacturer-owned
 * SmartFan takeovers. Never makes a Helios device request on its own.
 */
final class JVHeliosSmartFanReliability
{
    // The watchdog re-evaluates safety near expiry. It never blindly
    // reissues the same Party/Whisper to extend a native timer.
    public const RENEW_AFTER_SECONDS = 12 * 60;
    public const MAX_EVENTS = 48;

    /** AUTO returning close to the device's own 15-minute expiry is
     * normal, and must not trigger a spurious 30-minute actuator error.
     * This does NOT infer success when the manufacturer returned early.
     */
    public static function nearExpectedNativeTimerReturn(array $s,int $now):bool
    {
        $sent=(int)($s['sent_at']??0);
        $anchor=(int)($s['native_timer_anchor_at']??$sent);
        $until=(int)($s['until']??0);
        // Party_Set on a running native Party has not been proven to
        // reset its hardware timeout. Timestamp from the FIRST confirmed
        // run of a mode, never the most recent intra-mode request.
        $expected=$anchor+15*60;
        return in_array((int)($s['mode']??0),[2,3],true)
            && $sent>0 && $anchor>0 && $anchor<=$sent && $sent<=$now
            && $expected-90<=$now && $now<=$expected+90
            // If a change command occurred during the timer, the last
            // requested expiry may be later than the real native one.
            && $until>=$now-120 && $until<=$now+16*60
            && (int)($s['error_retry_after']??0)<=$now;
    }

    /**
     * Called by the light-weight 2-minute watchdog. Only a still-owned,
     * device-timed mode may request a new full safe Evaluate() cycle.
     */
    public static function renewalDue(array $state, int $now): bool
    {
        $sent = (int)($state['sent_at'] ?? 0);
        $until = (int)($state['until'] ?? 0);
        $mode = (int)($state['mode'] ?? 0);
        return $sent > 0 && $sent <= $now && $until > $now
            && $until - $sent <= 16 * 60
            && in_array($mode, [2, 3], true)
            && (int)($state['stage'] ?? 0) >= 1
            && (int)($state['stage'] ?? 0) <= 4
            && $now - $sent >= self::RENEW_AFTER_SECONDS
            && (int)($state['error_retry_after'] ?? 0) <= $now;
    }

    /**
     * Distinguish the *pre-command* AUTO/S4 from the SMART-owned S2 that
     * might be read back just seconds later. No person/location data.
     * Events are written only on state changes; memory remains bounded.
     */
    public static function observe(array $old, array $x, int $now): array
    {
        $oldEvents = array_values(array_filter((array)($old['events'] ?? []), 'is_array'));
        $before = is_array($x['before'] ?? null) ? $x['before'] : [];
        $after = is_array($x['after'] ?? null) ? $x['after'] : [];
        $plan = is_array($x['plan'] ?? null) ? $x['plan'] : [];
        $exec = is_array($x['execution'] ?? null) ? $x['execution'] : [];
        $enabled = ($x['enabled'] ?? false) === true;
        $actualMode = isset($before['mode']) && is_int($before['mode']) ? $before['mode'] : null;
        $actualFan = isset($before['fan']) && is_int($before['fan']) ? $before['fan'] : null;
        $target = isset($plan['target_stage']) && is_int($plan['target_stage'])
            ? $plan['target_stage'] : null;
        $execStatus = substr((string)($exec['status'] ?? 'UNKNOWN'), 0, 90);
        $reason = substr((string)($plan['reason'] ?? 'UNKNOWN'), 0, 90);
        $verified = ($exec['verified'] ?? false) === true;

        if (!$enabled) {
            $kind = 'SMART_DISABLED';
        } elseif ($actualMode === null || $actualFan === null) {
            $kind = 'DEVICE_LIVE_UNAVAILABLE';
        } elseif ($actualMode === 0 && $actualFan === 4) {
            $kind = $verified && ($after['fan'] ?? null) === $target
                && ($after['mode'] ?? null) !== 0
                ? 'AUTO_S4_RECOVERED_BY_SMART'
                : 'AUTO_S4_NO_SMART_CONFIRMATION';
        } elseif ($verified && $target !== null
            && ($after['fan'] ?? null) === $target
            && in_array($after['mode'] ?? null, [2, 3], true)) {
            $kind = 'SMART_STAGE_' . $target . '_CONFIRMED';
        } elseif ($actualMode === 0 && ($exec['attempted'] ?? false) === true) {
            $kind = 'AUTO_STAGE_' . $actualFan . '_COMMAND_PENDING';
        } elseif ($actualMode === 0 && ($plan['execute'] ?? false) === false) {
            $kind = 'AUTO_FALLBACK_' . $actualFan;
        } elseif (in_array($actualMode, [2, 3], true)) {
            $kind = 'TIMED_MODE_' . $actualMode . '_UNCONFIRMED';
        } else {
            $kind = 'DEVICE_MODE_' . $actualMode;
        }

        $issue = in_array($kind, [
            'AUTO_S4_NO_SMART_CONFIRMATION',
            'DEVICE_LIVE_UNAVAILABLE',
        ], true) || str_contains($execStatus, 'ERROR')
            || str_contains($execStatus, 'BACKOFF')
            || str_contains($execStatus, 'PENDING')
            || str_contains($execStatus, 'NOT_ACTIVE');
        // An operator can distinguish a real change from ordinary 5-minute
        // reports, and inspect why S4 was not reduced despite normal room RH.
        $fingerprint = $kind . '|' . (
            $issue || str_starts_with($kind, 'AUTO_FALLBACK_')
                ? $reason . '|' . $execStatus : (string)$target
        );
        if ($fingerprint !== (string)($old['fingerprint'] ?? '')) {
            $oldEvents[] = [
                'at' => $now, 'kind' => $kind,
                'before_mode' => $actualMode, 'before_stage' => $actualFan,
                'after_mode' => $after['mode'] ?? null,
                'after_stage' => $after['fan'] ?? null,
                'target_stage' => $target,
                'plan_reason' => $reason,
                'execution' => $execStatus,
                'native_extract_rh' => isset($x['native_extract_rh'])
                    && is_numeric($x['native_extract_rh'])
                    ? round((float)$x['native_extract_rh'], 1) : null,
                'weather_quality' => substr((string)($x['weather_quality'] ?? 'UNKNOWN'), 0, 30),
                'own_timer_until' => (int)($x['owned_until'] ?? 0),
                'retry_after' => (int)($x['retry_after'] ?? 0),
            ];
        }
        $events = array_slice($oldEvents, -self::MAX_EVENTS);
        // Count a real device command once by its persisted sent_at. A replay
        // of a diagnostic snapshot does not create another command.
        $commandSentAt=(int)($x['command_sent_at'] ?? $now);
        $sent = ($exec['sent'] ?? false) === true
            && $commandSentAt > (int)($old['last_own_command_sent_at'] ?? 0);
        $confirmed = $sent && $verified;
        return [
            'version' => 1,
            'fingerprint' => $fingerprint,
            'last_seen_at' => $now,
            'last_kind' => $kind,
            'last_own_command_sent_at' => $sent ? $commandSentAt
                : (int)($old['last_own_command_sent_at'] ?? 0),
            'last_own_command_confirmed_at' => $confirmed ? $commandSentAt
                : (int)($old['last_own_command_confirmed_at'] ?? 0),
            'confirmed_command_count' => min(1000000,
                (int)($old['confirmed_command_count'] ?? 0) + ($confirmed ? 1 : 0)),
            'events' => $events,
        ];
    }

    public static function diagnostic(array $state): array
    {
        return [
            'last_seen_at' => (int)($state['last_seen_at'] ?? 0),
            'last_kind' => (string)($state['last_kind'] ?? 'NO_OBSERVATIONS'),
            'last_own_command_sent_at' => (int)($state['last_own_command_sent_at'] ?? 0),
            'last_own_command_confirmed_at' => (int)($state['last_own_command_confirmed_at'] ?? 0),
            'confirmed_command_count' => (int)($state['confirmed_command_count'] ?? 0),
            'recent_events' => array_slice((array)($state['events'] ?? []), -12),
        ];
    }
}
