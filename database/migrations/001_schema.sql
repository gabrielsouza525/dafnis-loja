-- Dafnis Treinamentos — esquema inicial (MySQL 5.7+ / MariaDB 10.3+, utf8mb4)

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(20) NULL,
  document VARCHAR(14) NULL COMMENT 'CPF só com dígitos',
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remember_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  user_agent VARCHAR(255) NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_remember_selector (selector),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_token (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(30) NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts_identifier (scope, identifier, created_at),
  KEY idx_attempts_ip (scope, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  `key` VARCHAR(80) NOT NULL PRIMARY KEY,
  `value` TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  icon VARCHAR(30) NOT NULL DEFAULT 'clipboard',
  tone CHAR(1) NOT NULL DEFAULT 'a' COMMENT 'tom da capa: a, b, c ou d',
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL,
  category_id INT UNSIGNED NULL,
  nr_number TINYINT UNSIGNED NULL COMMENT 'número da NR para filtro e busca',
  code VARCHAR(20) NULL COMMENT 'rótulo exibido, ex.: NR 10, NR 31.7',
  title VARCHAR(190) NOT NULL,
  short_title VARCHAR(60) NULL COMMENT 'texto da capa para cursos sem NR',
  summary VARCHAR(400) NULL COMMENT 'descrição curta (cards, SEO)',
  description TEXT NULL COMMENT 'sobre o curso',
  audience TEXT NULL COMMENT 'para quem é',
  objectives TEXT NULL,
  syllabus TEXT NULL COMMENT 'JSON: [{"title","hours","topics"}]',
  requirements VARCHAR(255) NULL,
  hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  hours_note VARCHAR(120) NULL,
  modality ENUM('online','semipresencial','presencial') NOT NULL DEFAULT 'online',
  training_type ENUM('inicial','periodico') NOT NULL DEFAULT 'inicial',
  practical_required TINYINT(1) NOT NULL DEFAULT 0,
  practical_hours VARCHAR(160) NULL,
  practical_note VARCHAR(255) NULL,
  price DECIMAL(10,2) NULL COMMENT 'NULL = sob consulta',
  promo_price DECIMAL(10,2) NULL,
  certificate TINYINT(1) NOT NULL DEFAULT 1,
  access_days SMALLINT UNSIGNED NULL,
  access_url VARCHAR(255) NULL COMMENT 'link do curso na plataforma de ensino',
  image_path VARCHAR(255) NULL,
  icon VARCHAR(30) NOT NULL DEFAULT 'clipboard',
  keywords VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  featured_order SMALLINT UNSIGNED NULL COMMENT 'preenchido = aparece nos destaques da home',
  meta_title VARCHAR(160) NULL,
  meta_description VARCHAR(255) NULL,
  source_ref VARCHAR(160) NULL COMMENT 'origem do dado (planilha, linha)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_courses_slug (slug),
  KEY idx_courses_active (is_active, featured_order),
  KEY idx_courses_nr (nr_number),
  CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT chk_courses_price CHECK (price IS NULL OR price >= 0),
  CONSTRAINT chk_courses_promo CHECK (promo_price IS NULL OR promo_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  description VARCHAR(160) NULL,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL,
  min_subtotal DECIMAL(10,2) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  max_uses INT UNSIGNED NULL,
  uses INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupons_code (code),
  CONSTRAINT chk_coupons_value CHECK (value > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(20) NULL,
  user_id INT UNSIGNED NULL,
  status ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
  buyer_type ENUM('pf','pj') NOT NULL DEFAULT 'pf',
  buyer_name VARCHAR(120) NOT NULL,
  buyer_document VARCHAR(14) NOT NULL COMMENT 'CPF ou CNPJ só com dígitos',
  buyer_email VARCHAR(160) NOT NULL,
  buyer_phone VARCHAR(20) NOT NULL,
  company_name VARCHAR(160) NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  coupon_id INT UNSIGNED NULL,
  coupon_code VARCHAR(40) NULL,
  payment_method ENUM('pix','card','boleto') NOT NULL DEFAULT 'pix',
  gateway VARCHAR(30) NOT NULL DEFAULT 'manual',
  gateway_reference VARCHAR(120) NULL COMMENT 'preferência (checkout) no gateway',
  gateway_payment_id VARCHAR(60) NULL,
  gateway_status VARCHAR(40) NULL,
  checkout_url VARCHAR(500) NULL,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  admin_notes TEXT NULL,
  terms_accepted_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_number (number),
  KEY idx_orders_status (status, created_at),
  KEY idx_orders_user (user_id),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL,
  CONSTRAINT chk_orders_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NULL,
  course_title VARCHAR(190) NOT NULL,
  course_code VARCHAR(20) NULL,
  course_hours SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  list_price DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL,
  CONSTRAINT chk_order_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uma linha por vaga comprada. O participante pode ser o próprio comprador
-- ou alguém da equipe dele (compra para empresas).
CREATE TABLE enrollments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NULL,
  buyer_user_id INT UNSIGNED NULL,
  participant_name VARCHAR(120) NULL,
  participant_email VARCHAR(160) NULL,
  participant_document VARCHAR(14) NULL,
  status ENUM('awaiting_participant','processing','active','completed','cancelled') NOT NULL DEFAULT 'awaiting_participant',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  access_url VARCHAR(255) NULL,
  released_at DATETIME NULL,
  completed_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_enrollments_participant (participant_email, status),
  KEY idx_enrollments_buyer (buyer_user_id),
  KEY idx_enrollments_status (status, created_at),
  CONSTRAINT fk_enrollments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL,
  CONSTRAINT fk_enrollments_buyer FOREIGN KEY (buyer_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_enrollments_progress CHECK (progress <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL COMMENT 'código de verificação',
  file_path VARCHAR(255) NULL COMMENT 'PDF em storage/uploads (privado)',
  external_url VARCHAR(255) NULL COMMENT 'certificado hospedado na plataforma de ensino',
  issued_at DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_certificates_code (code),
  UNIQUE KEY uq_certificates_enrollment (enrollment_id),
  CONSTRAINT fk_certificates_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tudo o que o gateway enviou (webhook e retorno), para auditoria e idempotência.
CREATE TABLE payment_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NULL,
  gateway VARCHAR(30) NOT NULL,
  event VARCHAR(60) NOT NULL,
  reference VARCHAR(120) NULL,
  status VARCHAR(40) NULL,
  payload MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payment_events_order (order_id),
  CONSTRAINT fk_payment_events_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  subject ENUM('empresas','curso','conteudo','duvida') NOT NULL DEFAULT 'duvida',
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(20) NULL,
  company VARCHAR(160) NULL,
  participants SMALLINT UNSIGNED NULL,
  course_id INT UNSIGNED NULL,
  message TEXT NULL,
  status ENUM('new','handled') NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_contact_status (status, created_at),
  CONSTRAINT fk_contact_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  subject_type VARCHAR(40) NULL,
  subject_id INT UNSIGNED NULL,
  details VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_subject (subject_type, subject_id),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
