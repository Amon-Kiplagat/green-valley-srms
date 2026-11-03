-- ============================================================================
-- Migration: Reset tokens table (SHA-256 hashed tokens)
-- Date: 2026-10
--
-- Creates the reset_tokens table for secure password reset.
-- Tokens are hashed with SHA-256 before storage, so a database compromise
-- cannot be used to replay tokens. Single-use enforced by `used_at` timestamp.
--
-- HOW TO RUN:
--   psql -U mole -d green_valley -f migration_2026_10_reset_tokens.sql
-- ============================================================================

DROP TABLE IF EXISTS reset_tokens CASCADE;

CREATE TABLE reset_tokens (
    reset_token_id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_reset_tokens_user_id
    ON reset_tokens(user_id);
