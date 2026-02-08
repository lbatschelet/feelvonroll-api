# Feelvonroll API

Base URL: `/api`

All responses are JSON. Errors use `{"error": "message"}` with appropriate HTTP status.

## Public endpoints

### `GET /pins.php`
List approved pins.

Query params:
- `floor` (optional, int)

Response:
```json
[
  {
    "id": 1,
    "floor_index": 0,
    "position_x": 1.2,
    "position_y": 0.3,
    "position_z": -2.1,
    "wellbeing": 6,
    "reasons": ["licht", "ruhe"],
    "note": "string",
    "group_key": "staff",
    "approved": 1,
    "created_at": "2026-01-01 12:00:00"
  }
]
```

### `POST /pins.php`
Create a pin. Accepts the dynamic questionnaire payload.

Body:
```json
{
  "floor_index": 0,
  "x": 1.2,
  "y": 0.3,
  "z": -2.1,
  "answers": {
    "wellbeing": 6,
    "reasons": ["licht", "ruhe"],
    "group": "staff",
    "note": "string"
  }
}
```

Response: the created pin (same shape as `GET /pins.php`).

### `GET /questions.php`
Fetch the active questionnaire configuration.

Query params:
- `lang` (optional, defaults to `de`)

Response:
```json
[
  {
    "key": "wellbeing",
    "type": "slider",
    "required": true,
    "sort": 10,
    "config": { "min": 1, "max": 10, "step": 1, "default": 6 },
    "label": "How do you feel here?",
    "legend_low": "Not good at all",
    "legend_high": "Very good"
  },
  {
    "key": "reasons",
    "type": "multi",
    "required": false,
    "sort": 20,
    "config": { "allow_multiple": true },
    "label": "What contributes to your (un)wellbeing?",
    "options": [
      { "key": "licht", "sort": 10, "label": "Light" }
    ]
  }
]
```

### `GET /languages.php`
List enabled languages for the webapp switcher.

Response:
```json
[
  { "lang": "de", "label": "Deutsch" },
  { "lang": "en", "label": "English" }
]
```

### `GET /translations.php`
Fetch translations by language.

Query params:
- `lang` (required)
- `prefix` (optional, e.g. `questions.` or `options.reasons.`)

Response:
```json
{
  "questions.wellbeing.label": "How do you feel here?",
  "options.reasons.licht": "Light"
}
```

## Admin endpoints

All admin requests require a JWT Bearer token in the `Authorization` header:

```
Authorization: Bearer <token>
```

### `GET /admin_auth.php`
Check auth/bootstrap status.

Response:
```json
{ "bootstrap_required": false }
```

### `POST /admin_auth.php`
Authentication actions.

**Login:**
```json
{ "action": "login", "email": "admin@example.com", "password": "secret" }
```
Response:
```json
{ "token": "eyJ...", "user": { "id": 1, "email": "admin@example.com", "first_name": "Admin", "is_admin": true } }
```

**Bootstrap login** (first-time setup with admin_token from config):
```json
{ "action": "bootstrap_login", "admin_token": "your_configured_token" }
```

**Set password** (via reset token):
```json
{ "action": "set_password", "reset_token": "abc123", "password": "new_password" }
```

**Refresh token:**
```json
{ "action": "refresh" }
```
(Requires existing valid JWT in Authorization header.)

### `GET /admin_pins.php`
List all pins (including pending/rejected).

### `POST /admin_pins.php`
Update pin status or delete pins.

Body examples:
```json
{ "action": "update_approval", "ids": [1,2], "approved": 1 }
```
```json
{ "action": "delete", "ids": [1,2] }
```

### `GET /admin_questions.php`
List all questions.

### `POST /admin_questions.php`
Upsert or delete a question.

Body examples:
```json
{
  "action": "upsert",
  "question_key": "wellbeing",
  "type": "slider",
  "required": 1,
  "sort": 10,
  "is_active": 1,
  "config": { "min": 1, "max": 10, "step": 1, "default": 6 }
}
```
```json
{ "action": "delete", "question_key": "note" }
```

### `GET /admin_options.php`
List all options, optionally filtered by `question_key`.

### `POST /admin_options.php`
Upsert or delete an option.

Body examples:
```json
{
  "action": "upsert",
  "question_key": "group",
  "option_key": "staff",
  "sort": 10,
  "is_active": 1
}
```
```json
{ "action": "delete", "question_key": "group", "option_key": "staff" }
```

### `GET /admin_languages.php`
List all languages.

### `POST /admin_languages.php`
Upsert, toggle, or delete a language.

Body examples:
```json
{ "action": "upsert", "lang": "de", "label": "Deutsch", "enabled": 1 }
```
```json
{ "action": "toggle", "lang": "en", "enabled": 0 }
```
```json
{ "action": "delete", "lang": "fr" }
```

### `POST /admin_translations.php`
Upsert or delete translations.

Body examples:
```json
{
  "action": "upsert",
  "translation_key": "questions.note.label",
  "lang": "de",
  "text": "Anmerkung"
}
```
```json
{ "action": "delete", "translation_key": "questions.note.label", "lang": "de" }
```

### `GET /admin_users.php`
List all admin users. Requires admin role.

Response:
```json
[
  { "id": 1, "email": "admin@example.com", "first_name": "Admin", "last_name": "User", "is_admin": true }
]
```

### `POST /admin_users.php`
Manage admin users. Requires admin role.

**Create user:**
```json
{ "action": "create", "email": "new@example.com", "first_name": "New", "last_name": "User", "is_admin": false }
```
Response includes a `reset_link` for setting the initial password.

**Update user:**
```json
{ "action": "update", "user_id": 2, "first_name": "Updated", "is_admin": true }
```

**Delete user:**
```json
{ "action": "delete", "user_id": 2 }
```

**Reset password:**
```json
{ "action": "reset_password", "user_id": 2 }
```
Response includes a new `reset_link`.

**Update own profile** (any authenticated user):
```json
{ "action": "update_self", "first_name": "New Name", "last_name": "New Last" }
```

### `GET /admin_audit.php`
Fetch audit log entries with pagination.

Query params:
- `limit` (optional, default 50)
- `offset` (optional, default 0)

Response:
```json
{
  "items": [
    { "id": 1, "user_id": 1, "action": "login", "details": "{}", "created_at": "2026-01-01 12:00:00" }
  ],
  "total": 42
}
```

### `GET /admin_pins.php?action=export_csv`
Export all pins as CSV file. Returns `Content-Type: text/csv` with a timestamped filename.
