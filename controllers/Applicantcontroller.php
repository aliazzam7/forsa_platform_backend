<?php

require_once __DIR__ . '/ApplicationController.php';

class ApplicantController {
    public static function list(int $jobId): void {
        ApplicationController::jobApplicants($jobId);
    }

    public static function updateStatus(int $appId): void {
        ApplicationController::updateStatus($appId);
    }
}