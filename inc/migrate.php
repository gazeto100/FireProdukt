<?php
declare(strict_types=1);

/**
 * Прости миграции на базата. Нова инсталация получава schema.sql с последната версия
 * и settings.schema_version = SCHEMA_VERSION. При качване на нова версия на кода върху стара база
 * липсващите промени се прилагат автоматично при първата заявка.
 */
const SCHEMA_VERSION = 2;

function run_migrations(): void
{
    $v = (int)setting('schema_version', '1');
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    try {
        if ($v < 2) {
            $cols = db_all("SHOW COLUMNS FROM inquiries LIKE 'anonymized_at'");
            if (!$cols) {
                db()->exec('ALTER TABLE inquiries ADD COLUMN anonymized_at DATETIME NULL');
            }
            db()->exec("CREATE TABLE IF NOT EXISTS rejected_log (
                day DATE NOT NULL, reason VARCHAR(16) NOT NULL, cnt INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (day, reason)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        save_setting('schema_version', (string)SCHEMA_VERSION);
    } catch (Throwable $e) {
        error_log('migration: ' . $e->getMessage());
    }
}
