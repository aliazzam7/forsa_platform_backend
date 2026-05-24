<?php
class Company {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM companies WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id, company_name, email, description, industry, website, logo,
                    location, phone_number, linkedin_url, twitter_url, company_size,
                    status, is_approved, is_banned, created_at
             FROM companies WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            'INSERT INTO companies (company_name, email, password, description, industry, website, location)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['company_name'],
            $data['email'],
            $hash,
            $data['description'] ?? '',
            $data['industry']    ?? '',
            $data['website']     ?? '',
            $data['location']    ?? '',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getAll(): array {
        return $this->db->query(
            'SELECT c.id, c.company_name, c.email, c.industry, c.location,
                    c.is_approved, c.is_banned, c.created_at,
                    COUNT(j.id) AS jobs_count,
                    COALESCE(c.status, CASE
                        WHEN c.is_banned = 1 THEN "banned"
                        WHEN c.is_approved = 1 THEN "approved"
                        ELSE "pending"
                    END) AS status
             FROM companies c
             LEFT JOIN jobs j ON j.company_id = c.id
             GROUP BY c.id
             ORDER BY c.created_at DESC'
        )->fetchAll();
    }

    public function approve(int $id, int $status): bool {
        $stmt = $this->db->prepare('UPDATE companies SET is_approved = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function ban(int $id, int $status): bool {
        $stmt = $this->db->prepare('UPDATE companies SET is_banned = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM companies WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function update(int $id, array $data): bool {
        $allowed = [
            'company_name', 'description', 'industry', 'website',
            'location', 'logo',
            'email', 'phone_number', 'linkedin_url', 'twitter_url',
            'company_size', 'status',
        ];
        $fields = [];
        $values = [];
        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        $values[] = $id;
        $stmt = $this->db->prepare(
            'UPDATE companies SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        return $stmt->execute($values);
    }

    public function count(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM companies')->fetchColumn();
    }

    public function setStatus(int $id, int $is_approved, int $is_banned, string $status = 'pending'): void {
        $stmt = $this->db->prepare(
            'UPDATE companies SET is_approved = ?, is_banned = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$is_approved, $is_banned, $status, $id]);
    }
}