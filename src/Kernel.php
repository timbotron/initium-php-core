<?php

namespace Initium;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

/**
 * The HTTP entry point. Owns FastRoute dispatch and the session bootstrap that
 * used to live inline in www/index.php.
 *
 * A consumer builds it with the app-owned session-storage path, registers one
 * or more route sets, and calls run():
 *
 *     (new Kernel(__DIR__ . '/../storage/sessions'))
 *         ->routes(require __DIR__ . '/../routes/web.php')   // app routes
 *         ->routes(\Initium\Auth\Routes::register(...))      // core auth (CODE-100)
 *         ->run();
 *
 * Sessions are started only on a matched route — never for a 404/405 — matching
 * the original behavior.
 */
class Kernel
{
    /** @var callable[] Route registrars, each given a FastRoute RouteCollector. */
    private array $registrars = [];

    /**
     * @param string $sessionPath Private session store, above the web root and
     *                            owned by the app (not vendor-relative), so the
     *                            OS sessionclean cron cannot purge our files.
     */
    public function __construct(private string $sessionPath)
    {
    }

    /**
     * Add a route set. The callback receives a FastRoute\RouteCollector. May be
     * called more than once so app routes and mounted auth routes compose.
     */
    public function routes(callable $registrar): self
    {
        $this->registrars[] = $registrar;
        return $this;
    }

    public function run(): void
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->registrars as $registrar) {
                $registrar($r);
            }
        });

        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        // Strip query string and decode, as the original front controller did.
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                header('HTTP/1.0 404 Not Found');
                return;

            case Dispatcher::METHOD_NOT_ALLOWED:
                header('HTTP/1.0 405 Method Not Allowed');
                return;

            case Dispatcher::FOUND:
                $this->startSession();
                [$class, $method] = $routeInfo[1];
                $handler = new $class();
                $handler->{$method}($routeInfo[2]);
                return;
        }
    }

    /**
     * Session timeouts + persistent-by-default login, derived from LOGIN_TIMEOUT.
     */
    private function startSession(): void
    {
        $lifetime = 3600 * LOGIN_TIMEOUT;

        // Keep sessions in a private store above the web root so the OS
        // sessionclean cron cannot purge our long-lived session files.
        if (!is_dir($this->sessionPath)) {
            mkdir($this->sessionPath, 0700, true);
        }
        session_save_path($this->sessionPath);
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        $cookieBase = [
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
        ];
        session_set_cookie_params(['lifetime' => $lifetime] + $cookieBase);
        session_start();

        // Re-send the cookie each request so the expiry slides forward with activity.
        setcookie(session_name(), session_id(), ['expires' => time() + $lifetime] + $cookieBase);
    }
}
