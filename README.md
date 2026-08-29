# InitiumPHP Core

The framework half of [InitiumPHP](https://github.com/timbotron/initium-php-skeleton) —
a small, dependency-light PHP starter with turnkey user authentication. Installed
as a Composer library (`timbotron/initium-php-core`) and consumed by the skeleton
app; not used on its own.

- **Namespace:** `Initium\` (PSR-4 → `src/`)
- **Provides:** HTTP kernel + routing wiring, base classes, DB layer (Medoo),
  auth (`Cred` + auth controllers), email (Mailgun), default templates/assets,
  and the users migration.
- **Consumers** supply config (`define()` constants), routes, app models, and the
  web root. See [`ARCHITECTURE.md`](ARCHITECTURE.md) for the full boundary.

Full install/config/usage docs land with the v1 release (CODE-103).
