<?php

namespace Initium;

/**
 * Synchronizer-token CSRF protection for state-changing POSTs.
 *
 * One token per session, minted lazily on first use and reused for the session's
 * lifetime. Templates emit it with $this->csrf_field() (registered in View), and
 * POST handlers gate on Base::verify_csrf() before doing any work.
 *
 * Requires an active session — the Kernel starts one on every matched route, so
 * both the GET that renders a form and the POST that verifies it have it.
 */
class Csrf {

    private const KEY = 'csrf_token';

    // The session's token, created on first request for it.
    public static function token(): string {
        if(empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    // Hidden form input carrying the token. Emitted by templates.
    public static function field(): string {
        return '<input type="hidden" name="' . self::KEY . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    // Constant-time check of the posted token against the session's.
    public static function verify(): bool {
        $sent = $_POST[self::KEY] ?? '';
        return is_string($sent)
            && !empty($_SESSION[self::KEY])
            && hash_equals($_SESSION[self::KEY], $sent);
    }
}
