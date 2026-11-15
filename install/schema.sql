-- FireProdukt: структура на базата (MySQL 5.7+ / MariaDB 10.3+, utf8mb4)

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  hint VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- shape: box (правоъгълен съд, вкл. мангали), hex (шестоъгълник; width_cm = диаметър през ъглите), grill (скара на стойка)
CREATE TABLE IF NOT EXISTS models (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  shape VARCHAR(16) NOT NULL DEFAULT 'box',
  width_cm DECIMAL(6,1) NOT NULL,
  depth_cm DECIMAL(6,1) NOT NULL,
  height_cm DECIMAL(6,1) NOT NULL,
  max_chars INT NOT NULL DEFAULT 20,
  price_min DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_max DECIMAL(10,2) NOT NULL DEFAULT 0,
  sheet_area_m2 DECIMAL(6,3) NULL,
  description VARCHAR(500) NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_models_type FOREIGN KEY (type_id) REFERENCES product_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS option_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  hint VARCHAR(255) NULL,
  multi TINYINT(1) NOT NULL DEFAULT 0,
  required TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- price_type: add (добавя сума) или factor (умножава базовата цена на модела; price_min/price_max = коефициент)
-- render: JSON с параметри за изгледа и изчисленията (виж README)
CREATE TABLE IF NOT EXISTS options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(100) NOT NULL,
  hint VARCHAR(255) NULL,
  price_type VARCHAR(8) NOT NULL DEFAULT 'add',
  price_min DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_max DECIMAL(10,2) NOT NULL DEFAULT 0,
  weight_kg DECIMAL(6,2) NOT NULL DEFAULT 0,
  render TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_group_code (group_id, code),
  CONSTRAINT fk_options_group FOREIGN KEY (group_id) REFERENCES option_groups(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Съвместимост: опцията важи за модела само ако има ред тук. NULL цени = общата цена на опцията.
CREATE TABLE IF NOT EXISTS model_options (
  model_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  price_min DECIMAL(10,2) NULL,
  price_max DECIMAL(10,2) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (model_id, option_id),
  CONSTRAINT fk_mo_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE,
  CONSTRAINT fk_mo_option FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_code VARCHAR(16) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(16) NOT NULL DEFAULT 'new',
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  email VARCHAR(190) NULL,
  city VARCHAR(100) NULL,
  note TEXT NULL,
  consent_at DATETIME NOT NULL,
  model_id INT UNSIGNED NULL,
  quantity INT NOT NULL DEFAULT 1,
  config_json MEDIUMTEXT NOT NULL,
  svg MEDIUMTEXT NULL,
  price_min DECIMAL(10,2) NULL,
  price_max DECIMAL(10,2) NULL,
  weight_kg DECIMAL(7,2) NULL,
  utm_source VARCHAR(190) NULL,
  utm_medium VARCHAR(190) NULL,
  utm_campaign VARCHAR(190) NULL,
  utm_term VARCHAR(190) NULL,
  utm_content VARCHAR(190) NULL,
  referrer VARCHAR(500) NULL,
  landing_url VARCHAR(500) NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  anonymized_at DATETIME NULL,
  KEY idx_status (status),
  KEY idx_created (created_at),
  KEY idx_ip_time (ip_hash, created_at),
  CONSTRAINT fk_inq_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiry_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inq (inquiry_id),
  CONSTRAINT fk_notes_inq FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Дневник на изпратените имейли (успешни и неуспешни)
CREATE TABLE IF NOT EXISTS mail_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  transport VARCHAR(16) NOT NULL,
  ok TINYINT(1) NOT NULL,
  error TEXT NULL,
  KEY idx_inq (inquiry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Отхвърлени запитвания: само дата, причина и брой (без лични данни)
CREATE TABLE IF NOT EXISTS rejected_log (
  day DATE NOT NULL,
  reason VARCHAR(16) NOT NULL,
  cnt INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (day, reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Събития за вътрешната статистика (без лични данни)
CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  day DATE NOT NULL,
  type VARCHAR(32) NOT NULL,
  session_id CHAR(32) NOT NULL,
  model_id INT UNSIGNED NULL,
  KEY idx_day_type (day, type),
  KEY idx_type_model (type, model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
