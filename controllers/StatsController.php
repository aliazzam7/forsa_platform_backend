<?php
// controllers/StatsController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Job.php';

class StatsController {

    // GET /api/stats  — public, no auth
    public static function index(): void {
        $db = Database::getInstance()->getConnection();

        $students  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
        $companies = (int) $db->query("SELECT COUNT(*) FROM companies WHERE status = 'approved'")->fetchColumn();
        $jobs      = (int) $db->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1")->fetchColumn();

        Response::success([
            'students'  => $students,
            'companies' => $companies,
            'jobs'      => $jobs,
        ]);
    }
}