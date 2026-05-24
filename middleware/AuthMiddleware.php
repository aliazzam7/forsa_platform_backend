<?php
class AuthMiddleware {
    public static function handle(): array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::error('Unauthorized: No token provided.', 401);
        }

        $token   = substr($authHeader, 7);
        $payload = JWT::verify($token);

        if (!$payload) {
            Response::error('Unauthorized: Invalid or expired token.', 401);
        }

        return $payload;
    }
}