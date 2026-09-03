<?php

namespace Initium;

/**
 * Request facts that are only trustworthy once you know whether a reverse proxy
 * sits in front of the app.
 *
 * Behind a proxy (e.g. the shipped Caddy stack), REMOTE_ADDR is the proxy's IP
 * and $_SERVER['HTTPS'] is unset, so the raw values mislead both the login
 * throttle and the session-cookie Secure flag. When — and only when — the app
 * declares it runs behind a trusted proxy (TRUST_FORWARDED), we honor the
 * forwarded headers that proxy set.
 *
 * Trust model: exactly one trusted proxy directly in front. X-Forwarded-For is
 * "client, proxy1, ..."; a client can prepend a spoofed value, but our proxy
 * appends the address it actually saw, so the RIGHT-MOST entry is the real
 * client. Never trust the header when TRUST_FORWARDED is off — without a proxy
 * it is fully client-controlled.
 */
class Request {

    public static function trustForwarded(): bool {
        return defined('TRUST_FORWARDED') && TRUST_FORWARDED;
    }

    // The client's IP, from the trusted proxy's forwarded header when configured,
    // else the direct peer.
    public static function clientIp(): string {
        if(self::trustForwarded() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim(end($parts)); // right-most hop = what our proxy recorded
            if(filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
