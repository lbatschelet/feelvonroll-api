-- Migration: store slider values as percent (0-100) with two decimals
ALTER TABLE pins
  MODIFY wellbeing DECIMAL(5,2) NOT NULL;

UPDATE pins
SET wellbeing = ROUND((wellbeing / 10) * 100, 2);

UPDATE questions
SET config = JSON_SET(
  IFNULL(config, JSON_OBJECT()),
  '$.min', 0,
  '$.max', 1,
  '$.step', 0.01,
  '$.default', 0.5
)
WHERE type = 'slider';

UPDATE questions
SET config = JSON_SET(IFNULL(config, JSON_OBJECT()), '$.use_for_color', true)
WHERE question_key = 'wellbeing';
