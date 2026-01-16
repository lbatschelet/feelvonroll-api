# feelvonroll-api

PHP + MySQL API for pins.

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

## Endpoints

- `GET /pins.php` → list all pins
- `GET /pins.php?floor=0` → list pins per floor
- `POST /pins.php` → create pin

Admin (requires `X-Admin-Token` header):
- `GET /admin_pins.php` → list all pins
- `POST /admin_pins.php` → update `approved`
