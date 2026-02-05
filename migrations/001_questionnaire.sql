-- Migration: questionnaire schema + translations
-- Apply manually on existing databases.

ALTER TABLE pins
  ADD COLUMN group_key VARCHAR(64) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS languages (
  lang VARCHAR(8) PRIMARY KEY,
  label VARCHAR(64) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1
);

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

CREATE TABLE IF NOT EXISTS translations (
  translation_key VARCHAR(128) NOT NULL,
  lang VARCHAR(8) NOT NULL,
  text VARCHAR(255) NOT NULL,
  PRIMARY KEY (translation_key, lang),
  INDEX idx_translations_lang (lang)
);

ALTER TABLE pin_reasons
  ADD COLUMN question_key VARCHAR(64) NOT NULL DEFAULT 'reasons',
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (pin_id, question_key, reason_key);

-- Seed languages
INSERT IGNORE INTO languages (lang, label, enabled) VALUES
  ('de', 'Deutsch', 1),
  ('en', 'English', 1);

-- Seed questions
INSERT IGNORE INTO questions (question_key, type, required, sort, is_active, config) VALUES
  ('wellbeing', 'slider', 1, 10, 1, JSON_OBJECT('min', 1, 'max', 10, 'step', 1, 'default', 5)),
  ('reasons', 'multi', 0, 20, 1, JSON_OBJECT('allow_multiple', 1)),
  ('group', 'multi', 0, 30, 1, JSON_OBJECT('allow_multiple', 0)),
  ('note', 'text', 0, 40, 1, JSON_OBJECT('rows', 3));

-- Seed options (reasons)
INSERT IGNORE INTO question_options (question_key, option_key, sort, is_active) VALUES
  ('reasons', 'licht', 10, 1),
  ('reasons', 'ruhe', 20, 1),
  ('reasons', 'laerm', 30, 1),
  ('reasons', 'aussicht', 40, 1),
  ('reasons', 'sicherheit', 50, 1),
  ('reasons', 'sauberkeit', 60, 1),
  ('reasons', 'layout', 70, 1),
  ('reasons', 'temperatur', 80, 1);

-- Seed options (group)
INSERT IGNORE INTO question_options (question_key, option_key, sort, is_active) VALUES
  ('group', 'staff', 10, 1),
  ('group', 'studi', 20, 1),
  ('group', 'dozierend', 30, 1),
  ('group', 'other', 40, 1);

-- Seed translations (questions)
INSERT IGNORE INTO translations (translation_key, lang, text) VALUES
  ('questions.wellbeing.label', 'de', 'Wie fühlst du dich hier?'),
  ('questions.wellbeing.legend_low', 'de', 'Gar nicht wohl'),
  ('questions.wellbeing.legend_high', 'de', 'Sehr wohl'),
  ('questions.reasons.label', 'de', 'Was trägt zu deinem (Un-)Wohlbefinden bei?'),
  ('questions.group.label', 'de', 'Zu welcher Gruppe gehörst du?'),
  ('questions.note.label', 'de', 'Anmerkung'),
  ('questions.wellbeing.label', 'en', 'How do you feel here?'),
  ('questions.wellbeing.legend_low', 'en', 'Not good at all'),
  ('questions.wellbeing.legend_high', 'en', 'Very good'),
  ('questions.reasons.label', 'en', 'What contributes to your (un)wellbeing?'),
  ('questions.group.label', 'en', 'Which group do you belong to?'),
  ('questions.note.label', 'en', 'Note');

-- Seed translations (reasons)
INSERT IGNORE INTO translations (translation_key, lang, text) VALUES
  ('options.reasons.licht', 'de', 'Licht'),
  ('options.reasons.ruhe', 'de', 'Ruhe'),
  ('options.reasons.laerm', 'de', 'Lärm'),
  ('options.reasons.aussicht', 'de', 'Aussicht'),
  ('options.reasons.sicherheit', 'de', 'Sicherheit'),
  ('options.reasons.sauberkeit', 'de', 'Sauberkeit'),
  ('options.reasons.layout', 'de', 'Layout'),
  ('options.reasons.temperatur', 'de', 'Temperatur'),
  ('options.reasons.licht', 'en', 'Light'),
  ('options.reasons.ruhe', 'en', 'Quiet'),
  ('options.reasons.laerm', 'en', 'Noise'),
  ('options.reasons.aussicht', 'en', 'View'),
  ('options.reasons.sicherheit', 'en', 'Safety'),
  ('options.reasons.sauberkeit', 'en', 'Cleanliness'),
  ('options.reasons.layout', 'en', 'Layout'),
  ('options.reasons.temperatur', 'en', 'Temperature');

-- Seed translations (group)
INSERT IGNORE INTO translations (translation_key, lang, text) VALUES
  ('options.group.staff', 'de', 'Staff'),
  ('options.group.studi', 'de', 'Studi'),
  ('options.group.dozierend', 'de', 'Dozierend'),
  ('options.group.other', 'de', 'Andere'),
  ('options.group.staff', 'en', 'Staff'),
  ('options.group.studi', 'en', 'Student'),
  ('options.group.dozierend', 'en', 'Lecturer'),
  ('options.group.other', 'en', 'Other');

-- Optional: remove legacy reasons table if it exists and is no longer used
-- DROP TABLE IF EXISTS reasons;
