<?php

namespace Initium\Auth;

use Initium\Base;
use Initium\Request;

class Cred extends Base {

	public static function userDetails(): array | bool {
		// returns array of user detail from session if logged in, else false
		return $_SESSION['user_data'] ?? false;
	}

	public function login(string $email, string $password): bool {

		if(empty($password) || strpos($password, "\0") !== false
			|| strlen($password) > 200)
		{
			return false;
		}

		// password at least is sensical, lets look up user
		$user = $this->db->get("users", ['id','email','password','is_admin'], ['is_active' => 1, 'email'=> $email]);

		if($user && password_verify($password, $user['password'])) {
			// login good. Clear this IP's failed-attempt history and record login time
			$this->db->delete('login_attempts', ['ip' => Request::clientIp()]);
			$this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

			// Regenerate the id at the privilege boundary to
			// prevent session fixation, then set session stuff
			session_regenerate_id(true);
			$_SESSION['user_data'] = [
				'user_id' => $user['id'],
				'email' => $user['email'],
				'is_admin' => (int) $user['is_admin'],
			];

			return true;
		}
		else {
			// username or password incorrect
			return false;
		}

	}

	public static function logout(): bool {
		session_unset();
		session_destroy();
		return true;
	}

	// A logged-in user is an admin when their users.is_admin flag is set, or
	// when their email matches the optional ADMIN_EMAIL config constant (a
	// config-only bootstrap admin that needs no DB change).
	public static function isAdmin(): bool {
		$user = self::userDetails();
		if(!$user) {
			return false;
		}
		if(!empty($user['is_admin'])) {
			return true;
		}
		return defined('ADMIN_EMAIL') && ADMIN_EMAIL !== '' && $user['email'] === ADMIN_EMAIL;
	}

	// --- brute-force throttle -------------------------------------------------
	// A lightweight per-IP throttle so every Initium project inherits some
	// protection against password guessing. Thresholds are optional constants
	// with sane defaults, so existing configs need no changes:
	//   LOGIN_THROTTLE_MAX    - failures allowed per IP in the window (default 10)
	//   LOGIN_THROTTLE_WINDOW - window length in minutes (default 15)
	//
	// The client IP comes from Initium\Request, which honors the trusted proxy's
	// forwarded header when TRUST_FORWARDED is set (otherwise every request behind
	// a proxy shares REMOTE_ADDR and the throttle self-DoSes or is bypassed).

	// True once this IP has too many recent failures. Check before verifying
	// credentials so a blocked client is refused early.
	public function login_throttled(): bool {
		$max = defined('LOGIN_THROTTLE_MAX') ? LOGIN_THROTTLE_MAX : 10;
		$window = defined('LOGIN_THROTTLE_WINDOW') ? LOGIN_THROTTLE_WINDOW : 15;
		$since = date('Y-m-d H:i:s', time() - $window * 60);

		return $this->db->count('login_attempts', [
			'ip' => Request::clientIp(),
			'created_at[>=]' => $since,
		]) >= $max;
	}

	// Record a failed attempt for this IP (email kept for auditing only).
	public function record_failed_login(string $email): void {
		$this->db->insert('login_attempts', [
			'ip' => Request::clientIp(),
			'email' => $email,
			'created_at' => date('Y-m-d H:i:s'),
		]);
	}

}
