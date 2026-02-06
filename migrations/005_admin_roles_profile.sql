-- Migration: add admin role + split name fields
ALTER TABLE admin_users
  ADD COLUMN first_name VARCHAR(120) DEFAULT NULL,
  ADD COLUMN last_name VARCHAR(120) DEFAULT NULL,
  ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;

UPDATE admin_users
SET is_admin = 1;

UPDATE admin_users
SET first_name = IFNULL(NULLIF(TRIM(SUBSTRING_INDEX(name, ' ', 1)), ''), name),
    last_name = IFNULL(NULLIF(TRIM(SUBSTRING(name, LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 2)), ''), '')
WHERE name IS NOT NULL
  AND (first_name IS NULL OR last_name IS NULL);
