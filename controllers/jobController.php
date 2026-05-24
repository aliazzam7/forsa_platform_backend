<?php
require_once __DIR__ . '/../models/Job.php';

class JobController {

    // GET /api/jobs
    public static function index(): void {
        $filters = [
            'type'   => $_GET['type']   ?? '',
            'mode'   => $_GET['mode']   ?? '',
            'field'  => $_GET['field']  ?? '',
            'search' => $_GET['search'] ?? '',
        ];
        $jobModel = new Job();
        Response::success($jobModel->getAll($filters));
    }

    // GET /api/jobs/:id
    public static function show(int $id): void {
        $jobModel = new Job();
        $job      = $jobModel->findById($id);
        if (!$job) Response::error('Job not found.', 404);
        Response::success($job);
    }
}