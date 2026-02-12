-- =============================================================================
-- feelvonRoll API – Complete Database Schema
-- =============================================================================
-- Consolidated from migrations 001–008.
-- Run this on a blank database to get the full schema + seed data.
-- =============================================================================

-- ── Pins ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS pins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  floor_index INT NOT NULL,
  position_x FLOAT NOT NULL,
  position_y FLOAT NOT NULL,
  position_z FLOAT NOT NULL,
  wellbeing DECIMAL(5,2) NOT NULL,
  note TEXT NOT NULL,
  group_key VARCHAR(64) DEFAULT NULL,
  station_key VARCHAR(64) DEFAULT NULL,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Languages ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS languages (
  lang VARCHAR(8) PRIMARY KEY,
  label VARCHAR(64) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1
);

-- ── Translations ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS translations (
  translation_key VARCHAR(128) NOT NULL,
  lang VARCHAR(8) NOT NULL,
  text VARCHAR(255) NOT NULL,
  PRIMARY KEY (translation_key, lang),
  INDEX idx_translations_lang (lang)
);

-- ── Questions Library ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS questions (
  question_key VARCHAR(64) PRIMARY KEY,
  type VARCHAR(16) NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  config JSON DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS question_options (
  question_key VARCHAR(64) NOT NULL,
  option_key VARCHAR(64) NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (question_key, option_key),
  FOREIGN KEY (question_key) REFERENCES questions(question_key) ON DELETE CASCADE
);

-- ── Pin Reasons (legacy multi-choice answers) ───────────────────────────────

CREATE TABLE IF NOT EXISTS pin_reasons (
  pin_id INT NOT NULL,
  question_key VARCHAR(64) NOT NULL DEFAULT 'reasons',
  reason_key VARCHAR(64) NOT NULL,
  PRIMARY KEY (pin_id, question_key, reason_key),
  FOREIGN KEY (pin_id) REFERENCES pins(id) ON DELETE CASCADE,
  FOREIGN KEY (question_key, reason_key)
    REFERENCES question_options(question_key, option_key) ON DELETE CASCADE
);

-- ── Pin Answers (generic answer storage) ────────────────────────────────────

CREATE TABLE IF NOT EXISTS pin_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pin_id INT NOT NULL,
  question_key VARCHAR(64) NOT NULL,
  answer_text TEXT,
  answer_numeric DECIMAL(5,2),
  FOREIGN KEY (pin_id) REFERENCES pins(id) ON DELETE CASCADE,
  UNIQUE KEY uq_pin_question (pin_id, question_key)
);

-- ── Questionnaires ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS questionnaires (
  id INT AUTO_INCREMENT PRIMARY KEY,
  questionnaire_key VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_questionnaire_key (questionnaire_key)
);

CREATE TABLE IF NOT EXISTS questionnaire_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  questionnaire_id INT NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  mode ENUM('fixed', 'pool') NOT NULL DEFAULT 'fixed',
  pool_count INT NOT NULL DEFAULT 1,
  required TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS questionnaire_slot_questions (
  slot_id INT NOT NULL,
  question_key VARCHAR(64) NOT NULL,
  PRIMARY KEY (slot_id, question_key),
  FOREIGN KEY (slot_id) REFERENCES questionnaire_slots(id) ON DELETE CASCADE,
  FOREIGN KEY (question_key) REFERENCES questions(question_key) ON DELETE CASCADE
);

-- ── Stations (QR-code locations) ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS stations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  station_key VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  floor_index INT NOT NULL,
  camera_x FLOAT NOT NULL,
  camera_y FLOAT NOT NULL,
  camera_z FLOAT NOT NULL,
  target_x FLOAT NOT NULL,
  target_y FLOAT NOT NULL,
  target_z FLOAT NOT NULL,
  questionnaire_id INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_station_key (station_key),
  FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE SET NULL
);

-- ── Admin Users ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  first_name VARCHAR(120) DEFAULT NULL,
  last_name VARCHAR(120) DEFAULT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) DEFAULT NULL,
  must_set_password TINYINT(1) NOT NULL DEFAULT 1,
  reset_token_hash VARCHAR(64) DEFAULT NULL,
  reset_token_expires TIMESTAMP NULL DEFAULT NULL,
  token_version INT NOT NULL DEFAULT 1,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Admin Audit Log ─────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(64) NOT NULL,
  target VARCHAR(128) NOT NULL,
  payload JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_audit_user (user_id)
);

-- ── Content Pages ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS content_pages (
  page_key VARCHAR(64) NOT NULL,
  lang VARCHAR(8) NOT NULL,
  body TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (page_key, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================================
-- Seed Data
-- =============================================================================

-- ── Languages ───────────────────────────────────────────────────────────────

INSERT IGNORE INTO languages (lang, label, enabled) VALUES
  ('de', 'Deutsch', 1),
  ('en', 'English', 1);

-- ── Questions (slider config: 0–1 percent range) ────────────────────────────

INSERT IGNORE INTO questions (question_key, type, required, sort, is_active, config) VALUES
  ('wellbeing', 'slider', 1, 10, 1, JSON_OBJECT('min', 0, 'max', 1, 'step', 0.01, 'default', 0.5, 'use_for_color', true)),
  ('reasons',   'multi',  0, 20, 1, JSON_OBJECT('allow_multiple', 1)),
  ('group',     'multi',  0, 30, 1, JSON_OBJECT('allow_multiple', 0)),
  ('note',      'text',   0, 40, 1, JSON_OBJECT('rows', 3));

-- ── Question Options ────────────────────────────────────────────────────────

INSERT IGNORE INTO question_options (question_key, option_key, sort, is_active) VALUES
  ('reasons', 'licht',       10, 1),
  ('reasons', 'ruhe',        20, 1),
  ('reasons', 'laerm',       30, 1),
  ('reasons', 'aussicht',    40, 1),
  ('reasons', 'sicherheit',  50, 1),
  ('reasons', 'sauberkeit',  60, 1),
  ('reasons', 'layout',      70, 1),
  ('reasons', 'temperatur',  80, 1),
  ('group',   'staff',       10, 1),
  ('group',   'studi',       20, 1),
  ('group',   'dozierend',   30, 1),
  ('group',   'other',       40, 1);

-- ── Translations (DE) ──────────────────────────────────────────────────────

INSERT IGNORE INTO translations (translation_key, lang, text) VALUES
  ('questions.wellbeing.label',      'de', 'Wie fühlst du dich hier?'),
  ('questions.wellbeing.legend_low', 'de', 'Gar nicht wohl'),
  ('questions.wellbeing.legend_high','de', 'Sehr wohl'),
  ('questions.reasons.label',        'de', 'Was trägt zu deinem (Un-)Wohlbefinden bei?'),
  ('questions.group.label',          'de', 'Zu welcher Gruppe gehörst du?'),
  ('questions.note.label',           'de', 'Anmerkung'),
  ('options.reasons.licht',          'de', 'Licht'),
  ('options.reasons.ruhe',           'de', 'Ruhe'),
  ('options.reasons.laerm',          'de', 'Lärm'),
  ('options.reasons.aussicht',       'de', 'Aussicht'),
  ('options.reasons.sicherheit',     'de', 'Sicherheit'),
  ('options.reasons.sauberkeit',     'de', 'Sauberkeit'),
  ('options.reasons.layout',         'de', 'Layout'),
  ('options.reasons.temperatur',     'de', 'Temperatur'),
  ('options.group.staff',            'de', 'Staff'),
  ('options.group.studi',            'de', 'Studi'),
  ('options.group.dozierend',        'de', 'Dozierend'),
  ('options.group.other',            'de', 'Andere');

-- ── Translations (EN) ──────────────────────────────────────────────────────

INSERT IGNORE INTO translations (translation_key, lang, text) VALUES
  ('questions.wellbeing.label',      'en', 'How do you feel here?'),
  ('questions.wellbeing.legend_low', 'en', 'Not good at all'),
  ('questions.wellbeing.legend_high','en', 'Very good'),
  ('questions.reasons.label',        'en', 'What contributes to your (un)wellbeing?'),
  ('questions.group.label',          'en', 'Which group do you belong to?'),
  ('questions.note.label',           'en', 'Note'),
  ('options.reasons.licht',          'en', 'Light'),
  ('options.reasons.ruhe',           'en', 'Quiet'),
  ('options.reasons.laerm',          'en', 'Noise'),
  ('options.reasons.aussicht',       'en', 'View'),
  ('options.reasons.sicherheit',     'en', 'Safety'),
  ('options.reasons.sauberkeit',     'en', 'Cleanliness'),
  ('options.reasons.layout',         'en', 'Layout'),
  ('options.reasons.temperatur',     'en', 'Temperature'),
  ('options.group.staff',            'en', 'Staff'),
  ('options.group.studi',            'en', 'Student'),
  ('options.group.dozierend',        'en', 'Lecturer'),
  ('options.group.other',            'en', 'Other');

-- ── Default Questionnaire ───────────────────────────────────────────────────

INSERT IGNORE INTO questionnaires (questionnaire_key, name, description, is_default, is_active)
VALUES ('default', 'Default', 'Standard questionnaire with all active questions', 1, 1);

SET @default_qid = (SELECT id FROM questionnaires WHERE questionnaire_key = 'default');

INSERT IGNORE INTO questionnaire_slots (questionnaire_id, sort, mode, pool_count, required) VALUES
  (@default_qid, 10, 'fixed', 1, 1),
  (@default_qid, 20, 'fixed', 1, 0),
  (@default_qid, 30, 'fixed', 1, 0),
  (@default_qid, 40, 'fixed', 1, 0);

INSERT IGNORE INTO questionnaire_slot_questions (slot_id, question_key)
SELECT qs.id, q.question_key
FROM questionnaire_slots qs
JOIN questions q ON q.sort = qs.sort AND q.is_active = 1
WHERE qs.questionnaire_id = @default_qid;

-- ── About Page Content ──────────────────────────────────────────────────────

INSERT INTO content_pages (page_key, lang, body) VALUES
('about', 'de', '# Willkommen bei feelvonRoll\n\n![PHBern Logo](https://www.phbern.ch/themes/custom/phbern/logo.svg)\n\n**feelvonRoll** ist ein interaktives Tool zur Erfassung des raeumlichen Wohlbefindens im Von-Roll-Areal der PHBern. Das Projekt entstand im Rahmen von [RealTransform](https://sustainability.uzh.ch/de/forschung-lehre/forschung/realtransform.html), einer hochschuluebergreifenden Initiative fuer nachhaltige Campustransformation.\n\n---\n\n## So funktioniert es\n\n1. **Stockwerk waehlen** -- Navigiere mit den Stockwerk-Buttons rechts zum gewuenschten Geschoss.\n2. **Pin setzen** -- Klicke auf *+ Pin* und platziere einen Punkt auf dem 3D-Modell.\n3. **Fragen beantworten** -- Bewerte dein Wohlbefinden, waehle Gruende und hinterlasse optional eine Anmerkung.\n4. **Absenden** -- Dein Beitrag wird nach einer kurzen Pruefung sichtbar.\n\n## Farbmodi\n\n- **Wohlbefinden** -- Pins werden von rot (unwohl) bis gruen (sehr wohl) eingefaerbt.\n- **Gruende** -- Pins werden nach dem haeufigsten gewaehlten Grund eingefaerbt.\n\n## Datenschutz\n\nAlle Eingaben werden **anonym** gespeichert. Es werden keine personenbezogenen Daten erhoben.\n\n---\n\n> *Wie fuehlen sich Menschen in den Raeumen, in denen sie lernen und arbeiten?*\n\nEin Projekt der [PHBern](https://www.phbern.ch) -- Entwickelt von [Lukas Batschelet](https://lukasbatschelet.ch)')
ON DUPLICATE KEY UPDATE body = VALUES(body);

INSERT INTO content_pages (page_key, lang, body) VALUES
('about', 'en', '# Welcome to feelvonRoll\n\n![PHBern Logo](https://www.phbern.ch/themes/custom/phbern/logo.svg)\n\n**feelvonRoll** is an interactive tool for capturing spatial wellbeing within the Von Roll campus of PHBern. The project was created as part of [RealTransform](https://sustainability.uzh.ch/de/forschung-lehre/forschung/realtransform.html), a cross-university initiative for sustainable campus transformation.\n\n---\n\n## How it works\n\n1. **Select a floor** -- Use the floor buttons on the right to navigate to the desired storey.\n2. **Place a pin** -- Click *+ Pin* and place a point on the 3D model.\n3. **Answer questions** -- Rate your wellbeing, choose contributing factors, and optionally leave a note.\n4. **Submit** -- Your contribution will become visible after a brief review.\n\n## Colour modes\n\n- **Wellbeing** -- Pins are coloured from red (unwell) to green (very well).\n- **Reasons** -- Pins are coloured by the most frequently selected reason.\n\n## Privacy\n\nAll entries are stored **anonymously**. No personal data is collected.\n\n---\n\n> *How do people feel in the spaces where they learn and work?*\n\nA project by [PHBern](https://www.phbern.ch) -- Developed by [Lukas Batschelet](https://lukasbatschelet.ch)')
ON DUPLICATE KEY UPDATE body = VALUES(body);
