CREATE TABLE IF NOT EXISTS example_schema (
    id TINYINT UNSIGNED NOT NULL,
    schema_version VARCHAR(64) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO example_schema (id, schema_version)
VALUES (1, '2.0.0')
ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version);
