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
All admin requests require `X-Admin-Token` header.

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
