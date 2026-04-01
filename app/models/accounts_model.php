<?php

/**
 * Accounts Model
 *
 * Handles all database interactions related to user accounts.
 */
class accounts_model extends model
{
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
        $role = ((int)$data['user_level'] === 9) ? 'admin' : 'user';

        return $this->query(
            "INSERT INTO accounts 
            (username, password_hash, display_name, user_level, role, email_address) 
            VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['display_name'],
                (int)$data['user_level'],
                $role,
                $data['email_address']
            ]
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
