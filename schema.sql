CREATE TABLE IF NOT EXISTS pins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  floor_index INT NOT NULL,
  position_x FLOAT NOT NULL,
  position_y FLOAT NOT NULL,
  position_z FLOAT NOT NULL,
  wellbeing INT NOT NULL,
  note TEXT NOT NULL,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reasons (
  reason_key VARCHAR(64) PRIMARY KEY,
  label VARCHAR(128) NOT NULL
);

CREATE TABLE IF NOT EXISTS pin_reasons (
  pin_id INT NOT NULL,
  reason_key VARCHAR(64) NOT NULL,
  PRIMARY KEY (pin_id, reason_key),
  FOREIGN KEY (pin_id) REFERENCES pins(id) ON DELETE CASCADE,
  FOREIGN KEY (reason_key) REFERENCES reasons(reason_key) ON DELETE CASCADE
);

INSERT IGNORE INTO reasons (reason_key, label) VALUES
  ('licht', 'Licht'),
  ('ruhe', 'Ruhe'),
  ('laerm', 'Lärm'),
  ('aussicht', 'Aussicht'),
  ('sicherheit', 'Sicherheit'),
  ('sauberkeit', 'Sauberkeit'),
  ('layout', 'Layout'),
  ('temperatur', 'Temperatur');
