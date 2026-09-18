INSERT INTO example_records (id, title, body, is_active)
VALUES
    (
        1,
        'Canonical Example Record',
        'This record is supplied by the Example Module and is restored by Data Reset.',
        1
    ),
    (
        2,
        'Inactive Example Record',
        'This canonical record demonstrates an inactive module-owned record.',
        0
    )
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    body = VALUES(body),
    is_active = VALUES(is_active);
