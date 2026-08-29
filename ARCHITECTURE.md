# InitiumPHP — architecture & package boundary

InitiumPHP ships as two Composer packages split out of the original single-repo
starter:

| Package | Composer name | Namespace | Role |
|---|---|---|---|
| **Core** | `timbotron/initium-php-core` | `Initium\` (PSR-4 → `src/`) | The framework: kernel, routing wiring, auth, DB, email, helpers, default templates/assets, users migration. No project config or app models. |
| **Skeleton** | `timbotron/initium-php-skeleton` | `App\` (PSR-4 → `src/`) | Boilerplate app. Owns config, routes, app models/pages, template overrides, the web root. `require`s core. |

A new project runs `composer create-project timbotron/initium-php-skeleton myapp`,
fills in `config/_env.php`, imports the users migration, and has a working
auth-enabled app. Framework fixes arrive via `composer update`.

## Naming decisions (locked)

- HTTP entry class is **`Initium\Kernel`** (not `Application`).
- Auth request handlers live in **`Initium\Auth\Controller`**; the session/auth
  authority is **`Initium\Auth\Cred`**.
- The old `aaronholbrook/autoload` directory-scanning is **dropped**. Autoloading
  is PSR-4; wiring that used to be implicit (config/models auto-included) becomes
  explicit route/service registration from the skeleton.

## Class boundary

Derived from the original `app/models/*` + `www/index.php`.

### Core (`Initium\`)

| Class / file | From | Notes |
|---|---|---|
| `Initium\Kernel` | `www/index.php` | Session bootstrap + FastRoute dispatch, exposed via a route-registration API and `run()`. Session save path is injected (app-owned), not vendor-relative. |
| `Initium\Base` | `app/models/base.php` | Message queue, `return_code`, `isUUID`. Owns `$this->db`. Gains `generate_uuid` (moved off `User`). |
| `Initium\DB` | `app/models/db.php` | Medoo singleton. Reads `DB_*`. |
| `Initium\Email` | `app/models/email.php` | Mailgun curl sender. Reads `EMAIL_*`, `SITE_NAME`. |
| `Initium\Auth\Cred` | `app/models/cred.php` | `userDetails` / `login` / `logout`. Keeps `session_regenerate_id(true)` + `last_login`. |
| `Initium\Auth\Controller` | auth methods of `app/models/user.php` | `login(_page)`, `create_account(_page)`, `create_user`, `send_set_password_email`, `forgot_password(_page)`, `reset_password(_page)`, `logout_page`. Behavior preserved exactly. |
| `Initium\Support` helpers | `app/config/settings.php` | `time_elapsed_string` etc., registered via composer `files` autoload. |
| `Initium\Config` | new | Validates required constants at boot; fails fast listing any missing. |
| default templates | `app/templates/*` | `basic` layout + auth pages, overridable by the skeleton. |
| default assets | `www/css/*` | Published/copied into the skeleton web root. |
| `migrations/001-migration-start.sql` | repo root | Users table. |

### Skeleton (`App\`)

| Class / file | From | Notes |
|---|---|---|
| `public/index.php` | `www/index.php` | Thin: autoload → load `_env.php` → build `Kernel` → mount core auth routes → load `routes/web.php` → `run()`. |
| `App\Controllers\Home` | app methods of `user.php` | `home_page`, `logged_in_page`. Extends `Initium\Base`, uses `Initium\Auth\Cred`. **Not** part of core. |
| `config/_env.php(.template)` | `app/config/_env.php` | Constants. Template's source of truth is core; skeleton ships a copy. |
| `routes/web.php` | route block of `index.php` | App routes; also where core auth routes are mounted. |
| `templates/` | — | Empty by default; same-named files override core templates. |
| `storage/sessions/` | `app/storage/sessions/` | App-owned session store above the web root. |

## Public API the skeleton depends on

The contract later tickets must keep stable:

1. **Boot / dispatch** — construct `Initium\Kernel` with the app's session-storage
   path; register routes via a callback receiving a `FastRoute\RouteCollector`;
   call `run()`.
2. **Auth route mounting** — one core helper registers all auth routes onto the
   collector, mapping to `Initium\Auth\Controller`.
3. **Post-login landing** — configurable by the app (was the hardcoded
   `SITE_URL . 'logged-in-page'`), not baked into core.
4. **Config** — global `define()` constants remain the mechanism. Required set:
   `SITE_NAME`, `SITE_URL` (trailing slash), `DB_NAME`, `DB_SERVER`, `DB_USER`,
   `DB_PASS`, `EMAIL_MAILGUN_KEY`, `EMAIL_MAILGUN_DOMAIN`, `EMAIL_SUPPORT_ADDRESS`,
   `ALLOW_SIGNUPS`, `LOGIN_TIMEOUT`. `Initium\Config` validates them at boot.
5. **Template overrides** — the Plates engine resolves the skeleton's `templates/`
   ahead of core defaults, so a same-named file in the app wins with no vendor edits.

## Local development (pre-publish)

Until the repos are public on Packagist, the skeleton resolves core through a
Composer **path repository** pointing at `../initium-php-core` (symlink install).
At publish time this is replaced by a plain `require: { "timbotron/initium-php-core": "^1.0" }`.
