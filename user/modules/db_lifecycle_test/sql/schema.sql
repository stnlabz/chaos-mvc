CREATE TABLE db_lifecycle_test_records (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    note VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO db_lifecycle_test_records (id, note)
VALUES (1, 'ChAoS MVC database lifecycle qualification');
