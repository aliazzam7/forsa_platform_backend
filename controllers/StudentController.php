<?php
require_once __DIR__ . '/../models/User.php';

class StudentController {

    // GET /api/student/profile
    public static function getProfile(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('
            SELECT u.id, u.name, u.email, u.avatar, u.created_at,
                   sp.bio, sp.skills, sp.experience, sp.cv_path,
                   sp.university, sp.major, sp.graduation_year,
                   sp.phone, sp.location,
                   sp.website, sp.linkedin, sp.github
            FROM users u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            WHERE u.id = ?
        ');

        $stmt->execute([$payload['id']]);
        $profile = $stmt->fetch();

        if (!$profile) Response::error('Profile not found.', 404);

        $profile['skills']     = json_decode($profile['skills']     ?? '[]', true);
        $profile['experience'] = json_decode($profile['experience'] ?? '[]', true);

        Response::success($profile);
    }

    // PUT /api/student/profile
    public static function updateProfile(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $db   = Database::getInstance()->getConnection();

        // Update users table
        if (!empty($body['name'])) {
            $db->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$body['name'], $payload['id']]);
        }

        // Upsert student_profiles
        $check = $db->prepare('SELECT id FROM student_profiles WHERE user_id = ?');
        $check->execute([$payload['id']]);

        $skillsJson = isset($body['skills'])     ? json_encode($body['skills'])     : null;
        $expJson    = isset($body['experience']) ? json_encode($body['experience']) : null;

        if ($check->fetch()) {
            $stmt = $db->prepare('
                UPDATE student_profiles SET
                    bio             = COALESCE(?, bio),
                    skills          = COALESCE(?, skills),
                    experience      = COALESCE(?, experience),
                    university      = COALESCE(?, university),
                    major           = COALESCE(?, major),
                    graduation_year = COALESCE(?, graduation_year),
                    phone           = COALESCE(?, phone),
                    location        = COALESCE(?, location),
                    website         = COALESCE(?, website),
                    linkedin        = COALESCE(?, linkedin),
                    github          = COALESCE(?, github)
                WHERE user_id = ?
            ');
            
            $stmt->execute([
                $body['bio']             ?? null,
                $skillsJson,
                $expJson,
                $body['university']      ?? null,
                $body['major']           ?? null,
                $body['graduation_year'] ?? null,
                $body['phone']           ?? null,
                $body['location']        ?? null,
                $body['website']         ?? null,  
                $body['linkedin']        ?? null,  
                $body['github']          ?? null,  
                $payload['id'],
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO student_profiles
                    (user_id, bio, skills, experience, university, major,
                     graduation_year, phone, location, website, linkedin, github)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $payload['id'],
                $body['bio']             ?? '',
                $skillsJson              ?? '[]',
                $expJson                 ?? '[]',
                $body['university']      ?? '',
                $body['major']           ?? '',
                $body['graduation_year'] ?? null,
                $body['phone']           ?? '',
                $body['location']        ?? '',
                $body['website']         ?? null,  
                $body['linkedin']        ?? null,  
                $body['github']          ?? null,  
            ]);
        }

        Response::success(null, 'Profile updated successfully.');
    }

    // POST /api/student/upload-cv
    public static function uploadCV(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        if (empty($_FILES['cv'])) Response::error('No file uploaded.');

        $path = FileHelper::upload($_FILES['cv'], 'cvs');
        if (!$path) Response::error('Invalid file type. Only PDF allowed.');

        $db = Database::getInstance()->getConnection();

        $check = $db->prepare('SELECT id FROM student_profiles WHERE user_id = ?');
        $check->execute([$payload['id']]);
        if ($check->fetch()) {
            $db->prepare('UPDATE student_profiles SET cv_path = ? WHERE user_id = ?')->execute([$path, $payload['id']]);
        } else {
            $db->prepare('INSERT INTO student_profiles (user_id, cv_path) VALUES (?, ?)')->execute([$payload['id'], $path]);
        }

        Response::success(['cv_path' => $path], 'CV uploaded successfully.');
    }

    // POST /api/student/upload-avatar
    public static function uploadAvatar(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        if (empty($_FILES['avatar'])) Response::error('No file uploaded.');

        $file    = $_FILES['avatar'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            Response::error('Only image files allowed (jpg, png, gif, webp).');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('Image must be under 5MB.');
        }

        $path = FileHelper::upload($file, 'avatars');
        if (!$path) Response::error('Failed to upload image.');

        $db = Database::getInstance()->getConnection();
        $db->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$path, $payload['id']]);

        Response::success(['avatar' => $path], 'Avatar updated successfully.');
    }

    // GET /api/student/dashboard
    public static function dashboard(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT COUNT(*) FROM applications WHERE student_id = ?');
        $stmt->execute([$payload['id']]);
        $totalApps = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'accepted'");
        $stmt->execute([$payload['id']]);
        $accepted = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'pending'");
        $stmt->execute([$payload['id']]);
        $pending = (int)$stmt->fetchColumn();

        $recentJobs = $db->query('
            SELECT j.id, j.title, j.type, j.mode, j.location,
                   j.skills_required, j.deadline,
                   c.company_name, c.logo
            FROM jobs j
            JOIN companies c ON j.company_id = c.id
            WHERE j.is_active = 1 AND j.status = \'active\' AND c.status = \'approved\'
            ORDER BY j.created_at DESC
            LIMIT 5
        ')->fetchAll();

        // Decode skills_required لكل job
        foreach ($recentJobs as &$job) {
            $job['skills_required'] = json_decode($job['skills_required'] ?? '[]', true);
        }

        $stmt = $db->prepare('
            SELECT a.status, a.applied_at, j.title, c.company_name
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            JOIN companies c ON j.company_id = c.id
            WHERE a.student_id = ?
            ORDER BY a.applied_at DESC
            LIMIT 5
        ');
        $stmt->execute([$payload['id']]);
        $recentApps = $stmt->fetchAll();

        Response::success([
            'stats' => [
                'total_applications' => $totalApps,
                'accepted'           => $accepted,
                'pending'            => $pending,
            ],
            'recent_jobs'         => $recentJobs,
            'recent_applications' => $recentApps,
        ]);
    }

    // PUT /api/student/change-password
    public static function changePassword(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $err  = Validator::required($body, ['current_password', 'new_password']);
        if ($err) Response::error($err);
        if (!Validator::minLength($body['new_password'], 6))
            Response::error('Password must be at least 6 characters.');

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$payload['id']]);
        $row  = $stmt->fetch();
        if (!$row || !password_verify($body['current_password'], $row['password']))
            Response::error('Current password is incorrect.');

        $hash = password_hash($body['new_password'], PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')
           ->execute([$hash, $payload['id']]);
        Response::success(null, 'Password changed successfully.');
    }
}