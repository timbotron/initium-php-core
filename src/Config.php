<?php

namespace Initium;

/**
 * The config contract between the framework and a consuming app.
 *
 * InitiumPHP keeps configuration as global define() constants (supplied by the
 * app in config/_env.php). This class names the constants core depends on and
 * asserts they exist at boot, so a misconfigured install fails with one clear
 * message instead of a fatal surfacing deep inside a request handler.
 */
class Config
{
    /** Constants a consuming app must define in config/_env.php. */
    public const REQUIRED = [
        'SITE_NAME',
        'SITE_URL',              // must end with a trailing slash; handlers build SITE_URL . 'path'
        'DB_NAME',
        'DB_SERVER',
        'DB_USER',
        'DB_PASS',
        'EMAIL_MAILGUN_KEY',
        'EMAIL_MAILGUN_DOMAIN',
        'EMAIL_SUPPORT_ADDRESS',
        'ALLOW_SIGNUPS',
        'LOGIN_TIMEOUT',         // session lifetime in hours
    ];

    /**
     * Assert every required config constant is defined. Call once at boot,
     * before routing.
     *
     * @throws \RuntimeException naming every missing constant.
     */
    public static function validate(): void
    {
        $missing = array_filter(self::REQUIRED, fn ($name) => !defined($name));

        if ($missing) {
            throw new \RuntimeException(
                'InitiumPHP config error: missing required constant(s): '
                . implode(', ', $missing)
                . '. Define them in config/_env.php (copy from _env.php.template).'
            );
        }
    }
}
