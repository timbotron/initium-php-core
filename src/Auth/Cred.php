<?php

namespace Initium\Auth;

use Initium\Base;

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
		$user = $this->db->get("users", ['id','email','password'], ['is_active' => 1, 'email'=> $email]);

		if($user && password_verify($password, $user['password'])) {
			// login good. Clear this IP's failed-attempt history and record login time
			$this->db->delete('login_attempts', ['ip' => $this->client_ip()]);
			$this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

			// Regenerate the id at the privilege boundary to
			// prevent session fixation, then set session stuff
			session_regenerate_id(true);
			$_SESSION['user_data'] = [
				'user_id' => $user['id'],
				'email' => $user['email'],
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

	// --- brute-force throttle -------------------------------------------------
	// A lightweight per-IP throttle so every Initium project inherits some
	// protection against password guessing. Thresholds are optional constants
	// with sane defaults, so existing configs need no changes:
	//   LOGIN_THROTTLE_MAX    - failures allowed per IP in the window (default 10)
	//   LOGIN_THROTTLE_WINDOW - window length in minutes (default 15)

	private function client_ip(): string {
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	// True once this IP has too many recent failures. Check before verifying
	// credentials so a blocked client is refused early.
	public function login_throttled(): bool {
		$max = defined('LOGIN_THROTTLE_MAX') ? LOGIN_THROTTLE_MAX : 10;
		$window = defined('LOGIN_THROTTLE_WINDOW') ? LOGIN_THROTTLE_WINDOW : 15;
		$since = date('Y-m-d H:i:s', time() - $window * 60);

		return $this->db->count('login_attempts', [
			'ip' => $this->client_ip(),
			'created_at[>=]' => $since,
		]) >= $max;
	}

	// Record a failed attempt for this IP (email kept for auditing only).
	public function record_failed_login(string $email): void {
		$this->db->insert('login_attempts', [
			'ip' => $this->client_ip(),
			'email' => $email,
			'created_at' => date('Y-m-d H:i:s'),
		]);
	}

}
