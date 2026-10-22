<?php

/**
 * Accounts Model
 *
 * Handles all database interactions related to user accounts.
 */
class accounts_model extends model
{
    private static bool $accessSchemaReady = false;

    /**
     * Authenticate user credentials
     *
     * @param string $username
     * @param string $password
     * @return array|false
     */
    public function authenticate($username, $password)
    {
        $row = $this->fetch(
            "SELECT * FROM accounts WHERE username = ? LIMIT 1",
            [$username]
        );

        if ($row && password_verify($password, $row['password_hash'])) {
            return $row;
        }

        return false;
    }

    /**
     * Retrieve all accounts
     *
     * @return array
     */
    public function get_all()
    {
        return $this->fetchAll(
            "SELECT id, username, email_address, user_level, role, display_name
             FROM accounts
             ORDER BY id ASC"
        );
    }

    /**
     * Create a new account
     *
     * @param array $data
     * @return bool
     */
    public function create($data)
    {
        $role = (string) ($data['role'] ?? 'user');
        $levels = [
            'user' => 1,
            'developer' => 7,
            'ai' => 7,
            'admin' => 9,
        ];

        if (!isset($levels[$role])) {
            return false;
        }

        $userLevel = $levels[$role];

        return $this->query(
            "INSERT INTO accounts 
            (username, password_hash, display_name, user_level, role, email_address) 
            VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['display_name'],
                $userLevel,
                $role,
                $data['email_address']
            ]
        );
    }

    public function get_by_id(int $id)
    {
        return $this->fetch(
            'SELECT id, username, user_level, role FROM accounts WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    public function ensure_admin_module_access_schema(): void
    {
        if (self::$accessSchemaReady) {
            return;
        }

        $this->query(
            "CREATE TABLE IF NOT EXISTS `account_module_access` (
                `account_id` int(11) NOT NULL,
                `module_slug` varchar(63) NOT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`account_id`, `module_slug`),
                CONSTRAINT `account_module_access_account_fk`
                    FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        self::$accessSchemaReady = true;
    }

    public function has_admin_module_access(int $accountId, string $module): bool
    {
        if ($accountId < 1 || preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module) !== 1) {
            return false;
        }

        $this->ensure_admin_module_access_schema();
        return (bool) $this->fetch(
            'SELECT 1 FROM account_module_access WHERE account_id = ? AND module_slug = ? LIMIT 1',
            [$accountId, $module]
        );
    }

    /** @return array<int, string> */
    public function get_admin_modules_for_account(int $accountId): array
    {
        if ($accountId < 1) {
            return [];
        }

        $this->ensure_admin_module_access_schema();
        $rows = $this->fetchAll(
            'SELECT module_slug FROM account_module_access WHERE account_id = ? ORDER BY module_slug',
            [$accountId]
        );

        return array_map(
            static fn (array $row): string => (string) $row['module_slug'],
            $rows
        );
    }

    /** @return array<int, array<int, string>> */
    public function get_admin_module_access_map(): array
    {
        $this->ensure_admin_module_access_schema();
        $rows = $this->fetchAll(
            'SELECT account_id, module_slug FROM account_module_access ORDER BY account_id, module_slug'
        );
        $access = [];

        foreach ($rows as $row) {
            $access[(int) $row['account_id']][] = (string) $row['module_slug'];
        }

        return $access;
    }

    /**
     * Update one account classification and replace its complete module grant set.
     *
     * @param array<int, string> $modules
     */
    public function update_admin_access_profile(
        int $accountId,
        string $role,
        array $modules
    ): bool {
        $levels = [
            'user' => 1,
            'developer' => 7,
            'ai' => 7,
            'admin' => 9,
        ];

        if ($accountId < 1 || !isset($levels[$role])) {
            return false;
        }

        $this->ensure_admin_module_access_schema();
        $modules = in_array($role, ['developer', 'ai'], true)
            ? array_values(array_unique($modules))
            : [];

        foreach ($modules as $module) {
            if (!is_string($module)
                || preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module) !== 1
            ) {
                throw new InvalidArgumentException('Invalid Admin module identifier.');
            }
        }

        $this->db->beginTransaction();

        try {
            $updated = $this->query(
                'UPDATE accounts SET role = ?, user_level = ? WHERE id = ?',
                [$role, $levels[$role], $accountId]
            );

            if ($updated->rowCount() < 1 && $this->get_by_id($accountId) === false) {
                throw new RuntimeException('Account does not exist.');
            }

            $this->query(
                'DELETE FROM account_module_access WHERE account_id = ?',
                [$accountId]
            );

            foreach ($modules as $module) {
                $this->query(
                    'INSERT INTO account_module_access (account_id, module_slug) VALUES (?, ?)',
                    [$accountId, $module]
                );
            }

            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function revoke_admin_module_access(string $module): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module) !== 1) {
            throw new InvalidArgumentException('Invalid Admin module identifier.');
        }

        $this->ensure_admin_module_access_schema();
        $this->query(
            'DELETE FROM account_module_access WHERE module_slug = ?',
            [$module]
        );
    }

    /**
     * Change account password
     *
     * @param int $id
     * @param string $password
     * @return void
     */
    public function change_password($id, $password)
    {
        $this->query(
            "UPDATE accounts SET password_hash = ? WHERE id = ?",
            [
                password_hash($password, PASSWORD_DEFAULT),
                (int)$id
            ]
        );
    }

    /**
     * Delete an account
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $this->query(
            "DELETE FROM accounts WHERE id = ?",
            [(int)$id]
        );

        $check = $this->fetch(
            "SELECT id FROM accounts WHERE id = ?",
            [(int)$id]
        );

        return $check === false;
    }

    /**
     * Update account email address
     *
     * @param int $id
     * @param string $email
     * @return bool
     */
    public function update_email($id, $email)
    {
        return $this->query(
            "UPDATE accounts SET email_address = ? WHERE id = ?",
            [
                $email,
                (int)$id
            ]
        );
    }
}
