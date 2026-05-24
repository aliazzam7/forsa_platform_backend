<?php
require_once __DIR__ . '/../models/User.php';

class UploadController {

    // POST /api/upload/avatar
    public static function avatar(): void {
        $payload = AuthMiddleware::handle();

        if (empty($_FILES['avatar'])) Response::error('No file uploaded.');
        $path = FileHelper::upload($_FILES['avatar'], 'avatars');
        if (!$path) Response::error('Invalid file type. Only JPG/PNG allowed.');

        $userModel = new User();
        $userModel->updateProfile($payload['id'], ['avatar' => $path]);

        Response::success(['avatar' => $path], 'Avatar uploaded.');
    }
}