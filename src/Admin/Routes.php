<?php

namespace Initium\Admin;

use FastRoute\RouteCollector;

/**
 * One-call registration of the admin routes. Mount alongside the auth routes:
 *
 *     $kernel->routes([\Initium\Admin\Routes::class, 'register']);
 *
 * Handlers map to Initium\Admin\Controller, gated to admins.
 */
class Routes {
	public static function register(RouteCollector $r): void {
		$r->get('/admin', [Controller::class, 'settings_page']);
		$r->post('/admin', [Controller::class, 'settings_save']);
	}
}
