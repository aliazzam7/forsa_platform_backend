<?php
class RoleMiddleware {
    public static function require(array $payload, string ...$roles): void {
        if (!in_array($payload['role'], $roles)) {
            Response::error('Forbidden: Access denied.', 403);
        }
    }
}