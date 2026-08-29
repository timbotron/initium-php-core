<?php

namespace Initium\Auth;

use FastRoute\RouteCollector;

/**
 * One-call registration of the core auth routes. Pass it to the Kernel:
 *
 *     $kernel->routes(\Initium\Auth\Routes::register(...));
 *
 * Handlers map to Initium\Auth\Controller, which the Kernel instantiates.
 */
class Routes {
    public static function register(RouteCollector $r): void {
        $r->get('/login', [Controller::class, 'login_page']);
        $r->post('/login', [Controller::class, 'login']);
        $r->get('/logout', [Controller::class, 'logout_page']);
        $r->get('/create-account', [Controller::class, 'create_account_page']);
        $r->post('/create-account', [Controller::class, 'create_account']);
        $r->get('/password-forgot', [Controller::class, 'forgot_password_page']);
        $r->post('/password-forgot', [Controller::class, 'forgot_password']);
        $r->get('/password-reset/{pass_uuid}', [Controller::class, 'reset_password_page']);
        $r->post('/password-reset/{pass_uuid}', [Controller::class, 'reset_password']);
    }
}
