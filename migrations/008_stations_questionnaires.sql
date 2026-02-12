-- Migration 008: Questionnaire system + Stations + Generic answers
--
-- New tables:
--   questionnaires          - Named, reusable questionnaire configurations
--   questionnaire_slots     - Ordered positions (fixed or random pool)
--   questionnaire_slot_questions - Questions assigned to each slot
--   stations                - Physical QR-code locations with camera position
--   pin_answers             - Generic answer storage for any question type
--
-- Modified tables:
--   pins                    - Added station_key column
--
-- Backward compatibility:
--   Creates a 'default' questionnaire from existing active questions

-- ── New tables ──────────────────────────────────────────────────

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

CREATE TABLE IF NOT EXISTS pin_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin_id INT NOT NULL,
    question_key VARCHAR(64) NOT NULL,
    answer_text TEXT,
    answer_numeric DECIMAL(5,2),
    FOREIGN KEY (pin_id) REFERENCES pins(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pin_question (pin_id, question_key)
);

-- ── Extend pins table ───────────────────────────────────────────

ALTER TABLE pins ADD COLUMN station_key VARCHAR(64) DEFAULT NULL;

-- ── Backward compatibility: create default questionnaire ────────

INSERT INTO questionnaires (questionnaire_key, name, description, is_default, is_active)
VALUES ('default', 'Default', 'Auto-generated from existing active questions', 1, 1);

SET @default_qid = LAST_INSERT_ID();

-- Create one fixed slot per active question, preserving sort order and required flag
INSERT INTO questionnaire_slots (questionnaire_id, sort, mode, pool_count, required)
SELECT @default_qid, sort, 'fixed', 1, required
FROM questions
WHERE is_active = 1
ORDER BY sort;

-- Link each slot to its question
-- We match by sort order since we just inserted slots in the same order
INSERT INTO questionnaire_slot_questions (slot_id, question_key)
SELECT qs.id, q.question_key
FROM questionnaire_slots qs
JOIN questions q ON q.sort = qs.sort AND q.is_active = 1
WHERE qs.questionnaire_id = @default_qid;
