-- Existing installations: back up the database, then import this file ONCE.
-- Fresh installations already have these fields in schema.sql.
ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE notes ADD COLUMN deleted_at DATETIME NULL,
  ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN level VARCHAR(20) NOT NULL DEFAULT '',
  ADD COLUMN syllabus_code VARCHAR(30) NOT NULL DEFAULT '',
  ADD COLUMN seo_title VARCHAR(160) NOT NULL DEFAULT '',
  ADD COLUMN seo_description VARCHAR(255) NOT NULL DEFAULT '',
  ADD KEY idx_notes_deleted (deleted_at);
ALTER TABLE past_papers ADD COLUMN deleted_at DATETIME NULL,
  ADD COLUMN level VARCHAR(20) NOT NULL DEFAULT '',
  ADD COLUMN syllabus_code VARCHAR(30) NOT NULL DEFAULT '',
  ADD COLUMN exam_session VARCHAR(50) NOT NULL DEFAULT '',
  ADD COLUMN seo_title VARCHAR(160) NOT NULL DEFAULT '',
  ADD COLUMN seo_description VARCHAR(255) NOT NULL DEFAULT '',
  ADD KEY idx_papers_deleted (deleted_at);

CREATE TABLE note_revisions (
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
CREATE TABLE note_autosaves (
  user_id INT UNSIGNED NOT NULL,
  draft_key VARCHAR(64) NOT NULL,
  note_id INT UNSIGNED NULL,
  payload MEDIUMTEXT NOT NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, draft_key),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE admin_activity (
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
