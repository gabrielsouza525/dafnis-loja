-- Cursos próprios (feitos no Rise 360, Storyline ou outra ferramenta de autoria) exportados em SCORM 1.2
-- e abertos dentro da loja, com o andamento e o tempo de estudo de cada participante.

-- Cada envio do pacote é uma versão. Quem já começou continua na versão em que começou.
CREATE TABLE course_packages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  version SMALLINT UNSIGNED NOT NULL COMMENT '1, 2, 3... dentro do curso',
  is_current TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'versão que os novos participantes recebem',
  title VARCHAR(190) NULL COMMENT 'título do imsmanifest.xml',
  directory VARCHAR(120) NOT NULL COMMENT 'pasta dentro de storage/scorm',
  launch_path VARCHAR(255) NOT NULL COMMENT 'arquivo de abertura, relativo à pasta',
  mastery_score TINYINT UNSIGNED NULL COMMENT 'nota mínima do manifesto (adlcp:masteryscore)',
  file_count INT UNSIGNED NOT NULL DEFAULT 0,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  original_name VARCHAR(190) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_packages_version (course_id, version),
  KEY idx_packages_current (course_id, is_current),
  CONSTRAINT fk_packages_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
  CONSTRAINT fk_packages_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O que o curso gravou pela API do SCORM, por participante e versão.
CREATE TABLE scorm_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NOT NULL,
  lesson_status VARCHAR(20) NOT NULL DEFAULT 'not attempted' COMMENT 'cmi.core.lesson_status',
  score_raw DECIMAL(6,2) NULL,
  score_max DECIMAL(6,2) NULL,
  lesson_location VARCHAR(255) NULL,
  suspend_data MEDIUMTEXT NULL COMMENT 'onde o aluno parou (formato de cada ferramenta)',
  interactions MEDIUMTEXT NULL COMMENT 'JSON com as respostas enviadas pelo curso (provas e exercícios)',
  progress TINYINT UNSIGNED NULL COMMENT 'percentual de lições concluídas, quando a ferramenta informa',
  passed_at DATETIME NULL COMMENT 'primeira vez com aprovação ou conclusão',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempts (enrollment_id, package_id),
  CONSTRAINT fk_attempts_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE CASCADE,
  CONSTRAINT fk_attempts_package FOREIGN KEY (package_id) REFERENCES course_packages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de acesso: cada vez que o participante abre o curso.
CREATE TABLE study_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_active_at DATETIME NULL COMMENT 'último aviso de presença com o participante estudando',
  active_seconds INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'tempo com o curso aberto e em uso, medido pela loja',
  course_seconds INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'cmi.core.session_time informado pelo curso',
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  KEY idx_sessions_attempt (attempt_id, started_at),
  CONSTRAINT fk_sessions_attempt FOREIGN KEY (attempt_id) REFERENCES scorm_attempts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
