<?php

namespace Initium\Auth;

use Initium\Base;
use Initium\Email;
use Initium\Settings;
use Initium\View;

/**
 * The auth request handlers, split out of the original monolithic User class.
 * Owns account creation, the set/reset-password flow, login, and logout. App
 * pages (home, logged-in landing) are NOT here — they belong to the consuming
 * app.
 *
 * Behavior is preserved from the original: the non-enumerable signup/forgot
 * flow, ALLOW_SIGNUPS gating, cost-12 hashing, and UUID validation. Templates
 * are referenced through the "app::" folder so the app can override any of them
 * (see Initium\View).
 */
class Controller extends Base {

	protected $templates;

	public function __construct() {
		parent::__construct();
		$this->templates = View::engine();
		$this->templates->addData([
			'is_logged_in' => Cred::userDetails() ? true : false,
			'is_admin' => Cred::isAdmin(),
		], ['app::basic']);
	}

	// Where login() sends the user afterward. App-configurable via the optional
	// LOGIN_REDIRECT constant (a path appended to SITE_URL); defaults to the
	// skeleton's logged-in page.
	protected function login_redirect(): string {
		$path = defined('LOGIN_REDIRECT') ? LOGIN_REDIRECT : 'logged-in-page';
		return SITE_URL . $path;
	}

	// create user
	public function create_user($email, $uuid) {

		$this->db->insert("users", [
	    	"email" => $email,
	    	"created_at" => date("Y-m-d"),
	    	"password_reset" => $uuid,
	    	"is_active" => 0,
	    ]);

	    if($this->db->error) {
	    	// log server-side; never surface the raw SQL error to the client
	    	error_log('create_user DB error: ' . $this->db->error);
	    	return false;
	    }

	    return true;
	}

	// render + send the set/reset-password email for a given uuid
	protected function send_set_password_email($email, $uuid, $reset_type, $subject) {
		$validate_url = SITE_URL . 'password-reset/' . $uuid;

		$this->templates->addData(['reset_type' => $reset_type, 'page_title' => SITE_NAME, 'reset_link' => $validate_url], ['app::reset_password_email']);
		$email_html = $this->templates->render('app::reset_password_email');

		$mailer = new Email();
		$mailer->send_mailgun($email, $subject, 'Set/Reset Password here: ' . $validate_url . "\n\n-The " . SITE_NAME . ' team', $email_html);
	}

	public function login_page() {
		// just draw page
		$this->templates->addData(['page_title' => SITE_NAME . ' Login'], ['app::basic']);
		// drive the Create Account link off the runtime setting, not the constant,
		// so it hides when an admin has turned signups off (route would 404)
		$this->templates->addData(['allow_signups' => (new Settings())->allow_signups()], ['app::login']);

		echo $this->templates->render('app::login');

	}

	public function login() {
		$cred = new Cred();

		// Brute-force throttle: refuse early (before any credential work) once
		// this IP has too many recent failures.
		if($cred->login_throttled()) {
			$this->add_message('error', 'Too many failed login attempts. Please try again in a few minutes.');
			$this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
			$this->templates->addData(['post_content' => $_POST], ['app::login']);
			$this->login_page();
			return true;
		}

		$v = new \Valitron\Validator($_POST);
		$v->rule('required', ['email', 'password']);
		$v->rule('email', 'email');
		$v->rule('lengthMin', 'password', 8);
		$v->rule('lengthMax', 'password', 199);
		if(!$v->validate()) {
		    // Errors
		    foreach($v->errors() as $err_section) {
		    	foreach($err_section as $e) {
		    		$this->add_message('error', $e);
		    	}
		    }

		    $this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
		    $this->templates->addData(['post_content' => $_POST], ['app::login']);
		    $this->login_page();
		    return true;
		}

		if(!$cred->login($_POST['email'], $_POST['password'])) {
			$cred->record_failed_login($_POST['email']);
			$this->add_message('error', 'Email or password incorrect.');
			$this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
		    $this->templates->addData(['post_content' => $_POST], ['app::login']);
		    $this->login_page();
		    return true;
		}

		header('Location: ' . $this->login_redirect());
        exit;

	}

	public function logout_page() {

		Cred::logout();

		$this->templates->addData(['is_logged_in' =>false], ['app::basic']);
		$this->templates->addData(['page_title' => SITE_NAME], ['app::basic']);
		$this->templates->addData(['is_error' => 0, 'top_title' => "Logout Successful.", "page_message" =>"<p>You have been logged out.</p>"], ['app::general_message_page']);
		echo $this->templates->render('app::general_message_page');

	}

	public function create_account_page() {
		if(!(new Settings())->allow_signups()) {
			$this->return_code(404);
		}

		// just draw page
		$this->templates->addData(['page_title' => SITE_NAME . ' Account Creation'], ['app::basic']);
		echo $this->templates->render('app::create_account');

	}

	public function create_account() {
		if(!(new Settings())->allow_signups()) {
			$this->return_code(404);
		}

		// validate

		$v = new \Valitron\Validator($_POST);
		$v->rule('required', ['email', 'email2']);
		$v->rule('email', 'email');
		$v->rule('equals', 'email', 'email2');
		if(!$v->validate()) {
		    // Errors
		    foreach($v->errors() as $err_section) {
		    	foreach($err_section as $e) {
		    		$this->add_message('error', $e);
		    	}
		    }

		    $this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
		    $this->templates->addData(['post_content' => $_POST], ['app::create_account']);
		    $this->create_account_page();
		    return true;
		}

		// validated. Work out whether this signup earns a set-password uuid, then
		// either email the link (default) or, when require_valid_email is off,
		// send them straight to the set-password page (no Mailgun needed).
		$email = $_POST['email'];
		$existing = $this->db->get('users', ['id', 'is_active'], ['email' => $email]);

		$uuid = null;
		if(!$existing) {
			// brand-new email: create the inactive user
			$uuid = $this->generate_uuid();
			if(!$this->create_user($email, $uuid)) {
				$uuid = null; // create failed; fall through without a link
			}
		}
		elseif(!$existing['is_active']) {
			// abandoned signup: refresh the uuid so they aren't dead-ended
			$uuid = $this->generate_uuid();
			$this->db->update('users', ['password_reset' => $uuid], ['id' => $existing['id']]);
		}
		// existing and active: $uuid stays null — never reset an active account
		// from the signup form (that would be account takeover).

		if(!(new Settings())->require_valid_email()) {
			// No-Mailgun mode: skip the email round-trip.
			if($uuid !== null) {
				// new/abandoned user sets their password immediately
				header('Location: ' . SITE_URL . 'password-reset/' . $uuid);
				exit;
			}
			// active account (or a failed create): don't reveal or reset — to login
			header('Location: ' . SITE_URL . 'login');
			exit;
		}

		// Email mode (default, non-enumerable): email the link when there is one,
		// then always render the same success page regardless of the email's state.
		if($uuid !== null) {
			$this->send_set_password_email($email, $uuid, 'new', 'Welcome to ' . SITE_NAME);
		}

		$this->templates->addData(['page_title' => SITE_NAME], ['app::basic']);
		$this->templates->addData(['is_error' => 0, 'top_title' => "Created account", "page_message" =>"<p>Your account was successfully created. Please check your email for your confirmation and link to set your password.</p>"], ['app::general_message_page']);
		echo $this->templates->render('app::general_message_page');
	}

	public function forgot_password_page() {
		// just draw page
		$this->templates->addData(['page_title' => SITE_NAME], ['app::basic']);
		echo $this->templates->render('app::forgot_password_page');

	}

	public function forgot_password() {
		// validate

		$v = new \Valitron\Validator($_POST);
		$v->rule('required', ['email']);
		$v->rule('email', 'email');
		if(!$v->validate()) {
		    // Errors
		    foreach($v->errors() as $err_section) {
		    	foreach($err_section as $e) {
		    		$this->add_message('error', $e);
		    	}
		    }

		    $this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
		    $this->templates->addData(['post_content' => $_POST], ['app::forgot_password_page']);
		    $this->forgot_password_page();
		    return true;
		}

		$user_id = $this->db->get('users','id', ['email'=>$_POST['email'], 'is_active' => 1]);

		if($user_id) {
			// actually found user, lets set uuid and trigger email
			$uuid = $this->generate_uuid();

			// update user record to have new uuid
			$this->db->update("users", ['password_reset' => $uuid], ['id' => $user_id]);

			$this->send_set_password_email($_POST['email'], $uuid, 'same', 'Reset Password for ' . SITE_NAME);
		}


		// either way, show success-y page

		$this->templates->addData(['page_title' => SITE_NAME], ['app::basic']);
		$this->templates->addData(['is_error' => 0, 'top_title' => "New Password requested", "page_message" =>"<p>If your email exists in our system, you should receive an email with a password reset link soon.</p>"], ['app::general_message_page']);
		echo $this->templates->render('app::general_message_page');

	}

	public function reset_password_page($vars) {
		if(!$this->isUUID($vars['pass_uuid'])) {
			// is not a UUID
			$this->return_code(400);
		}

		// look up and see if uuid exists
		if(!$this->db->has('users',['password_reset'=>$vars['pass_uuid']])) {
			// UUID not found, 400 it
			$this->return_code(400);
		}

		// just draw page
		$this->templates->addData(['page_title' => SITE_NAME . ' Change Password'], ['app::basic']);
		$this->templates->addData(['uuid' => $vars['pass_uuid']], ['app::reset_password_page']);
		echo $this->templates->render('app::reset_password_page');
	}

	public function reset_password($vars) {
		if(!$this->isUUID($vars['pass_uuid'])) {
			// is not a UUID
			$this->return_code(400);
		}

		$v = new \Valitron\Validator($_POST);
		$v->rule('required', ['password', 'password2']);
		$v->rule('lengthMin', 'password', 8);
		$v->rule('lengthMax', 'password', 199);
		$v->rule('equals', 'password', 'password2');
		if(!$v->validate()) {
		    // Errors
		    foreach($v->errors() as $err_section) {
		    	foreach($err_section as $e) {
		    		$this->add_message('error', $e);
		    	}
		    }

		    $this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
		    $this->reset_password_page($vars);
		    return true;
		}

		// look up and see if uuid exists
		$user_id = $this->db->get('users','id', ['password_reset'=>$vars['pass_uuid']]);
		if(!$user_id) {
			// user not found, 400 it
			$this->return_code(400);
		}
		else {
			// found user so lets set password, wipte password hash and move on in life
			$password = password_hash($_POST["password"], PASSWORD_DEFAULT, ['cost' => 12]);

			if(!$this->db->update("users",["is_active" => 1, "password" => $password, "password_reset" => ''], ["id" => $user_id])) {
				// create user failed
				$this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
			    $this->reset_password_page($vars);
			    return true;
			}
			// just draw gen message
			$this->templates->addData(['page_title' => SITE_NAME], ['app::basic']);
			$this->templates->addData(['is_error' => 0, 'top_title' => "Password Changed Successfully", "page_message" =>"<p>Your password was changed successfully, please proceed to login.</p>\n"], ['app::general_message_page']);
			echo $this->templates->render('app::general_message_page');

		}
	}
}
