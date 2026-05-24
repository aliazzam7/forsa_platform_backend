<?php
require_once __DIR__ . '/../models/Company.php';

class CompanyController {

    // GET /api/company/profile
    public static function getProfile(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $model   = new Company();
        $company = $model->findById($payload['id']);
        if (!$company) Response::error('Company not found.', 404);

        Response::success($company);
    }

    // PUT /api/company/profile
    public static function updateProfile(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // Map frontend keys → DB columns
        $mapped = [];
        if (isset($body['company_name'])) $mapped['company_name'] = $body['company_name'];
        if (isset($body['industry']))     $mapped['industry']     = $body['industry'];
        if (isset($body['size']))         $mapped['company_size'] = $body['size'];
        if (isset($body['location']))     $mapped['location']     = $body['location'];
        if (isset($body['about']))        $mapped['description']  = $body['about'];
        if (isset($body['email']))        $mapped['email']        = $body['email'];
        if (isset($body['phone']))        $mapped['phone_number'] = $body['phone'];
        if (isset($body['website']))      $mapped['website']      = $body['website'];
        if (isset($body['linkedin']))     $mapped['linkedin_url'] = $body['linkedin'];
        if (isset($body['twitter']))      $mapped['twitter_url']  = $body['twitter'];

        $model = new Company();
        $model->update($payload['id'], $mapped);

        $updated = $model->findById($payload['id']);
        Response::success($updated, 'Company profile updated.');
    }

    // POST /api/company/upload-logo
    public static function uploadLogo(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        if (empty($_FILES['logo'])) Response::error('No file uploaded.');
        $path = FileHelper::upload($_FILES['logo'], 'logos');
        if (!$path) Response::error('Invalid file type.');

        $model = new Company();
        $model->update($payload['id'], ['logo' => $path]);

        Response::success(['logo' => $path], 'Logo uploaded.');
    }

    // GET /api/company/dashboard
    public static function dashboard(): void {
        $payload   = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $db        = Database::getInstance()->getConnection();
        $companyId = $payload['id'];

        // ── total jobs ──
        $stmt = $db->prepare('SELECT COUNT(*) FROM jobs WHERE company_id = ?');
        $stmt->execute([$companyId]);
        $totalJobs = (int)$stmt->fetchColumn();

        // ── total applicants ──
        $stmt = $db->prepare('
            SELECT COUNT(*)
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            WHERE j.company_id = ?
        ');
        $stmt->execute([$companyId]);
        $totalApplicants = (int)$stmt->fetchColumn();

        // ── accepted ──
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            WHERE j.company_id = ? AND a.status = 'accepted'
        ");
        $stmt->execute([$companyId]);
        $accepted = (int)$stmt->fetchColumn();

        // ── pending ──
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            WHERE j.company_id = ? AND a.status = 'pending'
        ");
        $stmt->execute([$companyId]);
        $pending = (int)$stmt->fetchColumn();

        // ── rejected ──
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            WHERE j.company_id = ? AND a.status = 'rejected'
        ");
        $stmt->execute([$companyId]);
        $rejected = (int)$stmt->fetchColumn();

        // ── recent applicants (last 5) ──
        $stmt = $db->prepare("
            SELECT
                a.id,
                u.name        AS student_name,
                u.email       AS student_email,
                j.title       AS job_title,
                a.status,
                a.applied_at  AS applied_at
            FROM applications a
            JOIN jobs  j ON a.job_id     = j.id
            JOIN users u ON a.student_id = u.id
            WHERE j.company_id = ?
            ORDER BY a.applied_at DESC
            LIMIT 5
        ");
        $stmt->execute([$companyId]);
        $recentApplicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── chart data — last 6 months ──
        $stmt = $db->prepare("
            SELECT
                DATE_FORMAT(j.created_at, '%b') AS month,
                YEAR(j.created_at)              AS yr,
                MONTH(j.created_at)             AS mo,
                COUNT(DISTINCT j.id)            AS jobs,
                COUNT(a.id)                     AS apps
            FROM jobs j
            LEFT JOIN applications a ON a.job_id = j.id
            WHERE j.company_id = ?
              AND j.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY YEAR(j.created_at), MONTH(j.created_at)
            ORDER BY YEAR(j.created_at), MONTH(j.created_at)
        ");
        $stmt->execute([$companyId]);
        $rawChart = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chartData = [];
        foreach ($rawChart as $row) {
            $chartData[] = [
                'month' => $row['month'],
                'jobs'  => (int)$row['jobs'],
                'apps'  => (int)$row['apps'],
            ];
        }

        Response::success([
            'stats' => [
                'total_jobs'       => $totalJobs,
                'total_applicants' => $totalApplicants,
                'accepted'         => $accepted,
                'pending'          => $pending,
                'rejected'         => $rejected,
            ],
            'recent_applicants' => $recentApplicants,
            'chart_data'        => $chartData,
        ]);
    }

    // PUT /api/company/change-password
    public static function changePassword(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $err  = Validator::required($body, ['current_password', 'new_password']);
        if ($err) Response::error($err);
        if (!Validator::minLength($body['new_password'], 6))
            Response::error('Password must be at least 6 characters.');

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT password FROM companies WHERE id = ?');
        $stmt->execute([$payload['id']]);
        $row  = $stmt->fetch();
        if (!$row || !password_verify($body['current_password'], $row['password']))
            Response::error('Current password is incorrect.');

        $hash = password_hash($body['new_password'], PASSWORD_BCRYPT);
        $db->prepare('UPDATE companies SET password = ? WHERE id = ?')
           ->execute([$hash, $payload['id']]);

        Response::success(null, 'Password changed successfully.');
    }
}