-- ---------------------------------------------------------------------
-- RevisionHub database schema (MySQL 5.7+ / MariaDB 10.3+)
--
-- Import through phpMyAdmin (VistaPanel) or the mysql client:
--     mysql -u USER -p DATABASE < schema.sql
-- ---------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Administrators -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  email         VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'admin',
  active        TINYINT(1) NOT NULL DEFAULT 1,
  auth_version  INT UNSIGNED NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subjects -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(140) NOT NULL,
  description TEXT         NULL,
  icon_path   VARCHAR(2000) NULL, -- external https:// link to the icon
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subjects_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics (optional grouping inside a subject) --------------------------
CREATE TABLE IF NOT EXISTS topics (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id INT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL,
  slug       VARCHAR(140) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_topics_subject_slug (subject_id, slug),
  KEY idx_topics_subject (subject_id),
  CONSTRAINT fk_topics_subject FOREIGN KEY (subject_id)
    REFERENCES subjects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes (rich-text web pages) -----------------------------------------
CREATE TABLE IF NOT EXISTS notes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id   INT UNSIGNED NOT NULL,
  topic_id     INT UNSIGNED NULL,
  author_id    INT UNSIGNED NULL,
  title        VARCHAR(200) NOT NULL,
  slug         VARCHAR(220) NOT NULL,
  content      MEDIUMTEXT   NOT NULL,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  published_at DATETIME     NULL,
  deleted_at   DATETIME NULL,
  version      INT UNSIGNED NOT NULL DEFAULT 1,
  level VARCHAR(20) NOT NULL DEFAULT '',
  syllabus_code VARCHAR(30) NOT NULL DEFAULT '',
  seo_title VARCHAR(160) NOT NULL DEFAULT '',
  seo_description VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_notes_slug (slug),
  KEY idx_notes_subject (subject_id),
  KEY idx_notes_topic (topic_id),
  KEY idx_notes_status (status),
  KEY idx_notes_deleted (deleted_at),
  CONSTRAINT fk_notes_subject FOREIGN KEY (subject_id)
    REFERENCES subjects (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_notes_topic FOREIGN KEY (topic_id)
    REFERENCES topics (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_notes_author FOREIGN KEY (author_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Past papers (external PDF links only — no files stored on disk) -----
CREATE TABLE IF NOT EXISTS past_papers (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id   INT UNSIGNED NOT NULL,
  title        VARCHAR(200) NOT NULL,
  slug         VARCHAR(220) NOT NULL,
  year         SMALLINT UNSIGNED NULL,
  exam_board   VARCHAR(100) NULL,
  paper_number VARCHAR(50)  NULL,
  description  TEXT         NULL,
  file_path    VARCHAR(2000) NOT NULL, -- external https:// link to the PDF
  file_size    INT UNSIGNED NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  level VARCHAR(20) NOT NULL DEFAULT '',
  syllabus_code VARCHAR(30) NOT NULL DEFAULT '',
  exam_session VARCHAR(50) NOT NULL DEFAULT '',
  seo_title VARCHAR(160) NOT NULL DEFAULT '',
  seo_description VARCHAR(255) NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_papers_slug (slug),
  KEY idx_papers_subject (subject_id),
  KEY idx_papers_year (year),
  KEY idx_papers_deleted (deleted_at),
  CONSTRAINT fk_papers_subject FOREIGN KEY (subject_id)
    REFERENCES subjects (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Image links referenced by notes and subject icons (external URLs) ----
CREATE TABLE IF NOT EXISTS images (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path          VARCHAR(2000) NOT NULL, -- external https:// link to the image
  original_name VARCHAR(255) NULL,
  mime          VARCHAR(80)  NOT NULL, -- 'external' for links
  size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
  kind          ENUM('note','subject_icon') NOT NULL DEFAULT 'note',
  note_id       INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_images_path (path(191)),
  KEY idx_images_kind (kind),
  CONSTRAINT fk_images_note FOREIGN KEY (note_id)
    REFERENCES notes (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS note_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  note_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  snapshot MEDIUMTEXT NOT NULL,
  reason VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_revision_note (note_id, id),
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS note_autosaves (
  user_id INT UNSIGNED NOT NULL,
  draft_key VARCHAR(64) NOT NULL,
  note_id INT UNSIGNED NULL,
  payload MEDIUMTEXT NOT NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, draft_key),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admin_activity (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  actor_name VARCHAR(60) NOT NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  entity_id INT UNSIGNED NULL,
  summary VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
