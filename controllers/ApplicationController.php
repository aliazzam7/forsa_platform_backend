<?php
require_once __DIR__ . '/../models/Application.php';

class ApplicationController {

    // POST /api/applications
    public static function apply(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        $body = !empty($_FILES['cv'])
        ? $_POST
        : (json_decode(file_get_contents('php://input'), true) ?? []);
        $err  = Validator::required($body, ['job_id']);
        if ($err) Response::error($err);

        $appModel = new Application();
        if ($appModel->hasApplied($payload['id'], (int)$body['job_id'])) {
            Response::error('You have already applied to this job.');
        }

        // Handle CV upload if sent as file
        $cvPath = '';
        if (!empty($_FILES['cv'])) {
            $cvPath = FileHelper::upload($_FILES['cv'], 'cvs') ?? '';
        }

        $id = $appModel->apply($payload['id'], (int)$body['job_id'], $body['cover_letter'] ?? '', $cvPath);
        Response::success(['application_id' => $id], 'Application submitted.', 201);
    }

    // GET /api/student/applications
    public static function myApplications(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'student');

        $appModel = new Application();
        Response::success($appModel->getByStudent($payload['id']));
    }

    // GET /api/company/applications/:jobId
    public static function jobApplicants(int $jobId): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $appModel = new Application();
        Response::success($appModel->getByJob($jobId));
    }

    // PUT /api/applications/:id/status
    public static function updateStatus(int $id): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = $body['status'] ?? '';

        if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
            Response::error('Invalid status value.');
        }

        $appModel = new Application();
        $appModel->updateStatus($id, $status);
        // new code part for email sent
        if ($status === 'accepted' || $status === 'rejected') {
     
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT u.name AS student_name, u.email AS student_email, j.title AS job_title, c.company_name, c.email AS company_email FROM applications a JOIN users u ON u.id = a.student_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE a.id = ?");
        $stmt->execute([$id]);
        $info = $stmt->fetch();

        if ($info) {
            EmailHelper::sendApplicationStatusEmail(
                $info['student_email'],
                $info['student_name'],
                $info['job_title'],
                $info['company_name'],
                $info['company_email'], 
                $status
            );
        }
    }
        Response::success(null, 'Application status updated.');
    }
}