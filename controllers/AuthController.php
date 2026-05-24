<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Company.php';

class AuthController {

    // POST /api/auth/login
    public static function login(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $err = Validator::required($body, ['email', 'password']);
        if ($err) Response::error($err);

        if (!Validator::email($body['email'])) Response::error('Invalid email format.');

        // Try student/admin first
        $userModel = new User();
        $user      = $userModel->findByEmail($body['email']);

        if ($user && password_verify($body['password'], $user['password'])) {
            if ($user['is_banned']) Response::error('Your account has been banned.', 403);

            $token = JWT::generate([
                'id'   => $user['id'],
                'role' => $user['role'],
                'name' => $user['name'],
                'type' => 'user',
            ]);

            Response::success([
                'token' => $token,
                'role'  => $user['role'],
                'user'  => [
                    'id'     => $user['id'],
                    'name'   => $user['name'],
                    'email'  => $user['email'],
                    'role'   => $user['role'],
                    'avatar' => $user['avatar'],
                ],
            ], 'Login successful.');
        }

        // Try company
        $companyModel = new Company();
        $company      = $companyModel->findByEmail($body['email']);

        if ($company && password_verify($body['password'], $company['password'])) {
            if ($company['is_banned'])   Response::error('Your company account has been banned.', 403);
            if (!$company['is_approved']) Response::error('Your company account is pending approval.', 403);

            $token = JWT::generate([
                'id'   => $company['id'],
                'role' => 'company',
                'name' => $company['company_name'],
                'type' => 'company',
            ]);

            Response::success([
                'token' => $token,
                'role'  => 'company',
                'user'  => [
                    'id'   => $company['id'],
                    'name' => $company['company_name'],
                    'email'=> $company['email'],
                    'role' => 'company',
                    'logo' => $company['logo'],
                ],
            ], 'Login successful.');
        }

        Response::error('Invalid email or password.', 401);
    }

    // POST /api/auth/register/student
    public static function registerStudent(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $err = Validator::required($body, ['name', 'email', 'password']);
        if ($err) Response::error($err);

        if (!Validator::email($body['email']))          Response::error('Invalid email format.');
        if (!Validator::minLength($body['password'], 6)) Response::error('Password must be at least 6 characters.');

        $userModel = new User();
        if ($userModel->findByEmail($body['email'])) Response::error('Email already registered.');

        $id    = $userModel->create($body['name'], $body['email'], $body['password'], 'student');
        $token = JWT::generate(['id' => $id, 'role' => 'student', 'name' => $body['name'], 'type' => 'user']);

        Response::success(['token' => $token, 'role' => 'student'], 'Registration successful.', 201);
    }

    // POST /api/auth/register/company
    public static function registerCompany(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $err = Validator::required($body, ['company_name', 'email', 'password']);
        if ($err) Response::error($err);

        if (!Validator::email($body['email']))          Response::error('Invalid email format.');
        if (!Validator::minLength($body['password'], 6)) Response::error('Password must be at least 6 characters.');

        $companyModel = new Company();
        if ($companyModel->findByEmail($body['email'])) Response::error('Email already registered.');

        // Also check users table
        $userModel = new User();
        if ($userModel->findByEmail($body['email'])) Response::error('Email already registered.');

        $id = $companyModel->create($body);
        Response::success(['company_id' => $id], 'Registration submitted. Await admin approval.', 201);
    }

    // GET /api/auth/me
    public static function me(): void {
        $payload = AuthMiddleware::handle();

        if ($payload['type'] === 'company') {
            $model = new Company();
            $data  = $model->findById($payload['id']);
        } else {
            $model = new User();
            $data  = $model->findById($payload['id']);
        }

        if (!$data) Response::error('User not found.', 404);
        Response::success($data);
    }
}