# feelvonroll-api

PHP REST API for the [feelvonRoll](https://github.com/lbatschelet/feelvonroll) project. Serves both public endpoints (for the webapp) and authenticated admin endpoints (for the admin panel).

> [!NOTE]
> Part of the [feelvonRoll](https://github.com/lbatschelet/feelvonroll) project, developed for [PHBern](https://www.phbern.ch) within [RealTransform](https://sustainability.uzh.ch/de/forschung-lehre/forschung/realtransform.html). See the [main repository](https://github.com/lbatschelet/feelvonroll) for full documentation and project context.

## Requirements

- PHP >= 8.1
- MySQL / MariaDB

## Setup

1. **Create the database schema** (consolidated, includes all migrations 001–008)

   ```bash
   mysql -u root your_db_name < schema.sql
   ```

   > **Upgrading an existing database?** Apply only the migrations you haven't run yet from the `migrations/` directory (001 through 008).

2. **Configure credentials**

   ```bash
   cp config.example.php config.local.php
   ```

   Edit `config.local.php` with your database credentials, a `jwt_secret`, and an `admin_token` (used for the initial bootstrap).

3. **Run locally** (development)

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
| `GET` | `/questionnaire.php` | Resolve a questionnaire by key (optional `?key=...&lang=de`) |
| `GET` | `/stations.php` | Station info by key (`?key=...`) |
| `GET` | `/content.php` | Content page by key (`?key=about&lang=de`) |
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
| `GET/POST` | `/admin_questionnaires.php` | List, create, update, delete questionnaires and slots |
| `GET/POST` | `/admin_stations.php` | List, create, update, delete stations |
| `GET/POST` | `/admin_content.php` | Content page management |
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
