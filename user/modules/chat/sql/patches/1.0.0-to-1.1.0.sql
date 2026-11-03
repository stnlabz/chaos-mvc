ALTER TABLE chat_conversations ADD COLUMN github_repo VARCHAR(159) NULL DEFAULT NULL AFTER title;
CREATE TABLE chat_schema (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, schema_version VARCHAR(32) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO chat_schema (id, schema_version) VALUES (1, '1.1.0');
