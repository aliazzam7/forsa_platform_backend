<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Application.php';

class AdminController {

    private static function requireAdmin(): array {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'admin');
        return $payload;
    }

    // GET /api/admin/dashboard
    public static function dashboard(): void {
        self::requireAdmin();
        $userModel = new User();
        $compModel = new Company();
        $jobModel  = new Job();
        $appModel  = new Application();

        $db = Database::getInstance()->getConnection();

        $recentStudents = $db->query("SELECT id, name, email, created_at, IF(is_banned,'banned','active') AS status FROM users WHERE role='student' ORDER BY created_at DESC LIMIT 5")->fetchAll();
        $recentCompanies = $db->query("SELECT id, company_name, email, created_at, is_approved, status FROM companies ORDER BY created_at DESC LIMIT 5")->fetchAll();
        // Monthly registrations - last 6 months
            $studentMonthly = $db->query("
                SELECT DATE_FORMAT(created_at, '%b') AS month, COUNT(*) AS count
                FROM users
                WHERE role = 'student'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY YEAR(created_at), MONTH(created_at)
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();

            $companyMonthly = $db->query("
                SELECT DATE_FORMAT(created_at, '%b') AS month, COUNT(*) AS count
                FROM companies
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY YEAR(created_at), MONTH(created_at)
                ORDER BY YEAR(created_at), MONTH(created_at)
            ")->fetchAll();

        Response::success([
            'stats' => [
                'totalStudents'     => $userModel->countByRole('student'),
                'totalCompanies'    => $compModel->count(),
                'totalJobs'         => $jobModel->count(),
                'totalApplications' => $appModel->count(),
            ],
            'recentStudents'  => $recentStudents,
            'recentCompanies' => $recentCompanies,
            'monthlyStudents' => $studentMonthly,   
            'monthlyCompanies' => $companyMonthly,  
        ]);
    }

    // GET /api/admin/users
    public static function getUsers(): void {
        self::requireAdmin();
        $model = new User();
        Response::success($model->getAll());
    }

    // PUT /api/admin/users/:id/ban
    public static function banUser(int $id): void {
        self::requireAdmin();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = isset($body['ban']) && $body['ban'] ? 1 : 0;
        $model  = new User();
        $model->ban($id, $status);
        Response::success(null, $status ? 'User banned.' : 'User unbanned.');
    }

    // DELETE /api/admin/users/:id
    public static function deleteUser(int $id): void {
        self::requireAdmin();
        $model = new User();
        $model->delete($id);
        Response::success(null, 'User deleted.');
    }

    // GET /api/admin/companies
    public static function getCompanies(): void {
        self::requireAdmin();
        $model = new Company();
        Response::success($model->getAll());
    }

    // PUT /api/admin/companies/:id/approve
    public static function approveCompany(int $id): void {
        self::requireAdmin();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = isset($body['approve']) && $body['approve'] ? 1 : 0;
        $model  = new Company();
        $model->approve($id, $status);
        Response::success(null, $status ? 'Company approved.' : 'Company unapproved.');
    }

    // PUT /api/admin/companies/:id/ban
    public static function banCompany(int $id): void {
        self::requireAdmin();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = isset($body['ban']) && $body['ban'] ? 1 : 0;
        $model  = new Company();
        $model->ban($id, $status);
        Response::success(null, $status ? 'Company banned.' : 'Company unbanned.');
    }

    // DELETE /api/admin/companies/:id
    public static function deleteCompany(int $id): void {
        self::requireAdmin();
        $model = new Company();
        $model->delete($id);
        Response::success(null, 'Company deleted.');
    }

    // GET /api/admin/jobs
    public static function getJobs(): void {
        self::requireAdmin();
        $model = new Job();
        Response::success($model->getAll());
    }

    // DELETE /api/admin/jobs/:id
    public static function deleteJob(int $id): void {
        self::requireAdmin();
        $model = new Job();
        $model->delete($id);
        Response::success(null, 'Job deleted.');
    }

    // ──────────────────────────────────────────────────────────
    //  PUT /api/admin/users/:id   — edit user info (name/email)
    // ──────────────────────────────────────────────────────────
    public static function updateUser(int $id): void {
        self::requireAdmin();
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $model = new User();
 
        $allowed = ['name', 'email'];
        $data    = array_intersect_key($body, array_flip($allowed));
 
        if (empty($data)) {
            Response::error('No valid fields provided.', 422);
            return;
        }
 
        // Check email uniqueness if changing email
        if (!empty($data['email'])) {
            $existing = $model->findByEmail($data['email']);
            if ($existing && $existing['id'] !== $id) {
                Response::error('Email already in use.', 409);
                return;
            }
        }
 
        $model->updateUser($id, $data);
        $updated = $model->findById($id);
        Response::success($updated, 'User updated successfully.');
    }
 
    // ──────────────────────────────────────────────────────────
    //  PUT /api/admin/jobs/:id/status  — set spam / deleted / active
    // ──────────────────────────────────────────────────────────
    public static function updateJobStatus(int $id): void {
        self::requireAdmin();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $body['status'] ?? '';
 
        $allowed = ['active', 'spam', 'deleted'];
        if (!in_array($status, $allowed)) {
            Response::error('Invalid status. Must be: active, spam, deleted.', 422);
            return;
        }
 
        $model = new Job();
        $model->updateStatus($id, $status);
        Response::success(['status' => $status], 'Job status updated.');
    }
 
    // ──────────────────────────────────────────────────────────
    //  PUT /api/admin/companies/:id/status — set pending/approved/banned/rejected
    // ──────────────────────────────────────────────────────────
    public static function updateCompanyStatus(int $id): void {
        self::requireAdmin();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $body['status'] ?? '';
 
        $allowed = ['pending', 'approved', 'banned', 'rejected'];
        if (!in_array($status, $allowed)) {
            Response::error('Invalid status.', 422);
            return;
        }
 
        $model = new Company();
 
        // Map status → is_approved / is_banned columns
        $is_approved = 0;
        $is_banned   = 0;
        if ($status === 'approved')  $is_approved = 1;
        if ($status === 'banned')    $is_banned   = 1;
 
        $model->setStatus($id, $is_approved, $is_banned, $status);
        // new code part for email
        if ($status === 'approved' || $status === 'rejected') {
        $company = $model->findById($id);
        if ($company) {
            EmailHelper::sendCompanyStatusEmail(
                $company['email'],
                $company['company_name'],
                $status
            );
        }
    }
        Response::success(['status' => $status], 'Company status updated.');
    }
 
    // ──────────────────────────────────────────────────────────
    //  GET /api/admin/profile  — get admin profile
    // ──────────────────────────────────────────────────────────
    public static function getProfile(): void {
        $payload = self::requireAdmin();
        $model   = new User();
        $user    = $model->findById($payload['id']);
        if (!$user) {
            Response::error('Admin not found.', 404);
            return;
        }
        unset($user['password']);
        Response::success($user);
    }
 
    // ──────────────────────────────────────────────────────────
    //  PUT /api/admin/profile  — update name & email & avatar
    // ──────────────────────────────────────────────────────────
    public static function updateProfile(): void {
        $payload = self::requireAdmin();
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $model   = new User();
 
        $data = [];
        if (!empty($body['name']))   $data['name']   = trim($body['name']);
        if (!empty($body['email']))  $data['email']  = trim($body['email']);
        if (!empty($body['avatar'])) $data['avatar'] = trim($body['avatar']);
 
        if (empty($data)) {
            Response::error('Nothing to update.', 422);
            return;
        }
 
        // Email uniqueness check
        if (!empty($data['email'])) {
            $existing = $model->findByEmail($data['email']);
            if ($existing && (int)$existing['id'] !== (int)$payload['id']) {
                Response::error('Email already in use.', 409);
                return;
            }
        }
 
        $model->updateProfile($payload['id'], $data);
        $updated = $model->findById($payload['id']);
        unset($updated['password']);
        Response::success($updated, 'Profile updated successfully.');
    }
 
    // ──────────────────────────────────────────────────────────
    //  PUT /api/admin/change-password
    // ──────────────────────────────────────────────────────────
    public static function changePassword(): void {
        $payload = self::requireAdmin();
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
 
        $current = $body['current_password'] ?? '';
        $new     = $body['new_password']     ?? '';
 
        if (!$current || !$new) {
            Response::error('current_password and new_password are required.', 422);
            return;
        }
        if (strlen($new) < 8) {
            Response::error('New password must be at least 8 characters.', 422);
            return;
        }
 
        // Fetch admin full row (with password hash)
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$payload['id']]);
        $admin = $stmt->fetch();
 
        if (!$admin || !password_verify($current, $admin['password'])) {
            Response::error('Current password is incorrect.', 401);
            return;
        }
 
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $payload['id']]);
 
        Response::success(null, 'Password changed successfully.');
    }
    
}