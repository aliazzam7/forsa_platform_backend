<?php
class Application {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function apply(int $studentId, int $jobId, string $coverLetter = '', string $cvPath = ''): int {
        $stmt = $this->db->prepare('INSERT INTO applications (student_id, job_id, cover_letter, cv_path) VALUES (?, ?, ?, ?)');
        $stmt->execute([$studentId, $jobId, $coverLetter, $cvPath]);
        return (int)$this->db->lastInsertId();
    }

    public function getByStudent(int $studentId): array {
        $stmt = $this->db->prepare('
            SELECT a.*, j.title, j.type, j.mode, j.location, c.company_name, c.logo
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            JOIN companies c ON j.company_id = c.id
            WHERE a.student_id = ?
            ORDER BY a.applied_at DESC
        ');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    public function getByJob(int $jobId): array {
        $stmt = $this->db->prepare('
            SELECT a.*, u.name, u.email, u.avatar, sp.bio, sp.skills, sp.university, sp.major
            FROM applications a
            JOIN users u ON a.student_id = u.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            WHERE a.job_id = ?
            ORDER BY a.applied_at DESC
        ');
        $stmt->execute([$jobId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['skills'] = json_decode($row['skills'] ?? '[]', true);
        }
        return $rows;
    }

    public function updateStatus(int $applicationId, string $status): bool {
        $stmt = $this->db->prepare('UPDATE applications SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $applicationId]);
    }

    public function hasApplied(int $studentId, int $jobId): bool {
        $stmt = $this->db->prepare('SELECT id FROM applications WHERE student_id = ? AND job_id = ?');
        $stmt->execute([$studentId, $jobId]);
        return (bool)$stmt->fetch();
    }

    public function count(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM applications WHERE id = ?');
        return $stmt->execute([$id]);
    }
}