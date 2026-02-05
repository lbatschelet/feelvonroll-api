-- Migration: add token_version to admin_users
ALTER TABLE admin_users
  ADD COLUMN token_version INT NOT NULL DEFAULT 1;
