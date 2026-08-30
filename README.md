# InitiumPHP Core

The framework half of [InitiumPHP](https://github.com/timbotron/initium-php-skeleton) —
a small, dependency-light PHP starter with turnkey user authentication. Installed
as a Composer library (`timbotron/initium-php-core`) and consumed by the
[skeleton](https://github.com/timbotron/initium-php-skeleton) app; not used on its
own.

- **Namespace:** `Initium\` (PSR-4 → `src/`), plus one `files`-autoloaded helper module
- **Requires:** PHP ≥ 8.0
- **Provides:** the HTTP kernel + routing wiring, base classes, DB layer (Medoo),
  auth (`Cred` + auth controllers), email (Mailgun), default templates/assets, and
  the users migration.

Most people don't install this directly — they
`composer create-project timbotron/initium-php-skeleton myapp` and get a working
app with core already wired in. See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the
full core-vs-skeleton boundary.

## Install (into an app)

```bash
composer require timbotron/initium-php-core
```

## Config contract

The app supplies configuration as global `define()` constants (conventionally in
`config/_env.php`). Core validates the required set at boot and fails fast with a
single message naming anything missing:

```php
\Initium\Config::validate();
```

Required constants:

| Constant | Notes |
|---|---|
| `SITE_NAME` | Display name, used in page titles and outbound email |
| `SITE_URL` | **Trailing slash required** — handlers build `SITE_URL . 'path'` |
| `DB_NAME`, `DB_SERVER`, `DB_USER`, `DB_PASS` | MySQL connection (Medoo) |
| `EMAIL_MAILGUN_KEY`, `EMAIL_MAILGUN_DOMAIN`, `EMAIL_SUPPORT_ADDRESS` | Mailgun transactional email |
| `ALLOW_SIGNUPS` | Gate the create-account route/UI (truthy = open) |
| `LOGIN_TIMEOUT` | Session lifetime in **hours** |

Optional:

| Constant | Effect |
|---|---|
| `LOGIN_REDIRECT` | Path appended to `SITE_URL` that `login()` redirects to on success. Defaults to `logged-in-page`. |
| `LOGIN_THROTTLE_MAX` | Failed logins allowed per IP within the window before blocking. Defaults to `10`. |
| `LOGIN_THROTTLE_WINDOW` | Throttle window length in minutes. Defaults to `15`. |

The authoritative template lives at
[`config/_env.php.template`](config/_env.php.template); the skeleton ships a copy.

## Booting the kernel

`Initium\Kernel` owns FastRoute dispatch and the session bootstrap. Construct it
with the app-owned session store (kept above the web root so the OS `sessionclean`
cron can't purge long-lived sessions), register one or more route sets, and
`run()`:

```php
use Initium\Kernel;

(new Kernel(__DIR__ . '/../storage/sessions'))
    ->routes(require __DIR__ . '/../routes/web.php')   // app routes
    ->routes([\Initium\Auth\Routes::class, 'register']) // core auth routes
    ->run();
```

Each `routes()` argument is a callback receiving a `FastRoute\RouteCollector`, so
app routes and mounted auth routes compose. Sessions start only on a matched route
(never for a 404/405). A matched handler is instantiated and its method invoked
with the route vars as the single argument.

## Mounting the auth routes

`Initium\Auth\Routes::register` registers all auth endpoints in one call, mapping
them to `Initium\Auth\Controller`:

| Method | Path | Handler |
|---|---|---|
| GET/POST | `/login` | `login_page` / `login` |
| GET | `/logout` | `logout_page` |
| GET/POST | `/create-account` | `create_account_page` / `create_account` (404 when `ALLOW_SIGNUPS` is falsy) |
| GET/POST | `/password-forgot` | `forgot_password_page` / `forgot_password` |
| GET/POST | `/password-reset/{pass_uuid}` | `reset_password_page` / `reset_password` |

Account creation sets no password: it inserts an inactive user with a
`password_reset` UUID and emails a set-password link; `reset_password` hashes the
new password (cost 12), activates the user, and clears the UUID. Signup and
forgot-password are deliberately non-enumerable.

`login` is rate-limited per IP: failed attempts are recorded in `login_attempts`
and, once `LOGIN_THROTTLE_MAX` failures accumulate within `LOGIN_THROTTLE_WINDOW`
minutes, further attempts (even with the right password) are refused with a
friendly message until the window passes. A successful login clears that IP's
recorded attempts.

## Templates & assets (override without editing vendor)

Core ships default templates (`basic` layout + all auth pages, plus a starter
`home`) and default CSS. Views are resolved **app-first, core-fallback** through
the `app::` folder. Point core at the app's template directory once at boot:

```php
\Initium\View::override(__DIR__ . '/../templates');
```

Then any template rendered as `app::name` (e.g. `app::login`, `app::basic`)
resolves to a same-named file in the app's directory if present, otherwise the
core default — so an app overrides any view by dropping in a same-named file, no
vendor edits. Default CSS lives in [`assets/`](assets); copy it into the app's web
root (the skeleton ships it in `public/css/`).

## Migrations

Import every file in `migrations/` (in filename order) into your database once —
currently the `users` table and the `login_attempts` throttle table:

```bash
for f in vendor/timbotron/initium-php-core/migrations/*.sql; do
    mysql -u <user> -p <db> < "$f"
done
```

## Building blocks

- `Initium\Base` — `$this->db` (Medoo), the flash-style message queue, `return_code`, `isUUID`, `generate_uuid`.
- `Initium\DB` — Medoo singleton (clone/unserialize guarded); reads `DB_*`.
- `Initium\Email` — Mailgun sender over curl; reads `EMAIL_*` and `SITE_NAME`.
- `Initium\Auth\Cred` — session/auth authority: `userDetails`, `login` (regenerates the session id + stamps `last_login`), `logout`.
- `Initium\Support` helpers — `time_elapsed_string`, global via composer `files` autoload.

## Migrating from the old monolith

Before the split, InitiumPHP was a single repo cloned per project. To move an
existing project onto the packaged version, start a fresh
`composer create-project timbotron/initium-php-skeleton` app and carry your
app-specific pieces over: your config values into `config/_env.php`, your app
pages/models into `src/` (`App\`), your route declarations into `routes/web.php`,
and any customized templates into `templates/` (they override core by filename).
The framework itself is no longer copied into your repo — it's the core package,
updated with `composer update`.

Two conveniences that shipped in the old monolith's `composer.json` are **not**
part of core, since they weren't central to the framework:

- **`michelf/php-markdown`** and **`verot/class.upload.php`** — dropped. If a
  project needs Markdown rendering or file uploads, add them to its own skeleton
  `require`.
- **`aaronholbrook/autoload`** (directory-scanning) — dropped in favor of PSR-4
  autoloading plus explicit route/service wiring.
