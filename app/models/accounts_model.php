<?php

class accounts_model extends model
{

    public function authenticate($username, $password) {
    // Fetch the user record by username
    $row = $this->fetch("SELECT * FROM accounts WHERE username = :username LIMIT 1", [
        'username' => $username
    ]);

    if ($row) {
        // Compare the plain-text input to the hash in the 'password_hash' column
        if (password_verify($password, $row['password_hash'])) {
            return $row;
        }
    }
    return false;
    }

    public function get_all()
    {
        return $this->fetchAll(
            "SELECT id, username, user_level, display_name
             FROM accounts
             ORDER BY id ASC"
        );
    }


    public function create($username, $password, $name, $level, $email)
    {
        $this->query(
        "INSERT INTO accounts 
        (username, password_hash, display_name, user_level, email_address) 
        VALUES 
        (:u, :p, :n, :l, :e)",
        [
            'u' => $username,
            'p' => password_hash($password, PASSWORD_DEFAULT),
            'n' => $name,
            'l' => (int)$level,
            'e' => $email // Added missing email_address
        ]
        );
    }


    public function change_password($id, $password)
    {
        $this->query(
            "UPDATE accounts
             SET password_hash = :p
             WHERE id = :id",
            [
                'p'  => password_hash($password, PASSWORD_DEFAULT),
                'id' => (int)$id
            ]
        );
    }


    public function delete($id)
{
    // Execute the deletion using an explicit integer
    $this->query("DELETE FROM accounts WHERE id = :id", ['id' => (int)$id]);

    // Verify it is gone by trying to fetch it
    $check = $this->fetch("SELECT id FROM accounts WHERE id = :id", ['id' => (int)$id]);
    
    // If fetch returns false, the record was successfully removed
    return $check === false;
}
}
