-- Digit Chat 0.3.0: module-owned schema. Install explicitly from /admin/chat.
CREATE TABLE IF NOT EXISTS chat_projects (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,
 context_text TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_chat_project_owner (id, account_id),
 KEY idx_chat_project_owner (account_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_conversations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 project_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(120) NOT NULL,
 github_repo VARCHAR(159) NULL DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_chat_convo_scope (id, account_id, project_id),
 KEY idx_chat_convo_listing (account_id, project_id, updated_at),
 CONSTRAINT fk_chat_convo_project FOREIGN KEY (project_id, account_id)
   REFERENCES chat_projects (id, account_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 project_id BIGINT UNSIGNED NOT NULL,
 conversation_id BIGINT UNSIGNED NOT NULL,
 role ENUM('user','digit') NOT NULL,
 content TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_chat_message_scope (account_id, project_id, conversation_id, id),
 CONSTRAINT fk_chat_message_convo FOREIGN KEY (conversation_id, account_id, project_id)
   REFERENCES chat_conversations (id, account_id, project_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_deliveries (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 project_id BIGINT UNSIGNED NOT NULL,
 conversation_id BIGINT UNSIGNED NOT NULL,
 session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 display_name VARCHAR(127) NOT NULL,
 message TEXT NOT NULL,
 transport_text TEXT NOT NULL,
 state ENUM('queued','processing','complete') NOT NULL DEFAULT 'queued',
 result ENUM('ok','error') DEFAULT NULL,
 reply_text TEXT DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_chat_delivery_claim (state, created_at),
 KEY idx_chat_delivery_session (session_hash, id),
 CONSTRAINT fk_chat_delivery_convo FOREIGN KEY (conversation_id, account_id, project_id)
   REFERENCES chat_conversations (id, account_id, project_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_schema (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 schema_version VARCHAR(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO chat_schema (id, schema_version) VALUES (1, '1.1.0');
