<?php
require_once __DIR__ . '/../models/Job.php';

class PostJobController {

    /* ═══════════════════════════════════════════
       GET /api/company/jobs  — my jobs list
       ═══════════════════════════════════════════ */
    public static function myJobs(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $jobModel = new Job();
        Response::success($jobModel->getByCompany($payload['id']));
    }

    /* ═══════════════════════════════════════════
       POST /api/company/jobs  — create new job
       ═══════════════════════════════════════════ */
    public static function create(): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        // required fields
        $err = Validator::required($body, ['title', 'description', 'type', 'mode']);
        if ($err) Response::error($err);

        // allowed values
        $allowedTypes = ['full-time', 'part-time', 'internship'];
        $allowedModes = ['remote', 'onsite', 'hybrid'];

        $type = strtolower(trim($body['type'] ?? 'full-time'));
        $mode = strtolower(trim($body['mode'] ?? 'remote'));

        if (!in_array($type, $allowedTypes, true)) $type = 'full-time';
        if (!in_array($mode, $allowedModes, true)) $mode = 'remote';

        // skills_required: accept array or JSON string
        $skills = $body['skills_required'] ?? [];
        if (is_string($skills)) $skills = json_decode($skills, true) ?? [];
        if (!is_array($skills)) $skills = [];

        // optional fields
        $field    = trim($body['field']    ?? '');
        $location = trim($body['location'] ?? '');
        $deadline = !empty($body['deadline']) ? $body['deadline'] : null;
        $salary   = (isset($body['salary']) && $body['salary'] !== '')
                        ? (float) $body['salary']
                        : null;

        $jobModel = new Job();
        $newId    = $jobModel->create($payload['id'], [
            'title'           => trim($body['title']),
            'description'     => trim($body['description']),
            'type'            => $type,
            'mode'            => $mode,
            'field'           => $field,
            'location'        => $location,
            'deadline'        => $deadline,
            'salary'          => $salary,
            'skills_required' => $skills,
        ]);

        Response::success(['job_id' => $newId], 'Job posted successfully.', 201);
    }

    /* ═══════════════════════════════════════════
       PUT /api/company/jobs/:id  — update job
       ═══════════════════════════════════════════ */
    public static function update(int $id): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $jobModel = new Job();

        // ownership check
        $job = $jobModel->findById($id);
        if (!$job)                                    Response::error('Job not found.', 404);
        if ((int)$job['company_id'] !== (int)$payload['id']) Response::error('Forbidden.', 403);

        // build update data — only fields sent by frontend
        $data = [];

        if (array_key_exists('title', $body)) {
            $t = trim($body['title']);
            if ($t === '') Response::error('title cannot be empty.', 422);
            $data['title'] = $t;
        }

        if (array_key_exists('description', $body)) {
            $d = trim($body['description']);
            if ($d === '') Response::error('description cannot be empty.', 422);
            $data['description'] = $d;
        }

        if (array_key_exists('type', $body)) {
            $allowedTypes  = ['full-time', 'part-time', 'internship'];
            $t             = strtolower(trim($body['type']));
            $data['type']  = in_array($t, $allowedTypes, true) ? $t : 'full-time';
        }

        if (array_key_exists('mode', $body)) {
            $allowedModes  = ['remote', 'onsite', 'hybrid'];
            $m             = strtolower(trim($body['mode']));
            $data['mode']  = in_array($m, $allowedModes, true) ? $m : 'remote';
        }

        if (array_key_exists('field', $body)) {
            $data['field'] = trim($body['field']);
        }

        if (array_key_exists('location', $body)) {
            $data['location'] = trim($body['location']);
        }

        if (array_key_exists('deadline', $body)) {
            $data['deadline'] = !empty($body['deadline']) ? $body['deadline'] : null;
        }

        if (array_key_exists('salary', $body)) {
            $data['salary'] = ($body['salary'] !== null && $body['salary'] !== '')
                                ? (float) $body['salary']
                                : null;
        }

        if (array_key_exists('skills_required', $body)) {
            $skills = $body['skills_required'];
            if (is_string($skills)) $skills = json_decode($skills, true) ?? [];
            if (!is_array($skills)) $skills = [];
            $data['skills_required'] = $skills;
        }

        // is_active: 1 = active, 0 = closed
        if (array_key_exists('is_active', $body)) {
            $data['is_active'] = (int)(bool)$body['is_active'];
            $data['status'] = $data['is_active'] === 1 ? 'active' : 'closed';
        }

        if (empty($data)) Response::error('No fields provided to update.', 422);

        $jobModel->update($id, $data);
        Response::success(null, 'Job updated successfully.');
    }

    /* ═══════════════════════════════════════════
       DELETE /api/company/jobs/:id  — delete job
       ═══════════════════════════════════════════ */
    public static function delete(int $id): void {
        $payload = AuthMiddleware::handle();
        RoleMiddleware::require($payload, 'company');

        $jobModel = new Job();

        // ownership check
        $job = $jobModel->findById($id);
        if (!$job)                                    Response::error('Job not found.', 404);
        if ((int)$job['company_id'] !== (int)$payload['id']) Response::error('Forbidden.', 403);

        $jobModel->delete($id);
        Response::success(null, 'Job deleted successfully.');
    }
}