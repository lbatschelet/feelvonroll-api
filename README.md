# feelvonroll-api

PHP REST API for the [feelvonRoll](https://github.com/lbatschelet/feelvonroll) project. Serves both public endpoints (for the webapp) and authenticated admin endpoints (for the admin panel).

> [!NOTE]
> Part of the [feelvonRoll](https://github.com/lbatschelet/feelvonroll) project, developed for [PHBern](https://www.phbern.ch) within [RealTransform](https://sustainability.uzh.ch/de/forschung-lehre/forschung/realtransform.html). See the [main repository](https://github.com/lbatschelet/feelvonroll) for full documentation and project context.

## Requirements

- PHP >= 8.1
- MySQL / MariaDB

## Setup

1. **Create the database schema**

   ```bash
   mysql -u root your_db_name < schema.sql
   ```

2. **Apply migrations** (in order)

   ```bash
   mysql -u root your_db_name < migrations/001_questionnaire.sql
   mysql -u root your_db_name < migrations/002_slider_percent.sql
   mysql -u root your_db_name < migrations/003_admin_users.sql
   mysql -u root your_db_name < migrations/004_admin_token_version.sql
   mysql -u root your_db_name < migrations/005_admin_roles_profile.sql
   ```

3. **Configure credentials**

   ```bash
   cp config.example.php config.local.php
   ```

   Edit `config.local.php` with your database credentials, a `jwt_secret`, and an `admin_token` (used for the initial bootstrap).

4. **Run locally** (development)

   ```bash
   php -S localhost:8080
   ```

## API Documentation

See [API.md](API.md) for the full endpoint reference with request/response examples.

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/pins.php` | List approved pins (optional `?floor=N`) |
| `POST` | `/pins.php` | Create a pin |
| `GET` | `/questions.php` | Questionnaire config (optional `?lang=de`) |
| `GET` | `/languages.php` | Enabled languages |
| `GET` | `/translations.php` | Translations by language and prefix |

### Admin Endpoints (JWT required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET/POST` | `/admin_auth.php` | Login, bootstrap, password reset, token refresh |
| `GET/POST` | `/admin_pins.php` | List all pins, update approval, delete, CSV export |
| `GET/POST` | `/admin_questions.php` | List, upsert, delete questions |
| `GET/POST` | `/admin_options.php` | List, upsert, delete question options |
| `GET/POST` | `/admin_languages.php` | List, upsert, toggle, delete languages |
| `POST` | `/admin_translations.php` | Upsert, delete translations |
| `GET/POST` | `/admin_users.php` | List, create, update, delete users, password reset |
| `GET` | `/admin_audit.php` | Audit log with pagination |

## Authentication

Admin endpoints use JWT Bearer tokens. The authentication flow:

1. **Bootstrap**: On first run, use the `admin_token` from config to create the initial admin user
2. **Login**: `POST /admin_auth.php` with `action: "login"` returns a JWT
3. **Requests**: Include `Authorization: Bearer <token>` header
4. **Refresh**: Tokens can be refreshed via `action: "refresh"`

## Tests

```bash
composer test
```

## License

[AGPL-3.0](../LICENSE) -- [Lukas Batschelet](https://lukasbatschelet.ch) for [PHBern](https://www.phbern.ch)
