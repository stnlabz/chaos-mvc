ALTER TABLE db_lifecycle_test_records
ADD COLUMN qualified_at TIMESTAMP NULL DEFAULT NULL;

UPDATE db_lifecycle_test_records
SET qualified_at = CURRENT_TIMESTAMP WHERE id = 1;
