-- Migration: Add email column to users table for password reset functionality
-- Safe to run on existing databases (idempotent)

-- Add email column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(120) UNIQUE;

-- Set email addresses for existing users
UPDATE users SET email = 'admin@greenvalley.sc.ke' WHERE username = 'admin' AND (email IS NULL OR email = '');
UPDATE users SET email = 'jane@greenvalley.sc.ke' WHERE username = 'jane' AND (email IS NULL OR email = '');
