<?php

namespace Initium\Admin;

use Initium\Auth\Cred;
use Initium\Base;
use Initium\Settings;
use Initium\View;

/**
 * The admin settings area. Deliberately tiny: two platform toggles stored in the
 * settings table (Initium\Settings) —
 *
 *   allow_signups        - is the create-account route open?
 *   require_valid_email  - email a set-password link (Mailgun), or skip straight
 *                          to the set-password page (for installs without Mailgun)?
 *
 * Access is gated by Cred::isAdmin() (users.is_admin flag OR the ADMIN_EMAIL
 * config constant); non-admins get a 404 so the area's existence isn't revealed.
 */
class Controller extends Base {

	protected $templates;
	protected $settings;

	public function __construct() {
		parent::__construct();
		$this->templates = View::engine();
		$this->templates->addData([
			'is_logged_in' => Cred::userDetails() ? true : false,
			'is_admin' => Cred::isAdmin(),
		], ['app::basic']);
		$this->settings = new Settings();
	}

	protected function require_admin() {
		if(!Cred::isAdmin()) {
			$this->return_code(404);
		}
	}

	public function settings_page() {
		$this->require_admin();
		$this->render_settings();
	}

	public function settings_save() {
		$this->verify_csrf();
		$this->require_admin();

		// unchecked checkboxes are absent from POST
		$this->settings->set('allow_signups', isset($_POST['allow_signups']) ? '1' : '0');
		$this->settings->set('require_valid_email', isset($_POST['require_valid_email']) ? '1' : '0');

		$this->add_message('info', 'Settings saved.');
		$this->render_settings();
	}

	protected function render_settings() {
		$this->templates->addData(['page_title' => SITE_NAME . ' Admin'], ['app::basic']);
		$this->templates->addData(['messages' => $this->get_messages()], ['app::basic']);
		$this->templates->addData([
			'allow_signups' => $this->settings->allow_signups(),
			'require_valid_email' => $this->settings->require_valid_email(),
		], ['app::admin_settings']);
		echo $this->templates->render('app::admin_settings');
	}
}
