<?php
class Job {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════
       Public listing — GET /api/jobs
       ═══════════════════════════════════════════ */
    public function getAll(array $filters = []): array {
        $sql = "SELECT j.*, c.company_name, c.logo
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                WHERE j.is_active = 1
                  AND j.status    = 'active'
                  AND c.status    = 'approved'";

        $params = [];

        if (!empty($filters['type'])) {
            $sql     .= ' AND j.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['mode'])) {
            $sql     .= ' AND j.mode = ?';
            $params[] = $filters['mode'];
        }
        if (!empty($filters['field'])) {
            $sql     .= ' AND j.field = ?';
            $params[] = $filters['field'];
        }
        if (!empty($filters['search'])) {
            $sql     .= ' AND (j.title LIKE ? OR j.description LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $sql .= ' ORDER BY j.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['skills_required'] = json_decode($row['skills_required'] ?? '[]', true);
        }

        return $rows;
    }

    /* ═══════════════════════════════════════════
       Single job — GET /api/jobs/:id
       (used by EditJobPage to load job data)
       ═══════════════════════════════════════════ */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT j.*,
                    c.company_name,
                    c.logo,
                    c.description AS company_desc,
                    c.website,
                    c.industry
             FROM jobs j
             JOIN companies c ON j.company_id = c.id
             WHERE j.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['skills_required'] = json_decode($row['skills_required'] ?? '[]', true);
        }

        return $row ?: null;
    }

    /* ═══════════════════════════════════════════
       Create — POST /api/company/jobs
       Columns: title, description, type, mode,
                field, skills_required, salary,
                location, deadline
       ═══════════════════════════════════════════ */
    public function create(int $companyId, array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO jobs
               (company_id, title, description, type, mode,
                field, skills_required, salary, location, deadline)
             VALUES
               (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $companyId,
            $data['title'],
            $data['description'],
            $data['type']                        ?? 'full-time',
            $data['mode']                        ?? 'remote',
            $data['field']                       ?? '',
            json_encode($data['skills_required'] ?? []),
            $data['salary']                      ?? null,
            $data['location']                    ?? '',
            $data['deadline']                    ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /* ═══════════════════════════════════════════
       Update — PUT /api/company/jobs/:id
       Only updates fields that are passed in $data
       ═══════════════════════════════════════════ */
    public function update(int $id, array $data): bool {
        $allowed = [
            'title', 'description', 'type', 'mode',
            'field', 'location', 'deadline',
            'salary',       // single $/month column
            'is_active',
            'status',
        ];

        $fields = [];
        $values = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = ?";
                $values[] = $data[$col];
            }
        }

        // skills_required is stored as JSON
        if (array_key_exists('skills_required', $data)) {
            $fields[] = 'skills_required = ?';
            $values[] = json_encode($data['skills_required']);
        }

        if (empty($fields)) return false;

        $values[] = $id;

        $stmt = $this->db->prepare(
            'UPDATE jobs SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );

        return $stmt->execute($values);
    }

    /* ═══════════════════════════════════════════
       Delete — DELETE /api/company/jobs/:id
       ═══════════════════════════════════════════ */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM jobs WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /* ═══════════════════════════════════════════
       Company's own jobs — GET /api/company/jobs
       Includes applicant count per job
       ═══════════════════════════════════════════ */
    public function getByCompany(int $companyId): array {
        $stmt = $this->db->prepare(
            'SELECT j.*,
                    (SELECT COUNT(*)
                     FROM applications
                     WHERE job_id = j.id) AS applicants_count
             FROM jobs j
             WHERE j.company_id = ?
             ORDER BY j.created_at DESC'
        );
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['skills_required'] = json_decode($row['skills_required'] ?? '[]', true);
        }

        return $rows;
    }

    /* ═══════════════════════════════════════════
       Helpers used by AdminController
       ═══════════════════════════════════════════ */
    public function count(): int {
        return (int) $this->db->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare('UPDATE jobs SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }
}