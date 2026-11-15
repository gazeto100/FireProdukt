<?php
declare(strict_types=1);

/**
 * Прости миграции на базата. Нова инсталация получава schema.sql с последната версия
 * и settings.schema_version = SCHEMA_VERSION. При качване на нова версия на кода върху стара база
 * липсващите промени се прилагат автоматично при първата заявка.
 */
const SCHEMA_VERSION = 3;

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
        if ($v < 3) {
            // вид запитване (config = от конфигуратора, project = по проект за хотели) и качени файлове
            if (!db_all("SHOW COLUMNS FROM inquiries LIKE 'kind'")) {
                db()->exec("ALTER TABLE inquiries ADD COLUMN kind VARCHAR(16) NOT NULL DEFAULT 'config' AFTER public_code, ADD KEY idx_kind (kind)");
            }
            db()->exec("CREATE TABLE IF NOT EXISTS uploads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, inquiry_id INT UNSIGNED NOT NULL, stored_name VARCHAR(64) NOT NULL,
                original_name VARCHAR(190) NOT NULL, mime VARCHAR(64) NOT NULL, ext VARCHAR(8) NOT NULL, size INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_inq (inquiry_id),
                CONSTRAINT fk_upl_inq FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        save_setting('schema_version', (string)SCHEMA_VERSION);
    } catch (Throwable $e) {
        error_log('migration: ' . $e->getMessage());
    }
}
