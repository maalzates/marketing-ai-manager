-- The test suite runs against MySQL, not sqlite, so it needs its own schema.
-- Runs once, when the data volume is first created. `make test-db` recreates it
-- idempotently on volumes that already exist.
CREATE DATABASE IF NOT EXISTS marketing_ai_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON marketing_ai_testing.* TO 'marketing_ai'@'%';

FLUSH PRIVILEGES;
