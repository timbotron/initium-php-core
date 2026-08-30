<?php

namespace Initium;

/**
 * Runtime-editable platform settings, backed by the `settings` key/value table
 * and managed from the admin area (Initium\Admin\Controller).
 *
 * Reads fall back to a default when a row is absent, so behavior is unchanged
 * until an admin saves a value. The two toggles the framework itself consults:
 *
 *   allow_signups        - is the create-account route open? (default: the
 *                          ALLOW_SIGNUPS config constant)
 *   require_valid_email  - does signup email a set-password link (Mailgun), or
 *                          skip straight to the set-password page? (default: on)
 */
class Settings extends Base {

    public function get(string $name, string $default = ''): string {
        $value = $this->db->get('settings', 'value', ['name' => $name]);
        return $value === null ? $default : $value;
    }

    public function set(string $name, string $value): void {
        if($this->db->has('settings', ['name' => $name])) {
            $this->db->update('settings', ['value' => $value], ['name' => $name]);
        }
        else {
            $this->db->insert('settings', ['name' => $name, 'value' => $value]);
        }
    }

    public function allow_signups(): bool {
        $default = (defined('ALLOW_SIGNUPS') && ALLOW_SIGNUPS) ? '1' : '0';
        return (bool) (int) $this->get('allow_signups', $default);
    }

    public function require_valid_email(): bool {
        return (bool) (int) $this->get('require_valid_email', '1');
    }
}
