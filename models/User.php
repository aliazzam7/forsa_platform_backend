<?php
class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT id, name, email, role, avatar, is_banned, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $name, string $email, string $password, string $role = 'student'): int {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, $role]);
        return (int)$this->db->lastInsertId();
    }

    public function getAll(): array {
        return $this->db->query('SELECT id, name, email, role, avatar, is_banned, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    }

    public function ban(int $id, int $status): bool {
        $stmt = $this->db->prepare('UPDATE users SET is_banned = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function updateProfile(int $id, array $data): bool {
        $fields = [];
        $values = [];
        foreach (['name', 'avatar'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        $values[] = $id;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($values);
    }

    public function countByRole(string $role): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $stmt->execute([$role]);
        return (int)$stmt->fetchColumn();
    }
    
     // Update name and/or email for a user (used by admin edit)
    public function updateUser(int $id, array $data): bool {
        $allowed = ['name', 'email'];
        $fields  = [];
        $values  = [];
        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        $values[] = $id;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        return $stmt->execute($values);
    }

}