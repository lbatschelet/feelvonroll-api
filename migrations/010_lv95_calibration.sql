-- =============================================================================
-- 010_lv95_calibration.sql
-- Adds LV95 calibration storage and optional station LV95 coordinates.
-- =============================================================================

-- Store calibration history (keep all versions, one active at a time).
CREATE TABLE IF NOT EXISTS lv95_calibrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_by_admin_user_id INT NULL,
  label VARCHAR(128) DEFAULT NULL,

  -- Points as entered:
  -- [
  --   { "world": { "x":..., "z":... }, "lv95": { "e":..., "n":... } },
  --   ...
  -- ]
  points JSON NOT NULL,

  -- Computed transform parameters:
  -- { "scale":..., "rotation_rad":..., "tx":..., "ty":... }
  -- where [e,n] = scale*R*[x,z] + [tx,ty]
  params JSON NOT NULL,

  -- Optional diagnostics (residuals, rms, etc).
  diagnostics JSON DEFAULT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_lv95_calibrations_active (is_active),
  INDEX idx_lv95_calibrations_created_by (created_by_admin_user_id),
  FOREIGN KEY (created_by_admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Optional LV95 coordinates for stations (usually derived from target_x/target_z).
ALTER TABLE stations
  ADD COLUMN lv95_e DOUBLE DEFAULT NULL,
  ADD COLUMN lv95_n DOUBLE DEFAULT NULL;

CREATE INDEX idx_stations_lv95 ON stations (lv95_e, lv95_n);

