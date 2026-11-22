-- Add email addresses to existing users for password reset functionality
-- Run this on the Render PostgreSQL database

UPDATE users SET email = 'kiplamon29@gmail.com' WHERE username = 'admin' AND (email IS NULL OR email = '');
UPDATE users SET email = 'john@greenvalley.ac.ke' WHERE username = 'teacher' AND (email IS NULL OR email = '');
