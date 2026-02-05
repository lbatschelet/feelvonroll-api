# feelvonroll-api

PHP + MySQL API for pins.

## API Docs

See `API.md` for full endpoint documentation and examples.

## Setup

1) Create the table
   - Run `schema.sql` in your MySQL database.

2) Configure credentials
   - Copy `config.example.php` to `config.local.php`
   - Fill in `db_host`, `db_name`, `db_user`, `db_pass`
   - Set `admin_token` to a long random string

3) Deploy
   - Upload this folder to your hosting (e.g. `/api`)
   - Endpoint: `https://your-domain.tld/api/pins.php`

## Migrations

Existing databases should apply the SQL in `migrations/`.

- `migrations/001_questionnaire.sql` adds questions/options/translations and group support.

## Tests

```bash
composer install
composer test
```

## Endpoints

- `GET /pins.php` → list all pins
- `GET /pins.php?floor=0` → list pins per floor
- `POST /pins.php` → create pin
- `GET /questions.php?lang=de` → questionnaire config
- `GET /languages.php` → enabled languages

Admin (requires `X-Admin-Token` header):
- `GET /admin_pins.php` → list all pins
- `POST /admin_pins.php` → update `approved`
- `GET /admin_questions.php` → list questions
- `POST /admin_questions.php` → upsert/delete questions
- `GET /admin_options.php` → list question options
- `POST /admin_options.php` → upsert/delete options
- `GET /admin_languages.php` → list languages
- `POST /admin_languages.php` → upsert/delete/toggle languages
- `POST /admin_translations.php` → upsert/delete translations
